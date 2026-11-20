# Changelog

## 1.0.0 - Initial release

- Initial release

## 1.0.1 - Renaming of plugin

- Renaming from individual-js-scripts to Skyrocket script manager

## 1.0.2 - Addition of build and update tools

- Addition of build and update tools

## 1.1.0 - Additional of default script capability

- Additional of default script capability - initial script is regex abbreviation replacement for specifiable terms.

## 1.2.0 - Addition of new window script with CSS enqueuing

- Addition of new window script with CSS enqueuing

## 1.3.0 - Finalisation of build and update tools

- Finalisation of build and update tools

## 1.3.0 - Move to using composer and dependabot for code updater versioning

- Move to using composer and dependabot for code updater versioning

## 1.4.0 - Genericise the update process

- Remove namespacing from update functions
- Remove tokens

## 1.4.1 - Include v. 1.0.1 of the updater plugin

- Check for update feature appears on the plugins page in WordPress.
- Messaging is visible when there is or is not an update to install.


## 1.4.2 - Include v. 1.0.2 of the updater plugin

- Resolved an issue where manual update checks (Check for updates) would silently fail or report the plugin as up to date if triggered within 60 seconds of a previous check, by forcing WordPress to reset its update_plugins transient cache.

## 1.4.3 - Utilise version 1.0.3 of the plugin updater

1.0.3 of the plugin updater does the following:

- Move responsibility for creating and managing the updater include file from the recipient plugins to the updater itself.
- Comprehensive documentation of the updater plugin.

## 1.4.4 - Utilise version 1.0.4 of the plugin updater
- Build tool now fully generic.
- Build tool now moved into /tools subfolder.

## 1.4.5 - Utilise version 1.0.5 of the plugin updater
- Now using "lavamitts/skyrocket-plugin-updater" instead of "lavamitts/github-plugin-updater".
- Build tooling adjusted to NOT include the composer and autoload files in the distribution.

## 1.4.6 - Correct broken reference to plugin updater
- Correct broken reference to plugin updater
- Remove vendor files from repo

## 1.4.7 - Add in registered trademark default script
- Add in registered trademark default script

## 1.4.8 - Improve registered trademark default script
- Improve registered trademark default script
- Resolve issues in Blocksy menus

## 1.4.9 - Add in JS Snippets link to plugin window
- Add in JS Snippets link to plugin window