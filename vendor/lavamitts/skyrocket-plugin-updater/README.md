# GitHub Plugin Updater

A minimal, single-file, zero-dependency WordPress plugin updater that checks GitHub Releases and installs **explicitly uploaded `.zip` release assets** rather than GitHub's auto-generated source archives.

Built specifically for shared hosting environments with strict **inode limits**, where multi-dozen-file update libraries (and full Composer `vendor/` trees) add unnecessary filesystem overhead.

---

## Key Features

- **Single-File Footprint:** One self-contained PHP class (`src/Updater.php`) with zero external runtime dependencies.
- **Explicit Release Assets Only:** Ignores GitHub's auto-generated `zipball_url` / `tarball_url` source archives and downloads the first `.zip` binary attached directly to your GitHub Release—avoiding folder-rename mismatches (`repo-1.2.3/` vs `repo/`) during WordPress upgrades.
- **Native WordPress Integration:** Hooks directly into `pre_set_site_transient_update_plugins`, `plugins_api` ("View details" changelog modal), and `upgrader_process_complete`.
- **Manual Update Check + Admin Notices:** Adds a **Check for updates** action link on the WordPress Plugins screen that flushes the cache, queries GitHub immediately, and displays a native dismissible WordPress notice (`is up to date` or `An update is available`).
- **Transient Caching:** Caches GitHub API responses for 6 hours by default (and caches failed requests for 15 minutes) to stay well within GitHub's unauthenticated API rate limits.
- **Public & Private Repository Support:** Works out of the box with public repositories and supports GitHub Personal Access Tokens (PATs) for private repositories.

---

## Requirements

- **PHP:** `>= 8.0`
- **WordPress:** `>= 6.0` (uses native `wp_remote_get()` and WordPress transients)

---

## Installation

Add the repository to your plugin's `composer.json` and require the package:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/lavamitts/skyrocket-plugin-updater"
        }
    ],
    "require-dev": {
        "lavamitts/skyrocket-plugin-updater": "^1.0"
    }
}
```

Then run:

```bash
composer install
```

---

## Usage

You can integrate the updater in two ways depending on how you distribute your plugin.

### Option A: Zero-Vendor Single-File Distribution (Recommended for Low-Inode Hosting)

Use Composer strictly as a development/build tool to manage versions of `Updater.php`, copy that single file into your plugin's `includes/` folder during your release build, and omit `vendor/` from your distribution `.zip`.

1. **Copy `Updater.php` during your build step:**
   ```bash
   cp vendor/lavamitts/skyrocket-plugin-updater/src/Updater.php build/your-plugin/includes/Updater.php
   ```

2. **Load and instantiate it in your plugin** (wrapped in `class_exists()` so multiple plugins using this updater on the same WordPress site never collide):
   ```php
   <?php
   /**
    * Plugin Name: My Custom Plugin
    * Version:     1.0.0
    */

   if (!defined('ABSPATH')) {
       exit;
   }

   if (!class_exists(\Skyrocket\GitHubUpdater\Updater::class)) {
       require_once __DIR__ . '/includes/Updater.php';
   }

   new \Skyrocket\GitHubUpdater\Updater(
       __FILE__,
       'lavamitts/my-custom-plugin'
   );
   ```

### Option B: Standard Composer Autoloading

If you already ship a `vendor/` directory with your plugin, move `"lavamitts/skyrocket-plugin-updater": "^1.0"` from `require-dev` to `require` in `composer.json`, include Composer's autoloader, and instantiate the class:

```php
require_once __DIR__ . '/vendor/autoload.php';

new \Skyrocket\GitHubUpdater\Updater(
    __FILE__,
    'lavamitts/my-custom-plugin'
);
```

---

## Constructor Reference

```php
new \Skyrocket\GitHubUpdater\Updater(
    string $pluginFile,
    string $githubRepo,
    ?string $accessToken = null,
    int $cacheTtl = 21600
);
```

| Parameter | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `$pluginFile` | `string` | *Required* | Full path to your plugin's main PHP file (always pass `__FILE__`). |
| `$githubRepo` | `string` | *Required* | Target GitHub repository in `owner/repo` format (e.g., `'lavamitts/my-custom-plugin'`). |
| `$accessToken` | `?string` | `null` | Optional GitHub Personal Access Token (PAT) for private repositories. |
| `$cacheTtl` | `int` | `21600` | How long (in seconds) to cache GitHub release metadata in a WordPress transient (`21600` = 6 hours). |

---

## Private Repositories

To enable updates from a private GitHub repository, generate a **Fine-grained Personal Access Token** on GitHub with **Contents: Read-only** permission scoped to the target repository, and pass it as the third argument:

```php
new \Skyrocket\GitHubUpdater\Updater(
    __FILE__,
    'lavamitts/my-private-plugin',
    'github_pat_xxxxxxxxxxxxxxxxxxxx'
);
```

When an access token is provided, the updater automatically switches from `browser_download_url` to the authenticated GitHub API asset endpoint (`application/octet-stream`) and injects the `Authorization: Bearer` header into WordPress's HTTP download request.

---

## Publishing Releases on GitHub

For WordPress to detect and install an update cleanly, your GitHub Release **must** follow these three rules:

1. **Tag Format:** Create a GitHub Release with a semantic version tag matching or prefixed with `v` (e.g., `1.1.0` or `v1.1.0`). Leading `v`/`V` characters are stripped automatically when comparing against your plugin header's `Version:`.
2. **Attach a `.zip` Asset:** Upload your packaged plugin `.zip` directly to the release's **Assets** section. Releases without an attached `.zip` asset are ignored.
3. **Clean Top-Level Folder Inside the `.zip`:** The top-level folder inside your uploaded `.zip` **must match your plugin's installed directory slug** (without version numbers):
   ```text
   my-custom-plugin-1.1.0.zip
   └── my-custom-plugin/
       ├── my-custom-plugin.php
       ├── includes/
       │   └── Updater.php
       └── README.md
   ```
   *(If the folder inside the `.zip` has a version suffix like `my-custom-plugin-1.1.0/`, WordPress will treat it as a separate plugin directory upon extraction rather than overwriting the active plugin.)*

---

## How Caching & Manual Checks Work

- **Automatic Checks:** Whenever WordPress runs its scheduled plugin update check, `Updater` reads from a transient named `gh_updater_<md5_of_plugin_basename>`. If expired or missing, it fetches `https://api.github.com/repos/{owner}/{repo}/releases/latest`.
- **Rate-Limit Protection:** If GitHub returns a non-`200` response (such as rate-limiting or a network timeout), the empty result is cached for **15 minutes** (`900` seconds) to prevent repeated blocking HTTP requests on admin page loads.
- **Manual Check:** Clicking **Check for updates** under the plugin's description on **Plugins -> Installed Plugins** deletes the transient, forces `wp_update_plugins()` to run immediately, and displays a dismissible admin notice confirming whether an update was found.
- **Post-Upgrade Cleanup:** Hooking into `upgrader_process_complete` automatically clears the cached release transient after a plugin update finishes.

---

## License

MIT