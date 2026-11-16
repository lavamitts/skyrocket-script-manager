
# Individual JS script



## Features

### Administration


## Installation


## Structure


## Development

## Builds

To build the release candidate, run this script:

`./tools/build-release-zip.sh`

In preparation for this:

- ensure that the plugin main file has an up to date version unmber
- while this may not be needed on every plugin, it is good practice to define a cache busting constant, namespaced as per the plugin, e.g:

    `define('SKYROCKET_SCRIPT_MANAGER_VERSION', '1.0.2');`

- ensure that the label and the version match the folder / filename and stated plugin version
- ensure that there is an up to date CHANGELOG.md with a version that matches the latest version

## Licence

Private project. Not intended for public distribution.


## Sym links

ln -s \
"$HOME/skyrocket-dev/wordpress-plugins/skyrocket-script-manager" \
"$HOME/skyrocket-dev/plugin-development-environment/app/public/wp-content/plugins/skyrocket-script-manager"

To update the update mechanism

`composer update lavamitts/github-plugin-updater`