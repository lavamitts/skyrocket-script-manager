<?php

/**
 * GitHub Updater Initialisation
 *
 * Loads and initialises the GitHub Updater for a WordPress plugin.
 *
 * This file is intended to be included by a plugin's main file, with
 * the required configuration supplied through the
 * $github_updater_config variable before inclusion.
 *
 * Expected configuration:
 *
 * $github_updater_config = [
 *     'username'     => 'github-username',
 *     'repository'   => 'github-repository',
 *     'plugin_file'  => __FILE__,
 *     'access_token' => null, // Optional GitHub personal access token.
 * ];
 *
 * The updater class is loaded from the same directory as this file
 * if it has not already been loaded.
 *
 * Initialisation is skipped if WordPress is not loaded, the
 * configuration is missing or invalid, or the updater class file
 * cannot be found.
 *
 * @package Skyrocket\GitHubUpdater
 */

declare(strict_types=1);

// Prevent direct access outside of WordPress.
if (!defined('ABSPATH')) {
    exit;
}

// Validate the configuration before attempting to initialise the updater.
if (
    !isset($github_updater_config) ||
    !is_array($github_updater_config) ||
    empty($github_updater_config['username']) ||
    empty($github_updater_config['repository']) ||
    empty($github_updater_config['plugin_file'])
) {
    return;
}

// Locate the updater class file relative to this file.
$updaterClassFile = __DIR__ . '/Updater.php';

// Load the updater class only if it has not already been declared.
if (!class_exists(\Skyrocket\GitHubUpdater\Updater::class)) {

    // Skip initialisation if the updater class file is unavailable.
    if (!is_file($updaterClassFile)) {
        return;
    }

    require_once $updaterClassFile;
}

// Create and initialise the updater using the supplied configuration.
new \Skyrocket\GitHubUpdater\Updater(
    $github_updater_config['plugin_file'],
    $github_updater_config['username'] . '/' . $github_updater_config['repository'],
    $github_updater_config['access_token'] ?? null
);
