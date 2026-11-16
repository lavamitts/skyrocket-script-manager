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