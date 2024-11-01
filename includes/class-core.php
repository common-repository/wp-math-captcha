<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Math_Captcha_Core class.
 *
 * @class Math_Captcha_Core
 */
class Math_Captcha_Core {
	
	public $error_messages;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// actions
		add_action( 'init', [ $this, 'load_integrations' ], 1 );
		add_action( 'plugins_loaded', [ $this, 'load_defaults' ] );
		add_action( 'admin_init', [ $this, 'flush_rewrites' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );
			
		// actions
		add_action( 'wp_ajax_mcaptcha_get', [ $this, 'get_captcha' ] );
		add_action( 'wp_ajax_nopriv_mcaptcha_get', [ $this, 'get_captcha' ] );

		// filters
		add_filter( 'mod_rewrite_rules', [ $this, 'block_direct_comments' ] );
	}

	/**
	 * Load defaults.
	 *
	 * @return void
	 */
	public function load_defaults() {
		$this->error_messages = [
			'fill'	 => '<strong>' . __( 'Error', 'math-captcha' ) . '</strong>: ' . __( 'Please enter captcha value.', 'math-captcha' ),
			'wrong'	 => '<strong>' . __( 'Error', 'math-captcha' ) . '</strong>: ' . __( 'Invalid captcha value.', 'math-captcha' ),
			'time'	 => '<strong>' . __( 'Error', 'math-captcha' ) . '</strong>: ' . __( 'Captcha time expired.', 'math-captcha' )
		];
	}

	/**
	 * Load integrations required files.
	 *
	 * @return void
	 */
	public function load_integrations() {
		// wordpress
		include_once( MATH_CAPTCHA_PATH . 'includes/integrations/wordpress.php' );
		
		// contact form 7
		if ( Math_Captcha()->options['general']['enable_for']['contact_form_7'] && class_exists( 'WPCF7_ContactForm' ) )
			include_once( MATH_CAPTCHA_PATH . 'includes/integrations/contact-form-7.php' );

		// bbPress
		if ( Math_Captcha()->options['general']['enable_for']['bbpress'] && class_exists( 'bbPress' ) )
			include_once( MATH_CAPTCHA_PATH . 'includes/integrations/bbpress.php' );
	}

	/**
	 * Get captcha.
	 */
	public function get_captcha() {
		// check post data
		if ( ! isset( $_POST['action'], $_POST['form_id'], $_POST['nonce'] ) )
			return false;

		// verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'mcaptcha_nonce' ) )
			return false;
		
		$form_id = sanitize_key( $_POST['form_id'] );
		
		$allowed_tags = [
			'input'		=> [
				'name'	=> true,
				'type'	=> true,
				'class'	=> true,
				'size'	=> true,
				'length'	=> true,
				'required'	=> true,
				'aria-required'	=> true,
				'autocomplete'	=> true
			]
		];
		
		$allowed_tags = array_merge( wp_kses_allowed_html( 'post' ), $allowed_tags ) ;
				
		echo wp_json_encode(
			[
				'form'			=> wp_kses( $this->add_captcha_form( $form_id ), $allowed_tags ),
				'session_id'	=> sanitize_text_field( Math_Captcha()->cookie_session->session_ids[$form_id] )
			]
		);

		exit;
	}
	
	/**
	 * Display captcha hidden field.
	 *
	 * @param string $form_id
	 * @return void
	 */
	public function add_captcha_field( $form_id = '', $echo = true ) {
		$field = '<input name="mcaptcha_placeholder" type="hidden" class="mcaptcha-placeholder" value="" data-mcaptchaid="' . $form_id . '">';
		
		if ( $echo ) 
			echo $field;
		else
			return $field;
	}

	/**
	 * Display and generate captcha.
	 *
	 * @return void
	 */
	public function add_captcha_form( $form_id = '' ) {
		// get captcha data
		$captcha_phrase = $this->generate_captcha_phrase( $form_id );

		$phrase_html = '';

		// get input position
		$input_no = $captcha_phrase[3];

		unset( $captcha_phrase[3] );

		// escape captcha phrase
		foreach ( $captcha_phrase as $key => $chunk ) {
			$phrase_html .= '<span class="mcaptcha-part">';
			
			// do not escape input
			if ( $input_no === $key )
				$phrase_html .= $chunk;
			else
				$phrase_html .= wp_kses_post( $chunk );
			
			$phrase_html .= '</span>';
		}

		return $phrase_html;
	}
	
	/**
	 * Generate captcha phrase.
	 *
	 * @param string $form_id
	 * @return array
	 */
	public function generate_captcha_phrase( $form_id = '' ) {
		$ops = [
			'addition'			=> '+',
			'subtraction'		=> '&#8722;',
			'multiplication'	=> '&#215;',
			'division'			=> '&#247;'
		];

		$operations = $groups = [];
		
		$input = '<input type="text" size="2" length="2" class="mcaptcha-input" name="mcaptcha" aria-required="true" autocomplete="off" required="required" />';

		// available operations
		foreach ( Math_Captcha()->options['general']['mathematical_operations'] as $operation => $enable ) {
			if ( $enable === true )
				$operations[] = $operation;
		}

		// available groups
		foreach ( Math_Captcha()->options['general']['groups'] as $group => $enable ) {
			if ( $enable === true )
				$groups[] = $group;
		}

		// number of groups
		$ao = count( $groups );

		// operation
		$rnd_op = $operations[mt_rand( 0, count( $operations ) - 1 )];
		$number[3] = $ops[$rnd_op];

		// place where to put empty input
		$rnd_input = mt_rand( 0, 2 );

		// which random operation
		switch ( $rnd_op ) {
			case 'addition':
				if ( $rnd_input === 0 ) {
					$number[0] = mt_rand( 1, 10 );
					$number[1] = mt_rand( 1, 89 );
				} elseif ( $rnd_input === 1 ) {
					$number[0] = mt_rand( 1, 89 );
					$number[1] = mt_rand( 1, 10 );
				} elseif ( $rnd_input === 2 ) {
					$number[0] = mt_rand( 1, 9 );
					$number[1] = mt_rand( 1, 10 - $number[0] );
				}

				$number[2] = $number[0] + $number[1];
				break;

			case 'subtraction':
				if ( $rnd_input === 0 ) {
					$number[0] = mt_rand( 2, 10 );
					$number[1] = mt_rand( 1, $number[0] - 1 );
				} elseif ( $rnd_input === 1 ) {
					$number[0] = mt_rand( 11, 99 );
					$number[1] = mt_rand( 1, 10 );
				} elseif ( $rnd_input === 2 ) {
					$number[0] = mt_rand( 11, 99 );
					$number[1] = mt_rand( $number[0] - 10, $number[0] - 1 );
				}

				$number[2] = $number[0] - $number[1];
				break;

			case 'multiplication':
				if ( $rnd_input === 0 ) {
					$number[0] = mt_rand( 1, 10 );
					$number[1] = mt_rand( 1, 9 );
				} elseif ( $rnd_input === 1 ) {
					$number[0] = mt_rand( 1, 9 );
					$number[1] = mt_rand( 1, 10 );
				} elseif ( $rnd_input === 2 ) {
					$number[0] = mt_rand( 1, 10 );
					$number[1] = ( $number[0] > 5 ? 1 : ( $number[0] === 4 && $number[0] === 5 ? mt_rand( 1, 2 ) : ( $number[0] === 3 ? mt_rand( 1, 3 ) : ($number[0] === 2 ? mt_rand( 1, 5 ) : mt_rand( 1, 10 ) ) ) ) );
				}

				$number[2] = $number[0] * $number[1];
				break;

			case 'division':
				$divide = [ 1 => 99, 2 => 49, 3 => 33, 4 => 24, 5 => 19, 6 => 16, 7 => 14, 8 => 12, 9 => 11, 10 => 9 ];

				if ( $rnd_input === 0 ) {
					$divide = [ 2 => [ 1, 2 ], 3 => [ 1, 3 ], 4 => [ 1, 2, 4 ], 5 => [ 1, 5 ], 6 => [ 1, 2, 3, 6 ], 7 => [ 1, 7 ], 8 => [ 1, 2, 4, 8 ], 9 => [ 1, 3, 9 ], 10 => [ 1, 2, 5, 10 ] ];
					$number[0] = mt_rand( 2, 10 );
					$number[1] = $divide[$number[0]][mt_rand( 0, count( $divide[$number[0]] ) - 1 )];
				} elseif ( $rnd_input === 1 ) {
					$number[1] = mt_rand( 1, 10 );
					$number[0] = $number[1] * mt_rand( 1, $divide[$number[1]] );
				} elseif ( $rnd_input === 2 ) {
					$number[2] = mt_rand( 1, 10 );
					$number[0] = $number[2] * mt_rand( 1, $divide[$number[2]] );
					$number[1] = (int) ($number[0] / $number[2]);
				}

				if ( ! isset( $number[2] ) )
					$number[2] = (int) ($number[0] / $number[1]);

				break;
		}

		// words
		if ( $ao === 1 && $groups[0] === 'words' ) {
			if ( $rnd_input === 0 ) {
				$number[1] = $this->numberToWords( $number[1] );
				$number[2] = $this->numberToWords( $number[2] );
			} elseif ( $rnd_input === 1 ) {
				$number[0] = $this->numberToWords( $number[0] );
				$number[2] = $this->numberToWords( $number[2] );
			} elseif ( $rnd_input === 2 ) {
				$number[0] = $this->numberToWords( $number[0] );
				$number[1] = $this->numberToWords( $number[1] );
			}
		// numbers and words
		} elseif ( $ao === 2 ) {
			if ( $rnd_input === 0 ) {
				if ( mt_rand( 1, 2 ) === 2 ) {
					$number[1] = $this->numberToWords( $number[1] );
					$number[2] = $this->numberToWords( $number[2] );
				} else
					$number[$tmp = mt_rand( 1, 2 )] = $this->numberToWords( $number[$tmp] );
			}
			elseif ( $rnd_input === 1 ) {
				if ( mt_rand( 1, 2 ) === 2 ) {
					$number[0] = $this->numberToWords( $number[0] );
					$number[2] = $this->numberToWords( $number[2] );
				} else
					$number[$tmp = array_rand( [ 0 => 0, 2 => 2 ], 1 )] = $this->numberToWords( $number[$tmp] );
			}
			elseif ( $rnd_input === 2 ) {
				if ( mt_rand( 1, 2 ) === 2 ) {
					$number[0] = $this->numberToWords( $number[0] );
					$number[1] = $this->numberToWords( $number[1] );
				} else
					$number[$tmp = mt_rand( 0, 1 )] = $this->numberToWords( $number[$tmp] );
			}
		}

		$result = [];

		// position of empty input
		if ( $rnd_input === 0 ) {
			$result[1] = '<span class="mcaptcha-sign">' . $number[3] . '</span><span class="mcaptcha-number">' . $this->encode_operation( $number[1] ) . '</span><span class="mcaptcha-sign">=</span>';
			$result[2] = '<span class="mcaptcha-number">' . $this->encode_operation( $number[2] ) . '</span>';
		} elseif ( $rnd_input === 1 ) {
			$result[0] = '<span class="mcaptcha-number">' . $this->encode_operation( $number[0] ) . '</span><span class="mcaptcha-sign">' . $number[3] . '</span>';
			$result[2] = '<span class="mcaptcha-sign">=</span>' . '<span class="mcaptcha-number">' . $this->encode_operation( $number[2] ) . '</span>';
		} elseif ( $rnd_input === 2 ) {
			$result[0] = '<span class="mcaptcha-number">' . $this->encode_operation( $number[0] ) . '</span><span class="mcaptcha-sign">' . $number[3] . '</span>';
			$result[1] = '<span class="mcaptcha-number">' . $this->encode_operation( $number[1] ) . '</span><span class="mcaptcha-sign">=</span>';
		}

		// position of empty input
		$result[$rnd_input] = $input;

		// sort result
		ksort( $result, SORT_NUMERIC );

		// where is input
		$result[3] = $rnd_input;

		$this->set_captcha_phrase( $form_id, $number[$rnd_input] );

		return $result;
	}
	
	/**
	 * Set captcha phrase.
	 *
	 * @param string $form_id
	 * @return array
	 */
	public function set_captcha_phrase( $form_id = '', $position = 0 ) {
		$session_id = Math_Captcha()->cookie_session->add_cookie( $form_id );

		set_transient( 'mcaptcha_' . $session_id, sha1( AUTH_KEY . $position . $session_id, false ), apply_filters( 'math_captcha_time', Math_Captcha()->options['general']['time'] ) );
	}

	/**
	 * Encode chars.
	 *
	 * @param string $string
	 * @return string
	 */
	private function encode_operation( $string ) {
		$chars = str_split( $string );
		$seed = mt_rand( 0, (int) abs( crc32( $string ) / strlen( $string ) ) );

		foreach ( $chars as $key => $char ) {
			$ord = ord( $char );

			// ignore non-ascii chars
			if ( $ord < 128 ) {
				// pseudo "random function"
				$r = ( $seed * ( 1 + $key ) ) % 100;

				if ( $r > 60 && $char !== '@' ) {
//todo?
				// plain character (not encoded), if not @-sign
				} elseif ( $r < 45 )
					$chars[$key] = '&#x' . dechex( $ord ) . ';'; // hexadecimal
				else
					$chars[$key] = '&#' . $ord . ';'; // decimal (ascii)
			}
		}

		return implode( '', $chars );
	}

	/**
	 * Convert numbers to words.
	 *
	 * @param int $number
	 * @return string
	 */
	private function numberToWords( $number ) {
		$words = [
			1	=> __( 'one', 'math-captcha' ),
			2	=> __( 'two', 'math-captcha' ),
			3	=> __( 'three', 'math-captcha' ),
			4	=> __( 'four', 'math-captcha' ),
			5	=> __( 'five', 'math-captcha' ),
			6	=> __( 'six', 'math-captcha' ),
			7	=> __( 'seven', 'math-captcha' ),
			8	=> __( 'eight', 'math-captcha' ),
			9	=> __( 'nine', 'math-captcha' ),
			10	=> __( 'ten', 'math-captcha' ),
			11	=> __( 'eleven', 'math-captcha' ),
			12	=> __( 'twelve', 'math-captcha' ),
			13	=> __( 'thirteen', 'math-captcha' ),
			14	=> __( 'fourteen', 'math-captcha' ),
			15	=> __( 'fifteen', 'math-captcha' ),
			16	=> __( 'sixteen', 'math-captcha' ),
			17	=> __( 'seventeen', 'math-captcha' ),
			18	=> __( 'eighteen', 'math-captcha' ),
			19	=> __( 'nineteen', 'math-captcha' ),
			20	=> __( 'twenty', 'math-captcha' ),
			30	=> __( 'thirty', 'math-captcha' ),
			40	=> __( 'forty', 'math-captcha' ),
			50	=> __( 'fifty', 'math-captcha' ),
			60	=> __( 'sixty', 'math-captcha' ),
			70	=> __( 'seventy', 'math-captcha' ),
			80	=> __( 'eighty', 'math-captcha' ),
			90	=> __( 'ninety', 'math-captcha' )
		];

		if ( isset( $words[$number] ) )
			return $words[$number];
		else {
			$reverse = false;

			switch ( get_bloginfo( 'language' ) ) {
				case 'de-DE':
					$spacer = 'und';
					$reverse = true;
					break;

				case 'nl-NL':
					$spacer = 'en';
					$reverse = true;
					break;

				case 'ru-RU':
				case 'pl-PL':
				case 'en-EN':
				default:
					$spacer = ' ';
			}

			$first = (int) ( substr( $number, 0, 1 ) * 10 );
			$second = (int) substr( $number, -1 );

			return ( $reverse === false ? $words[$first] . $spacer . $words[$second] : $words[$second] . $spacer . $words[$first] );
		}
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @return void
	 */
	public function flush_rewrites() {
		if ( Math_Captcha()->options['general']['flush_rules'] ) {
			global $wp_rewrite;

			$wp_rewrite->flush_rules();

			Math_Captcha()->options['general']['flush_rules'] = false;
			update_option( 'math_captcha_options', Math_Captcha()->options['general'] );
		}
	}

	/**
	 * Block direct comments.
	 *
	 * @param string $rules
	 * @return string
	 */
	public function block_direct_comments( $rules ) {
		if ( Math_Captcha()->options['general']['block_direct_comments'] ) {
			$new_rules = <<<EOT
\n# BEGIN Math Captcha
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_METHOD} POST
RewriteCond %{REQUEST_URI} .wp-comments-post.php*
RewriteCond %{HTTP_REFERER} !.*{$this->get_host()}.* [OR]
RewriteCond %{HTTP_USER_AGENT} ^$
RewriteRule (.*) ^http://%{REMOTE_ADDR}/$ [R=301,L]
</IfModule>
# END Math Captcha\n\n
EOT;

			return $new_rules . $rules;
		}

		return $rules;
	}

	/**
	 * Get host.
	 *
	 * @return string
	 */
	private function get_host() {
		$host = '';

		foreach ( [ 'HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR' ] as $source ) {
			if ( ! empty( $host ) )
				break;

			if ( empty( $_SERVER[$source] ) )
				continue;

			$host = $_SERVER[$source];

			if ( $source === 'HTTP_X_FORWARDED_HOST' ) {
				$elements = explode( ',', $host );
				$host = trim( end( $elements ) );
			}
		}

		// remove port number from host and return it
		return trim( preg_replace( '/:\d+$/', '', $host ) );
	}
	
	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @return void
	 */
	public function wp_enqueue_scripts() {
		wp_enqueue_script( 'math-captcha-frontend', MATH_CAPTCHA_URL . '/js/frontend.js', [], Math_Captcha()->defaults['version'], true );

		// prepare args
		$args = [
			'requestURL'	=> admin_url( 'admin-ajax.php' ),
			'nonce'			=> wp_create_nonce( 'mcaptcha_nonce' ),
			'multisite'		=> ( is_multisite() ? (int) get_current_blog_id() : false ),
			'path'			=> empty( COOKIEPATH ) || ! is_string( COOKIEPATH ) ? '/' : COOKIEPATH,
			'domain'		=> empty( COOKIE_DOMAIN ) || ! is_string( COOKIE_DOMAIN ) ? '' : COOKIE_DOMAIN,
			'title'			=> Math_Captcha()->options['general']['title'],
			'theme'			=> 'light',
			'reloading'		=> Math_Captcha()->options['general']['reloading']
		];

		wp_add_inline_script( 'post-views-counter-frontend', 'var mCaptchaArgs = ' . wp_json_encode( $args ) . ";\n", 'before' );
		
		// wp-login.php pages exception
		add_action( 'login_head', function() use ( $args ) { 
			$this->print_scripts( $args );
		} );
	}
	
	/**
	 * Print mCaptchaArgs when needed.
	 * 
	 * @param type $args
	 */
	public function print_scripts( $args ) {
		echo '
		<script>
			var mCaptchaArgs = ' . wp_json_encode( $args ) . ';
		</script>
		';
	}
}