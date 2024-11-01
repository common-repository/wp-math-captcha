<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Math_Captcha_Core class.
 *
 * @class Math_Captcha_Core
 */
class Math_Captcha_Integration_CF7 {
	
	public $errors;
	
	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// shortcode handler
		add_action( 'wpcf7_init', [ $this, 'register_shortcode' ] );
		// tag generator
		add_action( 'admin_init', [ $this, 'add_tag_generator' ], 45 );
		
		// validation
		add_filter( 'wpcf7_validate_mathcaptcha', [ $this, 'validation_filter' ], 10, 2 );
		add_filter( 'wpcf7_validate_mathcaptcha*', [ $this, 'validation_filter' ], 10, 2 );
		
		// error messages
		add_filter( 'wpcf7_messages', [ $this, 'error_messages' ] );
		
		// admin notices
		add_action( 'wpcf7_admin_notices', [ $this, 'admin_notices' ] );
	}

	
	/**
	 * Add shortcode.
	 */
	public function register_shortcode() {
		if ( function_exists( 'wpcf7_add_form_tag' ) )
			wpcf7_add_form_tag( [ 'mathcaptcha', 'mathcaptcha*' ], [ $this, 'shortcode_handler' ], [ 'name-attr' => true ] );
		elseif ( function_exists( 'wpcf7_add_shortcode' ) )
			wpcf7_add_shortcode( [ 'mathcaptcha', 'mathcaptcha*' ], [ $this, 'shortcode_handler' ], true );
	}
	
	/**
	 * Shortcode handler.
	 * 
	 * @param type $tag
	 * @return string
	 */
	public function shortcode_handler( $tag ) {
		if ( ! is_user_logged_in() || ( is_user_logged_in() && ! Math_Captcha()->options['general']['hide_for_logged_users'] ) ) {
			
			if ( function_exists( 'wpcf7_add_form_tag' ) )
				$tag = new WPCF7_FormTag( $tag );
			elseif ( function_exists( 'wpcf7_add_shortcode' ) )
				$tag = new WPCF7_Shortcode( $tag );

			if ( empty( $tag->name ) )
				return '';

			$validation_error = wpcf7_get_validation_error( $tag->name );
			$class = wpcf7_form_controls_class( $tag->type );

			if ( $validation_error )
				$class .= ' wpcf7-not-valid';
			
			// get form id from tagname
			$id = preg_replace( '/[^0-9.]+/', '', $tag->name );
			$form_id = 'cf7_' . $id;

			$atts = [];
			$atts['size'] = 2;
			$atts['maxlength'] = 2;
			$atts['class'] = $tag->get_class_option( $class );
			$atts['id'] = $id; // $tag->get_option( 'id', 'id', true );
			$atts['tabindex'] = $tag->get_option( 'tabindex', 'int', true );
			$atts['aria-required'] = 'true';
			$atts['type'] = 'text';
			$atts['name'] = $tag->name;
			$atts['value'] = '';
			
			$atts = wpcf7_format_atts( $atts );

			// @todo - we need a unique form id here
			$captcha_field = Math_Captcha()->core->add_captcha_field( $form_id, false );

			return sprintf( '<div class="wpcf7-form-control-wrap %1$s" data-name="%1$s">' . $captcha_field . '%3$s</div>', $tag->name, $atts, $validation_error );
		}
	}
	
	/**
	 * Validation.
	 * 
	 * @param type $result
	 * @param type $tag
	 * @return type
	 */
	public function validation_filter( $result, $tag ) {
		if ( function_exists( 'wpcf7_add_form_tag' ) )
			$tag = new WPCF7_FormTag( $tag );
		elseif ( function_exists( 'wpcf7_add_shortcode' ) )
			$tag = new WPCF7_Shortcode( $tag );

		// get form id from tagname
		$id = preg_replace( '/[^0-9.]+/', '', $tag->name );
		$form_id = 'cf7_' . $id;
			
		$name = 'mcaptcha'; // $tag->name;

		if ( isset( $_POST[$name] ) && $_POST[$name] !== '' && ! is_admin() ) {
			$mc_value = (int) $_POST[$name];

			if ( isset( Math_Captcha()->cookie_session->session_ids[$form_id] ) && get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ) !== false ) {
				if ( strcmp( get_transient( 'mcaptcha_' . Math_Captcha()->cookie_session->session_ids[$form_id] ), sha1( AUTH_KEY . $mc_value . Math_Captcha()->cookie_session->session_ids[$form_id], false ) ) !== 0 ) {
					if ( version_compare( WPCF7_VERSION, '4.1.0', '>=' ) )
						$result->invalidate( $tag, wpcf7_get_message( 'wrong_mathcaptcha' ) );
					else {
						$result['valid'] = false;
						$result['reason'][$tag->name] = wpcf7_get_message( 'wrong_mathcaptcha' );
					}
				}
			} else {
				if ( version_compare( WPCF7_VERSION, '4.1.0', '>=' ) )
					$result->invalidate( $tag, wpcf7_get_message( 'time_mathcaptcha' ) );
				else {
					$result['valid'] = false;
					$result['reason'][$tag->name] = wpcf7_get_message( 'time_mathcaptcha' );
				}
			}
		} else {
			if ( version_compare( WPCF7_VERSION, '4.1.0', '>=' ) )
				$result->invalidate( $tag, wpcf7_get_message( 'fill_mathcaptcha' ) );
			else {
				$result['valid'] = false;
				$result['reason'][$tag->name] = wpcf7_get_message( 'fill_mathcaptcha' );
			}
		}

		return $result;
	}
	
	/**
	 * Error messages.
	 * 
	 * @param type $messages
	 * @return type
	 */
	public function error_messages( $messages ) {
		return array_merge(
			$messages,
			[
				'wrong_mathcaptcha'	 => [
					'description'	 => __( 'Invalid captcha value.', 'math-captcha' ),
					'default'		 => wp_strip_all_tags( Math_Captcha()->core->error_messages['wrong'], true )
				],
				'fill_mathcaptcha'	 => [
					'description'	 => __( 'Please enter captcha value.', 'math-captcha' ),
					'default'		 => wp_strip_all_tags( Math_Captcha()->core->error_messages['fill'], true )
				],
				'time_mathcaptcha'	 => [
					'description'	 => __( 'Captcha time expired.', 'math-captcha' ),
					'default'		 => wp_strip_all_tags( Math_Captcha()->core->error_messages['time'], true )
				]
			]
		);
	}
	
	/**
	 * Tag generator.
	 */
	public function add_tag_generator() {
		if ( function_exists( 'wpcf7_add_tag_generator' ) )
			wpcf7_add_tag_generator( 'mathcaptcha', __( 'Math Captcha', 'math-captcha' ), 'wpcf7-mathcaptcha', [ $this, 'tag' ] );
	}
	
	/**
	 * Tag contents
	 * 
	 * @param type $contact_form
	 */
	public function tag( $contact_form ) {
		echo '
		<div class="control-box">
			<fieldset>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">' . esc_html__( 'Field type', 'contact-form-7' ) . '</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text">' . esc_html__( 'Field type', 'contact-form-7' ) . '</legend>
									<label><input type="checkbox" name="required" value="on" disabled checked>' . esc_html__( 'Required field', 'contact-form-7' ) . '</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="tag-generator-panel-mathcaptcha-name">' . esc_html__( 'Name', 'contact-form-7' ) . '</label>
							</th>
							<td>
								<input type="text" name="name" class="tg-name oneline" id="tag-generator-panel-mathcaptcha-name" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="tag-generator-panel-mathcaptcha-id">' . esc_html__( 'Id attribute', 'contact-form-7' ) . '</label>
							</th>
							<td>
								<input type="text" name="id" class="idvalue oneline option" id="tag-generator-panel-mathcaptcha-id" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="tag-generator-panel-mathcaptcha-class">' . esc_html__( 'Class attribute', 'contact-form-7' ) . '</label>
							</th>
							<td>
								<input type="text" name="class" class="classvalue oneline option" id="tag-generator-panel-mathcaptcha-class" />
							</td>
						</tr>
					</tbody>
				</table>
			</fieldset>
		</div>
		<div class="insert-box">
			<input type="text" name="mathcaptcha" class="tag code" readonly="readonly" onfocus="this.select();">
			<div class="submitbox">
				<input type="button" class="button button-primary insert-tag" value="' . esc_attr__( 'Insert Tag', 'contact-form-7' ) . '">
			</div>
			<br class="clear">
		</div>';
	}
	
	/**
	 * Admin notices.
	 * 
	 * @return type
	 */
	public function admin_notices() {
		if ( ! empty( $_GET['post'] ) )
			$id = (int) $_GET['post'];
		else
			return;

		if ( ! ( $contact_form = wpcf7_contact_form( $id ) ) )
			return;

		if ( version_compare( WPCF7_VERSION, '4.6.0', '>=' ) )
			$has_tags = (bool) $contact_form->scan_form_tags( [ 'type' => [ 'mathcaptcha' ] ] );
		else
			$has_tags = (bool) $contact_form->form_scan_shortcode( [ 'type' => [ 'mathcaptcha' ] ] );

		if ( ! $has_tags )
			return;
	}
	
}

new Math_Captcha_Integration_CF7();