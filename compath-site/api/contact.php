<?php
/**
 * Contact form for compath.ee.
 *
 * Sends one email to the address in config.php. It does not write outside
 * this directory. Secrets stay in config.php, which is not in git.
 *
 * Define COMPATH_CONTACT_LIBRARY to load the class without handling a request.
 */

declare(strict_types=1);

final class Compath_Contact {

	const MAX_BODY = 20000;

	const RATE_LIMIT = 8;

	const RATE_WINDOW = 3600;

	/**
	 * @return array<string, string>
	 */
	public static function destinations() {
		return array(
			'en' => '/contact/thank-you/',
			'et' => '/et/kontakt/tanu/',
			'ru' => '/ru/kontakty/spasibo/',
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function errors() {
		return array(
			'en' => '/contact/error/',
			'et' => '/et/kontakt/viga/',
			'ru' => '/ru/kontakty/oshibka/',
		);
	}

	public static function config_path() {
		$override = getenv( 'COMPATH_CONTACT_CONFIG' );
		if ( is_string( $override ) && '' !== $override ) {
			return $override;
		}

		return __DIR__ . '/config.php';
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function load_config( $path = null ) {
		$path = null === $path ? self::config_path() : $path;
		if ( ! is_string( $path ) || ! is_file( $path ) ) {
			return null;
		}
		$config = include $path;
		if ( ! is_array( $config ) ) {
			return null;
		}

		return $config;
	}

	public static function serve() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '';
		$length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		if ( $length > self::MAX_BODY ) {
			self::respond( 413, 'invalid', 'en', self::wants_json() );
			return;
		}

		$config = self::load_config();
		if ( ! is_array( $config ) ) {
			self::respond( 503, 'unavailable', 'en', self::wants_json() );
			return;
		}

		$raw = (string) file_get_contents( 'php://input' );
		if ( strlen( $raw ) > self::MAX_BODY ) {
			self::respond( 413, 'invalid', 'en', self::wants_json() );
			return;
		}

		$data = $_POST;
		$type = isset( $_SERVER['CONTENT_TYPE'] ) ? (string) $_SERVER['CONTENT_TYPE'] : '';
		if ( false !== stripos( $type, 'application/json' ) ) {
			$decoded = json_decode( $raw, true );
			$data    = is_array( $decoded ) ? $decoded : array();
		}

		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
		$store  = __DIR__;
		$result = self::handle(
			$method,
			$data,
			$config,
			$ip,
			time(),
			$origin,
			static function ( $message ) use ( $config ) {
				return self::smtp_send( $config, $message );
			},
			static function ( $client_ip, $now ) use ( $config, $store ) {
				$limit  = isset( $config['rate_limit'] ) ? (int) $config['rate_limit'] : self::RATE_LIMIT;
				$window = isset( $config['rate_window'] ) ? (int) $config['rate_window'] : self::RATE_WINDOW;
				return self::consume_rate( $store . '/contact-rate', $client_ip, $now, $limit, $window );
			}
		);

		self::respond( $result['status'], $result['error'], $result['locale'], self::wants_json(), $result['ok'] );
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $config
	 * @param callable             $mail function( array $message ): bool
	 * @param callable             $rate function( string $ip, int $now ): bool
	 * @return array{status:int, ok:bool, error:string, locale:string}
	 */
	public static function handle( $method, $data, $config, $ip, $now, $origin, $mail, $rate ) {
		$locale = self::locale_of( $data );
		if ( 'POST' !== strtoupper( (string) $method ) ) {
			return self::fail( 405, 'invalid', $locale );
		}
		if ( ! self::origin_ok( $origin ) ) {
			return self::fail( 403, 'invalid', $locale );
		}
		if ( ! is_array( $config ) || ! self::is_email( self::plain( isset( $config['to_email'] ) ? $config['to_email'] : '' ) ) ) {
			return self::fail( 503, 'unavailable', $locale );
		}
		if ( ! $rate( $ip, (int) $now ) ) {
			return self::fail( 429, 'rate_limited', $locale );
		}

		$honeypot = self::plain( isset( $data['company'] ) ? $data['company'] : '' );
		if ( '' !== $honeypot ) {
			return array(
				'status' => 200,
				'ok'     => true,
				'error'  => '',
				'locale' => $locale,
			);
		}

		$input = self::normalize( $data );
		if ( isset( $input['error'] ) ) {
			return self::fail( 400, 'invalid', $locale );
		}

		$sent = false;
		try {
			$sent = (bool) $mail( self::message( $config, $input ) );
		} catch ( Exception $error ) {
			$sent = false;
		}
		if ( ! $sent ) {
			self::append_log( __DIR__ . '/contact-mail.log', 'send failed' );
			return self::fail( 502, 'unavailable', $input['locale'] );
		}

		return array(
			'status' => 200,
			'ok'     => true,
			'error'  => '',
			'locale' => $input['locale'],
		);
	}

	/**
	 * @param array<string, mixed> $config
	 * @param array<string, string> $input
	 * @return array{to:string, subject:string, body:string, from_email:string, from_name:string, reply_to:string}
	 */
	public static function message( $config, $input ) {
		$body = "Name: {$input['name']}\nEmail: {$input['email']}\nPhone: {$input['phone']}\nLanguage: {$input['locale']}\n\n{$input['message']}\n";

		return array(
			'to'         => self::plain( $config['to_email'] ),
			'subject'    => 'Compath.ee enquiry',
			'body'       => $body,
			'from_email' => self::plain( isset( $config['from_email'] ) ? $config['from_email'] : '' ),
			'from_name'  => self::plain( isset( $config['from_name'] ) ? $config['from_name'] : 'Compath' ),
			'reply_to'   => $input['email'],
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	public static function normalize( $data ) {
		$name = self::limit( self::collapse( self::plain( isset( $data['name'] ) ? $data['name'] : '' ) ), 120 );
		$email = self::plain( isset( $data['email'] ) ? $data['email'] : '' );
		$phone = self::limit( self::collapse( self::plain( isset( $data['phone'] ) ? $data['phone'] : '' ) ), 40 );
		$message = self::limit( self::plain( isset( $data['message'] ) ? $data['message'] : '' ), 5000 );
		if ( self::length( $name ) < 2 || ! self::is_email( $email ) || self::length( $message ) < 10 ) {
			return array( 'error' => 'invalid' );
		}
		if ( '' !== $phone && 1 !== preg_match( '/\A[0-9+\s().-]{1,40}\z/', $phone ) ) {
			return array( 'error' => 'invalid' );
		}

		return array(
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'message' => $message,
			'locale'  => self::locale_of( $data ),
		);
	}

	/**
	 * @param array<string, mixed> $config
	 * @param array{to:string, subject:string, body:string, from_email:string, from_name:string, reply_to:string} $message
	 */
	public static function smtp_send( $config, $message ) {
		$host = self::plain( isset( $config['smtp_host'] ) ? $config['smtp_host'] : '' );
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
			static function ( $send ) use ( $socket ) {
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
	 * @param callable $exchange function( string $send ): string
	 * @param array<string, mixed> $config
	 * @param array{to:string, subject:string, body:string, from_email:string, from_name:string, reply_to:string} $message
	 */
	public static function smtp_converse( $exchange, $config, $message ) {
		$user = self::plain( isset( $config['smtp_user'] ) ? $config['smtp_user'] : '' );
		$pass = (string) ( isset( $config['smtp_pass'] ) ? $config['smtp_pass'] : '' );
		$from = (string) $message['from_email'];
		$to   = (string) $message['to'];
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
	 * @param array{to:string, subject:string, body:string, from_email:string, from_name:string, reply_to:string} $message
	 */
	public static function smtp_data( $message ) {
		$headers = array(
			'Date: ' . gmdate( 'D, d M Y H:i:s O' ),
			'From: ' . self::encode_header( (string) $message['from_name'] ) . ' <' . $message['from_email'] . '>',
			'To: <' . $message['to'] . '>',
			'Reply-To: <' . $message['reply_to'] . '>',
			'Subject: ' . self::encode_header( (string) $message['subject'] ),
			'MIME-Version: 1.0',
			'Content-Type: text/plain; charset=UTF-8',
			'Content-Transfer-Encoding: 8bit',
			'',
		);
		$body = str_replace( array( "\r\n", "\r" ), "\n", (string) $message['body'] );
		$body = str_replace( "\n", "\r\n", $body );
		$body = preg_replace( '/^\./m', '..', $body );
		if ( ! is_string( $body ) ) {
			$body = '';
		}

		return implode( "\r\n", $headers ) . $body . "\r\n.\r\n";
	}

	/**
	 * @param resource $socket
	 */
	private static function smtp_read( $socket ) {
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
	 * @param array<int> $codes
	 */
	public static function smtp_code( $reply, $codes ) {
		if ( 1 !== preg_match( '/\A(\d{3})/', (string) $reply, $matches ) ) {
			return false;
		}

		return in_array( (int) $matches[1], $codes, true );
	}

	public static function consume_rate( $dir, $ip, $now, $limit, $window ) {
		$root = self::real_dir( __DIR__ );
		if ( false === $root || ! is_dir( dirname( $dir ) ) ) {
			return false;
		}
		$parent = self::real_dir( dirname( $dir ) );
		if ( false === $parent || 0 !== strpos( $parent, $root ) ) {
			return false;
		}
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0700 ) && ! is_dir( $dir ) ) {
			return false;
		}
		$real = self::real_dir( $dir );
		if ( false === $real || 0 !== strpos( $real, $root ) ) {
			return false;
		}
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = 'unknown';
		}
		if ( $limit < 1 ) {
			$limit = self::RATE_LIMIT;
		}
		if ( $window < 1 ) {
			$window = self::RATE_WINDOW;
		}
		$path   = $real . '/' . hash( 'sha256', $ip ) . '.json';
		$handle = fopen( $path, 'c+' );
		if ( ! is_resource( $handle ) ) {
			return false;
		}
		flock( $handle, LOCK_EX );
		$raw    = stream_get_contents( $handle );
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

	public static function is_email( $email ) {
		if ( ! is_string( $email ) || strlen( $email ) < 6 || strlen( $email ) > 200 ) {
			return false;
		}
		if ( false !== strpos( $email, "\n" ) || false !== strpos( $email, "\r" ) ) {
			return false;
		}

		return 1 === preg_match( '/\A[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\z/', $email );
	}

	/**
	 * @param array<string, mixed> $body
	 */
	private static function respond( $status, $error, $locale, $json, $ok = false ) {
		if ( $json ) {
			header( 'Content-Type: application/json; charset=UTF-8' );
			header( 'X-Content-Type-Options: nosniff' );
			http_response_code( $status );
			echo (string) json_encode(
				array(
					'ok'    => (bool) $ok,
					'error' => $ok ? '' : $error,
				)
			);
			return;
		}
		$map  = $ok ? self::destinations() : self::errors();
		$path = isset( $map[ $locale ] ) ? $map[ $locale ] : $map['en'];
		header( 'Location: ' . $path, true, 303 );
	}

	private static function wants_json() {
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) $_SERVER['HTTP_ACCEPT'] : '';

		return false !== stripos( $accept, 'application/json' );
	}

	/**
	 * @return array{status:int, ok:bool, error:string, locale:string}
	 */
	private static function fail( $status, $error, $locale ) {
		return array(
			'status' => $status,
			'ok'     => false,
			'error'  => $error,
			'locale' => $locale,
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function locale_of( $data ) {
		$locale = self::plain( isset( $data['locale'] ) ? $data['locale'] : '' );
		if ( ! in_array( $locale, array( 'en', 'et', 'ru' ), true ) ) {
			return 'en';
		}

		return $locale;
	}

	private static function origin_ok( $origin ) {
		if ( '' === $origin ) {
			return true;
		}
		$parts = parse_url( $origin );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}
		$host = strtolower( (string) $parts['host'] );

		return in_array( $host, array( 'compath.ee', 'www.compath.ee', 'localhost', '127.0.0.1' ), true );
	}

	/**
	 * @param mixed $value
	 */
	private static function plain( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = (string) $value;
		$text = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $text );
		if ( ! is_string( $text ) ) {
			$text = '';
		}
		$text = strip_tags( $text );
		$text = str_replace( array( '<', '>' ), '', $text );
		$text = preg_replace( "/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/", '', $text );
		if ( ! is_string( $text ) ) {
			return '';
		}

		return trim( $text );
	}

	private static function collapse( $value ) {
		$value = preg_replace( '/\s+/u', ' ', $value );
		if ( ! is_string( $value ) ) {
			return '';
		}

		return trim( $value );
	}

	private static function limit( $value, $max ) {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $value, 0, $max );
		}

		return substr( $value, 0, $max );
	}

	private static function length( $value ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value );
		}

		return strlen( $value );
	}

	private static function encode_header( $value ) {
		if ( 1 === preg_match( '/\A[\x20-\x7E]*\z/', $value ) ) {
			return $value;
		}

		return '=?UTF-8?B?' . base64_encode( $value ) . '?=';
	}

	private static function append_log( $path, $line ) {
		$root = self::real_dir( __DIR__ );
		$dir  = self::real_dir( dirname( $path ) );
		if ( false === $root || false === $dir || 0 !== strpos( $dir, $root ) ) {
			return;
		}
		$line = str_replace( array( "\r", "\n" ), ' ', $line );
		@file_put_contents( $path, gmdate( 'c' ) . ' ' . $line . "\n", FILE_APPEND | LOCK_EX );
	}

	/**
	 * @return string|false
	 */
	private static function real_dir( $path ) {
		if ( is_dir( $path ) ) {
			return realpath( $path );
		}

		return false;
	}

}

if ( ! defined( 'COMPATH_CONTACT_LIBRARY' ) ) {
	Compath_Contact::serve();
}
