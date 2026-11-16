<?php
/**
 * Initialise the GitHub plugin update checker.
 */

// Capture the configuration supplied by the main plugin file.
$config = $github_updater_config ?? [];

add_action('plugins_loaded', static function () use ($config) {

    // Check that the updater library is available.
    $autoload_file = dirname($config['plugin_file']) . '/vendor/autoload.php';

    if (!file_exists($autoload_file)) {
        return;
    }

    require_once $autoload_file;

    // Initialise the GitHub update checker.
    $update_checker = \YahnisElsts\PluginUpdateChecker\v6\PucFactory::buildUpdateChecker(
        'https://github.com/' . $config['username'] . '/' . $config['repository'] . '/',
        $config['plugin_file'],
        $config['repository']
    );

    // Enable release assets.
    /** @var \YahnisElsts\PluginUpdateChecker\v6p0\Vcs\GitHubApi $vcsApi */
    $vcsApi = $update_checker->getVcsApi();
    $vcsApi->enableReleaseAssets();

});
