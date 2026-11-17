# Changelog

## 1.0.0 - Initial release

- Initial release

## 1.0.1 - Check for update feature and messaging

- Check for update feature appears on the plugins page in WordPress.
- Messaging is visible when there is or is not an update to install.

## 1.0.2 - Fix on check for updates
- Resolved an issue where manual update checks (`Check for updates`) would silently fail or report the plugin as up to date if triggered within 60 seconds of a previous check, by forcing WordPress to reset its `update_plugins` transient cache.

## 1.0.3 - Localise the plugin include file
- Move responsibility for creating and managing the updater include file from the recipient plugins to the updater itself.
- Comprehensive documentation of the updater plugin.

## 1.0.4 - Move destination plugin build tooling to the updater
- Build tool now fully generic.
- Build tool now moved into /tools subfolder.