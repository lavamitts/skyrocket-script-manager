# Skyrocket Script Manager

A lightweight WordPress plugin for managing individual JavaScript snippets, with optional CSS, directly from the WordPress dashboard.

Scripts can be loaded across the entire website or selectively assigned to individual posts, pages and supported custom post types. Choose where each script is injected, manage everything centrally, and avoid editing theme files or adding another general-purpose code management plugin.

## Features

- **Individual script management** – Create, edit, publish and manage JavaScript snippets independently.
- **Optional CSS** – Associate CSS with any snippet. Styles are loaded alongside the corresponding JavaScript.
- **Flexible targeting** – Choose between site-wide loading and assignment to specific content.
- **Block Editor integration** – Assign published snippets through a dedicated JavaScript Snippets panel in the post and page editor.
- **Classic Editor support** – A compatible meta box provides the same assignment functionality.
- **Multiple injection locations** – Load scripts in the document header, immediately after the opening body tag, or in the footer.
- **Custom post type support** – Works with public post types that support the required WordPress editor and custom-field functionality.
- **Centralised management** – View and manage all snippets from a dedicated WordPress admin menu.
- **Bundled default scripts** – Optional scripts can be included with the plugin and automatically installed as draft snippets on activation.
- **GitHub updates** – Supports plugin updates through the GitHub-based updater included with the plugin.

## Requirements

| Requirement | Minimum version |
|---|---|
| WordPress | 6.6 |
| PHP | 7.4 |

The Block Editor assignment panel requires the relevant post type to support the WordPress REST API and custom fields.

## Installation

1. Download the latest release from the [GitHub Releases](../../releases) page.
2. Upload the plugin ZIP through **Plugins → Add New Plugin → Upload Plugin** in WordPress.
3. Activate **Skyrocket Script Manager**.
4. A new **JS Snippets** menu will appear in the WordPress dashboard.

Alternatively, install the plugin directly into your WordPress plugins directory.

## Managing snippets

Navigate to **JS Snippets → Add New Snippet**.

Each snippet has the following settings:

### JavaScript code

Enter the JavaScript to be executed on the frontend.

Do not include `<script>` tags. The plugin generates the script element automatically.

### Optional CSS

Add any CSS associated with the snippet.

Do not include `<style>` tags. The CSS is registered and loaded as an inline stylesheet on the same pages as the associated JavaScript.

### Description

Add an internal description explaining the purpose of the snippet. This is displayed in the administration list to make individual scripts easier to identify and maintain.

Descriptions are not displayed on the frontend.

### Injection location

Choose where the JavaScript should be inserted:

| Location | WordPress hook | Output position |
|---|---|---|
| Header | `wp_head` | Document head |
| Body | `wp_body_open` | Immediately after opening body tag |
| Footer | `wp_footer` | Footer |

The selected location applies to the JavaScript snippet. Associated CSS is enqueued separately through WordPress.

### Targeting

Each snippet has two targeting options:

**Entire Site**

The snippet is loaded across the website wherever the relevant WordPress frontend hooks execute.

**Assigned via Post/Page Editor**

The snippet is loaded only on content to which it has been assigned.

Assignments are made through the JavaScript Snippets panel in the Block Editor or the corresponding meta box in the Classic Editor.

Only published snippets are available for assignment and frontend execution.

## Assigning snippets to content

When editing a post, page or supported custom post type:

1. Open the **JavaScript Snippets** panel in the Block Editor sidebar.
2. Select the snippets you want to load on that particular piece of content.
3. Save or update the post.

The plugin also provides a compatible assignment meta box for the Classic Editor.

Snippets configured for site-wide loading are identified in the assignment interface. Assigning one of these snippets to individual content does not change its site-wide behaviour.

## Bundled default scripts

The plugin supports optional pre-packaged JavaScript snippets.

Bundled scripts are organised into individual folders within `default-scripts/`.

Each folder must contain a JavaScript file with the same name as its parent folder:

```text
default-scripts/
├── external-link-indicator/
│   ├── external-link-indicator.js
│   └── external-link-indicator.css
├── example-script/
│   └── example-script.js
```

The CSS file is optional.

When the plugin is activated, it scans this directory and creates a corresponding **draft snippet** for each valid script.

The generated snippets are configured with:

- The folder name converted into a readable title.
- The JavaScript code copied into the snippet.
- The optional CSS copied into the snippet.
- Footer injection as the default location.
- Site-wide targeting as the default targeting rule.
- Draft status, requiring manual review and publication.

Existing snippets with matching slugs are not duplicated during subsequent activations.

Bundled scripts are templates rather than automatically executed code. They must be reviewed and published before they will run.

## Permissions and security

Managing JavaScript snippets requires both the `manage_options` and `unfiltered_html` capabilities by default.

This restricts access to users with administrative privileges who are also permitted to save unfiltered HTML.

The `ijs_user_can_manage` filter allows developers to customise this permission check.

Snippet assignments are also validated against published snippets belonging to the plugin's custom post type.

Standard WordPress nonce verification, capability checks and input handling are used when saving snippet configuration and assignments.

**Important:** JavaScript and CSS are executed or applied on the frontend. Only add code from trusted sources and review bundled or third-party scripts before publishing them.

## Developer information

### Custom post type

The plugin registers a non-public custom post type:

```php
js_snippet
```

It is available in the WordPress administration area but is not exposed as a public post type or through the REST API.

### Assignment metadata

Snippet assignments are stored against the relevant post using:

```php
_assigned_js_snippets
```

The value is an array of published snippet IDs.

The metadata is registered for supported public post types and exposed through the REST API for Block Editor integration.

### Permission filter

The default permission check can be modified using:

```php
add_filter('ijs_user_can_manage', function ($can, $user_id) {
    // Return true or false to override the default permission.
    return $can;
}, 10, 2);
```

Use this filter carefully, as it controls access to functionality capable of injecting arbitrary JavaScript and CSS into the website.

### Frontend implementation

The plugin uses native WordPress actions and functions for frontend output:

- `wp_enqueue_scripts` – Registers and enqueues associated CSS.
- `wp_head` – Injects header scripts.
- `wp_body_open` – Injects body scripts.
- `wp_footer` – Injects footer scripts.

Active snippets are resolved once per request and cached for subsequent use.

## Updating

The plugin includes the Skyrocket Plugin Updater, which checks the configured GitHub repository for available releases.

Updates can be delivered through the standard WordPress plugin update mechanism.

## Building a release

The plugin uses [Skyrocket Plugin Updater](https://github.com/lavamitts/skyrocket-plugin-updater) as a Composer dependency.

The updater dependency also provides the distribution build script, which prepares the plugin files for release.

After installing the Composer dependencies, run the following command from the plugin root directory:

```bash
./vendor/lavamitts/skyrocket-plugin-updater/tools/build-distribution.sh
```

This builds the release candidate, preparing a distributable version of the plugin without requiring the development dependency structure to be included in the release.

The resulting distribution package is intended for installation through the standard WordPress plugin installation process.

## Licence

See the repository for licensing information.