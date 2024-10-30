=== JVM Protected Media ===
Contributors: jorisvanmontfort
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=VXZJG9GC34JJU
Tags: attachments, media, files, protect, protection, members
Requires at least: 4.4.1
Tested up to: 6.0.2
Stable tag: 1.0.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Restrict access to all your media files and implement your own custom file access rules.

== Description ==
Protect access to all your media files and implement your own custom file access rules using a hook. Works for apache with mod rewrite or nginx with some custom configuration. No Multisite support. This plugin is more or less a development tool for defining your own custom file access rules.

For nginx you will need to modify the config file as nginx does not handle .htacess files. Add the following code:

`
location ~ "^/wp-content/uploads/(.*)$" {
    rewrite ^/wp-content/uploads(/.*\.\w+)$ /index.php?jvm_protected_media_file=$1;
}
`

== Installation ==

1. Install the plugin from the Plugins or upload the plugin folder to the `/wp-content/plugins/` directory menu and then activate it. If your server does not meet the requirements the plugin will show a notification.
2. Define your own access rules in your themes functions.php

== Hooks ==

Without a custom hook all file access will be disabled. The user will see the 404 page for all requested files. Adding a hook is needed to handle your own file access rules. A simple example that could go into your functions.php:

`
function my_file_access_rule($file_info) {
	// Implement your own logic here
	$userHasAccess = true;

	if($userHasAccess) {
		// Send the file output if users has access to the file
		JVM_Protected_Media::send_file_output($file_info['path']);
	}
}

add_action( 'jvm_protected_media_file', 'my_file_access_rule');
`

The jvm_protected_media_file action has one parameter with the following file information:

`
Array
(
    [id] => id_of_the_file
    [url] => full/url/to/your/file
    [path] => full/path/to/your/file
    [is_resized_image] => bool (true if the requested file is a image thumbnail or resized version of an image)
)
`

== Actions ==

Available actions:

* jvm_protected_media_loaded (fires as soon as the plugin is loaded)
* jvm_protected_media_file (fires when a file is requested)

== Functions ==

To send the output of a file to you can call: 

`
JVM_Protected_Media::send_file_output($fullPathToFile)
`

== Changelog ==

= 1.0.6 =
Added nginx check and admin notice for nginx users.

= 1.0.5 =
Tested up to 5.2.2 and added a comment for nginx usage.

= 1.0.4 =
Better flushing of rewrite rules and plugin tested up to 5.0.3

= 1.0.3 =
Fix for the .htaccess file being reset in some situations.

= 1.0.2 =
Added a 24 hour cache time to the output. This effectivly trigger the 304 not modified header if the file is indeed not modified.

= 1.0.1 =
Bug fix: When requesting files that are not in the media library the plugin was not calling the jvm_protected_media_file action. Fixed this. In this case the action is called but the file_id will remain empty.

= 1.0.0 =
Initial release

= Stable =
1.0.3