<?php
/**
 * In-plugin feedback posted to the Compath relay.
 *
 * The relay creates the Jira task and sends the confirmation email.
 * This plugin stores no Jira token and no SMTP password.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Feedback {

	public const ENDPOINT = 'https://rehearsal.compath.ee/api/feedback.php';

	public const ENDPOINT_FILTER = 'choir_rehearsal_feedback_endpoint';

	public const RATE_LIMIT = 5;

	public const RATE_WINDOW = 3600;

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function endpoint(): string {
		$url = self::ENDPOINT;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( self::ENDPOINT_FILTER, $url );
			if ( is_string( $filtered ) && self::is_https_url( $filtered ) ) {
				return $filtered;
			}
		}

		return $url;
	}

	public static function register_routes(): void {
		register_rest_route(
			'choir-rehearsal/v1',
			'/feedback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'handle_submit' ),
				'permission_callback' => array( self::class, 'can_submit' ),
				'args'                => array(
					'type'        => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => static fn( $value ): string => sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ),
					),
					'title'       => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => static fn( $value ): string => sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ),
					),
					'description' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => static fn( $value ): string => sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' ),
					),
					'name'        => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => static fn( $value ): string => sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ),
					),
					'email'       => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => static fn( $value ): string => sanitize_email( is_scalar( $value ) ? (string) $value : '' ),
					),
					'company'     => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => static fn( $value ): string => sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ),
					),
				),
			)
		);
	}

	/**
	 * Guests, singers, voice leaders, and administrators may send feedback.
	 * The gate is the REST nonce (CSRF), not a choir capability.
	 */
	public static function can_submit( WP_REST_Request $request ): bool|WP_Error {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$param = $request->get_param( '_wpnonce' );
			$nonce = is_scalar( $param ) ? (string) $param : '';
		}

		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new WP_Error(
				'choir_feedback_forbidden',
				__( 'Security check failed. Reload the page and try again.', 'compath-choir-rehearsal' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	public static function handle_submit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		if ( ! is_email( $email ) ) {
			$email = '';
		}

		$result = self::submit_feedback(
			array(
				'type'        => (string) $request->get_param( 'type' ),
				'title'       => (string) $request->get_param( 'title' ),
				'description' => (string) $request->get_param( 'description' ),
				'name'        => (string) $request->get_param( 'name' ),
				'email'       => $email,
				'company'     => (string) $request->get_param( 'company' ),
			),
			array(
				'role'           => self::current_role(),
				'plugin_version' => defined( 'CHOIR_REHEARSAL_VERSION' ) ? (string) CHOIR_REHEARSAL_VERSION : '',
				'pro_version'    => defined( 'CHOIR_REHEARSAL_PRO_VERSION' ) ? (string) CHOIR_REHEARSAL_PRO_VERSION : '',
				'wp_version'     => (string) get_bloginfo( 'version' ),
				'php_version'    => PHP_VERSION,
				'site_url'       => (string) home_url( '/' ),
				'locale'         => self::sender_locale(),
				'endpoint'       => self::endpoint(),
				'now'            => time(),
				'rate_keys'      => self::rate_keys(),
			),
			static function ( string $key ): array {
				$stored = get_transient( self::transient_name( $key ) );
				return is_array( $stored ) ? $stored : array();
			},
			static function ( string $key, array $stamps ): void {
				set_transient( self::transient_name( $key ), $stamps, self::RATE_WINDOW );
			}
		);

		if ( ! $result['ok'] ) {
			return new WP_Error(
				'choir_feedback_' . $result['code'],
				$result['message'],
				array( 'status' => $result['status'] )
			);
		}

		return new WP_REST_Response(
			array(
				'message' => $result['message'],
				'case'    => $result['case'],
			),
			201
		);
	}

	public static function enqueue_assets(): void {
		wp_enqueue_script(
			'choir-rehearsal-feedback',
			CHOIR_REHEARSAL_URL . 'public/js/feedback.js',
			array(),
			CHOIR_REHEARSAL_VERSION,
			true
		);

		wp_localize_script(
			'choir-rehearsal-feedback',
			'choirRehearsalFeedback',
			array(
				'restUrl' => rest_url( 'choir-rehearsal/v1/feedback' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'sending'      => __( 'Sending…', 'compath-choir-rehearsal' ),
					'send'         => __( 'Send', 'compath-choir-rehearsal' ),
					'genericError' => __( 'Could not send feedback. Please try again later.', 'compath-choir-rehearsal' ),
				),
			)
		);
	}

	public static function render_panel(): void {
		$email = '';
		$name  = '';
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user instanceof WP_User ) {
				$email = sanitize_email( (string) $user->user_email );
				if ( ! is_email( $email ) ) {
					$email = '';
				}
				if ( isset( $user->display_name ) && is_string( $user->display_name ) ) {
					$name = sanitize_text_field( $user->display_name );
				}
			}
		}
		?>
		<details class="choir-feedback">
			<summary class="choir-feedback__toggle"><?php esc_html_e( 'Send feedback', 'compath-choir-rehearsal' ); ?></summary>
			<form id="choir-feedback-form" class="choir-feedback__form" method="post" action="<?php echo esc_url( rest_url( 'choir-rehearsal/v1/feedback' ) ); ?>">
				<?php wp_nonce_field( 'wp_rest' ); ?>
				<p class="choir-feedback__hp" style="position:absolute;left:-9999px;height:0;overflow:hidden;" aria-hidden="true">
					<label for="choir-feedback-company">Company</label>
					<input type="text" id="choir-feedback-company" name="company" tabindex="-1" autocomplete="off" value="" />
				</p>
				<p class="choir-feedback__intro"><?php esc_html_e( 'Wish, bug, or other note about Choir Rehearsal.', 'compath-choir-rehearsal' ); ?></p>
				<p class="choir-feedback__field">
					<label for="choir-feedback-type"><?php esc_html_e( 'Type', 'compath-choir-rehearsal' ); ?></label>
					<select id="choir-feedback-type" name="type" required>
						<option value="bug"><?php esc_html_e( 'Bug', 'compath-choir-rehearsal' ); ?></option>
						<option value="wish"><?php esc_html_e( 'Wish', 'compath-choir-rehearsal' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'compath-choir-rehearsal' ); ?></option>
					</select>
				</p>
				<p class="choir-feedback__field">
					<label for="choir-feedback-title"><?php esc_html_e( 'Title', 'compath-choir-rehearsal' ); ?></label>
					<input type="text" id="choir-feedback-title" name="title" required maxlength="120" placeholder="<?php esc_attr_e( 'Short summary', 'compath-choir-rehearsal' ); ?>" />
				</p>
				<p class="choir-feedback__field">
					<label for="choir-feedback-description"><?php esc_html_e( 'Description', 'compath-choir-rehearsal' ); ?></label>
					<textarea id="choir-feedback-description" name="description" required maxlength="4000" rows="5" placeholder="<?php esc_attr_e( 'What happened, or what you wish for', 'compath-choir-rehearsal' ); ?>"></textarea>
				</p>
				<p class="choir-feedback__field">
					<label for="choir-feedback-name"><?php esc_html_e( 'Name (optional)', 'compath-choir-rehearsal' ); ?></label>
					<input type="text" id="choir-feedback-name" name="name" maxlength="120" autocomplete="name" value="<?php echo esc_attr( $name ); ?>" />
				</p>
				<p class="choir-feedback__field">
					<label for="choir-feedback-email"><?php esc_html_e( 'Email', 'compath-choir-rehearsal' ); ?></label>
					<input type="email" id="choir-feedback-email" name="email" required maxlength="200" autocomplete="email" value="<?php echo esc_attr( $email ); ?>" />
				</p>
				<p class="choir-feedback__privacy"><?php esc_html_e( 'Your email is used only to answer this request.', 'compath-choir-rehearsal' ); ?></p>
				<p class="choir-feedback__status" role="status" aria-live="polite" hidden></p>
				<p class="choir-feedback__submit">
					<button type="submit" class="choir-feedback__button"><?php esc_html_e( 'Send', 'compath-choir-rehearsal' ); ?></button>
				</p>
			</form>
		</details>
		<?php
	}

	public static function to_plain_text( string $value ): string {
		$value = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $value ) ?? $value;
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$value = wp_strip_all_tags( $value );
		} else {
			$value = strip_tags( $value );
		}
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$value = wp_strip_all_tags( $value );
		} else {
			$value = strip_tags( $value );
		}
		$value = str_replace( array( '<', '>' ), '', $value );
		$value = preg_replace( "/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/", '', $value ) ?? $value;

		return trim( $value );
	}

	public static function normalize_type( string $type ): string {
		$type = strtolower( trim( $type ) );
		if ( ! in_array( $type, array( 'bug', 'wish', 'other' ), true ) ) {
			return '';
		}

		return $type;
	}

	/**
	 * Local SureCart record used for the support request. No remote call by itself.
	 *
	 * @param array<string, mixed> $options sc_* values from the Pro license option.
	 * @return array{pro: bool, status: string, license_id: string, activation_id: string, customer_id: string, purchase_id: string, order_id: string, license_key: string}
	 */
	public static function license_snapshot( bool $is_pro, array $options ): array {
		$status_raw = strtolower( self::option_string( $options, 'sc_license_status' ) );
		$inactive   = array( 'expired', 'revoked', 'invalid', 'inactive', 'canceled', 'cancelled', 'unlicensed' );
		if ( in_array( $status_raw, $inactive, true ) ) {
			$status = 'cancelled' === $status_raw ? 'canceled' : $status_raw;
			$pro    = false;
		} elseif ( $is_pro ) {
			$status = 'active';
			$pro    = true;
		} else {
			$status = 'Lite';
			$pro    = false;
		}

		return array(
			'pro'           => $pro,
			'status'        => $status,
			'license_id'    => self::option_string( $options, 'sc_license_id' ),
			'activation_id' => self::option_string( $options, 'sc_activation_id' ),
			'customer_id'   => self::option_string( $options, 'sc_customer_id' ),
			'purchase_id'   => self::option_string( $options, 'sc_purchase_id' ),
			'order_id'      => self::option_string( $options, 'sc_order_id' ),
			'license_key'   => self::option_string( $options, 'sc_license_key' ),
		);
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function option_string( array $options, string $key ): string {
		if ( ! isset( $options[ $key ] ) || ! is_scalar( $options[ $key ] ) ) {
			return '';
		}

		return trim( (string) $options[ $key ] );
	}

	/**
	 * @return array{pro: bool, status: string, license_id: string, activation_id: string, customer_id: string, purchase_id: string, order_id: string, license_key: string}
	 */
	public static function current_license(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return self::license_snapshot( false, array() );
		}

		if ( class_exists( 'Choir_Rehearsal_Pro_Licensing', false ) ) {
			Choir_Rehearsal_Pro_Licensing::refresh_cached_details();
			return self::license_snapshot(
				Choir_Rehearsal_Pro_Licensing::is_licensed(),
				Choir_Rehearsal_Pro_Licensing::stored_options()
			);
		}

		return self::license_snapshot( false, array() );
	}

	/**
	 * Accept either a license_snapshot() result or the same shape.
	 *
	 * @param array<string, mixed> $license
	 * @return array{pro: bool, status: string, license_id: string, activation_id: string, customer_id: string, purchase_id: string, order_id: string, license_key: string}
	 */
	public static function normalize_license( array $license ): array {
		return self::license_snapshot(
			! empty( $license['pro'] ),
			array(
				'sc_license_status' => $license['status'] ?? '',
				'sc_license_id'     => $license['license_id'] ?? '',
				'sc_activation_id'  => $license['activation_id'] ?? '',
				'sc_customer_id'    => $license['customer_id'] ?? '',
				'sc_purchase_id'    => $license['purchase_id'] ?? '',
				'sc_order_id'       => $license['order_id'] ?? '',
				'sc_license_key'    => $license['license_key'] ?? '',
			)
		);
	}

	public static function mask_license_key( string $key ): string {
		$key = trim( $key );
		$len = strlen( $key );
		if ( $len < 8 ) {
			return str_repeat( '*', max( 4, $len ) );
		}

		return substr( $key, 0, 4 ) . str_repeat( '*', $len - 8 ) . substr( $key, -4 );
	}

	/**
	 * Ids sent to the relay. The full license key is never included.
	 *
	 * @param array<string, mixed> $license
	 * @return array<string, string>
	 */
	public static function license_payload( array $license ): array {
		$license = self::normalize_license( $license );
		$payload = array(
			'status'        => $license['status'],
			'license_id'    => $license['license_id'],
			'purchase_id'   => $license['purchase_id'],
			'order_id'      => $license['order_id'],
			'customer_id'   => $license['customer_id'],
			'activation_id' => $license['activation_id'],
		);
		$public = false;
		foreach ( array( 'license_id', 'purchase_id', 'order_id', 'customer_id', 'activation_id' ) as $key ) {
			if ( '' !== $payload[ $key ] ) {
				$public = true;
				break;
			}
		}
		if ( ! $public && '' !== $license['license_key'] && 'Lite' !== $license['status'] ) {
			$payload['license_key_masked'] = self::mask_license_key( $license['license_key'] );
		}

		return $payload;
	}

	/**
	 * English lines for the support request. Role and license names are not translated.
	 *
	 * @param array{pro: bool, status: string, license_id: string, activation_id: string, customer_id: string, purchase_id: string, order_id: string, license_key: string} $license
	 * @return list<string>
	 */
	public static function license_lines( array $license ): array {
		$lines = array( 'License: ' . $license['status'] );
		$ids   = array(
			'License ID'    => $license['license_id'],
			'Purchase ID'   => $license['purchase_id'],
			'Order ID'      => $license['order_id'],
			'Customer ID'   => $license['customer_id'],
			'Activation ID' => $license['activation_id'],
		);
		$public = false;
		foreach ( $ids as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$public  = true;
			$lines[] = $label . ': ' . self::to_plain_text( $value );
		}

		if ( ! $public && '' !== $license['license_key'] && 'Lite' !== $license['status'] ) {
			$lines[] = 'License key: ' . self::mask_license_key( $license['license_key'] );
		}

		return $lines;
	}

	/**
	 * Logged-in user's locale, or the site locale for a guest.
	 */
	public static function sender_locale(): string {
		$locale = '';
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && function_exists( 'get_user_locale' ) ) {
			$locale = self::sanitize_locale( (string) get_user_locale() );
		}
		if ( '' === $locale && function_exists( 'get_locale' ) ) {
			$locale = self::sanitize_locale( (string) get_locale() );
		}

		return $locale;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function locale_from_context( array $context ): string {
		if ( isset( $context['locale'] ) && is_scalar( $context['locale'] ) ) {
			$locale = self::sanitize_locale( (string) $context['locale'] );
			if ( '' !== $locale ) {
				return $locale;
			}
		}

		return self::sender_locale();
	}

	public static function sanitize_locale( string $locale ): string {
		$locale = trim( $locale );
		if ( 1 !== preg_match( '/\A[A-Za-z]{2,3}(?:_[A-Za-z0-9]{2,8}){0,2}\z/', $locale ) ) {
			return '';
		}

		return $locale;
	}

	/**
	 * @param array{type: string, email: string, role: string, plugin_version: string, wp_version: string, php_version: string, site_url: string, description: string, locale?: string, name?: string, license?: array<string, mixed>} $fields
	 */
	public static function issue_body( array $fields ): string {
		$type_labels = array(
			'bug'   => 'Bug',
			'wish'  => 'Wish',
			'other' => 'Other',
		);
		$type  = $type_labels[ $fields['type'] ] ?? 'Other';
		$role  = '' !== $fields['role'] ? $fields['role'] : Choir_Rehearsal_Roles::LABEL_GUEST;
		$email = '' !== $fields['email'] ? $fields['email'] : '(not provided)';
		$name  = isset( $fields['name'] ) ? self::to_plain_text( (string) $fields['name'] ) : '';
		$license = isset( $fields['license'] ) && is_array( $fields['license'] )
			? self::normalize_license( $fields['license'] )
			: self::license_snapshot( false, array() );

		$lines = array(
			'Type: ' . $type,
			'Name: ' . ( '' !== $name ? $name : '(not provided)' ),
			'Contact: ' . $email,
			'Role: ' . $role,
			'Plugin version: ' . $fields['plugin_version'],
			'WordPress version: ' . $fields['wp_version'],
			'PHP version: ' . $fields['php_version'],
			'Site: ' . $fields['site_url'],
			'Locale: ' . ( isset( $fields['locale'] ) && is_string( $fields['locale'] ) ? self::sanitize_locale( $fields['locale'] ) : self::sender_locale() ),
		);
		foreach ( self::license_lines( $license ) as $line ) {
			$lines[] = $line;
		}
		$lines[] = '';
		$lines[] = 'Message:';
		$lines[] = $fields['description'];

		return implode( "\n", $lines );
	}

	/**
	 * @param array<int, mixed> $stamps
	 * @return list<int>
	 */
	public static function filter_recent( array $stamps, int $now, int $window ): array {
		$recent = array();
		foreach ( $stamps as $stamp ) {
			if ( is_int( $stamp ) ) {
				$value = $stamp;
			} elseif ( is_string( $stamp ) && ctype_digit( $stamp ) ) {
				$value = (int) $stamp;
			} else {
				continue;
			}
			if ( $value > 0 && $value <= $now && ( $now - $value ) < $window ) {
				$recent[] = $value;
			}
		}

		return $recent;
	}

	/**
	 * @param array<string, array<int, int>> $buckets
	 */
	public static function is_over_limit( array $buckets, int $limit ): bool {
		foreach ( $buckets as $stamps ) {
			if ( is_array( $stamps ) && count( $stamps ) >= $limit ) {
				return true;
			}
		}

		return false;
	}

	public static function plain_length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value );
		}

		return strlen( $value );
	}

	public static function looks_like_email( string $email ): bool {
		if ( strlen( $email ) < 6 || strlen( $email ) > 200 ) {
			return false;
		}

		return 1 === preg_match( '/\A[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\z/', $email );
	}

	public static function is_case( string $case ): bool {
		return 1 === preg_match( '/\A[A-Z][A-Z0-9]{1,10}-\d{1,8}\z/', $case );
	}

	/**
	 * Email is required for guests and for logged-in users. The confirmation
	 * message is sent to this address. Logged-in users see their profile email
	 * filled in and can change it.
	 *
	 * @param string $case  Case number such as WP-57.
	 * @param string $email Address that receives the confirmation.
	 */
	public static function success_message( string $case, string $email ): string {
		return sprintf(
			/* translators: 1: case number such as WP-57, 2: email address */
			__( 'Request received, case number %1$s. A confirmation has been sent to %2$s.', 'compath-choir-rehearsal' ),
			$case,
			$email
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $context
	 * @param callable             $load_bucket function( string $key ): array
	 * @param callable             $save_bucket function( string $key, array $stamps ): void
	 * @return array{ok: bool, status: int, message: string, case: string, code: string}
	 */
	public static function submit_feedback( array $input, array $context, callable $load_bucket, callable $save_bucket, ?callable $transport = null ): array {
		$company = self::to_plain_text( isset( $input['company'] ) && is_scalar( $input['company'] ) ? (string) $input['company'] : '' );
		if ( '' !== $company ) {
			return self::result(
				false,
				400,
				__( 'Please enter a type, title, and description.', 'compath-choir-rehearsal' ),
				'',
				'invalid'
			);
		}

		$type        = self::normalize_type( isset( $input['type'] ) && is_scalar( $input['type'] ) ? (string) $input['type'] : '' );
		$title       = self::to_plain_text( isset( $input['title'] ) && is_scalar( $input['title'] ) ? (string) $input['title'] : '' );
		$description = self::to_plain_text( isset( $input['description'] ) && is_scalar( $input['description'] ) ? (string) $input['description'] : '' );
		$name        = self::to_plain_text( isset( $input['name'] ) && is_scalar( $input['name'] ) ? (string) $input['name'] : '' );
		$email       = self::to_plain_text( isset( $input['email'] ) && is_scalar( $input['email'] ) ? (string) $input['email'] : '' );
		if ( ! self::looks_like_email( $email ) ) {
			$email = '';
		}

		if ( '' === $type || self::plain_length( $title ) < 3 || self::plain_length( $description ) < 3 ) {
			return self::result(
				false,
				400,
				__( 'Please enter a type, title, and description.', 'compath-choir-rehearsal' ),
				'',
				'invalid'
			);
		}
		if ( '' === $email ) {
			return self::result(
				false,
				400,
				__( 'Please enter a valid email address.', 'compath-choir-rehearsal' ),
				'',
				'email'
			);
		}

		$title       = self::limit_chars( preg_replace( '/\s+/u', ' ', $title ) ?? $title, 120 );
		$description = self::limit_chars( $description, 4000 );
		$name        = self::limit_chars( preg_replace( '/\s+/u', ' ', $name ) ?? $name, 120 );

		$now    = isset( $context['now'] ) ? (int) $context['now'] : time();
		$limit  = isset( $context['rate_limit'] ) ? (int) $context['rate_limit'] : self::RATE_LIMIT;
		$window = isset( $context['rate_window'] ) ? (int) $context['rate_window'] : self::RATE_WINDOW;
		$keys   = array();
		if ( isset( $context['rate_keys'] ) && is_array( $context['rate_keys'] ) ) {
			foreach ( $context['rate_keys'] as $key ) {
				if ( is_string( $key ) && '' !== $key ) {
					$keys[] = $key;
				}
			}
		}
		if ( array() === $keys ) {
			$keys[] = 'unknown';
		}

		$buckets = array();
		foreach ( $keys as $key ) {
			$stored          = $load_bucket( $key );
			$buckets[ $key ] = self::filter_recent( is_array( $stored ) ? $stored : array(), $now, $window );
		}
		if ( self::is_over_limit( $buckets, max( 1, $limit ) ) ) {
			return self::result(
				false,
				429,
				__( 'Please wait before sending more feedback.', 'compath-choir-rehearsal' ),
				'',
				'rate_limited'
			);
		}
		foreach ( $buckets as $key => $stamps ) {
			$stamps[] = $now;
			$save_bucket( $key, $stamps );
		}

		$role = self::to_plain_text( isset( $context['role'] ) && is_scalar( $context['role'] ) ? (string) $context['role'] : Choir_Rehearsal_Roles::LABEL_GUEST );
		if ( '' === $role ) {
			$role = Choir_Rehearsal_Roles::LABEL_GUEST;
		}

		$license = self::normalize_license(
			isset( $context['license'] ) && is_array( $context['license'] )
				? $context['license']
				: self::current_license()
		);
		$locale = self::locale_from_context( $context );
		$endpoint = isset( $context['endpoint'] ) && is_string( $context['endpoint'] ) && self::is_https_url( $context['endpoint'] )
			? $context['endpoint']
			: self::endpoint();

		$payload = array(
			'type'           => $type,
			'title'          => $title,
			'message'        => $description,
			'name'           => $name,
			'email'          => $email,
			'locale'         => $locale,
			'site_url'       => self::to_plain_text( isset( $context['site_url'] ) && is_scalar( $context['site_url'] ) ? (string) $context['site_url'] : '' ),
			'role'           => $role,
			'edition'        => ! empty( $license['pro'] ) ? 'Pro' : 'Lite',
			'plugin_version' => self::to_plain_text( isset( $context['plugin_version'] ) && is_scalar( $context['plugin_version'] ) ? (string) $context['plugin_version'] : '' ),
			'pro_version'    => self::to_plain_text( isset( $context['pro_version'] ) && is_scalar( $context['pro_version'] ) ? (string) $context['pro_version'] : '' ),
			'wp_version'     => self::to_plain_text( isset( $context['wp_version'] ) && is_scalar( $context['wp_version'] ) ? (string) $context['wp_version'] : '' ),
			'php_version'    => self::to_plain_text( isset( $context['php_version'] ) && is_scalar( $context['php_version'] ) ? (string) $context['php_version'] : PHP_VERSION ),
			'pro'            => ! empty( $license['pro'] ),
			'license'        => self::license_payload( $license ),
			'company'        => '',
		);

		$transport ??= array( self::class, 'relay_request' );
		$response = self::normalize_transport_result( $transport( $endpoint, $payload ) );
		$case = isset( $response['data']['case'] ) && is_string( $response['data']['case'] ) ? $response['data']['case'] : '';
		$accepted = ! empty( $response['data']['ok'] ) && self::is_case( $case ) && in_array( $response['code'], array( 200, 201 ), true );
		if ( ! $accepted ) {
			return self::result(
				false,
				502,
				__( 'Could not send feedback. Please try again later.', 'compath-choir-rehearsal' ),
				'',
				'relay'
			);
		}

		return self::result( true, 201, self::success_message( $case, $email ), $case, 'sent' );
	}

	public static function is_https_url( string $url ): bool {
		$url = trim( $url );
		if ( 1 !== preg_match( '#\Ahttps://[^\s]+\z#', $url ) ) {
			return false;
		}

		return false !== filter_var( $url, FILTER_VALIDATE_URL );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{code: int, data: array<string, mixed>}
	 */
	private static function relay_request( string $url, array $payload ): array {
		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) ) {
			return array(
				'code' => 0,
				'data' => array(),
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'User-Agent'   => 'Choir-Rehearsal/' . ( defined( 'CHOIR_REHEARSAL_VERSION' ) ? CHOIR_REHEARSAL_VERSION : 'dev' ),
				),
				'body'    => $encoded,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'code' => 0,
				'data' => array(),
			);
		}

		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'data' => $data,
		);
	}

	/**
	 * @param mixed $result
	 * @return array{code: int, data: array<string, mixed>}
	 */
	private static function normalize_transport_result( $result ): array {
		if ( ! is_array( $result ) ) {
			return array(
				'code' => 0,
				'data' => array(),
			);
		}

		$data = array();
		if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
			$data = $result['data'];
		}

		return array(
			'code' => isset( $result['code'] ) ? (int) $result['code'] : 0,
			'data' => $data,
		);
	}

	/**
	 * @return list<string>
	 */
	private static function rate_keys(): array {
		if ( is_user_logged_in() ) {
			return array( 'u' . (string) get_current_user_id() );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on the next line via sanitize_text_field.
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip          = sanitize_text_field( $remote_addr );
		if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return array( 'unknown' );
		}

		return array( 'ip' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 40 ) );
	}

	private static function transient_name( string $key ): string {
		return 'choir_rehearsal_fb_' . $key;
	}

	private static function current_role(): string {
		if ( ! is_user_logged_in() ) {
			return Choir_Rehearsal_Roles::LABEL_GUEST;
		}

		$label = Choir_Rehearsal_Access::get_role_badge_label();
		if ( '' !== $label ) {
			return $label;
		}

		$user = wp_get_current_user();
		if ( $user instanceof WP_User && is_array( $user->roles ) && isset( $user->roles[0] ) && is_string( $user->roles[0] ) ) {
			return $user->roles[0];
		}

		return Choir_Rehearsal_Roles::LABEL_GUEST;
	}

	private static function limit_chars( string $value, int $max ): string {
		if ( $max < 1 ) {
			return '';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $value, 0, $max );
		}

		return substr( $value, 0, $max );
	}

	/**
	 * @return array{ok: bool, status: int, message: string, case: string, code: string}
	 */
	private static function result( bool $ok, int $status, string $message, string $case, string $code ): array {
		return array(
			'ok'      => $ok,
			'status'  => $status,
			'message' => $message,
			'case'    => $case,
			'code'    => $code,
		);
	}
}
