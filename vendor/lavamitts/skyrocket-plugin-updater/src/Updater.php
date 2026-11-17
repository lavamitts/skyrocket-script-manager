<?php

/**
 * GitHub Plugin Updater
 *
 * Provides automatic GitHub release update integration for a WordPress plugin.
 *
 * Connects WordPress's native plugin update system to the GitHub Releases API,
 * allowing plugins hosted outside the WordPress.org repository to receive
 * updates through the standard WordPress Plugins screen.
 *
 * Features:
 * - Checks GitHub for the latest published release.
 * - Compares the remote release version against the installed plugin version.
 * - Uses explicitly uploaded ZIP release assets as update packages.
 * - Supports public and private GitHub repositories.
 * - Supports optional GitHub personal access token authentication.
 * - Provides plugin information and changelog data for the WordPress
 *   "View details" modal.
 * - Caches GitHub API responses to reduce unnecessary requests.
 * - Clears cached release information following plugin updates.
 * - Provides a manual "Check for updates" link on the Plugins screen.
 *
 * GitHub release requirements:
 * - Releases must have a version tag (e.g. v1.2.0 or 1.2.0).
 * - Releases must contain an explicitly uploaded ZIP archive.
 * - The ZIP archive must contain the plugin files in the expected structure.
 *
 * Usage:
 *
 * Instantiate the class from a plugin's initialisation file:
 *
 * new \Skyrocket\GitHubUpdater\Updater(
 *     plugin_basename(__FILE__),
 *     'github-username/github-repository',
 *     null
 * );
 *
 * For private repositories, supply a GitHub personal access token as
 * the third argument.
 *
 * Requirements:
 * - WordPress
 * - PHP 8.0 or later
 * - A GitHub repository with published releases
 *
 * @package Skyrocket\GitHubUpdater
 * @author  Skyrocket Phytopharma UK Ltd
 */

declare(strict_types=1);

namespace Skyrocket\GitHubUpdater;

/**
 * Handles GitHub release checks and integrates them with WordPress's
 * native plugin update and information systems.
 */
class Updater
{
    /**
     * Absolute path to the main plugin file.
     */
    private string $pluginFile;

    /**
     * WordPress plugin identifier, relative to the plugins directory.
     */
    private string $pluginSlug;

    /**
     * GitHub repository in owner/repository format.
     */
    private string $githubRepo;

    /**
     * Optional GitHub personal access token.
     *
     * Null when accessing a public repository without authentication.
     */
    private ?string $accessToken;

    /**
     * WordPress transient key used to cache GitHub release information.
     */
    private string $transientKey;

    /**
     * Duration, in seconds, for which successful release responses are cached.
     */
    private int $cacheTtl;

    /**
     * Initialise the updater and register its WordPress hooks.
     *
     * @param string      $pluginFile  Absolute path to the plugin's main file.
     * @param string      $githubRepo  GitHub repository in owner/repository format.
     * @param string|null $accessToken Optional GitHub personal access token.
     * @param int         $cacheTtl    Successful response cache duration in seconds.
     */
    public function __construct(
        string $pluginFile,
        string $githubRepo,
        ?string $accessToken = null,
        int $cacheTtl = 21600
    ) {
        $this->pluginFile = $pluginFile;
        $this->pluginSlug = plugin_basename($pluginFile);
        $this->githubRepo = trim($githubRepo, '/');
        $this->accessToken = $accessToken;
        $this->transientKey = 'gh_updater_' . md5($this->pluginSlug);
        $this->cacheTtl = $cacheTtl;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);
        add_filter('plugins_api', [$this, 'pluginInfo'], 10, 3);
        add_filter('http_request_args', [$this, 'addAuthHeader'], 10, 2);
        add_action('upgrader_process_complete', [$this, 'clearCache'], 10, 2);

        // Add "Check for updates" link on Plugins page & handle manual check
        add_filter('plugin_row_meta', [$this, 'addCheckUpdateLink'], 10, 2);
        add_action('admin_init', [$this, 'handleManualCheck']);
        add_action('admin_notices', [$this, 'renderAdminNotice']);
    }

    /**
     * Add a "Check for updates" link in the plugin's row meta on the Plugins screen.
     */
    public function addCheckUpdateLink(array $links, string $file): array
    {
        if ($file === $this->pluginSlug && current_user_can('update_plugins')) {
            $url = wp_nonce_url(
                add_query_arg([
                    'gh_check_update' => $this->pluginSlug,
                ], admin_url('plugins.php')),
                'gh_check_update_' . $this->pluginSlug
            );

            $links[] = sprintf('<a href="%s">Check for updates</a>', esc_url($url));
        }

        return $links;
    }

    /**
     * Clear the cached GitHub response and force WordPress to re-check updates.
     */
    public function handleManualCheck(): void
    {
        if (($_GET['gh_check_update'] ?? '') !== $this->pluginSlug) {
            return;
        }

        check_admin_referer('gh_check_update_' . $this->pluginSlug);

        if (!current_user_can('update_plugins')) {
            return;
        }

        delete_transient($this->transientKey);
        delete_site_transient('update_plugins');
        wp_update_plugins();

        $updates = get_site_transient('update_plugins');
        $hasUpdate = is_object($updates) && isset($updates->response[$this->pluginSlug]);
        $status = $hasUpdate ? 'available' : 'current';

        $redirectUrl = add_query_arg(
            [
                'gh_update_status' => $status,
                'gh_plugin' => $this->pluginSlug,
            ],
            remove_query_arg(['gh_check_update', '_wpnonce'])
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    /**
     * Render a standard WordPress admin notice after a manual update check.
     */
    public function renderAdminNotice(): void
    {
        if (($_GET['gh_plugin'] ?? '') !== $this->pluginSlug) {
            return;
        }

        $status = $_GET['gh_update_status'] ?? '';
        if ($status === '') {
            return;
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginData = get_plugin_data($this->pluginFile, false, false);
        $pluginName = $pluginData['Name'] ?: dirname($this->pluginSlug);

        if ($status === 'available') {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                sprintf(
                    /* translators: %s: Plugin name */
                    esc_html__('An update is available for %s.', 'default'),
                    '<strong>' . esc_html($pluginName) . '</strong>'
                )
            );
        } elseif ($status === 'current') {
            printf(
                '<div class="notice notice-info is-dismissible"><p>%s</p></div>',
                sprintf(
                    /* translators: %s: Plugin name */
                    esc_html__('%s is up to date.', 'default'),
                    '<strong>' . esc_html($pluginName) . '</strong>'
                )
            );
        }
    }

    /**
     * Inject update data into WordPress's update transient.
     */
    public function checkForUpdate(mixed $transient): mixed
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $release = $this->getLatestRelease();
        if (!$release) {
            return $transient;
        }

        $currentVersion = $transient->checked[$this->pluginSlug] ?? null;
        if (!$currentVersion) {
            return $transient;
        }

        $remoteVersion = ltrim($release['tag_name'], 'vV');

        if (version_compare($currentVersion, $remoteVersion, '<')) {
            $transient->response[$this->pluginSlug] = (object) [
                'slug' => dirname($this->pluginSlug),
                'plugin' => $this->pluginSlug,
                'new_version' => $remoteVersion,
                'url' => $release['html_url'],
                'package' => $release['asset_url'],
            ];
        }

        return $transient;
    }

    /**
     * Populate the "View details" modal in the WordPress Plugins screen.
     */
    public function pluginInfo(mixed $result, string $action, object $args): mixed
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== dirname($this->pluginSlug)) {
            return $result;
        }

        $release = $this->getLatestRelease();
        if (!$release) {
            return $result;
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginData = get_plugin_data($this->pluginFile);

        return (object) [
            'name' => $pluginData['Name'],
            'slug' => dirname($this->pluginSlug),
            'version' => ltrim($release['tag_name'], 'vV'),
            'author' => $pluginData['Author'],
            'homepage' => $pluginData['PluginURI'] ?: $release['html_url'],
            'download_link' => $release['asset_url'],
            'last_updated' => $release['published_at'],
            'sections' => [
                'description' => $pluginData['Description'],
                'changelog' => nl2br(esc_html($release['body'] ?: 'See GitHub release notes.')),
            ],
        ];
    }

    /**
     * Attach authorization header for private repo downloads if a token is configured.
     */
    public function addAuthHeader(array $args, string $url): array
    {
        if ($this->accessToken && str_contains($url, 'api.github.com/repos/' . $this->githubRepo)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->accessToken;
            $args['headers']['Accept'] = 'application/octet-stream';
        }

        return $args;
    }

    /**
     * Clear cached release data after an update completes.
     */
    public function clearCache(\WP_Upgrader $upgrader, array $options): void
    {
        if (($options['action'] ?? '') === 'update' && ($options['type'] ?? '') === 'plugin') {
            delete_transient($this->transientKey);
        }
    }

    /**
     * Fetch and cache the latest release that contains an explicitly uploaded .zip asset.
     */
    private function getLatestRelease(): ?array
    {
        $cached = get_transient($this->transientKey);
        if (is_array($cached)) {
            return $cached ?: null;
        }

        $apiUrl = sprintf('https://api.github.com/repos/%s/releases/latest', $this->githubRepo);
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
        ];

        if ($this->accessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        $response = wp_remote_get($apiUrl, [
            'timeout' => 10,
            'headers' => $headers,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_transient($this->transientKey, [], 900); // Cache failure for 15 mins
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['assets']) || !is_array($data['assets'])) {
            set_transient($this->transientKey, [], $this->cacheTtl);
            return null;
        }

        // Find the first explicitly uploaded .zip asset
        $assetUrl = null;
        foreach ($data['assets'] as $asset) {
            if (isset($asset['name']) && str_ends_with(strtolower($asset['name']), '.zip')) {
                // Use API asset endpoint for private repos, browser_download_url for public
                $assetUrl = $this->accessToken
                    ? ($asset['url'] ?? null)
                    : ($asset['browser_download_url'] ?? null);
                break;
            }
        }

        if (!$assetUrl) {
            set_transient($this->transientKey, [], $this->cacheTtl);
            return null;
        }

        $release = [
            'tag_name' => $data['tag_name'] ?? '',
            'html_url' => $data['html_url'] ?? '',
            'published_at' => $data['published_at'] ?? '',
            'body' => $data['body'] ?? '',
            'asset_url' => $assetUrl,
        ];

        set_transient($this->transientKey, $release, $this->cacheTtl);

        return $release;
    }
}
