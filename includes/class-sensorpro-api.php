<?php
/**
 * SensorPro REST API client.
 *
 * Auth model (same as the SensorPro Cursor plugin):
 *  1. POST {base}/auth/sys/signin with header "x-apikey" and JSON body
 *     { Organization, User, Password } (User must be an API user).
 *  2. The returned Token is appended to every call: {base}/api/Contact/UpdateAdd/{Token}.
 * Credentials and tokens never leave the server and are never logged.
 *
 * @package SensorPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API client.
 */
class SensorPro_API {

	const TOKEN_TRANSIENT = 'sensorpro_token';
	const BLOCK_TRANSIENT = 'sensorpro_signin_block';
	const EMPTY_GUID      = '00000000-0000-0000-0000-000000000000';

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $s;

	/**
	 * Constructor.
	 *
	 * @param array|null $settings Settings (defaults to saved settings).
	 */
	public function __construct( $settings = null ) {
		$this->s = is_array( $settings ) ? $settings : sensorpro_get_settings();
	}

	/**
	 * Are all credentials present?
	 *
	 * @return bool
	 */
	public function is_configured() {
		foreach ( array( 'api_base', 'api_key', 'organization', 'api_user', 'api_password' ) as $k ) {
			if ( empty( $this->s[ $k ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Base URL without trailing slash.
	 *
	 * @return string
	 */
	private function base() {
		return untrailingslashit( $this->s['api_base'] );
	}

	/**
	 * Remove secrets from a string before it is shown or logged.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public function redact( $text ) {
		$text = (string) $text;
		foreach ( array( $this->s['api_key'], $this->s['api_password'], get_transient( self::TOKEN_TRANSIENT ) ) as $secret ) {
			if ( is_string( $secret ) && strlen( $secret ) >= 4 ) {
				$text = str_replace( $secret, '[redacted]', $text );
			}
		}
		// SensorPro echoes the API key in IP-whitelist errors.
		$text = preg_replace( '/(API key\s+)([0-9a-f]{6})[0-9a-f]{10,}/i', '$1$2…[redacted]', $text );
		return preg_replace( '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?=\/?$)/i', '[token]', $text );
	}

	/**
	 * Send a JSON request.
	 *
	 * @param string $method  HTTP method.
	 * @param string $url     URL.
	 * @param array  $body    Body.
	 * @param array  $headers Extra headers.
	 * @return array|WP_Error { status, json, text }
	 */
	private function request( $method, $url, $body, $headers = array() ) {
		$res = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'timeout' => (int) apply_filters( 'sensorpro_http_timeout', 20 ),
				'headers' => array_merge(
					array(
						'Content-Type' => 'application/json',
						'Accept'       => 'application/json',
					),
					$headers
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'sensorpro_network', $this->redact( $res->get_error_message() ) );
		}
		$text = wp_remote_retrieve_body( $res );
		return array(
			'status' => (int) wp_remote_retrieve_response_code( $res ),
			'json'   => json_decode( $text, true ),
			'text'   => $text,
		);
	}

	/**
	 * Extract errors from a SensorPro Result object.
	 *
	 * @param mixed $json Decoded response.
	 * @return array List of [code, description].
	 */
	public static function result_errors( $json ) {
		$out = array();
		if ( ! is_array( $json ) ) {
			return $out;
		}
		$r = isset( $json['Result'] ) ? $json['Result'] : ( isset( $json['RESULT'] ) ? $json['RESULT'] : null );
		if ( ! is_array( $r ) ) {
			return $out;
		}
		$list = isset( $r['ErrorMessages'] ) ? $r['ErrorMessages'] : array();
		foreach ( (array) $list as $e ) {
			$out[] = array(
				isset( $e['Code'] ) ? (string) $e['Code'] : 'Error',
				isset( $e['Description'] ) ? (string) $e['Description'] : '',
			);
		}
		$total = isset( $r['TotalErrors'] ) ? (int) $r['TotalErrors'] : count( $out );
		if ( $total > 0 && empty( $out ) ) {
			$out[] = array( 'Error', '' );
		}
		return $out;
	}

	/**
	 * Classify errors.
	 *
	 * @param array $errors Errors from result_errors().
	 * @return string ip|auth|token|duplicate|invalid_email|api
	 */
	private static function classify( $errors ) {
		$all = strtolower( implode( ' ', array_map( 'implode', array_fill( 0, count( $errors ), ' ' ), $errors ) ) );
		if ( false !== strpos( $all, 'ipnotfound' ) || false !== strpos( $all, 'whitelist' ) ) {
			return 'ip';
		}
		if ( preg_match( '/token|session|expired|not authenticated|unauthori[sz]ed/', $all ) ) {
			return 'token';
		}
		if ( preg_match( '/invalidemail|email.*invalid|not a valid email/', $all ) ) {
			return 'invalid_email';
		}
		return 'api';
	}

	/**
	 * Sign in and cache the token.
	 *
	 * @param bool $force Ignore a previous credential failure.
	 * @return string|WP_Error Token.
	 */
	public function signin( $force = false ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'sensorpro_config', __( 'SensorPro is not configured. Enter the API key, organization, API user and password under Settings > SensorPro.', 'sensorpro' ) );
		}
		if ( ! $force && get_transient( self::BLOCK_TRANSIENT ) ) {
			return new WP_Error( 'sensorpro_auth_blocked', __( 'Sign-in paused after a credential failure (SensorPro locks accounts after 3 failed logins). Fix the credentials and use Test connection.', 'sensorpro' ) );
		}
		$r = $this->request(
			'POST',
			$this->base() . '/auth/sys/signin',
			array(
				'Organization' => $this->s['organization'],
				'User'         => $this->s['api_user'],
				'Password'     => $this->s['api_password'],
			),
			array( 'x-apikey' => $this->s['api_key'] )
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$errors = self::result_errors( $r['json'] );
		$token  = is_array( $r['json'] ) && isset( $r['json']['Token'] ) ? (string) $r['json']['Token'] : '';
		if ( $r['status'] >= 400 || $errors || '' === $token || self::EMPTY_GUID === $token ) {
			$detail = $errors ? implode( ' | ', array_map( array( __CLASS__, 'fmt' ), $errors ) ) : substr( wp_strip_all_tags( $r['text'] ), 0, 300 );
			$detail = $this->redact( $detail );
			if ( 'ip' === self::classify( $errors ) ) {
				return new WP_Error(
					'sensorpro_ip',
					/* translators: %s: error detail from SensorPro */
					sprintf( __( 'SensorPro did not accept requests from this server\'s IP address. If this continues, contact SensorPro support. (%s)', 'sensorpro' ), $detail )
				);
			}
			set_transient( self::BLOCK_TRANSIENT, 1, 15 * MINUTE_IN_SECONDS );
			return new WP_Error(
				'sensorpro_auth',
				/* translators: 1: HTTP status, 2: error detail */
				sprintf( __( 'SensorPro sign-in failed (HTTP %1$d). Check the API key, organization, API user and password. Details: %2$s', 'sensorpro' ), $r['status'], $detail ? $detail : '-' )
			);
		}
		delete_transient( self::BLOCK_TRANSIENT );
		$ttl = isset( $r['json']['ExpiresIn'] ) ? (int) $r['json']['ExpiresIn'] : 3600;
		$ttl = max( 60, (int) max( $ttl - 120, $ttl * 0.8 ) );
		set_transient( self::TOKEN_TRANSIENT, $token, $ttl );
		return $token;
	}

	/**
	 * Format one error.
	 *
	 * @param array $e [code, description].
	 * @return string
	 */
	public static function fmt( $e ) {
		return trim( $e[0] . ': ' . $e[1], ': ' );
	}

	/**
	 * Call an API path with the token, retrying once on a token error.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   e.g. api/Contact/UpdateAdd.
	 * @param array  $body   Body.
	 * @return array|WP_Error Decoded JSON.
	 */
	public function call( $method, $path, $body ) {
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$token = get_transient( self::TOKEN_TRANSIENT );
			if ( ! $token ) {
				$token = $this->signin();
				if ( is_wp_error( $token ) ) {
					return $token;
				}
			}
			$r = $this->request( $method, $this->base() . '/' . ltrim( $path, '/' ) . '/' . rawurlencode( $token ), $body );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$errors = self::result_errors( $r['json'] );
			$kind   = self::classify( $errors );
			if ( 0 === $attempt && ( 401 === $r['status'] || ( $errors && 'token' === $kind ) ) ) {
				delete_transient( self::TOKEN_TRANSIENT );
				continue;
			}
			if ( ! is_array( $r['json'] ) ) {
				/* translators: %d: HTTP status */
				return new WP_Error( 'sensorpro_http', sprintf( __( 'SensorPro returned HTTP %d with an unexpected response.', 'sensorpro' ), $r['status'] ) );
			}
			if ( $errors ) {
				return new WP_Error( 'sensorpro_' . $kind, $this->redact( implode( ' | ', array_map( array( __CLASS__, 'fmt' ), $errors ) ) ), $r['json'] );
			}
			return $r['json'];
		}
		return new WP_Error( 'sensorpro_auth', __( 'SensorPro rejected the session token.', 'sensorpro' ) );
	}

	/**
	 * Add or update one contact (POST api/Contact/UpdateAdd, matched by email).
	 *
	 * @param array $contact SensorPro contact fields (PersonalEMail required).
	 * @param array $tags    Tags for AddToList.
	 * @return array|WP_Error Response (AddCount / UpdateCount / NoChangeCount).
	 */
	public function upsert_contact( array $contact, array $tags = array() ) {
		$welcome = ! empty( $this->s['send_welcome'] );
		$body    = array(
			'AddToList'            => array_values( array_filter( array_map( 'strval', $tags ) ) ),
			'Contact'              => array( $contact ),
			'Options'              => array(
				'Parameters' => new stdClass(),
				'Action'     => '',
			),
			'ReturnFailedRequests' => false,
			'UpdateByKey'          => 'email',
			'SendWelcomeEmail'     => $welcome,
			// No SensorPro signup form is linked: SensorPro uses the account's default welcome settings.
			'SignupFormId'         => self::EMPTY_GUID,
		);
		$body = apply_filters( 'sensorpro_upsert_body', $body, $contact );
		return $this->call( 'POST', 'api/Contact/UpdateAdd', $body );
	}

	/**
	 * Read one contact by email (POST api/Contact/GetContacts). Read-only.
	 *
	 * @param string $email Email.
	 * @return array|null|WP_Error Contact record, null if not found.
	 */
	public function get_contact( $email ) {
		$res = $this->call(
			'POST',
			'api/Contact/GetContacts',
			array(
				'ExtLinkIds'   => null,
				'EmailAddress' => array( $email ),
				'ContactIds'   => null,
			)
		);
		if ( is_wp_error( $res ) ) {
			// A missing email comes back as a GetFailed error.
			return false !== stripos( $res->get_error_message(), 'GetFailed' ) ? null : $res;
		}
		return ! empty( $res['Contacts'][0] ) && is_array( $res['Contacts'][0] ) ? $res['Contacts'][0] : null;
	}

	/**
	 * Is this contact record opted out (OptOut -10)?
	 *
	 * @param array $contact Contact record.
	 * @return bool
	 */
	public static function is_opted_out( $contact ) {
		if ( ! is_array( $contact ) || ! isset( $contact['OptOut'] ) ) {
			return false;
		}
		$v = $contact['OptOut'];
		return ( is_numeric( $v ) && -10 === (int) $v ) || ( is_string( $v ) && 'out' === strtolower( $v ) );
	}

	/**
	 * Resubscribe a contact who had opted out. Only call on a consented signup.
	 *
	 * UpdateAdd never changes an opted-out contact back to subscribed (see
	 * APIHelperImport.SyncContactUpdate in NT.Email.61), so this uses the supported
	 * PUT api/Contact/ChangeOptOutStatus with Status 0. It reads first and only
	 * writes when the contact is actually opted out.
	 *
	 * @param string $email Email.
	 * @return string|WP_Error 'resubscribed', 'not_opted_out' or 'not_found'.
	 */
	public function resubscribe_if_opted_out( $email ) {
		$c = $this->get_contact( $email );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		if ( null === $c ) {
			return 'not_found';
		}
		if ( ! self::is_opted_out( $c ) ) {
			return 'not_opted_out';
		}
		$res = $this->call(
			'PUT',
			'api/Contact/ChangeOptOutStatus',
			array(
				'EmailAddress'         => array( $email ),
				'ExtLinkIds'           => array(),
				'ContactIds'           => array(),
				'Status'               => 0,
				'ReturnFailedRequests' => false,
			)
		);
		return is_wp_error( $res ) ? $res : 'resubscribed';
	}

	/**
	 * GUID check.
	 *
	 * @param string $s Value.
	 * @return bool
	 */
	public static function is_guid( $s ) {
		return (bool) preg_match( '/^\{?[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\}?$/i', (string) $s );
	}
}
