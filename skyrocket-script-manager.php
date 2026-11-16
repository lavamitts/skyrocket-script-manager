<?php
/**
 * Plugin Name: Skyrocket Script Manager
 * Description: Manage individual JavaScript snippets (with optional CSS) with site-wide targeting or assignment via a post/page editor panel.
 * Version: 1.3.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Biddenham Society
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin version constant, used for cache-busting CSS and JavaScript.
define('SKYROCKET_SCRIPT_MANAGER_VERSION', '1.3.0');
define('SKYROCKET_SCRIPT_MANAGER_PATH', plugin_dir_path(__FILE__));
define('SSM_PATH', plugin_dir_path(__FILE__));

// Token loaded
require_once SSM_PATH . 'includes/token.php';

/**
 * Initialise the GitHub plugin update checker.
 */
function ssm_init_update_checker()
{
    // Require a GitHub access token.
    if (!defined('SSM_GITHUB_TOKEN') || SSM_GITHUB_TOKEN === '') {
        return;
    }

    // Check that the updater library is available.
    $updater_file = SSM_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

    if (!file_exists($updater_file)) {
        return;
    }

    require_once $updater_file;

    // Initialise the GitHub update checker.
    $update_checker = \YahnisElsts\PluginUpdateChecker\v6\PucFactory::buildUpdateChecker(
        'https://github.com/lavamitts/skyrocket-script-manager/',
        __FILE__,
        'skyrocket-script-manager'
    );

    // Authenticate with the private GitHub repository.
    $update_checker->setAuthentication(SSM_GITHUB_TOKEN);

    // Explicitly typecast for IDE static analysis
    /** @var \YahnisElsts\PluginUpdateChecker\v6p0\Vcs\GitHubApi $vcsApi */
    $vcsApi = $update_checker->getVcsApi();
    $vcsApi->enableReleaseAssets();
}
add_action('plugins_loaded', 'ssm_init_update_checker');

class Individual_JS_Snippets
{
    const CPT = 'js_snippet';
    const ASSIGN_META = '_assigned_js_snippets';
    const VERSION = '3.1.0';

    /** @var WP_Post[]|null Per-request cache of published snippets */
    private $published_snippets = null;

    /** @var int[]|null Per-request cache of snippet IDs active on this request */
    private $active_ids = null;

    public function __construct()
    {
        add_action('init', [$this, 'register_snippet_cpt'], 0);
        // Late priority so other plugins' public post types exist by now
        add_action('init', [$this, 'register_assignment_meta'], 99);

        // Snippet edit screen (admin-only post type, classic meta boxes are fine here)
        add_action('add_meta_boxes', [$this, 'add_snippet_meta_boxes']);
        add_action('save_post_' . self::CPT, [$this, 'save_snippet_meta']);

        // Custom columns for the snippet admin list (using priority 20 to run after other plugins)
        add_filter('manage_edit-' . self::CPT . '_columns', [$this, 'add_snippet_columns'], 20);
        add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'render_snippet_columns'], 10, 2);

        // Assignment UI on posts/pages:
        //  - Block editor: sidebar panel backed by registered post meta (REST)
        //  - Classic editor: back-compat meta box only
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_panel']);
        add_action('add_meta_boxes', [$this, 'add_post_assignment_meta_boxes']);
        add_action('save_post', [$this, 'save_post_assignment_meta'], 10, 2);

        // Frontend injection
        add_action('wp_enqueue_scripts', [$this, 'enqueue_snippet_styles']);
        add_action('wp_head', [$this, 'inject_snippets_header'], 99);
        add_action('wp_body_open', [$this, 'inject_snippets_body'], 99);
        add_action('wp_footer', [$this, 'inject_snippets_footer'], 99);
    }

    /**
     * Fired upon plugin activation. Scans the default-scripts directory and seeds drafts.
     */
    public static function activate()
    {
        // Register CPT immediately so wp_insert_post recognizes 'js_snippet' during activation flow
        $instance = new self();
        $instance->register_snippet_cpt();

        $defaults_dir = SKYROCKET_SCRIPT_MANAGER_PATH . 'default-scripts/';
        if (!is_dir($defaults_dir)) {
            return;
        }

        $folders = scandir($defaults_dir);
        if ($folders === false) {
            return;
        }

        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') {
                continue;
            }

            $folder_path = $defaults_dir . $folder;
            if (!is_dir($folder_path)) {
                continue;
            }

            $script_file = $folder_path . '/' . $folder . '.js';
            if (!file_exists($script_file) || !is_readable($script_file)) {
                continue;
            }

            // Check if a snippet with this slug/title already exists to prevent duplication on reactivation
            $title = ucwords(str_replace(['-', '_'], ' ', $folder));
            $existing = get_page_by_path($folder, OBJECT, self::CPT);

            if ($existing) {
                continue;
            }

            $code = file_get_contents($script_file);
            if ($code === false) {
                $code = '';
            }

            // Insert as Draft
            $post_id = wp_insert_post([
                'post_type' => self::CPT,
                'post_title' => $title,
                'post_name' => $folder,
                'post_status' => 'draft',
                'post_content' => '',
            ]);

            if ($post_id && !is_wp_error($post_id)) {
                // Populate default meta fields
                update_post_meta($post_id, '_js_snippet_code', wp_slash($code));
                update_post_meta($post_id, '_js_snippet_description', 'Default template script installed automatically.');
                update_post_meta($post_id, '_js_snippet_location', 'footer');
                update_post_meta($post_id, '_js_snippet_target_type', 'sitewide');

                // Seed bundled CSS, if the snippet ships with one
                $css_file = $folder_path . '/' . $folder . '.css';
                if (file_exists($css_file) && is_readable($css_file)) {
                    $css = file_get_contents($css_file);
                    if ($css !== false) {
                        update_post_meta($post_id, '_js_snippet_css', wp_slash($css));
                    }
                }
            }
        }
    }

    /* ---------------------------------------------------------------
    - Helpers
    - ------------------------------------------------------------- */

    private function user_can_manage($user_id = 0)
    {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        $can = user_can($user_id, 'manage_options') && user_can($user_id, 'unfiltered_html');
        return (bool) apply_filters('ijs_user_can_manage', $can, $user_id);
    }

    private function get_location($snippet_id)
    {
        $loc = get_post_meta($snippet_id, '_js_snippet_location', true);
        return in_array($loc, ['header', 'body', 'footer'], true) ? $loc : 'footer';
    }

    private function get_target_type($snippet_id)
    {
        $type = get_post_meta($snippet_id, '_js_snippet_target_type', true);
        return in_array($type, ['sitewide', 'specific'], true) ? $type : 'sitewide';
    }

    private function get_assignable_post_types()
    {
        $types = get_post_types(['public' => true], 'names');
        unset($types['attachment'], $types[self::CPT]);
        return array_values($types);
    }

    private function get_panel_post_types()
    {
        $out = [];
        foreach ($this->get_assignable_post_types() as $pt) {
            $obj = get_post_type_object($pt);
            if ($obj && $obj->show_in_rest && post_type_supports($pt, 'custom-fields')) {
                $out[] = $pt;
            }
        }
        return $out;
    }

    private function get_published_snippets()
    {
        if (null === $this->published_snippets) {
            $this->published_snippets = get_posts([
                'post_type' => self::CPT,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'no_found_rows' => true,
            ]);
        }
        return $this->published_snippets;
    }

    public function sanitize_assigned($value)
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $id) {
            $id = absint($id);
            if ($id && get_post_type($id) === self::CPT && get_post_status($id) === 'publish') {
                $out[$id] = $id;
            }
        }
        return array_values($out);
    }

    /* ---------------------------------------------------------------
    - CPT
    - ------------------------------------------------------------- */

    public function register_snippet_cpt()
    {
        $cap = 'manage_options';

        register_post_type(self::CPT, [
            'labels' => [
                'name' => 'JS Snippets',
                'singular_name' => 'JS Snippet',
                'menu_name' => 'JS Snippets',
                'add_new' => 'Add New Snippet',
                'add_new_item' => 'Add New JS Snippet',
                'edit_item' => 'Edit JS Snippet',
                'new_item' => 'New JS Snippet',
                'view_item' => 'View JS Snippet',
                'search_items' => 'Search JS Snippets',
                'not_found' => 'No JS snippets found',
                'not_found_in_trash' => 'No JS snippets found in trash',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-code-standards',
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'capabilities' => [
                'create_posts' => $cap,
                'edit_posts' => $cap,
                'edit_others_posts' => $cap,
                'edit_published_posts' => $cap,
                'edit_private_posts' => $cap,
                'publish_posts' => $cap,
                'read_private_posts' => $cap,
                'delete_posts' => $cap,
                'delete_others_posts' => $cap,
                'delete_published_posts' => $cap,
            ],
        ]);
    }

    public function register_assignment_meta()
    {
        foreach ($this->get_assignable_post_types() as $pt) {
            register_post_meta($pt, self::ASSIGN_META, [
                'type' => 'array',
                'single' => true,
                'default' => [],
                'show_in_rest' => [
                    'schema' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                    ],
                ],
                'sanitize_callback' => [$this, 'sanitize_assigned'],
                'auth_callback' => [$this, 'auth_assignment_meta'],
            ]);
        }
    }

    public function auth_assignment_meta($allowed, $meta_key, $post_id, $user_id)
    {
        return user_can($user_id, 'edit_post', $post_id) && $this->user_can_manage($user_id);
    }

    /* ---------------------------------------------------------------
    - Snippet edit screen
    - ------------------------------------------------------------- */

    public function add_snippet_meta_boxes()
    {
        add_meta_box('js_snippet_code_box', 'JavaScript Code', [$this, 'render_code_meta_box'], self::CPT, 'normal', 'high');
        add_meta_box('js_snippet_settings_box', 'Snippet Settings and Targeting', [$this, 'render_settings_meta_box'], self::CPT, 'side', 'default');
    }

    public function render_code_meta_box($post)
    {
        wp_nonce_field('js_snippet_save_meta', 'js_snippet_nonce');
        $code = get_post_meta($post->ID, '_js_snippet_code', true);
        $css = get_post_meta($post->ID, '_js_snippet_css', true);
        ?>
        <p>Paste your JavaScript code below. Do not include script tags.</p>
        <textarea name="js_snippet_code" rows="12"
            style="width:100%; font-family:monospace;"><?php echo esc_textarea($code); ?></textarea>

        <p style="margin-top:16px;"><strong>Optional CSS</strong><br>
            Loaded on the same pages as this snippet. Do not include style tags.</p>
        <textarea name="js_snippet_css" rows="8"
            style="width:100%; font-family:monospace;"><?php echo esc_textarea($css); ?></textarea>
        <?php
    }

    public function render_settings_meta_box($post)
    {
        $location = $this->get_location($post->ID);
        $target_type = $this->get_target_type($post->ID);
        $description = get_post_meta($post->ID, '_js_snippet_description', true);
        ?>
        <p>
            <strong><label for="js_snippet_description">Description</label></strong><br>
            <textarea name="js_snippet_description" id="js_snippet_description" rows="4" style="width:100%;"
                placeholder="Describe the purpose of this script..."><?php echo esc_textarea($description); ?></textarea>
            <span class="description">Internal use only. This will not be shown on the published site.</span>
        </p>

        <p>
            <strong><label for="js_snippet_location">Injection Location</label></strong><br>
            <select name="js_snippet_location" id="js_snippet_location" style="width:100%;">
                <option value="header" <?php selected($location, 'header'); ?>>Header (wp_head)</option>
                <option value="body" <?php selected($location, 'body'); ?>>Body Open (wp_body_open)</option>
                <option value="footer" <?php selected($location, 'footer'); ?>>Footer (wp_footer)</option>
            </select>
        </p>

        <p>
            <strong><label for="js_snippet_target_type">Targeting Rule</label></strong><br>
            <select name="js_snippet_target_type" id="js_snippet_target_type" style="width:100%;">
                <option value="sitewide" <?php selected($target_type, 'sitewide'); ?>>Entire Site</option>
                <option value="specific" <?php selected($target_type, 'specific'); ?>>Assigned via Post/Page Editor</option>
            </select>
        </p>
        <?php
    }

    public function save_snippet_meta($post_id)
    {
        if (
            !isset($_POST['js_snippet_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['js_snippet_nonce'])), 'js_snippet_save_meta')
        ) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id) || !$this->user_can_manage()) {
            return;
        }

        if (isset($_POST['js_snippet_code'])) {
            $code = wp_unslash($_POST['js_snippet_code']);
            update_post_meta($post_id, '_js_snippet_code', wp_slash($code));
        }

        if (isset($_POST['js_snippet_css'])) {
            $css = wp_unslash($_POST['js_snippet_css']);
            update_post_meta($post_id, '_js_snippet_css', wp_slash($css));
        }

        if (isset($_POST['js_snippet_description'])) {
            $description = sanitize_textarea_field(wp_unslash($_POST['js_snippet_description']));
            update_post_meta($post_id, '_js_snippet_description', $description);
        }

        $location = isset($_POST['js_snippet_location']) ? sanitize_text_field(wp_unslash($_POST['js_snippet_location'])) : 'footer';
        if (!in_array($location, ['header', 'body', 'footer'], true)) {
            $location = 'footer';
        }
        update_post_meta($post_id, '_js_snippet_location', $location);

        $target = isset($_POST['js_snippet_target_type']) ? sanitize_text_field(wp_unslash($_POST['js_snippet_target_type'])) : 'sitewide';
        if (!in_array($target, ['sitewide', 'specific'], true)) {
            $target = 'sitewide';
        }
        update_post_meta($post_id, '_js_snippet_target_type', $target);
    }

    public function add_snippet_columns($columns)
    {
        unset($columns['image'], $columns['featured_image'], $columns['column-image']);

        $new_columns = [];
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'title') {
                $new_columns['snippet_description'] = 'Description';
            }
        }
        return $new_columns;
    }

    public function render_snippet_columns($column, $post_id)
    {
        if ($column === 'snippet_description') {
            $description = get_post_meta($post_id, '_js_snippet_description', true);
            echo $description ? nl2br(esc_html($description)) : '<span style="color:#72777c;">—</span>';
        }
    }

    public function enqueue_editor_panel()
    {
        if (!$this->user_can_manage()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $panel_types = $this->get_panel_post_types();
        if (!$screen || empty($screen->post_type) || !in_array($screen->post_type, $panel_types, true)) {
            return;
        }

        $snippets = [];
        foreach ($this->get_published_snippets() as $snippet) {
            $snippets[] = [
                'id' => (int) $snippet->ID,
                'title' => $snippet->post_title !== '' ? $snippet->post_title : '(no title)',
                'location' => $this->get_location($snippet->ID),
                'sitewide' => $this->get_target_type($snippet->ID) === 'sitewide',
            ];
        }

        $config = [
            'metaKey' => self::ASSIGN_META,
            'postTypes' => $panel_types,
            'snippets' => $snippets,
            'emptyText' => 'No published JS snippets yet. Create or publish one under JS Snippets.',
        ];

        $handle = 'ijs-editor-panel';
        wp_register_script($handle, false, ['wp-plugins', 'wp-editor', 'wp-element', 'wp-data', 'wp-components'], self::VERSION, true);
        wp_enqueue_script($handle);

        wp_add_inline_script(
            $handle,
            'window.ijsSnippetsConfig = ' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            'before'
        );
        wp_add_inline_script($handle, $this->get_panel_js());
    }

    private function get_panel_js()
    {
        return <<<'JS'
( function ( wp, cfg ) {
    if ( ! wp || ! cfg || ! wp.plugins || ! wp.editor || ! wp.editor.PluginDocumentSettingPanel ) {
        return;
    }
    var el = wp.element.createElement;
    var Panel = wp.editor.PluginDocumentSettingPanel;
    var CheckboxControl = wp.components.CheckboxControl;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;

    function SnippetPanel() {
        var data = useSelect( function ( select ) {
            var editor = select( 'core/editor' );
            return {
                postType: editor.getCurrentPostType(),
                meta: editor.getEditedPostAttribute( 'meta' ) || {}
            };
        }, [] );
        var editPost = useDispatch( 'core/editor' ).editPost;

        if ( cfg.postTypes.indexOf( data.postType ) === -1 ) {
            return null;
        }

        var assigned = ( data.meta[ cfg.metaKey ] || [] ).map( Number );

        function toggle( id, on ) {
            var next = assigned.filter( function ( x ) { return x !== id; } );
            if ( on ) { next.push( id ); }
            var meta = {};
            meta[ cfg.metaKey ] = next;
            editPost( { meta: meta } );
        }

        var children = cfg.snippets.length
            ? cfg.snippets.map( function ( s ) {
                return el( CheckboxControl, {
                    key: s.id,
                    __nextHasNoMarginBottom: true,
                    label: s.title + ' (' + s.location + ( s.sitewide ? ', already sitewide' : '' ) + ')',
                    checked: assigned.indexOf( s.id ) !== -1,
                    onChange: function ( on ) { toggle( s.id, on ); }
                } );
            } )
            : el( 'p', null, cfg.emptyText );

        return el( Panel, { name: 'ijs-snippets', title: 'JavaScript Snippets' }, children );
    }

    wp.plugins.registerPlugin( 'ijs-snippets', { render: SnippetPanel } );
} )( window.wp, window.ijsSnippetsConfig );
JS;
    }

    public function add_post_assignment_meta_boxes()
    {
        if (!$this->user_can_manage()) {
            return;
        }

        foreach ($this->get_assignable_post_types() as $screen) {
            add_meta_box(
                'assign_js_snippets_box',
                'Active JavaScript Snippets',
                [$this, 'render_post_assignment_meta_box'],
                $screen,
                'side',
                'default',
                ['__back_compat_meta_box' => true]
            );
        }
    }

    public function render_post_assignment_meta_box($post)
    {
        wp_nonce_field('assign_js_save_meta', 'assign_js_nonce');

        $all_snippets = $this->get_published_snippets();
        $assigned = get_post_meta($post->ID, self::ASSIGN_META, true);
        $assigned = is_array($assigned) ? array_map('intval', $assigned) : [];

        if (empty($all_snippets)) {
            echo '<p>No published JS snippets yet. <a href="' . esc_url(admin_url('edit.php?post_type=' . self::CPT)) . '">Manage JS Snippets</a></p>';
            return;
        }

        echo '<p>Select which snippets should load on this ' . esc_html($post->post_type) . ':</p>';
        echo '<div style="max-height:200px; overflow-y:auto; border:1px solid #ccd0d4; padding:6px; background:#fff;">';

        foreach ($all_snippets as $snippet) {
            $sitewide = ($this->get_target_type($snippet->ID) === 'sitewide');

            echo '<label style="display:block; margin-bottom:6px;">';
            echo '<input type="checkbox" name="assigned_js_snippets[]" value="' . esc_attr($snippet->ID) . '" ' . checked(in_array((int) $snippet->ID, $assigned, true), true, false) . '> ';
            echo esc_html($snippet->post_title !== '' ? $snippet->post_title : '(no title)');
            echo ' <span style="color:#666; font-size:11px;">(' . esc_html($this->get_location($snippet->ID)) . ($sitewide ? ', already sitewide' : '') . ')</span>';
            echo '</label>';
        }

        echo '</div>';
    }

    public function save_post_assignment_meta($post_id, $post)
    {
        if (
            !isset($_POST['assign_js_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['assign_js_nonce'])), 'assign_js_save_meta')
        ) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if ($post->post_type === self::CPT) {
            return;
        }
        if (!current_user_can('edit_post', $post_id) || !$this->user_can_manage()) {
            return;
        }

        $submitted = isset($_POST['assigned_js_snippets']) ? (array) wp_unslash($_POST['assigned_js_snippets']) : [];
        $valid = $this->sanitize_assigned($submitted);

        if (empty($valid)) {
            delete_post_meta($post_id, self::ASSIGN_META);
        } else {
            update_post_meta($post_id, self::ASSIGN_META, $valid);
        }
    }

    /* ---------------------------------------------------------------
    - Frontend output
    - ------------------------------------------------------------- */

    /**
     * IDs of published snippets that apply to the current request:
     * those assigned to the current singular post, plus all sitewide ones.
     * Shared by CSS enqueueing and JS injection.
     *
     * @return int[]
     */
    private function get_active_snippet_ids()
    {
        if (null !== $this->active_ids) {
            return $this->active_ids;
        }

        $ids = [];

        if (is_singular()) {
            $current_post_id = get_queried_object_id();
            $assigned = $current_post_id ? get_post_meta($current_post_id, self::ASSIGN_META, true) : [];
            if (is_array($assigned)) {
                foreach ($assigned as $snippet_id) {
                    $snippet_id = (int) $snippet_id;
                    if (
                        get_post_type($snippet_id) === self::CPT &&
                        get_post_status($snippet_id) === 'publish'
                    ) {
                        $ids[$snippet_id] = $snippet_id;
                    }
                }
            }
        }

        foreach ($this->get_published_snippets() as $snippet) {
            if ($this->get_target_type($snippet->ID) === 'sitewide') {
                $ids[$snippet->ID] = (int) $snippet->ID;
            }
        }

        $this->active_ids = array_values($ids);
        return $this->active_ids;
    }

    public function enqueue_snippet_styles()
    {
        foreach ($this->get_active_snippet_ids() as $id) {
            $css = get_post_meta($id, '_js_snippet_css', true);
            if (!is_string($css) || trim($css) === '') {
                continue;
            }
            $handle = 'ijs-snippet-css-' . $id;
            wp_register_style($handle, false, [], self::VERSION);
            wp_enqueue_style($handle);
            wp_add_inline_style($handle, $css);
        }
    }

    private function render_snippets_for_location($location_name)
    {
        foreach ($this->get_active_snippet_ids() as $id) {
            if ($this->get_location($id) !== $location_name) {
                continue;
            }
            $code = get_post_meta($id, '_js_snippet_code', true);
            if (is_string($code) && trim($code) !== '') {
                echo "<!-- JS Snippet: " . esc_html(get_the_title($id)) . " -->\n";
                wp_print_inline_script_tag($code);
            }
        }
    }

    public function inject_snippets_header()
    {
        $this->render_snippets_for_location('header');
    }
    public function inject_snippets_body()
    {
        $this->render_snippets_for_location('body');
    }
    public function inject_snippets_footer()
    {
        $this->render_snippets_for_location('footer');
    }
}

// Register plugin activation hook
register_activation_hook(__FILE__, ['Individual_JS_Snippets', 'activate']);

new Individual_JS_Snippets();