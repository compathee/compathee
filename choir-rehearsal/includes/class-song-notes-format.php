<?php
/**
 * Pure helpers for song notes: text, @ mentions, time marks, and permission flags.
 *
 * No database or WordPress APIs, so the rules can be tested without a site.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Song_Notes_Format {

	public const MAX_LENGTH = 5000;

	public static function sanitize_body( string $body ): string {
		$body = html_entity_decode( $body, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$body = strip_tags( $body );
		$body = str_replace( array( "\r\n", "\r" ), "\n", $body );
		$body = preg_replace( "/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/", '', $body );
		if ( ! is_string( $body ) ) {
			return '';
		}
		$body = trim( $body );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $body ) > self::MAX_LENGTH ) {
			$body = mb_substr( $body, 0, self::MAX_LENGTH );
		} elseif ( strlen( $body ) > self::MAX_LENGTH ) {
			$body = substr( $body, 0, self::MAX_LENGTH );
		}

		return $body;
	}

	/**
	 * @param list<array{id:int|string,label:string}> $tracks
	 */
	public static function resolve_track_id( string $body, array $tracks ): int {
		$body   = self::sanitize_body( $body );
		$tracks = self::tracks_by_label_length( $tracks );
		$length = strlen( $body );
		for ( $i = 0; $i < $length; $i++ ) {
			if ( '@' !== $body[ $i ] || ! self::boundary_before( $body, $i ) ) {
				continue;
			}
			$mention = self::mention_at( $body, $i, $tracks );
			if ( null !== $mention ) {
				return $mention['id'];
			}
		}

		return 0;
	}

	/**
	 * @param list<array{id:int|string,label:string}> $tracks
	 * @return list<array{type:string,text:string,track_id?:int,label?:string,seconds?:int}>
	 */
	public static function segments( string $body, array $tracks, int $stored_track_id = 0 ): array {
		$body     = self::sanitize_body( $body );
		$tracks   = self::tracks_by_label_length( $tracks );
		$resolved = self::resolve_track_id( $body, $tracks );
		$track_id = $resolved > 0 ? $resolved : max( 0, $stored_track_id );
		$length   = strlen( $body );
		$out      = array();
		$buffer   = '';

		for ( $i = 0; $i < $length; ) {
			if ( '@' === $body[ $i ] && self::boundary_before( $body, $i ) ) {
				$mention = self::mention_at( $body, $i, $tracks );
				if ( null !== $mention ) {
					$out      = self::flush_text( $out, $buffer );
					$buffer   = '';
					$token    = '@' . $mention['label'];
					$out[]    = array(
						'type'     => 'mention',
						'text'     => $token,
						'track_id' => $mention['id'],
						'label'    => $mention['label'],
					);
					$i       += strlen( $token );
					continue;
				}
			}

			if ( $track_id > 0 && self::boundary_before( $body, $i ) ) {
				$clock = self::clock_at( $body, $i );
				if ( null !== $clock ) {
					$out    = self::flush_text( $out, $buffer );
					$buffer = '';
					$out[]  = array(
						'type'    => 'time',
						'text'    => $clock['label'],
						'label'   => $clock['label'],
						'seconds' => $clock['seconds'],
					);
					$i     += strlen( $clock['label'] );
					continue;
				}
			}

			$buffer .= $body[ $i ];
			++$i;
		}

		return self::flush_text( $out, $buffer );
	}

	public static function format_clock( int $seconds ): string {
		if ( $seconds < 0 ) {
			$seconds = 0;
		}
		if ( $seconds > 359999 ) {
			$seconds = 359999;
		}
		$hours = intdiv( $seconds, 3600 );
		$mins  = intdiv( $seconds % 3600, 60 );
		$secs  = $seconds % 60;
		if ( $hours > 0 ) {
			return $hours . ':' . str_pad( (string) $mins, 2, '0', STR_PAD_LEFT ) . ':' . str_pad( (string) $secs, 2, '0', STR_PAD_LEFT );
		}

		return $mins . ':' . str_pad( (string) $secs, 2, '0', STR_PAD_LEFT );
	}

	public static function parse_clock( string $token ): ?int {
		$clock = self::clock_at( $token, 0 );
		if ( null === $clock || strlen( $clock['label'] ) !== strlen( $token ) ) {
			return null;
		}

		return $clock['seconds'];
	}

	public static function user_can_read( bool $feature_enabled, bool $can_view_song ): bool {
		return $feature_enabled && $can_view_song;
	}

	public static function user_can_write( bool $feature_enabled, bool $logged_in, bool $can_manage ): bool {
		return $feature_enabled && $logged_in && $can_manage;
	}

	/**
	 * BCP-47 tag for the Web Speech API from a WordPress locale.
	 */
	public static function speech_lang( string $locale ): string {
		$locale = strtolower( str_replace( '_', '-', trim( $locale ) ) );
		if ( '' === $locale ) {
			return 'en-US';
		}
		if ( str_starts_with( $locale, 'et' ) ) {
			return 'et-EE';
		}
		if ( str_starts_with( $locale, 'ru' ) ) {
			return 'ru-RU';
		}
		if ( str_starts_with( $locale, 'en' ) ) {
			return 'en-US';
		}

		return $locale;
	}

	/**
	 * @param list<array{id:int|string,label:string}> $tracks
	 * @return list<array{id:int,label:string}>
	 */
	private static function tracks_by_label_length( array $tracks ): array {
		$clean = array();
		foreach ( $tracks as $track ) {
			if ( ! is_array( $track ) ) {
				continue;
			}
			$label = isset( $track['label'] ) ? trim( (string) $track['label'] ) : '';
			$id    = isset( $track['id'] ) ? (int) $track['id'] : 0;
			if ( '' === $label || $id <= 0 || str_contains( $label, '@' ) ) {
				continue;
			}
			$clean[] = array(
				'id'    => $id,
				'label' => $label,
			);
		}

		usort(
			$clean,
			static function ( array $a, array $b ): int {
				return strlen( $b['label'] ) <=> strlen( $a['label'] );
			}
		);

		return $clean;
	}

	/**
	 * @param list<array{id:int,label:string}> $tracks
	 * @return array{id:int,label:string}|null
	 */
	private static function mention_at( string $body, int $offset, array $tracks ): ?array {
		foreach ( $tracks as $track ) {
			$token = '@' . $track['label'];
			$size  = strlen( $token );
			if ( substr( $body, $offset, $size ) !== $token ) {
				continue;
			}
			$after = $offset + $size;
			if ( $after < strlen( $body ) && ! self::is_space_byte( $body[ $after ] ) ) {
				continue;
			}

			return $track;
		}

		return null;
	}

	/**
	 * @return array{seconds:int,label:string}|null
	 */
	private static function clock_at( string $body, int $offset ): ?array {
		$rest = substr( $body, $offset );
		if ( ! preg_match( '/^(\d{1,3}):([0-5]\d)(?::([0-5]\d))?/', $rest, $matches ) ) {
			return null;
		}
		$label = $matches[0];
		$end   = $offset + strlen( $label );
		if ( $end < strlen( $body ) && ctype_digit( $body[ $end ] ) ) {
			return null;
		}

		if ( isset( $matches[3] ) && '' !== $matches[3] ) {
			$seconds = ( (int) $matches[1] * 3600 ) + ( (int) $matches[2] * 60 ) + (int) $matches[3];
		} else {
			$seconds = ( (int) $matches[1] * 60 ) + (int) $matches[2];
		}
		if ( $seconds > 359999 ) {
			return null;
		}

		return array(
			'seconds' => $seconds,
			'label'   => $label,
		);
	}

	private static function boundary_before( string $body, int $offset ): bool {
		if ( $offset <= 0 ) {
			return true;
		}

		return self::is_space_byte( $body[ $offset - 1 ] );
	}

	private static function is_space_byte( string $byte ): bool {
		return '' !== $byte && ctype_space( $byte );
	}

	/**
	 * @param list<array<string, mixed>> $out
	 * @return list<array<string, mixed>>
	 */
	private static function flush_text( array $out, string $buffer ): array {
		if ( '' !== $buffer ) {
			$out[] = array(
				'type' => 'text',
				'text' => $buffer,
			);
		}

		return $out;
	}
}
