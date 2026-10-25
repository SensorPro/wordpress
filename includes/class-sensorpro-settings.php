<?php
/**
 * Settings > SensorPro admin page.
 *
 * @package SensorPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page.
 */
class SensorPro_Settings {

	/** Fields whose saved value is never printed back. */
	const SECRET_FIELDS = array( 'api_key', 'api_password' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_sensorpro_test_connection', array( __CLASS__, 'ajax_test' ) );
	}

	/**
	 * Menu item.
	 */
	public static function menu() {
		add_options_page( __( 'SensorPro', 'sensorpro' ), __( 'SensorPro', 'sensorpro' ), 'manage_options', 'sensorpro', array( __CLASS__, 'page' ) );
	}

	/**
	 * Admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( 'settings_page_sensorpro' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'sensorpro-admin', SENSORPRO_URL . 'assets/css/admin.css', array(), SENSORPRO_VERSION );
		wp_enqueue_script( 'sensorpro-admin', SENSORPRO_URL . 'assets/js/admin.js', array(), SENSORPRO_VERSION, true );
		wp_localize_script(
			'sensorpro-admin',
			'SensorProAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sensorpro_test_connection' ),
				'testing' => __( 'Testing…', 'sensorpro' ),
				'failed'  => __( 'Request failed.', 'sensorpro' ),
			)
		);
	}

	/**
	 * Register setting.
	 */
	public static function register() {
		register_setting(
			'sensorpro',
			SENSORPRO_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => sensorpro_default_settings(),
			)
		);
	}

	/**
	 * Sanitize settings. Blank secret fields keep the stored value.
	 *
	 * @param array $in Raw input (already unslashed by options.php).
	 * @return array
	 */
	public static function sanitize( $in ) {
		$old = sensorpro_get_settings();
		$in  = is_array( $in ) ? $in : array();
		$out = $old;

		$base = isset( $in['api_base'] ) ? esc_url_raw( trim( sanitize_text_field( $in['api_base'] ) ) ) : $old['api_base'];
		if ( $base && 0 !== strpos( $base, 'https://' ) ) {
			add_settings_error( SENSORPRO_OPTION, 'base', __( 'The API URL must start with https://.', 'sensorpro' ) );
			$base = $old['api_base'];
		}
		$out['api_base'] = $base ? untrailingslashit( $base ) : sensorpro_default_settings()['api_base'];

		foreach ( self::SECRET_FIELDS as $k ) {
			if ( ! empty( $in[ $k . '_clear' ] ) ) {
				$out[ $k ] = '';
			} elseif ( isset( $in[ $k ] ) && '' !== sanitize_text_field( $in[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( $in[ $k ] );
			}
		}
		foreach ( array( 'organization', 'api_user', 'import_name', 'button_text' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		}
		$out['tags'] = isset( $in['tags'] ) ? implode( ', ', SensorPro_Form::parse_tags( sanitize_text_field( $in['tags'] ) ) ) : '';
		unset( $out['signup_form_id'] ); // Setting removed before 1.0.0.

		foreach ( array( 'send_welcome', 'show_first_name', 'show_last_name', 'require_names', 'consent_enabled' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}

		$out['consent_text']    = isset( $in['consent_text'] ) ? wp_kses( $in['consent_text'], array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'strong' => array(), 'em' => array() ) ) : '';
		$out['success_message'] = isset( $in['success_message'] ) ? sanitize_textarea_field( $in['success_message'] ) : '';
		$out['redirect_url']    = isset( $in['redirect_url'] ) ? esc_url_raw( trim( sanitize_text_field( $in['redirect_url'] ) ) ) : '';

		$cf                   = isset( $in['consent_field'] ) ? sanitize_text_field( $in['consent_field'] ) : '';
		$out['consent_field'] = in_array( $cf, self::consent_fields(), true ) ? $cf : '';

		delete_transient( SensorPro_API::TOKEN_TRANSIENT );
		return $out;
	}

	/**
	 * Contact fields that may hold the consent record.
	 *
	 * @return string[]
	 */
	public static function consent_fields() {
		$f = array();
		for ( $i = 2; $i <= 15; $i++ ) {
			$f[] = 'UserChar' . $i;
		}
		return $f;
	}

	/**
	 * Masked hint for a stored secret.
	 *
	 * @param string $v Secret.
	 * @return string
	 */
	private static function mask( $v ) {
		if ( '' === (string) $v ) {
			return __( 'Not set', 'sensorpro' );
		}
		/* translators: %s: last 4 characters */
		return sprintf( __( 'Saved (ends in …%s). Leave blank to keep.', 'sensorpro' ), substr( $v, -4 ) );
	}

	/**
	 * AJAX: test connection.
	 */
	public static function ajax_test() {
		check_ajax_referer( 'sensorpro_test_connection', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'sensorpro' ) ), 403 );
		}
		delete_transient( SensorPro_API::TOKEN_TRANSIENT );
		$api = new SensorPro_API();
		$t   = $api->signin( true );
		if ( is_wp_error( $t ) ) {
			wp_send_json_error( array( 'message' => $t->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Connected. SensorPro accepted the credentials from this server.', 'sensorpro' ) ) );
	}

	/**
	 * Render page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = sensorpro_get_settings();
		$n = SENSORPRO_OPTION;
		?>
		<div class="wrap sensorpro-admin">
			<h1><?php esc_html_e( 'SensorPro', 'sensorpro' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Connect your SensorPro account, then add the form with the [sensorpro_form] shortcode, the SensorPro Signup Form block, or the SensorPro widget.', 'sensorpro' ); ?></p>
			<?php settings_errors( $n ); ?>
			<form method="post" action="options.php" autocomplete="off">
				<?php settings_fields( 'sensorpro' ); ?>

				<h2><?php esc_html_e( 'API connection', 'sensorpro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="sp-base"><?php esc_html_e( 'API URL', 'sensorpro' ); ?></label></th>
						<td><input id="sp-base" class="regular-text" type="url" name="<?php echo esc_attr( $n ); ?>[api_base]" value="<?php echo esc_attr( $s['api_base'] ); ?>">
						<p class="description"><?php esc_html_e( 'Usually https://apinie.sensorpro.net.', 'sensorpro' ); ?></p></td></tr>
					<tr><th scope="row"><label for="sp-key"><?php esc_html_e( 'API key', 'sensorpro' ); ?></label></th>
						<td><input id="sp-key" class="regular-text" type="password" name="<?php echo esc_attr( $n ); ?>[api_key]" value="" autocomplete="new-password">
						<p class="description"><?php echo esc_html( self::mask( $s['api_key'] ) ); ?> <?php esc_html_e( 'Found in SensorPro under API > API keys.', 'sensorpro' ); ?></p></td></tr>
					<tr><th scope="row"><label for="sp-org"><?php esc_html_e( 'Organization', 'sensorpro' ); ?></label></th>
						<td><input id="sp-org" class="regular-text" type="text" name="<?php echo esc_attr( $n ); ?>[organization]" value="<?php echo esc_attr( $s['organization'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="sp-user"><?php esc_html_e( 'API user', 'sensorpro' ); ?></label></th>
						<td><input id="sp-user" class="regular-text" type="text" name="<?php echo esc_attr( $n ); ?>[api_user]" value="<?php echo esc_attr( $s['api_user'] ); ?>">
						<p class="description"><?php esc_html_e( 'Must be an API user. Normal SensorPro users cannot use the API.', 'sensorpro' ); ?></p></td></tr>
					<tr><th scope="row"><label for="sp-pass"><?php esc_html_e( 'API user password', 'sensorpro' ); ?></label></th>
						<td><input id="sp-pass" class="regular-text" type="password" name="<?php echo esc_attr( $n ); ?>[api_password]" value="" autocomplete="new-password">
						<p class="description"><?php echo esc_html( self::mask( $s['api_password'] ) ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Check', 'sensorpro' ); ?></th>
						<td><button type="button" class="button sp-btn" id="sp-test"><?php esc_html_e( 'Test connection', 'sensorpro' ); ?></button>
						<span id="sp-test-result" role="status" aria-live="polite"></span>
						<p class="description"><?php esc_html_e( 'Save first. After a failed sign-in the plugin pauses for 15 minutes, because SensorPro locks accounts after 3 failed logins.', 'sensorpro' ); ?></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'Where contacts go', 'sensorpro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="sp-tags"><?php esc_html_e( 'Tags / lists', 'sensorpro' ); ?></label></th>
						<td><input id="sp-tags" class="regular-text" type="text" name="<?php echo esc_attr( $n ); ?>[tags]" value="<?php echo esc_attr( $s['tags'] ); ?>" placeholder="Newsletter, Website">
						<p class="description"><?php esc_html_e( 'Comma separated. Applied to every signup; SensorPro creates tags that do not exist yet. A form can add its own tags.', 'sensorpro' ); ?></p></td></tr>
					<tr><th scope="row"><label for="sp-import"><?php esc_html_e( 'Source (import name)', 'sensorpro' ); ?></label></th>
						<td><input id="sp-import" class="regular-text" type="text" name="<?php echo esc_attr( $n ); ?>[import_name]" value="<?php echo esc_attr( $s['import_name'] ); ?>">
						<p class="description"><?php esc_html_e( 'Saved in the contact\'s ImportName field so you can see where they came from.', 'sensorpro' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Welcome email', 'sensorpro' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[send_welcome]" value="1" <?php checked( $s['send_welcome'] ); ?>> <?php esc_html_e( 'Treat as a new signup and send SensorPro\'s welcome email, if one is set up', 'sensorpro' ); ?></label>
						<p class="description"><?php esc_html_e( 'Off by default. When off, no email is sent by the signup.', 'sensorpro' ); ?></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'Form', 'sensorpro' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Fields', 'sensorpro' ); ?></th>
						<td><fieldset>
							<label><input type="checkbox" checked disabled> <?php esc_html_e( 'Email (always shown, required)', 'sensorpro' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_first_name]" value="1" <?php checked( $s['show_first_name'] ); ?>> <?php esc_html_e( 'First name', 'sensorpro' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_last_name]" value="1" <?php checked( $s['show_last_name'] ); ?>> <?php esc_html_e( 'Last name', 'sensorpro' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[require_names]" value="1" <?php checked( $s['require_names'] ); ?>> <?php esc_html_e( 'Make shown name fields required', 'sensorpro' ); ?></label>
						</fieldset></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Consent (GDPR)', 'sensorpro' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[consent_enabled]" value="1" <?php checked( $s['consent_enabled'] ); ?>> <?php esc_html_e( 'Show a required consent checkbox (recommended in Ireland, the UK and the EU)', 'sensorpro' ); ?></label>
						<textarea class="large-text" rows="3" name="<?php echo esc_attr( $n ); ?>[consent_text]"><?php echo esc_textarea( $s['consent_text'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Links allowed, e.g. to your privacy policy.', 'sensorpro' ); ?></p></td></tr>
					<tr><th scope="row"><label for="sp-cf"><?php esc_html_e( 'Store consent record in', 'sensorpro' ); ?></label></th>
						<td><select id="sp-cf" name="<?php echo esc_attr( $n ); ?>[consent_field]">
							<option value=""><?php esc_html_e( 'Do not store', 'sensorpro' ); ?></option>
							<?php foreach ( self::consent_fields() as $f ) : ?>
								<option value="<?php echo esc_attr( $f ); ?>" <?php selected( $s['consent_field'], $f ); ?>><?php echo esc_html( $f ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Writes "WP consent <date UTC> <site>" to this SensorPro contact field as proof of consent. Pick a field you do not use for anything else.', 'sensorpro' ); ?></p>
						<p class="description"><?php esc_html_e( 'If someone who previously unsubscribed signs up again and ticks the consent box, they are resubscribed. Without the consent box, unsubscribed contacts stay unsubscribed.', 'sensorpro' ); ?></p></td></tr>
					<tr><th scope="row"><label for="sp-btn"><?php esc_html_e( 'Button text', 'sensorpro' ); ?></label></th>
						<td><input id="sp-btn" class="regular-text" type="text" name="<?php echo esc_attr( $n ); ?>[button_text]" value="<?php echo esc_attr( $s['button_text'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="sp-msg"><?php esc_html_e( 'Success message', 'sensorpro' ); ?></label></th>
						<td><textarea id="sp-msg" class="large-text" rows="2" name="<?php echo esc_attr( $n ); ?>[success_message]"><?php echo esc_textarea( $s['success_message'] ); ?></textarea></td></tr>
					<tr><th scope="row"><label for="sp-redirect"><?php esc_html_e( 'Redirect after signup', 'sensorpro' ); ?></label></th>
						<td><input id="sp-redirect" class="regular-text" type="url" name="<?php echo esc_attr( $n ); ?>[redirect_url]" value="<?php echo esc_attr( $s['redirect_url'] ); ?>" placeholder="https://">
						<p class="description"><?php esc_html_e( 'Optional. Leave blank to show the success message in place.', 'sensorpro' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Save settings', 'sensorpro' ), 'primary sp-btn-primary' ); ?>
			</form>
		</div>
		<?php
	}
}
