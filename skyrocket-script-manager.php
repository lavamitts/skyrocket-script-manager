<?php
/**
 * Plugin Name: Skyrocket Script Manager
 * Description: Manage JavaScript and HTML snippets (with optional CSS, priority ordering, and post/page targeting).
 * Version: 1.5.1
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Skyrocket Digital Development
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SRSM_VERSION', '1.5.1');
define('SRSM_PLUGIN_FILE', __FILE__);
define('SRSM_PLUGIN_DIR', plugin_dir_path(__FILE__));

// GitHub updater configuration.
$github_updater_config = [
    'username' => 'lavamitts',
    'repository' => 'skyrocket-script-manager',
    'plugin_file' => __FILE__,
];

// Updater code
require_once SRSM_PLUGIN_DIR . 'vendor/lavamitts/skyrocket-plugin-updater/src/plugin-updater.php';

// Remove the temporary configuration variable.
unset($github_updater_config);

require_once SRSM_PLUGIN_DIR . 'includes/class-snippet-manager.php';

register_activation_hook(__FILE__, ['Individual_JS_Snippets', 'activate']);

add_action('plugins_loaded', static function () {
    new Individual_JS_Snippets();
});

add_filter(
    'plugin_action_links_' . plugin_basename(__FILE__),
    static function (array $links): array {
        $snippets_url = admin_url('edit.php?post_type=' . Individual_JS_Snippets::CPT);
        $snippets_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url($snippets_url),
            esc_html__('JS Snippets', 'skyrocket-script-manager')
        );

        array_unshift($links, $snippets_link);

        return $links;
    }
);