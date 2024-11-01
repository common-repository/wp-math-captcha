<?php
/*
Plugin Name: Math Captcha
Description: Math Captcha is a <strong>100% effective CAPTCHA for WordPress</strong> that integrates into login, registration, comments, Contact Form 7 and bbPress.
Version: 1.3.0
Author: dFactory
Author URI: http://www.dfactory.co/
Plugin URI: http://www.dfactory.co/products/math-captcha/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: math-captcha
Domain Path: /languages

Math Captcha
Copyright (C) 2013-2024, Digital Factory - info@digitalfactory.pl

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Math Captcha class.
 *
 * @class Math_Captcha
 * @version 1.3.0
 */
class Math_Captcha {

	private static $instance;
	public $core;
	public $cookie_session;
	public $options;
	public $defaults = [
		'general'	=> [
			'enable_for'				=> [
				'login_form'			=> false,
				'registration_form'		=> true,
				'reset_password_form'	=> true,
				'comment_form'			=> true,
				'bbpress'				=> false,
				'contact_form_7'		=> false
			],
			'reloading'					=> true,
			'block_direct_comments'		=> false,
			'hide_for_logged_users'		=> true,
			'title'						=> 'Math Captcha',
			'mathematical_operations'	=> [
				'addition'			=> true,
				'subtraction'		=> true,
				'multiplication'	=> false,
				'division'			=> false
			],
			'groups'					=> [
				'numbers'	=> true,
				'words'		=> false
			],
			'time'						=> 300,
			'deactivation_delete'		=> false,
			'flush_rules'				=> false
		],
		'version'	=> '1.3.0'
	];

	/**
	 * Main plugin instance, insures that only one instance of the class exists in memory at one time.
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Math_Captcha ) ) {
			self::$instance = new Math_Captcha();

			add_action( 'init', [ self::$instance, 'load_textdomain' ] );

			self::$instance->includes();

			// initialize admin classes
			if ( is_admin() ) {
				new Math_Captcha_Update();
				new Math_Captcha_Settings();
			}

			// initialize other classes
			self::$instance->cookie_session = new Math_Captcha_Cookie_Session();
			self::$instance->core = new Math_Captcha_Core();
		}

		return self::$instance;
	}

	/**
	 * Disable object cloning.
	 *
	 * @return void
	 */
	public function __clone() {}

	/**
	 * Disable unserializing of the class.
	 *
	 * @return void
	 */
	public function __wakeup() {}

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// define plugin constants
		$this->define_constants();

		register_activation_hook( __FILE__, [ $this, 'activation' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivation' ] );

		// settings
		$this->options = [
			'general' => array_merge( $this->defaults['general'], get_option( 'math_captcha_options', $this->defaults['general'] ) )
		];

		// actions
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_comments_scripts_styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_comments_scripts_styles' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'frontend_comments_scripts_styles' ] );

		// filters
		add_filter( 'plugin_action_links_' . MATH_CAPTCHA_BASENAME, [ $this, 'plugin_settings_link' ] );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_extend_links' ], 10, 2 );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @return void
	 */
	private function define_constants() {
		define( 'MATH_CAPTCHA_URL', plugins_url( '', __FILE__ ) );
		define( 'MATH_CAPTCHA_BASENAME', plugin_basename( __FILE__ ) );
		define( 'MATH_CAPTCHA_REL_PATH', dirname( MATH_CAPTCHA_BASENAME ) );
		define( 'MATH_CAPTCHA_PATH', plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Include required files.
	 *
	 * @return void
	 */
	private function includes() {
		if ( is_admin() ) {
			include_once( MATH_CAPTCHA_PATH . 'includes/class-update.php' );
			include_once( MATH_CAPTCHA_PATH . 'includes/class-settings.php' );
		}

		include_once( MATH_CAPTCHA_PATH . 'includes/class-cookie-session.php' );
		include_once( MATH_CAPTCHA_PATH . 'includes/class-core.php' );
	}

	/**
	 * Plugin activation.
	 *
	 * @global object $wpdb
	 *
	 * @param bool $network
	 * @return void
	 */
	public function activation( $network ) {
		// network activation?
		if ( is_multisite() && $network ) {
			global $wpdb;

			// get all available sites
			$blogs_ids = $wpdb->get_col( 'SELECT blog_id FROM ' . $wpdb->blogs );

			foreach ( $blogs_ids as $blog_id ) {
				// change to another site
				switch_to_blog( (int) $blog_id );

				// run current site activation process
				$this->activate_site();

				restore_current_blog();
			}
		} else
			$this->activate_site();
	}

	/**
	 * Single site activation.
	 *
	 * @return void
	 */
	public function activate_site() {
		// add default options
		add_option( 'math_captcha_options', $this->defaults['general'], null, false );
		add_option( 'math_captcha_version', $this->defaults['version'], null, false );
	}

	/**
	 * Plugin deactivation.
	 *
	 * @global object $wpdb
	 *
	 * @param bool $network
	 * @return void
	 */
	public function deactivation( $network ) {
		// network deactivation?
		if ( is_multisite() && $network ) {
			global $wpdb;

			// get all available sites
			$blogs_ids = $wpdb->get_col( 'SELECT blog_id FROM ' . $wpdb->blogs );

			foreach ( $blogs_ids as $blog_id ) {
				// change to another site
				switch_to_blog( (int) $blog_id );

				// run current site deactivation process
				$this->deactivate_site( true );

				restore_current_blog();
			}
		} else
			$this->deactivate_site();
	}

	/**
	 * Single site deactivation.
	 *
	 * @param bool $multi
	 * @return void
	 */
	public function deactivate_site( $multi = false ) {
		if ( $multi ) {
			$options = get_option( 'math_captcha_options' );
			$check = $options['deactivation_delete'];
		} else
			$check = $this->options['general']['deactivation_delete'];

		// delete options if needed
		if ( $check ) {
			delete_option( 'math_captcha_options' );
			delete_option( 'math_captcha_version' );
		}
	}

	/**
	 * Load text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'math-captcha', false, MATH_CAPTCHA_REL_PATH . '/languages/' );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $page
	 * @return void
	 */
	public function admin_comments_scripts_styles( $page ) {
		if ( $page === 'settings_page_math-captcha' ) {
			// style
			wp_register_style( 'math-captcha-admin', MATH_CAPTCHA_URL . '/css/admin.css', [], $this->defaults['version'] );
			wp_enqueue_style( 'math-captcha-admin' );

			// script
			wp_register_script( 'math-captcha-admin-settings', MATH_CAPTCHA_URL . '/js/admin-settings.js', [ 'jquery' ], $this->defaults['version'] );
			wp_enqueue_script( 'math-captcha-admin-settings' );

			// prepare script data
			$script_data = [
				'resetToDefaults'	=> __( 'Are you sure you want to reset these settings to defaults?', 'math-captcha' )
			];

			wp_add_inline_script( 'math-captcha-admin-settings', 'var mcArgsSettings = ' . wp_json_encode( $script_data ) . ";\n", 'before' );
		}
	}

	/**
	 * Enqueue frontend scripts and styles
	 *
	 * @return void
	 */
	public function frontend_comments_scripts_styles() {
		wp_register_style( 'math-captcha-frontend', MATH_CAPTCHA_URL . '/css/frontend.css', [], $this->defaults['version'] );
		wp_enqueue_style( 'math-captcha-frontend' );
	}

	/**
	 * Add links to Support Forum.
	 *
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	public function plugin_extend_links( $links, $file ) {
		if ( ! current_user_can( 'install_plugins' ) )
			return $links;

		if ( $file === MATH_CAPTCHA_BASENAME )
			return array_merge( $links, [ sprintf( '<a href="http://www.dfactory.co/support/forum/math-captcha/" target="_blank">%s</a>', esc_html__( 'Support', 'math-captcha' ) ) ] );

		return $links;
	}

	/**
	 * Add link to Settings page.
	 *
	 * @param array $links
	 * @return array
	 */
	public function plugin_settings_link( $links ) {
		if ( ! current_user_can( 'manage_options' ) )
			return $links;

		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url_raw( admin_url( 'options-general.php?page=math-captcha' ) ), esc_html__( 'Settings', 'math-captcha' ) ) );

		return $links;
	}
}

/**
 * Initialize plugin.
 *
 * @return object
 */
function Math_Captcha() {
	static $instance;

	// first call to instance() initializes the plugin
	if ( $instance === null || ! ( $instance instanceof Math_Captcha ) )
		$instance = Math_Captcha::instance();

	return $instance;
}

Math_Captcha();