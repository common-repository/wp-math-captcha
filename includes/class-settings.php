<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Math_Captcha_Settings class.
 *
 * @class Math_Captcha_Settings
 */
class Math_Captcha_Settings {

	public $mathematical_operations;
	public $groups;
	public $forms;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// actions
		add_action( 'init', [ $this, 'load_defaults' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu_options' ] );
	}

	/**
	 * Load defaults.
	 *
	 * @return void
	 */
	public function load_defaults() {
		if ( ! is_admin() )
			return;

		$this->forms = [
			'login_form'			=> __( 'login form', 'math-captcha' ),
			'registration_form'		=> __( 'registration form', 'math-captcha' ),
			'reset_password_form'	=> __( 'reset password form', 'math-captcha' ),
			'comment_form'			=> __( 'comment form', 'math-captcha' ),
			'bbpress'				=> __( 'bbpress', 'math-captcha' ),
			'contact_form_7'		=> __( 'contact form 7', 'math-captcha' )
		];

		$this->mathematical_operations = [
			'addition'			=> __( 'addition (+)', 'math-captcha' ),
			'subtraction'		=> __( 'subtraction (-)', 'math-captcha' ),
			'multiplication'	=> __( 'multiplication (&#215;)', 'math-captcha' ),
			'division'			=> __( 'division (&#247;)', 'math-captcha' )
		];

		$this->groups = [
			'numbers'	=> __( 'numbers', 'math-captcha' ),
			'words'		=> __( 'words', 'math-captcha' )
		];
	}

	/**
	 * Add options menu.
	 *
	 * @return void
	 */
	public function admin_menu_options() {
		add_options_page( __( 'Math Captcha', 'math-captcha' ), __( 'Math Captcha', 'math-captcha' ), 'manage_options', 'math-captcha', [ $this, 'options_page' ] );
	}

	/**
	 * Render options page.
	 *
	 * @return void
	 */
	public function options_page() {
		echo '
		<div class="wrap">
			<h1>' . esc_html__( 'Math Captcha', 'math-captcha' ) . '</h1>
			<h2 class="nav-tab-wrapper"></h2>
			<div class="math-captcha-settings">
				<div class="df-sidebar">
					<div class="df-credits">
						<h3 class="hndle">' . esc_html__( 'Math Captcha', 'math-captcha' ) . ' ' . esc_html( Math_Captcha()->defaults['version'] ) . '</h3>
						<div class="inside">
							<h4 class="inner">' . esc_html__( 'Need support?', 'math-captcha' ) . '</h4>
							<p class="inner">' . sprintf( __( 'If you are having problems with this plugin, please talk about them in the <a href="%s" target="_blank">Support forum</a>.', 'math-captcha' ), 'http://www.dfactory.co/support/?utm_source=math-captcha-settings&utm_medium=link&utm_campaign=support' ) . '</p>
							<hr/>
							<h4 class="inner">' . esc_html__( 'Do you like this plugin?', 'math-captcha' ) . '</h4>
							<p class="inner">' . sprintf( __( '<a href="%s" target="_blank">Rate it 5</a> on WordPress.org.', 'math-captcha' ), 'https://wordpress.org/support/plugin/wp-math-captcha/reviews/?filter=5' ) . '<br />' .
							sprintf( __( 'Blog about it & link to the <a href="%s" target="_blank">plugin page</a>.', 'math-captcha' ), 'http://www.dfactory.co/products/math-captcha/?utm_source=math-captcha-settings&utm_medium=link&utm_campaign=blog-about' ) . '<br />' .
							sprintf( __( 'Check out our other <a href="%s" target="_blank">WordPress plugins</a>.', 'math-captcha' ), 'http://www.dfactory.co/products/?utm_source=math-captcha-settings&utm_medium=link&utm_campaign=other-plugins' ) . '
							</p>
							<hr/>
							<p class="df-link inner">' . esc_html__( 'Created by', 'math-captcha' ) . ' <a href="http://www.dfactory.co/?utm_source=math-captcha-settings&utm_medium=link&utm_campaign=created-by" target="_blank" title="dFactory - Quality plugins for WordPress"><img src="' . esc_url( MATH_CAPTCHA_URL ) . '/images/logo-dfactory.png" title="dFactory - Quality plugins for WordPress" alt="dFactory - Quality plugins for WordPress"/></a></p>
						</div>
					</div>
				</div>
				<form action="options.php" method="post">';

		wp_nonce_field( 'update-options' );
		settings_fields( 'math_captcha_options' );
		do_settings_sections( 'math_captcha_options' );

		echo '
					<p class="submit">';

		submit_button( '', 'primary', 'save_mc_general', false );

		echo ' ';

		submit_button( __( 'Reset to defaults', 'math-captcha' ), 'secondary reset_mc_settings', 'reset_mc_general', false );

		echo '
					</p>
				</form>
			</div>
			<div class="clear"></div>
		</div>';
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		// general settings
		register_setting( 'math_captcha_options', 'math_captcha_options', [ $this, 'validate_settings' ] );
		add_settings_section( 'math_captcha_settings', esc_html__( 'General Settings', 'math-captcha' ), '', 'math_captcha_options' );
		add_settings_field( 'mc_general_enable_captcha_for', esc_html__( 'Enable Math Captcha for', 'math-captcha' ), [ $this, 'mc_general_enable_captcha_for' ], 'math_captcha_options', 'math_captcha_settings' );
		add_settings_field( 'mc_general_hide_for_logged_users', esc_html__( 'Hide for logged in users', 'math-captcha' ), [ $this, 'mc_general_hide_for_logged_users' ], 'math_captcha_options', 'math_captcha_settings' );
		add_settings_field( 'mc_general_mathematical_operations', esc_html__( 'Mathematical operations', 'math-captcha' ), [ $this, 'mc_general_mathematical_operations' ], 'math_captcha_options', 'math_captcha_settings' );
		add_settings_field( 'mc_general_groups', esc_html__( 'Display captcha as', 'math-captcha' ), [ $this, 'mc_general_groups' ], 'math_captcha_options', 'math_captcha_settings' );
		add_settings_field( 'mc_general_title', esc_html__( 'Captcha field title', 'math-captcha' ), [ $this, 'mc_general_title' ], 'math_captcha_options', 'math_captcha_settings' );
		add_settings_field( 'mc_general_time', esc_html__( 'Captcha time', 'math-captcha' ), [ $this, 'mc_general_time' ], 'math_captcha_options', 'math_captcha_settings' );
		add_settings_field( 'mc_general_reloading', esc_html__( 'Allow Reloading', 'math-captcha' ), [ $this, 'mc_general_reloading' ], 'math_captcha_options', 'math_captcha_settings' );
		add_settings_field( 'mc_general_block_direct_comments', esc_html__( 'Block Direct Comments', 'math-captcha' ), [ $this, 'mc_general_block_direct_comments' ], 'math_captcha_options', 'math_captcha_settings' );
		add_settings_field( 'mc_general_deactivation_delete', esc_html__( 'Deactivation', 'math-captcha' ), [ $this, 'mc_general_deactivation_delete' ], 'math_captcha_options', 'math_captcha_settings' );
	}

	/**
	 * Setting: enable math captcha.
	 *
	 * @return void
	 */
	public function mc_general_enable_captcha_for() {
		$users_can_register = get_option( 'users_can_register' );

		echo '
		<div id="mc_general_enable_captcha_for">
			<fieldset>';

		foreach ( $this->forms as $val => $label ) {
			echo '
				<input id="mc-general-enable-captcha-for-' . esc_attr( $val ) . '" type="checkbox" name="math_captcha_options[enable_for][]" value="' . esc_attr( $val ) . '" ' . checked( true, Math_Captcha()->options['general']['enable_for'][$val], false ) . ' ' . disabled( ( ( $val === 'contact_form_7' && ! class_exists( 'WPCF7_ContactForm' ) ) || ( $val === 'bbpress' && ! class_exists( 'bbPress' ) ) || ( $val === 'registration_form' && ! $users_can_register ) ), true, false ) . '/><label for="mc-general-enable-captcha-for-' . esc_attr( $val ) . '">' . esc_html( $label ) . '</label>';
		}

		echo '
				<p class="description">' . esc_html__( 'Select where you\'d like to use Math Captcha.', 'math-captcha' ) . '</p>
			</fieldset>
		</div>';
	}

	/**
	 * Setting: hide for logged in users.
	 *
	 * @return void
	 */
	public function mc_general_hide_for_logged_users() {
		echo '
		<div id="mc_general_hide_for_logged_users">
			<fieldset>
				<input id="mc-general-hide-for-logged" type="checkbox" name="math_captcha_options[hide_for_logged_users]" ' . checked( true, Math_Captcha()->options['general']['hide_for_logged_users'], false ) . '/><label for="mc-general-hide-for-logged">' . esc_html__( 'Enable to hide captcha for logged in users.', 'math-captcha' ) . '</label>
				<p class="description">' . esc_html__( 'Would you like to hide captcha for logged in users?', 'math-captcha' ) . '</p>
			</fieldset>
		</div>';
	}

	/**
	 * Setting: mathematical operations.
	 *
	 * @return void
	 */
	public function mc_general_mathematical_operations() {
		echo '
		<div id="mc_general_mathematical_operations">
			<fieldset>';

		foreach ( $this->mathematical_operations as $val => $label ) {
			echo '
				<input id="mc-general-mathematical-operations-' . esc_attr( $val ) . '" type="checkbox" name="math_captcha_options[mathematical_operations][]" value="' . esc_attr( $val ) . '" ' . checked( true, Math_Captcha()->options['general']['mathematical_operations'][$val], false ) . '/><label for="mc-general-mathematical-operations-' . esc_attr( $val ) . '">' . esc_html( $label ) . '</label>';
		}

		echo '
				<p class="description">' . esc_html__( 'Select which mathematical operations to use in your captcha.', 'math-captcha' ) . '</p>
			</fieldset>
		</div>';
	}

	/**
	 * Setting: display captcha.
	 *
	 * @return void
	 */
	public function mc_general_groups() {
		echo '
		<div id="mc_general_groups">
			<fieldset>';

		foreach ( $this->groups as $val => $label ) {
			echo '
				<input id="mc-general-groups-' . esc_attr( $val ) . '" type="checkbox" name="math_captcha_options[groups][]" value="' . esc_attr( $val ) . '" ' . checked( true, Math_Captcha()->options['general']['groups'][$val], false ) . '/><label for="mc-general-groups-' . esc_attr( $val ) . '">' . esc_html( $label ) . '</label>';
		}

		echo '
				<p class="description">' . esc_html__( 'Select how you\'d like to display your captcha.', 'math-captcha' ) . '</p>
			</fieldset>
		</div>';
	}

	/**
	 * Setting: captcha field title.
	 *
	 * @return void
	 */
	public function mc_general_title() {
		echo '
		<div id="mc_general_title">
			<fieldset>
				<input type="text" name="math_captcha_options[title]" value="' . esc_attr( Math_Captcha()->options['general']['title'] ) . '"/>
				<p class="description">' . esc_html__( 'How to entitle field with captcha?', 'math-captcha' ) . '</p>
			</fieldset>
		</div>';
	}

	/**
	 * Setting: captcha time.
	 *
	 * @return void
	 */
	public function mc_general_time() {
		echo '
		<div id="mc_general_time">
			<fieldset>
				<input type="text" name="math_captcha_options[time]" value="' . esc_attr( Math_Captcha()->options['general']['time'] ) . '"/>
				<p class="description">' . esc_html__( 'Enter the time (in seconds) a user has to enter captcha value.', 'math-captcha' ) . '</p>
			</fieldset>
		</div>';
	}
	
	/**
	 * Setting: reloading.
	 *
	 * @return void
	 */
	public function mc_general_reloading() {
		echo '
		<div id="mc_general_block_reloading">
			<fieldset>
				<input id="mc-general-reloading" type="checkbox" name="math_captcha_options[reloading]" ' . checked( true, Math_Captcha()->options['general']['reloading'], false ) . '/><label for="mc-general-reloading">' . esc_html__( 'Allow the visitor to reload captcha phrase.', 'math-captcha' ) . '</label>
			</fieldset>
		</div>';
	}

	/**
	 * Setting: block direct comments.
	 *
	 * @return void
	 */
	public function mc_general_block_direct_comments() {
		echo '
		<div id="mc_general_block_direct_comments">
			<fieldset>
				<input id="mc-general-block-direct-comments" type="checkbox" name="math_captcha_options[block_direct_comments]" ' . checked( true, Math_Captcha()->options['general']['block_direct_comments'], false ) . '/><label for="mc-general-block-direct-comments">' . esc_html__( 'Block direct access to wp-comments-post.php.', 'math-captcha' ) . '</label>
				<p class="description">' . esc_html__( 'Enable this to prevent spambots from posting to Wordpress via a URL.', 'math-captcha' ) . '</p>
			</fieldset>
		</div>';
	}

	/**
	 * Setting: deactivation.
	 *
	 * @return void
	 */
	public function mc_general_deactivation_delete() {
		echo '
		<div id="mc_general_deactivation_delete">
			<fieldset>
				<input id="mc-general-deactivation-delete" type="checkbox" name="math_captcha_options[deactivation_delete]" ' . checked( true, Math_Captcha()->options['general']['deactivation_delete'], false ) . '/><label for="mc-general-deactivation-delete">' . esc_html__( 'Delete settings on plugin deactivation.', 'math-captcha' ) . '</label>
				<br/>
				<span class="description">' . esc_html__( 'Enable if you want all plugin data to be deleted on deactivation.', 'math-captcha' ) . '</span>
			</fieldset>
		</div>';
	}

	/**
	 * Validate settings.
	 *
	 * @param array $input
	 * @return array
	 */
	public function validate_settings( $input ) {
		if ( isset( $_POST['save_mc_general'] ) ) {
			// form types
			if ( ! isset( $input['enable_for'] ) || empty( $input['enable_for'] ) || ! is_array( $input['enable_for'] ) ) {
				foreach ( Math_Captcha()->defaults['general']['enable_for'] as $form_type => $bool ) {
					$input['enable_for'][$form_type] = false;
				}
			} else {
				// enable captcha forms
				$enable_for = [];

				foreach ( $this->forms as $form_type => $label ) {
					$enable_for[$form_type] = in_array( $form_type, $input['enable_for'], true );
				}

				$input['enable_for'] = $enable_for;
			}

			// check if users can register
			if ( ! get_option( 'users_can_register' ) && Math_Captcha()->options['general']['enable_for']['registration_form'] )
				$input['enable_for']['registration_form'] = true;

			// check contact form 7
			if ( ! class_exists( 'WPCF7_ContactForm' ) && Math_Captcha()->options['general']['enable_for']['contact_form_7'] )
				$input['enable_for']['contact_form_7'] = true;

			// check bbpress
			if ( ! class_exists( 'bbPress' ) && Math_Captcha()->options['general']['enable_for']['bbpress'] )
				$input['enable_for']['bbpress'] = true;

			// mathematical operations
			if ( ! isset( $input['mathematical_operations'] ) || empty( $input['mathematical_operations'] ) || ! is_array( $input['mathematical_operations'] ) ) {
				add_settings_error( 'empty-operations', 'settings_updated', __( 'You need to check at least one mathematical operation. Default settings restored.', 'math-captcha' ), 'error' );

				$input['mathematical_operations'] = Math_Captcha()->defaults['general']['mathematical_operations'];
			} else {
				// enable mathematical operations
				$mathematical_operations = [];

				foreach ( $this->mathematical_operations as $operation => $label ) {
					$mathematical_operations[$operation] = in_array( $operation, $input['mathematical_operations'], true );
				}

				$input['mathematical_operations'] = $mathematical_operations;
			}

			// display groups
			if ( ! isset( $input['groups'] ) || empty( $input['groups'] ) || ! is_array( $input['groups'] ) ) {
				add_settings_error( 'empty-groups', 'settings_updated', __( 'You need to check at least one display group. Default settings restored.', 'math-captcha' ), 'error' );

				$input['groups'] = Math_Captcha()->defaults['general']['groups'];
			} else {
				// enable groups
				$groups = [];

				foreach ( $this->groups as $group => $label ) {
					$groups[$group] = in_array( $group, $input['groups'], true );
				}

				$input['groups'] = $groups;
			}

			// hide for logged in users
			$input['hide_for_logged_users'] = isset( $input['hide_for_logged_users'] );
			
			// reloading
			$input['reloading'] = isset( $input['reloading'] );

			// block direct comments access
			$input['block_direct_comments'] = isset( $input['block_direct_comments'] );

			// deactivation delete
			$input['deactivation_delete'] = isset( $input['deactivation_delete'] );

			// captcha title
			if ( ! empty( $input['title'] ) )
				$input['title'] = sanitize_text_field( trim( $input['title'] ), Math_Captcha()->defaults['general']['title'] );
			else
				$input['title'] = Math_Captcha()->defaults['general']['title'];

			// captcha time
			$input['time'] = isset( $input['time'] ) ? (int) $input['time'] : Math_Captcha()->defaults['general']['time'];
			$input['time'] = $input['time'] < 0 ? 0 : $input['time'];

			// flush rules
			$input['flush_rules'] = true;
		} elseif ( isset( $_POST['reset_mc_general'] ) ) {
			$input = Math_Captcha()->defaults['general'];

			add_settings_error( 'settings', 'settings_reset', __( 'Settings restored to defaults.', 'math-captcha' ), 'updated' );
		}

		return $input;
	}
}
