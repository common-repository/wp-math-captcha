<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Math_Captcha_Core class.
 *
 * @class Math_Captcha_Core
 */
class Math_Captcha_Integration_WordPress {
	
	public $login_failed = false;
	public $errors;
	
	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// actions
		add_action( 'init', [ $this, 'init' ] );
	}
	
	/**
	 * Init.
	 */
	public function init() {
		if ( is_admin() )
			return;
		
		// get action
		$action = isset( $_GET['action'] ) && $_GET['action'] !== '' ? sanitize_key( $_GET['action'] ) : null;

		// registration
		if ( Math_Captcha()->options['general']['enable_for']['registration_form'] && get_option( 'users_can_register' ) && ( ! is_user_logged_in() || ( is_user_logged_in() && ! Math_Captcha()->options['general']['hide_for_logged_users'] ) ) && $action === 'register' ) {
			$form_id = 'wp_register';
			
			add_action( 'register_form', function() use ( $form_id ) { 
				Math_Captcha()->core->add_captcha_field( $form_id );
			} );
			add_action( 'signup_extra_fields', function() use ( $form_id ) { 
				Math_Captcha()->core->add_captcha_field( $form_id );
			} );
			add_action( 'register_post', [ $this, 'validate_registration_captcha' ], 10, 3 );
			add_filter( 'wpmu_validate_user_signup', [ $this, 'validate_multisite_registration_captcha' ] );
		}

		// lost password
		if ( Math_Captcha()->options['general']['enable_for']['reset_password_form'] && ( ! is_user_logged_in() || (is_user_logged_in() && ! Math_Captcha()->options['general']['hide_for_logged_users'] ) ) && $action === 'lostpassword' ) {
			$form_id = 'wp_lostpassword';
			
			add_action( 'lostpassword_form', function() use ( $form_id ) { 
				Math_Captcha()->core->add_captcha_field( $form_id );
			} );
			add_action( 'lostpassword_post', [ $this, 'validate_lostpassword_captcha' ] );
		}

		// login
		if ( Math_Captcha()->options['general']['enable_for']['login_form'] && ( ! is_user_logged_in() || ( is_user_logged_in() && ! Math_Captcha()->options['general']['hide_for_logged_users'] ) ) && $action === null ) {
			$form_id = 'wp_login';
			
			add_action( 'login_form', function() use ( $form_id ) { 
				Math_Captcha()->core->add_captcha_field( $form_id );
			} );
			add_filter( 'login_redirect', [ $this, 'validate_login_captcha' ], 10, 3 );
			add_filter( 'authenticate', [ $this, 'authenticate_user' ], 1000, 3 );
		}
		
		// comments
		if ( Math_Captcha()->options['general']['enable_for']['comment_form'] ) {
			$form_id = 'wp_comment';
			
			if ( ! is_user_logged_in() ) {
				add_action( 'comment_form_after_fields', function() use ( $form_id ) { 
					Math_Captcha()->core->add_captcha_field( $form_id );
				} );
			} elseif ( ! Math_Captcha()->options['general']['hide_for_logged_users'] ) {
				add_action( 'comment_form_logged_in_after', function() use ( $form_id ) { 
					Math_Captcha()->core->add_captcha_field( $form_id );
				} );
			}

			add_filter( 'preprocess_comment', [ $this, 'validate_comment_captcha' ] );
		}
		
		add_filter( 'shake_error_codes', [ $this, 'add_shake_error_codes' ], 1 );
	}
	
	/**
	 * Add lost password errors.
	 *
	 * @param array $errors
	 * @return array
	 */
	public function add_lostpassword_captcha_message( $errors ) {
		return $errors . $this->errors->errors['math-captcha-error'][0];
	}

	/**
	 * Add lost password errors (special way).
	 *
	 * @return array
	 */
	public function add_lostpassword_wp_message() {
		return $this->errors;
	}
	
	/**
	 * Add shake error code.
	 *
	 * @param array $codes
	 * @return array
	 */
	public function add_shake_error_codes( $codes ) {
		$codes[] = 'math-captcha-error';

		return $codes;
	}

	/**
	 * Validate lost password form.
	 *
	 * @return void
	 */
	public function validate_lostpassword_captcha() {
		$this->errors = new WP_Error();
		$user_error = false;
		$user_data = null;

		// checks captcha
		$form_id = 'wp_comment';
		
		if ( ! empty( $_POST['mcaptcha'] ) ) {
			$mc_value = (int) $_POST['mcaptcha'];

			if ( isset( Math_Captcha()->cookie_session->session_ids[$form_id] ) && get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ) !== false ) {
				if ( strcmp( get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ), sha1( AUTH_KEY . $mc_value . Math_Captcha()->cookie_session->session_ids[$form_id], false ) ) !== 0 )
					$this->errors->add( 'math-captcha-error', Math_Captcha()->core->error_messages['wrong'] );
			} else
				$this->errors->add( 'math-captcha-error', Math_Captcha()->core->error_messages['time'] );
		} else
			$this->errors->add( 'math-captcha-error', Math_Captcha()->core->error_messages['fill'] );

		// checks user_login (from wp-login.php)
		if ( empty( $_POST['user_login'] ) )
			$user_error = true;
		elseif ( strpos( $_POST['user_login'], '@' ) ) {
			$user_data = get_user_by( 'email', trim( $_POST['user_login'] ) );

			if ( empty( $user_data ) )
				$user_error = true;
		} else
			$user_data = get_user_by( 'login', trim( $_POST['user_login'] ) );

		if ( ! $user_data )
			$user_error = true;

		// something went wrong?
		if ( ! empty( $this->errors->errors ) ) {
			// nasty hack (captcha is invalid but user_login is fine)
			if ( $user_error === false )
				add_filter( 'allow_password_reset', [ $this, 'add_lostpassword_wp_message' ] );
			else
				add_filter( 'login_errors', [ $this, 'add_lostpassword_captcha_message' ] );
		}
	}

	/**
	 * Validate registration form.
	 *
	 * @param string $login
	 * @param string $email
	 * @param array $errors
	 * @return array
	 */
	public function validate_registration_captcha( $login, $email, $errors ) {
		$form_id = 'wp_register';
		
		if ( ! empty( $_POST['mcaptcha'] ) ) {
			$mc_value = (int) $_POST['mcaptcha'];

			if ( isset( Math_Captcha()->cookie_session->session_ids[$form_id] ) && get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ) !== false ) {
				if ( strcmp( get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ), sha1( AUTH_KEY . $mc_value . Math_Captcha()->cookie_session->session_ids[$form_id], false ) ) !== 0 )
					$errors->add( 'math-captcha-error', Math_Captcha()->core->error_messages['wrong'] );
			} else
				$errors->add( 'math-captcha-error', Math_Captcha()->core->error_messages['time'] );
		} else
			$errors->add( 'math-captcha-error', Math_Captcha()->core->error_messages['fill'] );

		return $errors;
	}

	/**
	 * Validate registration form.
	 *
	 * @param array $result
	 * @return array
	 */
	public function validate_multisite_registration_captcha( $result ) {
		$form_id = 'wp_register';
		
		if ( ! empty( $_POST['mcaptcha'] ) ) {
			$mc_value = (int) $_POST['mcaptcha'];

			if ( isset( Math_Captcha()->cookie_session->session_ids[$form_id] ) && get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ) !== false ) {
				if ( strcmp( get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ), sha1( AUTH_KEY . $mc_value . Math_Captcha()->cookie_session->session_ids[$form_id], false ) ) !== 0 )
					$result['errors']->add( 'math-captcha-error', Math_Captcha()->core->error_messages['wrong'] );
			} else
				$result['errors']->add( 'math-captcha-error', Math_Captcha()->core->error_messages['time'] );
		} else
			$result['errors']->add( 'math-captcha-error', Math_Captcha()->core->error_messages['fill'] );

		return $result;
	}

	/**
	 * Posts login form.
	 *
	 * @param string $redirect
	 * @param bool $bool
	 * @param array $errors
	 * @return array
	 */
	public function validate_login_captcha( $redirect, $bool, $errors ) {
		$form_id = 'wp_login';
		
		if ( $this->login_failed === false && ! empty( $_POST ) ) {
			$error = '';

			if ( ! empty( $_POST['mcaptcha'] ) ) {
				$mc_value = (int) $_POST['mcaptcha'];

				if ( isset( Math_Captcha()->cookie_session->session_ids[$form_id] ) && get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ) !== false ) {
					if ( strcmp( get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ), sha1( AUTH_KEY . $mc_value . Math_Captcha()->cookie_session->session_ids[$form_id], false ) ) !== 0 )
						$error = 'wrong';
				} else
					$error = 'time';
			} else
				$error = 'fill';

			if ( is_wp_error( $errors ) && ! empty( $error ) )
				$errors->add( 'math-captcha-error', Math_Captcha()->core->error_messages[$error] );
		}

		return $redirect;
	}

	/**
	 * Authenticate user.
	 *
	 * @param WP_Error $user
	 * @param string $username
	 * @param string $password
	 * @return object
	 */
	public function authenticate_user( $user, $username, $password ) {
		$form_id = 'wp_login';
		
		// user gave us valid login and password
		if ( ! is_wp_error( $user ) ) {
			if ( ! empty( $_POST ) ) {
				if ( ! empty( $_POST['mcaptcha'] ) ) {
					$mc_value = (int) $_POST['mcaptcha'];

					if ( isset( Math_Captcha()->cookie_session->session_ids[$form_id] ) && get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ) !== false ) {
						if ( strcmp( get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ), sha1( AUTH_KEY . $mc_value . Math_Captcha()->cookie_session->session_ids[$form_id], false ) ) !== 0 )
							$error = 'wrong';
					} else
						$error = 'time';
				} else
					$error = 'fill';
			}

			if ( ! empty( $error ) ) {
				// destroy cookie
				wp_clear_auth_cookie();

				$user = new WP_Error();
				$user->add( 'math-captcha-error', Math_Captcha()->core->error_messages[$error] );

				// inform redirect function that we failed to login
				$this->login_failed = true;
			}
		}

		return $user;
	}

	/**
	 * Add captcha to comment form.
	 *
	 * @param array $comment
	 * @return array|void
	 */
	//todo check if we need to return $comment
	public function validate_comment_captcha( $comment ) {
		$form_id = 'wp_comment';
		
		if ( ! empty( $_POST['mcaptcha'] ) ) {
			$mc_value = (int) $_POST['mcaptcha'];

			if ( ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) && ( $comment['comment_type'] === '' || $comment['comment_type'] === 'comment' ) ) {
				if ( isset( Math_Captcha()->cookie_session->session_ids[$form_id] ) && get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ) !== false ) {
					if ( strcmp( get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ), sha1( AUTH_KEY . $mc_value . Math_Captcha()->cookie_session->session_ids[$form_id], false ) ) === 0 )
						return $comment;
					else
						wp_die( Math_Captcha()->core->error_messages['wrong'] );
				} else
					wp_die( Math_Captcha()->core->error_messages['time'] );
			} else
				wp_die( Math_Captcha()->core->error_messages['fill'] );
		} else
			wp_die( Math_Captcha()->core->error_messages['fill'] );
	}
}

new Math_Captcha_Integration_WordPress();