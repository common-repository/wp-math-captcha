<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Math_Captcha_Cookie_Session class.
 *
 * @class Math_Captcha_Cookie_Session
 */
class Math_Captcha_Cookie_Session {

	public $session_ids = [];

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// actions
		add_action( 'plugins_loaded', [ $this, 'init_session' ], 1 );
	}

	/**
	 * Initialize cookie-session.
	 *
	 * @return void
	 */
	public function init_session() {
		if ( is_admin() && ! wp_doing_ajax() )
			return;
		
		$cookie_name = 'mcaptcha_session_';
		
		if ( isset( $_COOKIE ) ) {
			foreach ( $_COOKIE as $name => $value ) {
				$cookie = '';
				$form_id = '';
				
				if ( stripos( $name, $cookie_name ) === 0 ) {
					$cookie = sanitize_text_field( $_COOKIE[$name] );
					$form_id = sanitize_key( str_replace( $cookie_name, '', $name ) );
				}
				
				if ( $cookie && $form_id )
					$this->session_ids[$form_id] = $cookie;
			}
		}

		/* cookie exists?
		if ( isset( $_COOKIE['mcaptcha_session'] ) && is_string( $_COOKIE['mcaptcha_session'] ) ) {
			$cookie = json_decode( $_COOKIE['mcaptcha_session'], true );

			// valid cookie?
			if ( is_array( $cookie ) && ( json_last_error() === JSON_ERROR_NONE ) )
				$this->session_ids = $cookie;
		}
		*/
	}
	
	/**
	 * Set or update cookie.
	 */
	public function set_cookie( $form_id = '' ) {
		$form_id = sanitize_key( $form_id );
		
		if ( ! $form_id )
			return;
		
		// check whether php version is at least 7.3
		if ( version_compare( phpversion(), '7.3', '>=' ) ) {
			// set cookie
			setcookie(
				'mcaptcha_session_' . $form_id,
				$this->session_ids[$form_id],
				[
					'expires'	=> 0,
					'path'		=> COOKIEPATH,
					'domain'	=> COOKIE_DOMAIN,
					'secure'	=> is_ssl(),
					'httponly'	=> true,
					'samesite'	=> 'LAX'
				]
			);
		} else {
			// set cookie
			setcookie( 'mcaptcha_session_' . $form_id, $this->session_ids[$form_id], 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}
	}
	
	/**
	 * Add hashed cookie.
	 *
	 * @return void
	 */
	public function add_cookie( $form_id = '' ) {
		$form_id = sanitize_key( $form_id );
		
		if ( ! $form_id )
			return false;
		
		// valid cookie?
		$this->session_ids[$form_id] = sha1( $this->generate_password() );
		
		// set cookie
		$this->set_cookie( $form_id );
		
		return $this->session_ids[$form_id];
		
		/*
		$cookie = json_decode( $_COOKIE['mcaptcha_session'], true );

		// valid cookie?
		if ( is_array( $cookie ) && ( json_last_error() === JSON_ERROR_NONE ) ) {
			$this->session_ids = $cookie[$form_id] = sha1( $this->generate_password() );
		}
		
		if ( empty( $this->session_ids ) ) {
			// add default hash
			$this->session_ids = [
				$form_id	=> sha1( $this->generate_password() )
			];
		}
		
		// set cookie
		$this->set_cookie( $form_id );
		
		return $this->session_ids[$form_id];
		*/
	}

	/**
	 * Generate password helper, without wp_rand() call.
	 *
	 * @param int $length
	 * @return string
	 */
	private function generate_password( $length = 64 ) {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$password = '';

		for ( $i = 0; $i < $length; $i ++ ) {
			$password .= substr( $chars, mt_rand( 0, 61 ), 1 );
		}

		return $password;
	}
}
