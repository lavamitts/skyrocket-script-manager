<?php

declare(strict_types=1);

namespace Skyrocket\GitHubUpdater;

class Updater
{
    private string $pluginFile;
    private string $pluginSlug;
    private string $githubRepo;
    private ?string $accessToken;
    private string $transientKey;
    private int $cacheTtl;

    /**
     * @param string      $pluginFile  Full path to the main plugin file (__FILE__).
     * @param string      $githubRepo  GitHub repository in 'owner/repo' format.
     * @param string|null $accessToken Optional GitHub PAT for private repositories.
     * @param int         $cacheTtl    Cache duration in seconds (default 6 hours).
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