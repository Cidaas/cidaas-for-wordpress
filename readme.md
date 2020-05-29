# Cidaas for Wordpress

Contributors : gopimallela
Tested up to: 1.0.0
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enhance your login experience with social login like Google, FB, as well as explore options of SSO, passwordless, all while being secure and compliant to world standards


== Description ==

This plugin allows to authenticate users against Cidaas identity server with Authorization Code Flow.
Once installed, it can be configured to automatically authenticate users (SSO), or provide a "Login with Cidaas"
button on the login form. After consent has been obtained, an existing user is automatically logged into WordPress, while 
new users are created in WordPress database.


== Installation ==

1. Upload to the `/wp-content/plugins/` directory or Download it from plugin repository
1. Activate the plugin
1. Visit Settings > Cidaas and configure to meet your needs

For more information on installation visit : 


== Frequently Asked Questions ==

= What is the client's Redirect URI? =

The Cidaas servers will require whitelisting a set of redirect URIs for security purposes. The Redirect URI provided
by this client is like so:  https://example.com/wp-admin/admin-ajax.php?action=openid-connect-authorize

Replace `example.com` with your domain name and path to WordPress.

