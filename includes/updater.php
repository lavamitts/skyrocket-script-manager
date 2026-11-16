<?php
/**
 * Initialise the GitHub plugin update checker.
 */

// Capture the configuration supplied by the main plugin file.
$config = $github_updater_config ?? [];

add_action('plugins_loaded', static function () use ($config) {

    // Load Plugin Update Checker directly, without Composer's autoloader.
    $updater_file = dirname($config['plugin_file'])
        . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

    if (!file_exists($updater_file)) {
        return;
    }

    require_once $updater_file;

    // Initialise the GitHub update checker.
    $update_checker = \YahnisElsts\PluginUpdateChecker\v6\PucFactory::buildUpdateChecker(
        'https://github.com/' . $config['username'] . '/' . $config['repository'] . '/',
        $config['plugin_file'],
        $config['repository']
    );

    // Explicitly typecast for IDE static analysis.
    /** @var \YahnisElsts\PluginUpdateChecker\v6p0\Vcs\GitHubApi $vcsApi */
    $vcsApi = $update_checker->getVcsApi();

    $vcsApi->enableReleaseAssets();

});
