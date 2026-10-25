<?php
/**
 * Signup form: rendering (shortcode, block, widget) and server-side submission.
 *
 * @package SensorPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form handler.
 */
class SensorPro_Form {

	const NONCE = 'sensorpro_subscribe';

	/**
	 * Instance counter for unique ids.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'sensorpro_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest_routes' ) );
		add_action( 'admin_post_nopriv_sensorpro_subscribe', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_post_sensorpro_subscribe', array( __CLASS__, 'handle_post' ) );
	}

	/**
	 * Register front-end assets (enqueued only when a form renders).
	 */
	public static function register_assets() {
		if ( wp_script_is( 'sensorpro-form', 'registered' ) ) {
			return;
		}
		wp_register_style( 'sensorpro-form', SENSORPRO_URL . 'assets/css/sensorpro.css', array(), SENSORPRO_VERSION );
		wp_register_script( 'sensorpro-form', SENSORPRO_URL . 'assets/js/sensorpro-form.js', array(), SENSORPRO_VERSION, true );
		wp_localize_script(
			'sensorpro-form',
			'SensorProForm',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'sensorpro/v1/subscribe' ) ),
				'restNonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'sending'   => __( 'Sending…', 'sensorpro' ),
				'error'     => __( 'Sorry, something went wrong. Please try again.', 'sensorpro' ),
			)
		);
	}

	/**
	 * Parse a comma separated tag list.
	 *
	 * @param string|array $raw Raw tags.
	 * @return string[]
	 */
	public static function parse_tags( $raw ) {
		$parts = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$tags  = array();
		foreach ( $parts as $p ) {
			$p = trim( sanitize_text_field( $p ) );
			if ( '' !== $p ) {
				$tags[] = mb_substr( $p, 0, 100 );
			}
		}
		return array_values( array_unique( $tags ) );
	}

	/**
	 * Sign form-level tags so visitors cannot change them.
	 *
	 * @param string $tags Comma list.
	 * @return string
	 */
	private static function sign( $tags ) {
		return hash_hmac( 'sha256', 'sensorpro_tags|' . $tags, wp_salt( 'nonce' ) );
	}

	/**
	 * Shortcode [sensorpro_form title="" tags="" button=""].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'  => '',
				'tags'   => '',
				'button' => '',
			),
			$atts,
			'sensorpro_form'
		);
		return self::render( $atts );
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attrs Block attributes.
	 * @return string
	 */
	public static function render_block( $attrs ) {
		$attrs = is_array( $attrs ) ? $attrs : array();
		return self::render(
			array(
				'title'  => isset( $attrs['title'] ) ? $attrs['title'] : '',
				'tags'   => isset( $attrs['tags'] ) ? $attrs['tags'] : '',
				'button' => isset( $attrs['buttonText'] ) ? $attrs['buttonText'] : '',
			)
		);
	}

	/**
	 * Render the form.
	 *
	 * @param array $args title, tags, button.
	 * @return string
	 */
	public static function render( $args ) {
		$s = sensorpro_get_settings();
		self::$count++;
		$id   = 'sensorpro-form-' . self::$count;
		$tags = implode( ', ', self::parse_tags( isset( $args['tags'] ) ? $args['tags'] : '' ) );
		$btn  = ! empty( $args['button'] ) ? $args['button'] : $s['button_text'];
		$req  = ! empty( $s['require_names'] );

		self::register_assets();
		wp_enqueue_style( 'sensorpro-form' );
		wp_enqueue_script( 'sensorpro-form' );

		// No-JS result message after redirect back.
		$notice = '';
		$status = '';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( isset( $_GET['sensorpro'], $_GET['sp_form'] ) && sanitize_key( wp_unslash( $_GET['sp_form'] ) ) === $id ) {
			$status = sanitize_key( wp_unslash( $_GET['sensorpro'] ) );
			$code   = isset( $_GET['sp_code'] ) ? sanitize_key( wp_unslash( $_GET['sp_code'] ) ) : '';
			$notice = 'success' === $status ? $s['success_message'] : self::message( $code );
		}
		// phpcs:enable

		ob_start();
		?>
		<form id="<?php echo esc_attr( $id ); ?>" class="sensorpro-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<?php if ( ! empty( $args['title'] ) ) : ?>
				<h3 class="sensorpro-form__title"><?php echo esc_html( $args['title'] ); ?></h3>
			<?php endif; ?>
			<input type="hidden" name="action" value="sensorpro_subscribe">
			<input type="hidden" name="sp_form" value="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="sp_nonce" value="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>">
			<input type="hidden" name="sp_tags" value="<?php echo esc_attr( $tags ); ?>">
			<input type="hidden" name="sp_sig" value="<?php echo esc_attr( self::sign( $tags ) ); ?>">
			<input type="hidden" name="sp_return" value="<?php echo esc_url( self::current_url() ); ?>">
			<div class="sensorpro-form__hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $id ); ?>-website"><?php esc_html_e( 'Leave this field empty', 'sensorpro' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $id ); ?>-website" name="sp_website" value="" tabindex="-1" autocomplete="off">
			</div>

			<div class="sensorpro-form__field">
				<label for="<?php echo esc_attr( $id ); ?>-email"><?php esc_html_e( 'Email', 'sensorpro' ); ?> <span class="sensorpro-form__req" aria-hidden="true">*</span></label>
				<input type="email" id="<?php echo esc_attr( $id ); ?>-email" name="sp_email" required autocomplete="email" maxlength="254">
			</div>
			<?php if ( ! empty( $s['show_first_name'] ) || ! empty( $s['show_last_name'] ) ) : ?>
			<div class="sensorpro-form__row">
				<?php if ( ! empty( $s['show_first_name'] ) ) : ?>
				<div class="sensorpro-form__field">
					<label for="<?php echo esc_attr( $id ); ?>-first"><?php esc_html_e( 'First name', 'sensorpro' ); ?><?php echo $req ? ' <span class="sensorpro-form__req" aria-hidden="true">*</span>' : ''; ?></label>
					<input type="text" id="<?php echo esc_attr( $id ); ?>-first" name="sp_first_name" autocomplete="given-name" maxlength="100" <?php echo $req ? 'required' : ''; ?>>
				</div>
				<?php endif; ?>
				<?php if ( ! empty( $s['show_last_name'] ) ) : ?>
				<div class="sensorpro-form__field">
					<label for="<?php echo esc_attr( $id ); ?>-last"><?php esc_html_e( 'Last name', 'sensorpro' ); ?><?php echo $req ? ' <span class="sensorpro-form__req" aria-hidden="true">*</span>' : ''; ?></label>
					<input type="text" id="<?php echo esc_attr( $id ); ?>-last" name="sp_last_name" autocomplete="family-name" maxlength="100" <?php echo $req ? 'required' : ''; ?>>
				</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<?php if ( ! empty( $s['consent_enabled'] ) ) : ?>
			<div class="sensorpro-form__consent">
				<label><input type="checkbox" name="sp_consent" value="1" required> <span><?php echo wp_kses_post( $s['consent_text'] ); ?></span></label>
			</div>
			<?php endif; ?>
			<div class="sensorpro-form__actions">
				<button type="submit" class="sensorpro-form__submit"><?php echo esc_html( $btn ); ?></button>
			</div>
			<div class="sensorpro-form__message<?php echo $status ? ' is-' . esc_attr( 'success' === $status ? 'success' : 'error' ) : ''; ?>" role="status" aria-live="polite"><?php echo esc_html( $notice ); ?></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Current URL (for no-JS redirect back).
	 *
	 * @return string
	 */
	private static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$url = home_url( $uri );
		return remove_query_arg( array( 'sensorpro', 'sp_form', 'sp_code' ), $url );
	}

	/**
	 * Visitor-facing message for a result code.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function message( $code ) {
		$map = array(
			'invalid_email' => __( 'Please enter a valid email address.', 'sensorpro' ),
			'missing_name'  => __( 'Please enter your name.', 'sensorpro' ),
			'consent'       => __( 'Please tick the box to confirm you would like to hear from us.', 'sensorpro' ),
			'expired'       => __( 'This form has expired. Please refresh the page and try again.', 'sensorpro' ),
			'rate'          => __( 'Too many attempts. Please wait a few minutes and try again.', 'sensorpro' ),
			'unavailable'   => __( 'Signups are not available right now. Please try again later.', 'sensorpro' ),
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : __( 'Sorry, something went wrong. Please try again.', 'sensorpro' );
	}

	/**
	 * REST routes.
	 */
	public static function rest_routes() {
		register_rest_route(
			'sensorpro/v1',
			'/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_subscribe' ),
				'permission_callback' => '__return_true', // Public signup form; protected by nonce, honeypot and rate limit.
			)
		);
	}

	/**
	 * REST callback.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function rest_subscribe( WP_REST_Request $req ) {
		$r = self::process( self::sanitize_submission( $req->get_params() ) );
		$s = sensorpro_get_settings();
		if ( $r['ok'] ) {
			return new WP_REST_Response(
				array(
					'success'  => true,
					'message'  => $s['success_message'],
					'redirect' => $s['redirect_url'],
				),
				200
			);
		}
		return new WP_REST_Response(
			array(
				'success' => false,
				'code'    => $r['code'],
				'message' => self::message( $r['code'] ),
			),
			'rate' === $r['code'] ? 429 : 400
		);
	}

	/**
	 * No-JS fallback via admin-post.php.
	 */
	public static function handle_post() {
		// Each field is unslashed and sanitized as it is read. The nonce is verified in process().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$params = array(
			'sp_website'    => isset( $_POST['sp_website'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_website'] ) ) : '',
			'sp_nonce'      => isset( $_POST['sp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_nonce'] ) ) : '',
			'sp_email'      => isset( $_POST['sp_email'] ) ? sanitize_email( sanitize_text_field( wp_unslash( $_POST['sp_email'] ) ) ) : '',
			'sp_first_name' => isset( $_POST['sp_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_first_name'] ) ) : '',
			'sp_last_name'  => isset( $_POST['sp_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_last_name'] ) ) : '',
			'sp_consent'    => isset( $_POST['sp_consent'] ) ? absint( wp_unslash( $_POST['sp_consent'] ) ) : 0,
			'sp_tags'       => isset( $_POST['sp_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_tags'] ) ) : '',
			'sp_sig'        => isset( $_POST['sp_sig'] ) ? sanitize_key( wp_unslash( $_POST['sp_sig'] ) ) : '',
			'sp_form'       => isset( $_POST['sp_form'] ) ? sanitize_key( wp_unslash( $_POST['sp_form'] ) ) : '',
			'sp_return'     => isset( $_POST['sp_return'] ) && is_string( $_POST['sp_return'] ) ? esc_url_raw( wp_unslash( $_POST['sp_return'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$r      = self::process( $params );
		$s      = sensorpro_get_settings();
		$return = '' !== $params['sp_return'] ? wp_validate_redirect( $params['sp_return'], home_url( '/' ) ) : home_url( '/' );
		$form   = $params['sp_form'];
		if ( $r['ok'] && $s['redirect_url'] ) {
			wp_redirect( esc_url_raw( $s['redirect_url'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- admin-configured URL.
			exit;
		}
		$args = array(
			'sensorpro' => $r['ok'] ? 'success' : 'error',
			'sp_form'   => $form,
		);
		if ( ! $r['ok'] ) {
			$args['sp_code'] = $r['code'];
		}
		wp_safe_redirect( add_query_arg( $args, $return ) . '#' . $form );
		exit;
	}

	/**
	 * Sanitize a submission (REST request params) field by field.
	 *
	 * Only the fields the form sends are kept; everything else is dropped.
	 *
	 * @param array $raw Raw params.
	 * @return array Sanitized params.
	 */
	public static function sanitize_submission( $raw ) {
		$raw  = is_array( $raw ) ? $raw : array();
		$text = function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';
		};
		return array(
			'sp_website'    => $text( 'sp_website' ),
			'sp_nonce'      => $text( 'sp_nonce' ),
			'sp_email'      => sanitize_email( $text( 'sp_email' ) ),
			'sp_first_name' => $text( 'sp_first_name' ),
			'sp_last_name'  => $text( 'sp_last_name' ),
			'sp_consent'    => absint( $text( 'sp_consent' ) ),
			'sp_tags'       => $text( 'sp_tags' ),
			'sp_sig'        => sanitize_key( $text( 'sp_sig' ) ),
			'sp_form'       => sanitize_key( $text( 'sp_form' ) ),
			'sp_return'     => isset( $raw['sp_return'] ) && is_string( $raw['sp_return'] ) ? esc_url_raw( $raw['sp_return'] ) : '',
		);
	}

	/**
	 * Visitor IP (REMOTE_ADDR only; proxies can be added via filter).
	 *
	 * @return string
	 */
	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
		return sanitize_text_field( (string) apply_filters( 'sensorpro_client_ip', $ip ) );
	}

	/**
	 * Validate and send a submission.
	 *
	 * @param array $p Sanitized params from sanitize_submission() or handle_post().
	 * @return array { ok: bool, code: string }
	 */
	public static function process( $p ) {
		$p = is_array( $p ) ? $p : array();
		$s = sensorpro_get_settings();

		// Honeypot: pretend success so bots learn nothing.
		if ( ! empty( $p['sp_website'] ) ) {
			return array( 'ok' => true, 'code' => 'hp' );
		}
		if ( empty( $p['sp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $p['sp_nonce'] ), self::NONCE ) ) {
			return array( 'ok' => false, 'code' => 'expired' );
		}

		// Rate limit: max attempts per IP per 10 minutes.
		$key   = 'sensorpro_rl_' . md5( self::ip() );
		$hits  = (int) get_transient( $key );
		$limit = (int) apply_filters( 'sensorpro_rate_limit', 5 );
		if ( $hits >= $limit ) {
			return array( 'ok' => false, 'code' => 'rate' );
		}
		set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

		$email = isset( $p['sp_email'] ) ? sanitize_email( trim( (string) $p['sp_email'] ) ) : '';
		if ( '' === $email || ! is_email( $email ) || strlen( $email ) > 254 ) {
			return array( 'ok' => false, 'code' => 'invalid_email' );
		}
		$first = ! empty( $s['show_first_name'] ) && isset( $p['sp_first_name'] ) ? mb_substr( sanitize_text_field( $p['sp_first_name'] ), 0, 100 ) : '';
		$last  = ! empty( $s['show_last_name'] ) && isset( $p['sp_last_name'] ) ? mb_substr( sanitize_text_field( $p['sp_last_name'] ), 0, 100 ) : '';
		if ( ! empty( $s['require_names'] ) && ( ( ! empty( $s['show_first_name'] ) && '' === $first ) || ( ! empty( $s['show_last_name'] ) && '' === $last ) ) ) {
			return array( 'ok' => false, 'code' => 'missing_name' );
		}
		if ( ! empty( $s['consent_enabled'] ) && empty( $p['sp_consent'] ) ) {
			return array( 'ok' => false, 'code' => 'consent' );
		}

		$tags      = self::parse_tags( $s['tags'] );
		$form_tags = isset( $p['sp_tags'] ) ? implode( ', ', self::parse_tags( $p['sp_tags'] ) ) : '';
		$sig       = isset( $p['sp_sig'] ) ? (string) $p['sp_sig'] : '';
		if ( '' !== $form_tags && hash_equals( self::sign( $form_tags ), $sig ) ) {
			$tags = array_values( array_unique( array_merge( $tags, self::parse_tags( $form_tags ) ) ) );
		}

		$contact = array( 'PersonalEMail' => $email );
		if ( '' !== $first ) {
			$contact['FirstName'] = $first;
		}
		if ( '' !== $last ) {
			$contact['LastName'] = $last;
		}
		if ( '' !== $s['import_name'] ) {
			$contact['ImportName'] = mb_substr( $s['import_name'], 0, 100 );
		}
		if ( ! empty( $s['consent_enabled'] ) && $s['consent_field'] ) {
			$host                             = wp_parse_url( home_url(), PHP_URL_HOST );
			$contact[ $s['consent_field'] ] = mb_substr( 'WP consent ' . gmdate( 'Y-m-d H:i' ) . ' UTC ' . $host, 0, 100 );
		}
		$contact = apply_filters( 'sensorpro_contact', $contact, $p );

		$api = new SensorPro_API( $s );
		if ( ! $api->is_configured() ) {
			return array( 'ok' => false, 'code' => 'unavailable' );
		}
		$res = $api->upsert_contact( $contact, $tags );
		if ( is_wp_error( $res ) ) {
			if ( 'sensorpro_invalid_email' === $res->get_error_code() ) {
				return array( 'ok' => false, 'code' => 'invalid_email' );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[sensorpro] signup failed: ' . $api->redact( $res->get_error_code() . ' ' . $res->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
			do_action( 'sensorpro_signup_failed', $res, $contact );
			return array( 'ok' => false, 'code' => 'unavailable' );
		}
		// Resubscribe a previously unsubscribed contact, but only on a consented submission.
		$consented = ! empty( $s['consent_enabled'] ) && ! empty( $p['sp_consent'] );
		if ( $consented && empty( $res['AddCount'] ) && apply_filters( 'sensorpro_resubscribe_on_consent', true, $email ) ) {
			$re = $api->resubscribe_if_opted_out( $email );
			if ( is_wp_error( $re ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[sensorpro] resubscribe failed: ' . $api->redact( $re->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
			do_action( 'sensorpro_resubscribe_result', $re, $email );
		}
		do_action( 'sensorpro_signup_success', $res, $contact, $tags );
		return array( 'ok' => true, 'code' => 'ok' );
	}
}
