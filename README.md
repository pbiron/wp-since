#  WP Since
List changes in a WP version.

## Description
WP_CLI version of [https://developer.wordpress.org/reference/since/&lt;version>](https://developer.wordpress.org/reference/since/4.7.0).

Must be run on a local install, and will **not** run against the live DevHub.

## Options

`$ wp since [<version>] [--change_type=<change_type>] [--post_type=<type>]`

&nbsp;&nbsp;&nbsp;&nbsp;[&lt;version>]: the version to list changes for.<br />
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Default: the current version.<br />
&nbsp;&nbsp;&nbsp;&nbsp;[--change\_type=&lt;change\_type>]: type of change to list changes for. One of (any | introduced | modified).<br />
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Default: any.<br />
&nbsp;&nbsp;&nbsp;&nbsp;[--post\_type=&lt;type>]: post\_type to list changes for.  One of (any | wp-parser-class | wp-parser-method | wp-parser-function|wp-parser-hook).<br />
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Default: any.

## Examples

### List all changes in current version

`$ wp since`

### List all changes in version x.y.z

`$ wp since x.y.z`

### List changes introduced in version x.y.z

`$ wp since x.y.z --change_type=introduced`

### List changes modified in current version

`$ wp since --change_type=modified`

### List all changes to hooks in version x.y.z

`$ wp since x.y.z --post_type=wp-parser-hook`

## Installation
You can install this plugin by one of two methods:

1. install via the zip file
    1. Unzip the zip file into the `/wp-content/plugins/` directory
    1. Activate the plugin through the 'Plugins' menu in WordPress
1. install via [GitHub Updater](https://github.com/afragen/github-updater)
    1. Go to Settings > GitHub Updater in the WordPress admin
    1. Click on the "Install Plugin" tab
    1. Enter the URL for this repo in the "Plugin URI" field
    1. Click "Install Plugin"
    1. Activate this plugin
 
Regardless of the method used to install this plugin, you must then:

1. Activate the phpdoc-parser plugin
1. slurp up the current WP sources via: `$ wp parser create`
1. Once the sources are slurped, you can then deactive phpdoc-parser if you want.

### Minimum Requirements
* Whatever the requirements to run the wporg-developer theme and phpdoc-parser
plugin are (I'm not sure what they are, I've only tested with
[4.8-RC2-40868](https://wordpress.org/news/2017/06/wordpress-4-8-release-candidate-2/) and PHP 5.4).
* The wporg-developer theme (with the patch in the meta.trac ticket at
[allow filtering of @since tax archive by the type of change](https://meta.trac.wordpress.org/ticket/2847)
(or it's equivalent) applied) must be active.

## Changelog

### 0.1.0

* Initial commit

## Ideas?
Please let me know by creating a new [issue](https://github.com/pbiron/wp-since/issues/new) and describe your idea.  
Pull Requests are welcome!

## Buy me a beer

If you like this plugin, please support it's continued development by [buying me a beer](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Z6D97FA595WSU).
