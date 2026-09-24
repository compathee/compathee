<?php
/**
 * Choir Rehearsal feedback relay.
 *
 * Hosted at https://rehearsal.compath.ee/api/feedback.php
 * Secrets live in private/feedback-config.php, outside the web root.
 */

declare(strict_types=1);

final class Choir_Feedback_Relay {

	public const MAX_BODY = 32768;

	public const RATE_LIMIT = 40;

	public const RATE_WINDOW = 3600;

	public static function default_config_path(): string {
		$override = getenv( 'CHOIR_FEEDBACK_CONFIG' );
		if ( is_string( $override ) && '' !== $override ) {
			return $override;
		}

		return dirname( __DIR__, 2 ) . '/private/feedback-config.php';
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function load_config( ?string $path = null ): ?array {
		$path ??= self::default_config_path();
		if ( ! is_file( $path ) ) {
			return null;
		}

		$config = include $path;
		if ( ! is_array( $config ) ) {
			return null;
		}

		return $config;
	}

	public static function serve(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '';
		$raw    = (string) stream_get_contents( fopen( 'php://input', 'rb' ), self::MAX_BODY + 1 );
		$config = self::load_config();
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';

		if ( ! is_array( $config ) ) {
			self::emit( 503, array( 'ok' => false, 'error' => 'unavailable' ) );
			return;
		}

		$store = dirname( self::default_config_path() );
		$log   = $store . '/feedback-mail.log';
		$result = self::handle(
			$method,
			$raw,
			$config,
			$ip,
			time(),
			static function ( string $http_method, string $url, array $payload, string $user, string $token ): array {
				return self::http_json( $http_method, $url, $payload, $user, $token );
			},
			static function ( array $message ) use ( $config ): bool {
				return self::smtp_send( $config, $message );
			},
			static function ( string $line ) use ( $log ): void {
				self::append_log( $log, $line );
			},
			static function ( string $client_ip, int $now ) use ( $config, $store ): bool {
				$limit  = isset( $config['rate_limit'] ) ? (int) $config['rate_limit'] : self::RATE_LIMIT;
				$window = isset( $config['rate_window'] ) ? (int) $config['rate_window'] : self::RATE_WINDOW;
				return self::consume_rate( $store . '/feedback-rate', $client_ip, $now, $limit, $window );
			}
		);

		self::emit( $result['status'], $result['body'] );
	}

	/**
	 * @param array<string, mixed> $body
	 */
	public static function emit( int $status, array $body ): void {
		http_response_code( $status );
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		$json = json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		echo is_string( $json ) ? $json : '{"ok":false,"error":"unavailable"}';
	}

	/**
	 * @param array<string, mixed> $config
	 * @param callable             $jira    function( string $method, string $url, array $payload, string $user, string $token ): array{code:int, body:string}
	 * @param callable             $mail    function( array $message ): bool
	 * @param callable|null        $log     function( string $line ): void
	 * @param callable|null        $rate    function( string $ip, int $now ): bool  true when allowed
	 * @return array{status: int, body: array<string, mixed>}
	 */
	public static function handle( string $method, string $raw, array $config, string $ip, int $now, callable $jira, callable $mail, ?callable $log = null, ?callable $rate = null ): array {
		if ( 'POST' !== strtoupper( $method ) ) {
			return self::error( 405, 'invalid' );
		}
		if ( strlen( $raw ) > self::MAX_BODY ) {
			return self::error( 413, 'invalid' );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return self::error( 400, 'invalid' );
		}

		$honeypot = self::plain( $data['company'] ?? '' );
		if ( '' !== $honeypot ) {
			return self::error( 400, 'invalid' );
		}

		$input = self::normalize( $data );
		if ( isset( $input['error'] ) ) {
			return self::error( 400, 'invalid' );
		}

		if ( null !== $rate && ! $rate( $ip, $now ) ) {
			return self::error( 429, 'rate_limited' );
		}

		$created = self::create_task( $config, $input, $jira );
		if ( ! $created['ok'] ) {
			return self::error( 502, 'unavailable' );
		}

		$case = $created['key'];
		$sent = false;
		try {
			$sent = (bool) $mail( self::confirmation( $input, $case, $config ) );
		} catch ( Throwable $error ) {
			unset( $error );
			$sent = false;
		}
		if ( ! $sent && null !== $log ) {
			$log( 'confirmation email failed for ' . $case );
		}

		return array(
			'status' => 200,
			'body'   => array(
				'ok'   => true,
				'case' => $case,
			),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public static function normalize( array $data ): array {
		$message = self::limit( self::plain( $data['message'] ?? '' ), 8000 );
		$title   = self::limit( self::collapse( self::plain( $data['title'] ?? '' ) ), 120 );
		if ( self::length( $message ) < 3 && self::length( $title ) < 3 ) {
			return array( 'error' => 'message' );
		}
		if ( self::length( $message ) < 3 ) {
			$message = $title;
		}
		if ( '' === $title ) {
			$title = self::limit( self::collapse( $message ), 80 );
		}
		if ( self::length( $title ) < 3 ) {
			return array( 'error' => 'message' );
		}

		$email = self::plain( $data['email'] ?? '' );
		if ( ! self::is_email( $email ) ) {
			return array( 'error' => 'email' );
		}

		$type = strtolower( self::plain( $data['type'] ?? '' ) );
		if ( ! in_array( $type, array( 'bug', 'wish', 'other' ), true ) ) {
			$type = 'other';
		}

		$license = array();
		if ( isset( $data['license'] ) && is_array( $data['license'] ) ) {
			$license = $data['license'];
		}

		$status = self::limit( self::plain( $license['status'] ?? ( $data['license_status'] ?? '' ) ), 40 );
		$inactive = array( 'expired', 'revoked', 'invalid', 'inactive', 'canceled', 'cancelled', 'unlicensed', 'lite' );
		$pro = ! empty( $data['pro'] );
		if ( in_array( strtolower( $status ), $inactive, true ) || '' === $status ) {
			if ( in_array( strtolower( $status ), $inactive, true ) ) {
				$pro = false;
			}
		}
		if ( 'cancelled' === strtolower( $status ) ) {
			$status = 'canceled';
		}
		if ( '' === $status ) {
			$status = $pro ? 'active' : 'Lite';
		}

		$ids = array(
			'license_id'    => self::public_id( $license['license_id'] ?? '' ),
			'purchase_id'   => self::public_id( $license['purchase_id'] ?? '' ),
			'order_id'      => self::public_id( $license['order_id'] ?? '' ),
			'customer_id'   => self::public_id( $license['customer_id'] ?? '' ),
			'activation_id' => self::public_id( $license['activation_id'] ?? '' ),
		);
		$has_public = false;
		foreach ( $ids as $value ) {
			if ( '' !== $value ) {
				$has_public = true;
				break;
			}
		}

		$masked = '';
		$raw_key = self::plain( $license['license_key_masked'] ?? ( $license['license_key'] ?? '' ) );
		if ( ! $has_public && '' !== $raw_key && 'Lite' !== $status ) {
			$masked = self::mask_key( $raw_key );
		}

		$edition = $pro ? 'Pro' : 'Lite';
		$posted_edition = self::plain( $data['edition'] ?? '' );
		if ( ! $pro ) {
			$edition = 'Lite';
		} elseif ( 'Pro' === $posted_edition || 'Lite' === $posted_edition ) {
			$edition = 'Pro';
		}

		return array(
			'type'            => $type,
			'title'           => $title,
			'message'         => $message,
			'name'            => self::limit( self::collapse( self::plain( $data['name'] ?? '' ) ), 120 ),
			'email'           => $email,
			'locale'          => self::locale( $data['locale'] ?? '' ),
			'site_url'        => self::site_url( $data['site_url'] ?? '' ),
			'role'            => self::limit( self::plain( $data['role'] ?? '' ), 80 ),
			'edition'         => $edition,
			'plugin_version'  => self::limit( self::plain( $data['plugin_version'] ?? '' ), 40 ),
			'pro_version'     => self::limit( self::plain( $data['pro_version'] ?? '' ), 40 ),
			'wp_version'      => self::limit( self::plain( $data['wp_version'] ?? '' ), 40 ),
			'php_version'     => self::limit( self::plain( $data['php_version'] ?? '' ), 40 ),
			'pro'             => $pro,
			'license_status'  => $status,
			'license_id'      => $ids['license_id'],
			'purchase_id'     => $ids['purchase_id'],
			'order_id'        => $ids['order_id'],
			'customer_id'     => $ids['customer_id'],
			'activation_id'   => $ids['activation_id'],
			'license_key_masked' => $masked,
		);
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public static function summary( array $input ): string {
		$title  = self::limit( self::collapse( (string) ( $input['title'] ?? '' ) ), 120 );
		$prefix = ! empty( $input['pro'] ) ? '[CR][Pro] ' : '[CR] ';

		return self::limit( $prefix . $title, 255 );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return list<string>
	 */
	public static function labels( array $input ): array {
		$labels = array( 'choir-rehearsal', 'feedback' );
		if ( ! empty( $input['pro'] ) ) {
			$labels[] = 'pro';
		}

		return $labels;
	}

	/**
	 * English task text. Role names are copied as sent.
	 *
	 * @param array<string, mixed> $input
	 */
	public static function description( array $input ): string {
		$types = array(
			'bug'   => 'Bug',
			'wish'  => 'Wish',
			'other' => 'Other',
		);
		$type = $types[ (string) ( $input['type'] ?? '' ) ] ?? 'Other';
		$name = (string) ( $input['name'] ?? '' );
		$role = (string) ( $input['role'] ?? '' );
		$lines = array(
			'Type: ' . $type,
			'Name: ' . ( '' !== $name ? $name : '(not provided)' ),
			'Contact: ' . (string) ( $input['email'] ?? '' ),
			'Role: ' . ( '' !== $role ? $role : 'Guest' ),
			'Edition: ' . (string) ( $input['edition'] ?? 'Lite' ),
			'Plugin version: ' . (string) ( $input['plugin_version'] ?? '' ),
		);
		if ( '' !== (string) ( $input['pro_version'] ?? '' ) ) {
			$lines[] = 'Pro add-on version: ' . (string) $input['pro_version'];
		}
		$lines[] = 'WordPress version: ' . (string) ( $input['wp_version'] ?? '' );
		$lines[] = 'PHP version: ' . (string) ( $input['php_version'] ?? '' );
		$lines[] = 'Site: ' . (string) ( $input['site_url'] ?? '' );
		$lines[] = 'Locale: ' . (string) ( $input['locale'] ?? '' );
		$lines[] = 'License: ' . (string) ( $input['license_status'] ?? '' );
		foreach (
			array(
				'License ID'    => 'license_id',
				'Purchase ID'   => 'purchase_id',
				'Order ID'      => 'order_id',
				'Customer ID'   => 'customer_id',
				'Activation ID' => 'activation_id',
			) as $label => $key
		) {
			$value = (string) ( $input[ $key ] ?? '' );
			if ( '' !== $value ) {
				$lines[] = $label . ': ' . $value;
			}
		}
		$masked = (string) ( $input['license_key_masked'] ?? '' );
		if ( '' !== $masked ) {
			$lines[] = 'License key: ' . $masked;
		}
		$lines[] = '';
		$lines[] = 'Message:';
		$lines[] = (string) ( $input['message'] ?? '' );

		return implode( "\n", $lines );
	}

	/**
	 * @param array<string, mixed> $config
	 * @param array<string, mixed> $input
	 * @param callable             $jira
	 * @return array{ok: bool, key: string}
	 */
	public static function create_task( array $config, array $input, callable $jira ): array {
		$base = rtrim( self::plain( $config['jira_base'] ?? '' ), '/' );
		$user = self::plain( $config['jira_email'] ?? '' );
		$token = (string) ( $config['jira_token'] ?? '' );
		$project = self::plain( $config['jira_project'] ?? '' );
		$type = self::plain( $config['jira_issue_type'] ?? '' );
		if ( '' === $base || ! self::is_email( $user ) || '' === trim( $token ) || '' === $project || '' === $type ) {
			return array( 'ok' => false, 'key' => '' );
		}
		if ( 1 !== preg_match( '#\Ahttps://[A-Za-z0-9.-]+\z#', $base ) ) {
			return array( 'ok' => false, 'key' => '' );
		}

		$url = $base . '/rest/api/3/issue';
		$fields = self::jira_fields( $config, $input, true );
		$first = self::jira_call( $jira, $url, $fields, $user, $token );
		$key = self::issue_key( $first );
		if ( '' !== $key ) {
			return array( 'ok' => true, 'key' => $key );
		}
		if ( ! self::label_rejected( $first ) ) {
			return array( 'ok' => false, 'key' => '' );
		}

		$second = self::jira_call( $jira, $url, self::jira_fields( $config, $input, false ), $user, $token );
		$key = self::issue_key( $second );
		if ( '' === $key ) {
			return array( 'ok' => false, 'key' => '' );
		}

		return array( 'ok' => true, 'key' => $key );
	}

	/**
	 * @param array<string, mixed> $config
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public static function jira_fields( array $config, array $input, bool $with_labels ): array {
		$fields = array(
			'project'   => array( 'key' => self::plain( $config['jira_project'] ?? 'DBT' ) ),
			'issuetype' => array( 'name' => self::plain( $config['jira_issue_type'] ?? 'Task' ) ),
			'summary'   => self::summary( $input ),
			'description' => self::adf( self::description( $input ) ),
		);
		if ( $with_labels ) {
			$fields['labels'] = self::labels( $input );
		}

		return array( 'fields' => $fields );
	}

	/**
	 * @return array{type: string, version: int, content: list<array<string, mixed>>}
	 */
	public static function adf( string $text ): array {
		$lines = preg_split( "/\r\n|\n|\r/", $text );
		if ( ! is_array( $lines ) || array() === $lines ) {
			$lines = array( '(empty)' );
		}
		$content = array();
		foreach ( $lines as $index => $line ) {
			if ( $index > 0 ) {
				$content[] = array( 'type' => 'hardBreak' );
			}
			if ( '' === $line ) {
				continue;
			}
			$content[] = array(
				'type' => 'text',
				'text' => $line,
			);
		}
		if ( array() === $content ) {
			$content[] = array(
				'type' => 'text',
				'text' => '(empty)',
			);
		}

		return array(
			'type'    => 'doc',
			'version' => 1,
			'content' => array(
				array(
					'type'    => 'paragraph',
					'content' => $content,
				),
			),
		);
	}

	/**
	 * @param callable             $jira
	 * @param array<string, mixed> $fields
	 * @return array{code: int, body: string}
	 */
	private static function jira_call( callable $jira, string $url, array $fields, string $user, string $token ): array {
		$result = $jira( 'POST', $url, $fields, $user, $token );
		if ( ! is_array( $result ) ) {
			return array( 'code' => 0, 'body' => '' );
		}

		return array(
			'code' => isset( $result['code'] ) ? (int) $result['code'] : 0,
			'body' => isset( $result['body'] ) && is_string( $result['body'] ) ? $result['body'] : '',
		);
	}

	/**
	 * @param array{code: int, body: string} $response
	 */
	public static function issue_key( array $response ): string {
		if ( ! in_array( $response['code'], array( 200, 201 ), true ) ) {
			return '';
		}
		$decoded = json_decode( $response['body'], true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['key'] ) || ! is_string( $decoded['key'] ) ) {
			return '';
		}
		if ( 1 !== preg_match( '/\A[A-Z][A-Z0-9]{1,10}-\d{1,8}\z/', $decoded['key'] ) ) {
			return '';
		}

		return $decoded['key'];
	}

	/**
	 * @param array{code: int, body: string} $response
	 */
	private static function label_rejected( array $response ): bool {
		if ( 400 !== $response['code'] ) {
			return false;
		}

		return false !== stripos( $response['body'], 'label' );
	}

	public static function mail_language( string $locale ): string {
		$locale = strtolower( str_replace( '-', '_', trim( $locale ) ) );
		if ( 'et' === $locale || str_starts_with( $locale, 'et_' ) ) {
			return 'et';
		}
		if ( str_starts_with( $locale, 'ru' ) ) {
			return 'ru';
		}

		return 'en';
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $config
	 * @return array{to: string, subject: string, body: string, from_email: string, from_name: string, reply_to: string}
	 */
	public static function confirmation( array $input, string $case, array $config ): array {
		$language = self::mail_language( (string) ( $input['locale'] ?? '' ) );
		$message = (string) ( $input['message'] ?? '' );
		$copy = array(
			'en' => array(
				'subject' => 'Choir Rehearsal request ' . $case,
				'body'    => "Hello,\n\nWe received your request. Case number: {$case}.\n\nYour message:\n{$message}\n\nWe will write back when a fix ships.\n\nCompath Support\n",
			),
			'et' => array(
				'subject' => 'Choir Rehearsal päring ' . $case,
				'body'    => "Tere,\n\nSaime teie päringu kätte. Juhtumi number: {$case}.\n\nTeie sõnum:\n{$message}\n\nKirjutame teile, kui parandus on valmis.\n\nCompath Support\n",
			),
			'ru' => array(
				'subject' => 'Запрос Choir Rehearsal ' . $case,
				'body'    => "Здравствуйте,\n\nМы получили ваш запрос. Номер обращения: {$case}.\n\nВаш текст:\n{$message}\n\nМы напишем вам, когда исправление будет готово.\n\nCompath Support\n",
			),
		);
		$text = $copy[ $language ] ?? $copy['en'];

		return array(
			'to'         => (string) ( $input['email'] ?? '' ),
			'subject'    => $text['subject'],
			'body'       => $text['body'],
			'from_email' => self::plain( $config['from_email'] ?? 'support@compath.ee' ),
			'from_name'  => self::plain( $config['from_name'] ?? 'Compath Support' ),
			'reply_to'   => self::plain( $config['reply_to'] ?? 'support@compath.ee' ),
		);
	}

	/**
	 * @param array<string, mixed> $config
	 * @param array{to: string, subject: string, body: string, from_email: string, from_name: string, reply_to: string} $message
	 */
	public static function smtp_send( array $config, array $message ): bool {
		$host = self::plain( $config['smtp_host'] ?? '' );
		$port = isset( $config['smtp_port'] ) ? (int) $config['smtp_port'] : 465;
		if ( '' === $host || $port < 1 ) {
			return false;
		}

		$errno  = 0;
		$errstr = '';
		$socket = @stream_socket_client( 'ssl://' . $host . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT );
		if ( ! is_resource( $socket ) ) {
			return false;
		}
		stream_set_timeout( $socket, 20 );

		$ok = self::smtp_converse(
			static function ( string $send ) use ( $socket ): string {
				if ( '' !== $send ) {
					fwrite( $socket, $send );
				}

				return self::smtp_read( $socket );
			},
			$config,
			$message
		);
		fclose( $socket );

		return $ok;
	}

	/**
	 * @param callable             $exchange function( string $send ): string
	 * @param array<string, mixed> $config
	 * @param array{to: string, subject: string, body: string, from_email: string, from_name: string, reply_to: string} $message
	 */
	public static function smtp_converse( callable $exchange, array $config, array $message ): bool {
		$user = self::plain( $config['smtp_user'] ?? '' );
		$pass = (string) ( $config['smtp_pass'] ?? '' );
		$from = (string) ( $message['from_email'] ?? '' );
		$to   = (string) ( $message['to'] ?? '' );
		if ( ! self::is_email( $user ) || '' === $pass || ! self::is_email( $from ) || ! self::is_email( $to ) ) {
			return false;
		}
		if ( ! self::smtp_code( $exchange( '' ), array( 220 ) ) ) {
			return false;
		}
		if ( ! self::smtp_code( $exchange( "EHLO compath.ee\r\n" ), array( 250 ) ) ) {
			return false;
		}
		if ( ! self::smtp_code( $exchange( "AUTH LOGIN\r\n" ), array( 334 ) ) ) {
			return false;
		}
		if ( ! self::smtp_code( $exchange( base64_encode( $user ) . "\r\n" ), array( 334 ) ) ) {
			return false;
		}
		if ( ! self::smtp_code( $exchange( base64_encode( $pass ) . "\r\n" ), array( 235 ) ) ) {
			return false;
		}
		if ( ! self::smtp_code( $exchange( 'MAIL FROM:<' . $from . ">\r\n" ), array( 250 ) ) ) {
			return false;
		}
		if ( ! self::smtp_code( $exchange( 'RCPT TO:<' . $to . ">\r\n" ), array( 250, 251 ) ) ) {
			return false;
		}
		if ( ! self::smtp_code( $exchange( "DATA\r\n" ), array( 354 ) ) ) {
			return false;
		}
		if ( ! self::smtp_code( $exchange( self::smtp_data( $message ) ), array( 250 ) ) ) {
			return false;
		}
		$exchange( "QUIT\r\n" );

		return true;
	}

	/**
	 * @param array{to: string, subject: string, body: string, from_email: string, from_name: string, reply_to: string} $message
	 */
	public static function smtp_data( array $message ): string {
		$from_name = self::encode_header( (string) $message['from_name'] );
		$subject   = self::encode_header( (string) $message['subject'] );
		$headers   = array(
			'Date: ' . gmdate( 'D, d M Y H:i:s O' ),
			'From: ' . $from_name . ' <' . $message['from_email'] . '>',
			'To: <' . $message['to'] . '>',
			'Reply-To: <' . $message['reply_to'] . '>',
			'Subject: ' . $subject,
			'MIME-Version: 1.0',
			'Content-Type: text/plain; charset=UTF-8',
			'Content-Transfer-Encoding: 8bit',
			'',
		);
		$body = str_replace( array( "\r\n", "\r" ), "\n", (string) $message['body'] );
		$body = str_replace( "\n", "\r\n", $body );
		$body = preg_replace( '/^\./m', '..', $body ) ?? $body;

		return implode( "\r\n", $headers ) . $body . "\r\n.\r\n";
	}

	/**
	 * @param resource $socket
	 */
	private static function smtp_read( $socket ): string {
		$lines = '';
		while ( ! feof( $socket ) ) {
			$line = fgets( $socket, 1024 );
			if ( ! is_string( $line ) ) {
				break;
			}
			$lines .= $line;
			if ( 1 === preg_match( '/\A\d{3} /', $line ) ) {
				break;
			}
		}

		return $lines;
	}

	/**
	 * @param list<int> $codes
	 */
	private static function smtp_code( string $reply, array $codes ): bool {
		if ( 1 !== preg_match( '/\A(\d{3})/', $reply, $matches ) ) {
			return false;
		}

		return in_array( (int) $matches[1], $codes, true );
	}

	public static function consume_rate( string $dir, string $ip, int $now, int $limit, int $window ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = 'unknown';
		}
		if ( $limit < 1 ) {
			$limit = self::RATE_LIMIT;
		}
		if ( $window < 1 ) {
			$window = self::RATE_WINDOW;
		}
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) {
			self::append_log( dirname( $dir ) . '/feedback-mail.log', 'rate store unavailable' );
			return true;
		}

		$path = $dir . '/' . hash( 'sha256', $ip ) . '.json';
		$handle = fopen( $path, 'c+' );
		if ( ! is_resource( $handle ) ) {
			self::append_log( dirname( $dir ) . '/feedback-mail.log', 'rate store unavailable' );
			return true;
		}
		flock( $handle, LOCK_EX );
		$raw = stream_get_contents( $handle );
		$stamps = json_decode( is_string( $raw ) ? $raw : '', true );
		if ( ! is_array( $stamps ) ) {
			$stamps = array();
		}
		$recent = array();
		foreach ( $stamps as $stamp ) {
			if ( ! is_int( $stamp ) && ! ( is_string( $stamp ) && ctype_digit( $stamp ) ) ) {
				continue;
			}
			$value = (int) $stamp;
			if ( $value > 0 && $value <= $now && ( $now - $value ) < $window ) {
				$recent[] = $value;
			}
		}
		if ( count( $recent ) >= $limit ) {
			flock( $handle, LOCK_UN );
			fclose( $handle );
			return false;
		}
		$recent[] = $now;
		rewind( $handle );
		ftruncate( $handle, 0 );
		fwrite( $handle, (string) json_encode( $recent ) );
		fflush( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle );

		return true;
	}

	public static function mask_key( string $key ): string {
		$key = trim( $key );
		$stars = substr_count( $key, '*' );
		if ( $stars >= 4 && ! preg_match( '/[A-Za-z0-9]{5,}/', str_replace( '*', '', $key ) . 'x' ) ) {
			return $key;
		}
		$bare = str_replace( '*', '', $key );
		if ( $stars >= 4 && strlen( $bare ) <= 8 ) {
			return $key;
		}
		$len = strlen( $key );
		if ( $len < 8 ) {
			return str_repeat( '*', max( 4, $len ) );
		}

		return substr( $key, 0, 4 ) . str_repeat( '*', $len - 8 ) . substr( $key, -4 );
	}

	public static function append_log( string $path, string $line ): void {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$line = str_replace( array( "\r", "\n" ), ' ', $line );
		@file_put_contents( $path, gmdate( 'c' ) . ' ' . $line . "\n", FILE_APPEND | LOCK_EX );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{code: int, body: string}
	 */
	public static function http_json( string $method, string $url, array $payload, string $user, string $token ): array {
		$json = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			return array( 'code' => 0, 'body' => '' );
		}
		$auth = base64_encode( $user . ':' . $token );
		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init( $url );
			if ( false === $ch ) {
				return array( 'code' => 0, 'body' => '' );
			}
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_CUSTOMREQUEST  => $method,
					CURLOPT_POSTFIELDS     => $json,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT        => 20,
					CURLOPT_HTTPHEADER     => array(
						'Authorization: Basic ' . $auth,
						'Accept: application/json',
						'Content-Type: application/json',
					),
				)
			);
			$body = curl_exec( $ch );
			$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
			curl_close( $ch );

			return array(
				'code' => $code,
				'body' => is_string( $body ) ? $body : '',
			);
		}

		$context = stream_context_create(
			array(
				'http' => array(
					'method'  => $method,
					'header'  => "Authorization: Basic {$auth}\r\nAccept: application/json\r\nContent-Type: application/json\r\n",
					'content' => $json,
					'timeout' => 20,
				),
			)
		);
		$body = @file_get_contents( $url, false, $context );
		$code = 0;
		if ( isset( $http_response_header ) && is_array( $http_response_header ) && isset( $http_response_header[0] ) ) {
			if ( 1 === preg_match( '/\s(\d{3})\s/', (string) $http_response_header[0], $matches ) ) {
				$code = (int) $matches[1];
			}
		}

		return array(
			'code' => $code,
			'body' => is_string( $body ) ? $body : '',
		);
	}

	/**
	 * @return array{status: int, body: array<string, mixed>}
	 */
	private static function error( int $status, string $error ): array {
		return array(
			'status' => $status,
			'body'   => array(
				'ok'    => false,
				'error' => $error,
			),
		);
	}

	private static function plain( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = (string) $value;
		$text = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $text ) ?? $text;
		$text = strip_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = strip_tags( $text );
		$text = str_replace( array( '<', '>' ), '', $text );
		$text = preg_replace( "/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/", '', $text ) ?? $text;

		return trim( $text );
	}

	private static function collapse( string $value ): string {
		$value = preg_replace( '/\s+/u', ' ', $value ) ?? $value;

		return trim( $value );
	}

	private static function limit( string $value, int $max ): string {
		if ( $max < 1 ) {
			return '';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $value, 0, $max );
		}

		return substr( $value, 0, $max );
	}

	private static function length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value );
		}

		return strlen( $value );
	}

	public static function is_email( string $email ): bool {
		if ( strlen( $email ) < 6 || strlen( $email ) > 200 ) {
			return false;
		}
		if ( str_contains( $email, "\n" ) || str_contains( $email, "\r" ) ) {
			return false;
		}

		return 1 === preg_match( '/\A[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\z/', $email );
	}

	private static function locale( mixed $value ): string {
		$locale = self::plain( $value );
		if ( 1 !== preg_match( '/\A[A-Za-z]{2,3}(?:_[A-Za-z0-9]{2,8}){0,2}\z/', $locale ) ) {
			return '';
		}

		return $locale;
	}

	private static function site_url( mixed $value ): string {
		$url = self::limit( self::plain( $value ), 300 );
		if ( 1 !== preg_match( '#\Ahttps?://[^\s]+#', $url ) ) {
			return '';
		}

		return $url;
	}

	private static function public_id( mixed $value ): string {
		$id = self::plain( $value );
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_-]{4,80}\z/', $id ) ) {
			return '';
		}
		if ( self::is_email( $id ) ) {
			return '';
		}

		return $id;
	}

	private static function encode_header( string $value ): string {
		if ( 1 === preg_match( '/\A[\x20-\x7E]*\z/', $value ) ) {
			return $value;
		}

		return '=?UTF-8?B?' . base64_encode( $value ) . '?=';
	}
}

if ( ! defined( 'CHOIR_FEEDBACK_RELAY_LIBRARY' ) ) {
	Choir_Feedback_Relay::serve();
}
