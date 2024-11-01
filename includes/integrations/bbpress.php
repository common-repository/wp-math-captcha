<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Math_Captcha_Core class.
 *
 * @class Math_Captcha_Core
 */
class Math_Captcha_Integration_bbPress {
	
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
		if ( ! is_user_logged_in() || ( is_user_logged_in() && ! Math_Captcha()->options['general']['hide_for_logged_users'] ) ) {
			// new topic
			$form_id = 'bbp_topic';

			add_action( 'bbp_theme_after_topic_form_content', function() use ( $form_id ) { 
				Math_Captcha()->core->add_captcha_field( $form_id );
			} );
			add_action( 'bbp_new_topic_pre_extras', function() use ( $form_id ) { 
				$this->validate_bbpress_captcha( $form_id );
			} );

			// new reply
			$form_id = 'bbp_reply';

			add_action( 'bbp_theme_after_reply_form_content', function() use ( $form_id ) { 
				Math_Captcha()->core->add_captcha_field( $form_id );
			} );
			add_action( 'bbp_new_reply_pre_extras', function() use ( $form_id ) { 
				$this->validate_bbpress_captcha( $form_id );
			} );
		}
	}
	
	/**
	 * Validate bbPress topics and replies.
	 *
	 * @return void
	 */
	public function validate_bbpress_captcha( $form_id = '' ) {
		if ( ! empty( $_POST['mcaptcha'] ) ) {
			$mc_value = (int) $_POST['mcaptcha'];

			if ( isset( Math_Captcha()->cookie_session->session_ids[$form_id] ) && get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ) !== false ) {
				if ( strcmp( get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ), sha1( AUTH_KEY . $mc_value . Math_Captcha()->cookie_session->session_ids[$form_id], false ) ) !== 0 )
					bbp_add_error( 'math-captcha-wrong', Math_Captcha()->core->error_messages['wrong'] );
			} else
				bbp_add_error( 'math-captcha-wrong', Math_Captcha()->core->error_messages['time'] );
		} else
			bbp_add_error( 'math-captcha-wrong', Math_Captcha()->core->error_messages['fill'] );
	}

}

new Math_Captcha_Integration_bbPress();