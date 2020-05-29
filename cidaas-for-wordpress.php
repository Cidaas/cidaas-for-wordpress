<?php
/*
Plugin Name: Login using Cidaas
Plugin URI: 
Description: Enhance your login experience with social login like Google, FB, as well as explore options of SSO, passwordless, all while being secure and compliant to world standards
Version: 1.0.0
Author: Gopi Mallela
License: GPLv2
*/

class Cidaas {
	// plugin version
	const VERSION = '1.0.0';

	// plugin settings
	private $settings;

	// plugin logs
	private $logger;

	// openid connect generic client
	private $client;

	// settings admin page
	private $settings_page;

	// login form adjustments
	private $login_form;

	/**
	 * Setup the plugin
	 *
	 * @param Cidaas_Option_Settings $settings
	 * @param Cidaas_Option_Logger $logger
	 */
	function __construct( Cidaas_Option_Settings $settings, Cidaas_Option_Logger $logger ){
		$this->settings = $settings;
		$this->logger = $logger;
	}

	/**
	 * WP Hook 'init'
	 */
	function init(){

		$redirect_uri = admin_url( 'admin-ajax.php?action=openid-connect-authorize' );

		if ( $this->settings->alternate_redirect_uri ){
			$redirect_uri = site_url( '/openid-connect-authorize' );
		}

		$state_time_limit = 180;
		if ($this->settings->state_time_limit) {
			$state_time_limit = intval($this->settings->state_time_limit);
		}

		$this->client = new Cidaas_Client(
			$this->settings->client_id,
			$this->settings->client_secret,
			$this->settings->scope,
			$this->settings->endpoint_login,
			$this->settings->endpoint_userinfo,
			$this->settings->endpoint_token,
			$redirect_uri,
			$state_time_limit
		);

		$this->client_wrapper = Cidaas_Client_Wrapper::register( $this->client, $this->settings, $this->logger );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		$this->login_form = Cidaas_Login_Form::register( $this->settings, $this->client_wrapper );

		// add a shortcode to get the auth url
		add_shortcode( 'Cidaas_auth_url', array( $this->client_wrapper, 'get_authentication_url' ) );

		$this->upgrade();

		if ( is_admin() ){
			$this->settings_page = Cidaas_Settings_Page::register( $this->settings, $this->logger );
		}
	}

	/**
	 * Check if privacy enforcement is enabled, and redirect users that aren't
	 * logged in.
	 */
	function enforce_privacy_redirect() {
		if ( $this->settings->enforce_privacy && ! is_user_logged_in() ) {
			// our client endpoint relies on the wp admind ajax endpoint
			if ( ! defined( 'DOING_AJAX') || ! DOING_AJAX || ! isset( sanitize_text_field($_GET['action']) ) || sanitize_text_field($_GET['action']) != 'openid-connect-authorize' ) {
				auth_redirect();
			}
		}
	}

	/**
	 * Enforce privacy settings for rss feeds
	 *
	 * @param $content
	 *
	 * @return mixed
	 */
	function enforce_privacy_feeds( $content ){
		if ( $this->settings->enforce_privacy && ! is_user_logged_in() ) {
			$content = 'Private site';
		}
		return $content;
	}

	/**
	 * Handle plugin upgrades
	 */
	function upgrade(){
		$last_version = get_option( 'cidaas-plugin-version', 0 );
		$settings = $this->settings;

		if ( version_compare( self::VERSION, $last_version, '>' ) ) {
			// upgrade required

			// @todo move this to another file for upgrade scripts
			if ( isset( $settings->ep_login ) ) {
				$settings->endpoint_login = $settings->ep_login;
				$settings->endpoint_token = $settings->ep_token;
				$settings->endpoint_userinfo = $settings->ep_userinfo;

				unset( $settings->ep_login, $settings->ep_token, $settings->ep_userinfo );
				$settings->save();
			}

			// update the stored version number
			update_option( 'cidaas-plugin-version', self::VERSION );
		}
	}

	/**
	 * Simple autoloader
	 *
	 * @param $class
	 */
	static public function autoload( $class ) {
		$prefix = 'Cidaas_';

		if ( stripos($class, $prefix) !== 0 ) {
			return;
		}

		$filename = $class . '.php';

		// internal files are all lowercase and use dashes in filenames
		if ( false === strpos( $filename, '\\' ) ) {
			$filename = strtolower( str_replace( '_', '-', $filename ) );
		}
		else {
			$filename  = str_replace('\\', DIRECTORY_SEPARATOR, $filename);
		}

		$filepath = dirname( __FILE__ ) . '/includes/' . $filename;

		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}

	/**
	 * Instantiate the plugin and hook into WP
	 */
	static public function bootstrap(){
		spl_autoload_register( array( 'Cidaas', 'autoload' ) );

		$settings = new Cidaas_Option_Settings(
			'Cidaas_settings',
			// default settings values
			array(
				// oauth client settings
				'login_type'        => 'button',
				'client_id'         => '',
				'client_secret'     => '',
				'scope'             => '',
				'endpoint_login'    => '',
				'endpoint_userinfo' => '',
				'endpoint_token'    => '',
				'endpoint_end_session' => '',

				// non-standard settings
				'no_sslverify'    => 0,
				'http_request_timeout' => 5,
				'identity_key'    => 'preferred_username',
				'nickname_key'    => 'preferred_username',
				'email_format'       => '{email}',
				'displayname_format' => '',
				'identify_with_username' => false,

				// plugin settings
				'enforce_privacy' => 0,
				'alternate_redirect_uri' => 0,
				'link_existing_users' => 0,
				'redirect_user_back' => 0,
				'redirect_on_logout' => 1,
				'enable_logging'  => 0,
				'log_limit'       => 1000,
			)
		);

		$logger = new Cidaas_Option_Logger( 'cidaas-logs', 'error', $settings->enable_logging, $settings->log_limit );

		$plugin = new self( $settings, $logger );

		add_action( 'init', array( $plugin, 'init' ) );

		// privacy hooks
		add_action( 'template_redirect', array( $plugin, 'enforce_privacy_redirect' ), 0 );
		add_filter( 'the_content_feed', array( $plugin, 'enforce_privacy_feeds' ), 999 );
		add_filter( 'the_excerpt_rss',  array( $plugin, 'enforce_privacy_feeds' ), 999 );
		add_filter( 'comment_text_rss', array( $plugin, 'enforce_privacy_feeds' ), 999 );
	}
}

Cidaas::bootstrap();
