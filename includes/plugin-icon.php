<?php
/**
 * Custom Plugin Icon
 *
 * Adds a custom icon alongside the plugin name on the
 * WordPress Plugins administration screen.
 *
 * @package SkyrocketScriptManager
 */

defined('ABSPATH') || exit;

/**
 * Add the custom plugin icon to the Plugins screen.
 */
add_action('admin_head-plugins.php', static function (): void {
    $plugin_basename = plugin_basename(SRSM_PLUGIN_FILE);
    $icon_url = plugin_dir_url(SRSM_PLUGIN_FILE) . 'assets/images/icon-128x128.png';
    ?>
    <style>
        #the-list tr[data-plugin="<?php echo esc_attr($plugin_basename); ?>"] .plugin-title strong {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        #the-list tr[data-plugin="<?php echo esc_attr($plugin_basename); ?>"] .plugin-title strong::before {
            content: "";
            display: inline-block;
            flex: 0 0 32px;
            width: 32px;
            height: 32px;
            background: url("<?php echo esc_url($icon_url); ?>") center / contain no-repeat;
        }
    </style>
    <?php
});
