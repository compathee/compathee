<?php
/**
 * In-plugin feedback that opens a GitHub issue.
 *
 * The personal access token is read only on the server. It is never printed
 * on the rehearsal page or passed to front-end scripts.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Feedback {

	public const OPTION_TOKEN = 'choir_rehearsal_feedback_github_token';

	public const OPTION_REPO = 'choir_rehearsal_feedback_github_repo';

	public const DEFAULT_REPO = 'compathee/compathee';

	public const RATE_LIMIT = 5;

	public const RATE_WINDOW = 3600;

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_settings(): void {
		register_setting(
			'choir_rehearsal_settings',
			self::OPTION_REPO,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_repo' ),
				'show_in_rest'      => false,
				'default'           => self::DEFAULT_REPO,
			)
		);

		register_setting(
			'choir_rehearsal_settings',
			self::OPTION_TOKEN,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize_token' ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);
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
					'email'       => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => static fn( $value ): string => sanitize_email( is_scalar( $value ) ? (string) $value : '' ),
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
				'email'       => $email,
			),
			array(
				'token'          => (string) get_option( self::OPTION_TOKEN, '' ),
				'repo'           => (string) get_option( self::OPTION_REPO, self::DEFAULT_REPO ),
				'role'           => self::current_role(),
				'plugin_version' => defined( 'CHOIR_REHEARSAL_VERSION' ) ? (string) CHOIR_REHEARSAL_VERSION : '',
				'wp_version'     => (string) get_bloginfo( 'version' ),
				'php_version'    => PHP_VERSION,
				'site_url'       => (string) home_url( '/' ),
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
				'number'  => $result['number'],
				'url'     => $result['url'],
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
					'viewIssue'    => __( 'View issue', 'compath-choir-rehearsal' ),
					'genericError' => __( 'Could not send feedback. Please try again later.', 'compath-choir-rehearsal' ),
				),
			)
		);
	}

	public static function render_panel(): void {
		$email = '';
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user instanceof WP_User ) {
				$email = sanitize_email( (string) $user->user_email );
				if ( ! is_email( $email ) ) {
					$email = '';
				}
			}
		}
		?>
		<details class="choir-feedback">
			<summary class="choir-feedback__toggle"><?php esc_html_e( 'Send feedback', 'compath-choir-rehearsal' ); ?></summary>
			<form id="choir-feedback-form" class="choir-feedback__form" method="post" action="<?php echo esc_url( rest_url( 'choir-rehearsal/v1/feedback' ) ); ?>">
				<?php wp_nonce_field( 'wp_rest' ); ?>
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
					<input type="text" id="choir-feedback-title" name="title" required maxlength="120" />
				</p>
				<p class="choir-feedback__field">
					<label for="choir-feedback-description"><?php esc_html_e( 'Description', 'compath-choir-rehearsal' ); ?></label>
					<textarea id="choir-feedback-description" name="description" required maxlength="4000" rows="5"></textarea>
				</p>
				<p class="choir-feedback__field">
					<label for="choir-feedback-email"><?php esc_html_e( 'Contact email (optional)', 'compath-choir-rehearsal' ); ?></label>
					<input type="email" id="choir-feedback-email" name="email" maxlength="200" autocomplete="email" value="<?php echo esc_attr( $email ); ?>" />
				</p>
				<p class="choir-feedback__status" role="status" aria-live="polite" hidden></p>
				<p class="choir-feedback__submit">
					<button type="submit" class="choir-feedback__button"><?php esc_html_e( 'Send', 'compath-choir-rehearsal' ); ?></button>
				</p>
			</form>
		</details>
		<?php
	}

	public static function render_settings_rows(): void {
		$repo    = (string) get_option( self::OPTION_REPO, self::DEFAULT_REPO );
		$parsed  = self::parse_repo( $repo );
		$repo    = null === $parsed ? self::DEFAULT_REPO : $parsed['owner'] . '/' . $parsed['repo'];
		$has_token = self::is_token_shape( (string) get_option( self::OPTION_TOKEN, '' ) );
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Feedback', 'compath-choir-rehearsal' ); ?></th>
			<td>
				<p class="description" style="margin-top:0;">
					<?php esc_html_e( 'Singer, Voice Leader, Administrator, and guests can send a wish or bug from the song list. The site creates a GitHub issue. Lite and Pro both include this.', 'compath-choir-rehearsal' ); ?>
				</p>
				<p>
					<label for="<?php echo esc_attr( self::OPTION_REPO ); ?>"><?php esc_html_e( 'GitHub repository for feedback', 'compath-choir-rehearsal' ); ?></label><br />
					<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_REPO ); ?>" id="<?php echo esc_attr( self::OPTION_REPO ); ?>" value="<?php echo esc_attr( $repo ); ?>" />
				</p>
				<p class="description"><?php esc_html_e( 'Issues are created in this repository. Use owner/name, for example compathee/compathee.', 'compath-choir-rehearsal' ); ?></p>
				<p>
					<label for="<?php echo esc_attr( self::OPTION_TOKEN ); ?>"><?php esc_html_e( 'GitHub token', 'compath-choir-rehearsal' ); ?></label><br />
					<input type="password" class="regular-text" name="<?php echo esc_attr( self::OPTION_TOKEN ); ?>" id="<?php echo esc_attr( self::OPTION_TOKEN ); ?>" value="" autocomplete="off" spellcheck="false" />
				</p>
				<?php if ( $has_token ) : ?>
					<p class="description"><?php esc_html_e( 'A token is saved.', 'compath-choir-rehearsal' ); ?></p>
					<p>
						<label>
							<input type="checkbox" name="choir_rehearsal_feedback_clear_token" value="1" />
							<?php esc_html_e( 'Remove saved token', 'compath-choir-rehearsal' ); ?>
						</label>
					</p>
				<?php endif; ?>
				<p class="description">
					<?php esc_html_e( 'Create a fine-grained personal access token at GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens. Grant only this repository Issues: Read and write. Paste the token here. It stays on the server and is not shown on the rehearsal page. Leave blank to keep the saved token.', 'compath-choir-rehearsal' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * @param mixed $value Posted repository.
	 */
	public static function sanitize_repo( $value ): string {
		return self::sanitize_repo_value( is_string( $value ) ? $value : '' );
	}

	/**
	 * @param mixed $value Posted token. Empty keeps the saved token.
	 */
	public static function sanitize_token( $value ): string {
		$existing = (string) get_option( self::OPTION_TOKEN, '' );
		$posted   = is_string( $value ) ? $value : '';
		// options.php verifies the settings nonce before sanitizing registered options.
		$clear_raw = '';
		if ( isset( $_POST['choir_rehearsal_feedback_clear_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$clear_raw = sanitize_text_field( wp_unslash( (string) $_POST['choir_rehearsal_feedback_clear_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		$posted_trim = trim( $posted );
		if ( '' !== $posted_trim && ! self::is_token_shape( $posted_trim ) ) {
			add_settings_error(
				self::OPTION_TOKEN,
				'choir_feedback_token_invalid',
				__( 'That GitHub token does not look valid. The saved token was left unchanged.', 'compath-choir-rehearsal' )
			);
		}

		return self::resolve_token( $posted, $existing, '1' === $clear_raw );
	}

	public static function sanitize_repo_value( string $value ): string {
		$parsed = self::parse_repo( $value );
		if ( null === $parsed ) {
			return self::DEFAULT_REPO;
		}

		return $parsed['owner'] . '/' . $parsed['repo'];
	}

	/**
	 * @return array{owner: string, repo: string}|null
	 */
	public static function parse_repo( string $value ): ?array {
		$value = trim( $value );
		if ( 1 !== preg_match( '/\A([A-Za-z0-9_.-]+)\/([A-Za-z0-9_.-]+)\z/', $value, $matches ) ) {
			return null;
		}

		return array(
			'owner' => $matches[1],
			'repo'  => $matches[2],
		);
	}

	public static function resolve_token( string $posted, string $existing, bool $clear ): string {
		$posted = trim( $posted );
		if ( '' !== $posted ) {
			return self::is_token_shape( $posted ) ? $posted : $existing;
		}
		if ( $clear ) {
			return '';
		}

		return $existing;
	}

	public static function is_token_shape( string $token ): bool {
		$length = strlen( $token );
		if ( $length < 20 || $length > 255 ) {
			return false;
		}

		return 1 === preg_match( '/\A[A-Za-z0-9_]+\z/', $token );
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
	 * @return list<string>
	 */
	public static function labels_for_type( string $type ): array {
		$labels = array( 'feedback' );
		if ( 'bug' === $type ) {
			$labels[] = 'bug';
		} elseif ( 'wish' === $type ) {
			$labels[] = 'enhancement';
		}

		return $labels;
	}

	public static function issue_title( string $title ): string {
		$title = self::to_plain_text( $title );
		$title = preg_replace( '/\s+/u', ' ', $title ) ?? $title;
		$title = self::limit_chars( trim( $title ), 120 );

		return '[Choir Rehearsal] ' . $title;
	}

	/**
	 * @param array{type: string, email: string, role: string, plugin_version: string, wp_version: string, php_version: string, site_url: string, description: string} $fields
	 */
	public static function issue_body( array $fields ): string {
		$type_labels = array(
			'bug'   => 'Bug',
			'wish'  => 'Wish',
			'other' => 'Other',
		);
		$type = $type_labels[ $fields['type'] ] ?? 'Other';
		$role = '' !== $fields['role'] ? $fields['role'] : 'guest';
		$email = '' !== $fields['email'] ? $fields['email'] : '(not provided)';

		$lines = array(
			'Type: ' . $type,
			'Contact: ' . $email,
			'Role: ' . $role,
			'Plugin version: ' . $fields['plugin_version'],
			'WordPress version: ' . $fields['wp_version'],
			'PHP version: ' . $fields['php_version'],
			'Site: ' . $fields['site_url'],
			'',
			'Message:',
			$fields['description'],
		);

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

	public static function safe_issue_url( string $url, string $owner, string $repo ): string {
		$url = trim( $url );
		if ( 1 !== preg_match( '#\Ahttps://github\.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)/issues/(\d+)/?\z#', $url, $matches ) ) {
			return '';
		}
		if ( $matches[1] !== $owner || $matches[2] !== $repo ) {
			return '';
		}

		return 'https://github.com/' . $owner . '/' . $repo . '/issues/' . $matches[3];
	}

	public static function success_message( int $number ): string {
		if ( $number > 0 ) {
			return sprintf(
				/* translators: %d: GitHub issue number */
				__( 'Thank you. Feedback sent as issue #%d.', 'compath-choir-rehearsal' ),
				$number
			);
		}

		return __( 'Thank you. Feedback sent.', 'compath-choir-rehearsal' );
	}

	/**
	 * @param array<string, mixed>        $input
	 * @param array<string, mixed>        $context
	 * @param callable                    $load_bucket function( string $key ): array
	 * @param callable                    $save_bucket function( string $key, array $stamps ): void
	 * @return array{ok: bool, status: int, message: string, number: int, url: string, code: string}
	 */
	public static function submit_feedback( array $input, array $context, callable $load_bucket, callable $save_bucket, ?callable $transport = null ): array {
		$type        = self::normalize_type( isset( $input['type'] ) && is_scalar( $input['type'] ) ? (string) $input['type'] : '' );
		$title       = self::to_plain_text( isset( $input['title'] ) && is_scalar( $input['title'] ) ? (string) $input['title'] : '' );
		$description = self::to_plain_text( isset( $input['description'] ) && is_scalar( $input['description'] ) ? (string) $input['description'] : '' );
		$email       = self::to_plain_text( isset( $input['email'] ) && is_scalar( $input['email'] ) ? (string) $input['email'] : '' );
		if ( ! self::looks_like_email( $email ) ) {
			$email = '';
		}

		if ( '' === $type || self::plain_length( $title ) < 3 || self::plain_length( $description ) < 3 ) {
			return self::result(
				false,
				400,
				__( 'Please enter a type, title, and description.', 'compath-choir-rehearsal' ),
				0,
				'',
				'invalid'
			);
		}

		$title       = self::limit_chars( preg_replace( '/\s+/u', ' ', $title ) ?? $title, 120 );
		$description = self::limit_chars( $description, 4000 );
		$token       = isset( $context['token'] ) && is_string( $context['token'] ) ? $context['token'] : '';
		if ( ! self::is_token_shape( $token ) ) {
			return self::result(
				false,
				503,
				__( 'Feedback is not configured.', 'compath-choir-rehearsal' ),
				0,
				'',
				'not_configured'
			);
		}

		$repo_raw = isset( $context['repo'] ) && is_string( $context['repo'] ) ? $context['repo'] : '';
		$repo     = self::parse_repo( $repo_raw );
		if ( null === $repo ) {
			$repo = self::parse_repo( self::DEFAULT_REPO );
		}
		if ( null === $repo ) {
			return self::result(
				false,
				500,
				__( 'Could not send feedback. Please try again later.', 'compath-choir-rehearsal' ),
				0,
				'',
				'repo'
			);
		}

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
			$stored         = $load_bucket( $key );
			$buckets[ $key ] = self::filter_recent( is_array( $stored ) ? $stored : array(), $now, $window );
		}
		if ( self::is_over_limit( $buckets, max( 1, $limit ) ) ) {
			return self::result(
				false,
				429,
				__( 'Please wait before sending more feedback.', 'compath-choir-rehearsal' ),
				0,
				'',
				'rate_limited'
			);
		}
		foreach ( $buckets as $key => $stamps ) {
			$stamps[] = $now;
			$save_bucket( $key, $stamps );
		}

		$role = self::to_plain_text( isset( $context['role'] ) && is_scalar( $context['role'] ) ? (string) $context['role'] : 'guest' );
		if ( '' === $role ) {
			$role = 'guest';
		}

		$body = self::issue_body(
			array(
				'type'           => $type,
				'email'          => $email,
				'role'           => $role,
				'plugin_version' => self::to_plain_text( isset( $context['plugin_version'] ) && is_scalar( $context['plugin_version'] ) ? (string) $context['plugin_version'] : '' ),
				'wp_version'     => self::to_plain_text( isset( $context['wp_version'] ) && is_scalar( $context['wp_version'] ) ? (string) $context['wp_version'] : '' ),
				'php_version'    => self::to_plain_text( isset( $context['php_version'] ) && is_scalar( $context['php_version'] ) ? (string) $context['php_version'] : PHP_VERSION ),
				'site_url'       => self::to_plain_text( isset( $context['site_url'] ) && is_scalar( $context['site_url'] ) ? (string) $context['site_url'] : '' ),
				'description'    => $description,
			)
		);

		$created = self::create_issue(
			$repo['owner'],
			$repo['repo'],
			self::issue_title( $title ),
			$body,
			self::labels_for_type( $type ),
			$token,
			$transport
		);

		if ( ! $created['ok'] ) {
			return self::result( false, 502, $created['message'], 0, '', 'github' );
		}

		return self::result( true, 201, self::success_message( $created['number'] ), $created['number'], $created['url'], 'sent' );
	}

	/**
	 * @param list<string> $labels
	 * @return array{ok: bool, number: int, url: string, message: string}
	 */
	public static function create_issue( string $owner, string $repo, string $title, string $body, array $labels, string $token, ?callable $transport = null ): array {
		$fail = static function ( string $message ): array {
			return array(
				'ok'      => false,
				'number'  => 0,
				'url'     => '',
				'message' => $message,
			);
		};

		if ( ! self::is_token_shape( $token ) || null === self::parse_repo( $owner . '/' . $repo ) ) {
			return $fail( __( 'Feedback is not configured.', 'compath-choir-rehearsal' ) );
		}

		$transport ??= array( self::class, 'github_request' );
		$labels      = array_values(
			array_filter(
				$labels,
				static fn( $label ): bool => is_string( $label ) && '' !== $label
			)
		);

		$issue_url = 'https://api.github.com/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/issues';
		$with_labels = self::normalize_transport_result(
			$transport(
				'POST',
				$issue_url,
				self::issue_payload( $title, $body, $labels ),
				$token
			)
		);

		if ( self::is_created_issue( $with_labels ) ) {
			return self::created_issue_result( $with_labels, $owner, $repo );
		}

		if ( self::is_auth_or_missing_repo( $with_labels ) || ! self::is_label_problem( $with_labels ) ) {
			return $fail( self::failure_message( $with_labels ) );
		}

		$labels_ready = true;
		$label_url    = 'https://api.github.com/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo ) . '/labels';
		foreach ( $labels as $label ) {
			$created_label = self::normalize_transport_result(
				$transport(
					'POST',
					$label_url,
					array(
						'name'        => $label,
						'color'       => self::label_color( $label ),
						'description' => 'Choir Rehearsal feedback',
					),
					$token
				)
			);
			if ( ! self::label_write_ok( $created_label ) ) {
				$labels_ready = false;
				break;
			}
		}

		if ( $labels_ready && array() !== $labels ) {
			$retry = self::normalize_transport_result(
				$transport(
					'POST',
					$issue_url,
					self::issue_payload( $title, $body, $labels ),
					$token
				)
			);
			if ( self::is_created_issue( $retry ) ) {
				return self::created_issue_result( $retry, $owner, $repo );
			}
		}

		$plain = self::normalize_transport_result(
			$transport(
				'POST',
				$issue_url,
				self::issue_payload( $title, $body, array() ),
				$token
			)
		);
		if ( self::is_created_issue( $plain ) ) {
			return self::created_issue_result( $plain, $owner, $repo );
		}

		return $fail( self::failure_message( $plain ) );
	}

	/**
	 * @param list<string> $labels
	 * @return array{title: string, body: string, labels?: list<string>}
	 */
	private static function issue_payload( string $title, string $body, array $labels ): array {
		$payload = array(
			'title' => $title,
			'body'  => $body,
		);
		if ( array() !== $labels ) {
			$payload['labels'] = $labels;
		}

		return $payload;
	}

	/**
	 * @param array{code: int, message: string, data: array<string, mixed>} $response
	 */
	private static function is_created_issue( array $response ): bool {
		if ( ! in_array( $response['code'], array( 200, 201 ), true ) ) {
			return false;
		}

		return self::positive_int( $response['data']['number'] ?? 0 ) > 0;
	}

	/**
	 * @param array{code: int, message: string, data: array<string, mixed>} $response
	 * @return array{ok: bool, number: int, url: string, message: string}
	 */
	private static function created_issue_result( array $response, string $owner, string $repo ): array {
		$number = self::positive_int( $response['data']['number'] ?? 0 );
		$url    = '';
		if ( isset( $response['data']['html_url'] ) && is_string( $response['data']['html_url'] ) ) {
			$url = self::safe_issue_url( $response['data']['html_url'], $owner, $repo );
		}

		return array(
			'ok'      => true,
			'number'  => $number,
			'url'     => $url,
			'message' => self::success_message( $number ),
		);
	}

	/**
	 * @param array{code: int, message: string, data: array<string, mixed>} $response
	 */
	private static function is_label_problem( array $response ): bool {
		return 422 === $response['code'] && false !== stripos( $response['message'], 'label' );
	}

	/**
	 * @param array{code: int, message: string, data: array<string, mixed>} $response
	 */
	private static function is_auth_or_missing_repo( array $response ): bool {
		return in_array( $response['code'], array( 401, 403, 404 ), true );
	}

	/**
	 * @param array{code: int, message: string, data: array<string, mixed>} $response
	 */
	private static function label_write_ok( array $response ): bool {
		if ( in_array( $response['code'], array( 200, 201 ), true ) ) {
			return true;
		}

		return 422 === $response['code'] && false !== stripos( $response['message'], 'already_exists' );
	}

	private static function label_color( string $name ): string {
		return match ( $name ) {
			'bug' => 'd73a4a',
			'enhancement' => 'a2eeef',
			default => '1f4fd8',
		};
	}

	/**
	 * @param array{code: int, message: string, data: array<string, mixed>} $response
	 */
	private static function failure_message( array $response ): string {
		if ( in_array( $response['code'], array( 401, 403 ), true ) ) {
			return __( 'Could not send feedback. Please ask an administrator to check the GitHub token.', 'compath-choir-rehearsal' );
		}
		if ( 404 === $response['code'] ) {
			return __( 'Could not send feedback. Please ask an administrator to check the GitHub repository.', 'compath-choir-rehearsal' );
		}

		return __( 'Could not send feedback. Please try again later.', 'compath-choir-rehearsal' );
	}

	/**
	 * @param mixed $result
	 * @return array{code: int, message: string, data: array<string, mixed>}
	 */
	private static function normalize_transport_result( $result ): array {
		if ( ! is_array( $result ) ) {
			return array(
				'code'    => 0,
				'message' => '',
				'data'    => array(),
			);
		}

		$data = array();
		if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
			$data = $result['data'];
		}

		return array(
			'code'    => isset( $result['code'] ) ? (int) $result['code'] : 0,
			'message' => isset( $result['message'] ) && is_string( $result['message'] ) ? $result['message'] : '',
			'data'    => $data,
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{code: int, message: string, data: array<string, mixed>}
	 */
	private static function github_request( string $method, string $url, array $payload, string $token ): array {
		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) ) {
			return array(
				'code'    => 0,
				'message' => '',
				'data'    => array(),
			);
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'timeout' => 15,
				'headers' => array(
					'Authorization'        => 'Bearer ' . $token,
					'Accept'               => 'application/vnd.github+json',
					'Content-Type'         => 'application/json',
					'User-Agent'           => 'Choir-Rehearsal/' . ( defined( 'CHOIR_REHEARSAL_VERSION' ) ? CHOIR_REHEARSAL_VERSION : 'dev' ),
					'X-GitHub-Api-Version' => '2022-11-28',
				),
				'body'    => $encoded,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'code'    => 0,
				'message' => '',
				'data'    => array(),
			);
		}

		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$message = '';
		if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			$message = $data['message'];
		}
		if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
			foreach ( $data['errors'] as $error ) {
				if ( ! is_array( $error ) ) {
					continue;
				}
				if ( isset( $error['message'] ) && is_string( $error['message'] ) ) {
					$message .= ' ' . $error['message'];
				}
				if ( isset( $error['code'] ) && is_string( $error['code'] ) ) {
					$message .= ' ' . $error['code'];
				}
				if ( isset( $error['field'] ) && is_string( $error['field'] ) ) {
					$message .= ' ' . $error['field'];
				}
			}
		}

		return array(
			'code'    => (int) wp_remote_retrieve_response_code( $response ),
			'message' => $message,
			'data'    => $data,
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
			return 'guest';
		}

		$label = Choir_Rehearsal_Access::get_role_badge_label();
		if ( '' !== $label ) {
			return $label;
		}

		$user = wp_get_current_user();
		if ( $user instanceof WP_User && is_array( $user->roles ) && isset( $user->roles[0] ) && is_string( $user->roles[0] ) ) {
			return $user->roles[0];
		}

		return 'guest';
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

	private static function positive_int( mixed $value ): int {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : 0;
		}
		if ( is_string( $value ) && ctype_digit( $value ) ) {
			return (int) $value;
		}

		return 0;
	}

	/**
	 * @return array{ok: bool, status: int, message: string, number: int, url: string, code: string}
	 */
	private static function result( bool $ok, int $status, string $message, int $number, string $url, string $code ): array {
		return array(
			'ok'      => $ok,
			'status'  => $status,
			'message' => $message,
			'number'  => $number,
			'url'     => $url,
			'code'    => $code,
		);
	}
}
