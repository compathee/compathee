<?php
/**
 * Latin permalink slugs for songs (any source language).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Slugs {

	public static function register(): void {
		add_filter( 'wp_insert_post_data', array( self::class, 'filter_insert_post_data' ), 20, 2 );
		add_action( 'save_post_' . Choir_Rehearsal_Post_Types::SONG, array( self::class, 'ensure_latin_slug_after_save' ), 5, 2 );
	}

	/**
	 * Force Latin post_name for choir songs on create/update.
	 *
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $postarr
	 * @return array<string, mixed>
	 */
	public static function filter_insert_post_data( array $data, array $postarr ): array {
		$post_type = (string) ( $data['post_type'] ?? '' );
		if ( Choir_Rehearsal_Post_Types::SONG !== $post_type ) {
			return $data;
		}

		$status = (string) ( $data['post_status'] ?? '' );
		if ( in_array( $status, array( 'auto-draft', 'inherit' ), true ) ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$title   = (string) ( $data['post_title'] ?? '' );
		$slug    = (string) ( $data['post_name'] ?? '' );

		// Prefer an explicitly submitted slug (permalink editor), then existing, then title.
		if ( isset( $postarr['post_name'] ) && is_string( $postarr['post_name'] ) && '' !== trim( $postarr['post_name'] ) ) {
			$slug = $postarr['post_name'];
		}

		$slug = self::latin_slug_from_title_or_slug( $title, $slug );

		if ( '' === $slug ) {
			$slug = 'song';
		}

		$data['post_name'] = self::unique_slug( $slug, $post_id );

		return $data;
	}

	/**
	 * Safety net if core still stored a non-Latin or hex-dump slug.
	 */
	public static function ensure_latin_slug_after_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( Choir_Rehearsal_Post_Types::SONG !== $post->post_type ) {
			return;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'inherit', 'trash' ), true ) ) {
			return;
		}

		if ( self::is_usable_latin_slug( (string) $post->post_name ) ) {
			return;
		}

		$slug = self::unique_slug(
			self::latin_slug_from_title_or_slug( (string) $post->post_title, (string) $post->post_name ) ?: 'song',
			$post_id
		);

		remove_action( 'save_post_' . Choir_Rehearsal_Post_Types::SONG, array( self::class, 'ensure_latin_slug_after_save' ), 5 );
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $slug,
			)
		);
		add_action( 'save_post_' . Choir_Rehearsal_Post_Types::SONG, array( self::class, 'ensure_latin_slug_after_save' ), 5, 2 );
	}

	/**
	 * One-time repair of existing non-Latin / hex-dump song slugs.
	 */
	public static function migrate_existing_song_slugs(): int {
		$songs = get_posts(
			array(
				'post_type'      => Choir_Rehearsal_Post_Types::SONG,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$updated = 0;
		foreach ( $songs as $song ) {
			if ( ! $song instanceof WP_Post ) {
				continue;
			}

			if ( self::is_usable_latin_slug( (string) $song->post_name ) ) {
				continue;
			}

			$slug   = self::unique_slug(
				self::latin_slug_from_title_or_slug( (string) $song->post_title, (string) $song->post_name ) ?: 'song',
				(int) $song->ID
			);
			$result = wp_update_post(
				array(
					'ID'        => (int) $song->ID,
					'post_name' => $slug,
				),
				true
			);

			if ( ! is_wp_error( $result ) ) {
				++$updated;
			}
		}

		return $updated;
	}

	/**
	 * Build a Latin slug from title/slug, repairing WP percent-encoded Cyrillic.
	 */
	public static function latin_slug_from_title_or_slug( string $title, string $slug ): string {
		$source = self::resolve_slug_source( $slug, $title );
		return self::latin_slug( $source );
	}

	/**
	 * Choose the best raw string to transliterate.
	 */
	public static function resolve_slug_source( string $slug, string $title ): string {
		$slug  = trim( $slug );
		$title = trim( $title );

		if ( '' === $slug || self::is_usable_latin_slug( $slug ) ) {
			return '' !== $slug ? $slug : $title;
		}

		$decoded = self::decode_uri_artifacts( $slug );

		// Percent-encoded Cyrillic (%d0%9f…) or UTF-8 hex dumps must not win over the title.
		if ( self::looks_like_utf8_hex_dump( $slug ) || self::looks_like_utf8_hex_dump( $decoded ) ) {
			return '' !== $title ? $title : $decoded;
		}

		if ( self::has_letters( $decoded ) ) {
			return $decoded;
		}

		return '' !== $title ? $title : $decoded;
	}

	public static function is_latin_slug( string $slug ): bool {
		$slug = trim( $slug );
		if ( '' === $slug ) {
			return false;
		}

		return (bool) preg_match( '/^[a-z0-9\-]+$/', $slug );
	}

	/**
	 * Latin slug that is not a leftover UTF-8 hex dump (e.g. d09fd0b5…).
	 */
	public static function is_usable_latin_slug( string $slug ): bool {
		$slug = trim( $slug );
		if ( '' === $slug || ! self::is_latin_slug( $slug ) ) {
			return false;
		}

		return ! self::looks_like_utf8_hex_dump( $slug );
	}

	public static function latin_slug( string $text ): string {
		$text = self::decode_uri_artifacts( $text );
		$text = wp_strip_all_tags( $text );
		$text = self::transliterate( $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		// Avoid sanitize_title() — WordPress may apply unrelated global filters.
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $text ) ?? '';
		$slug = preg_replace( '/-+/', '-', $slug ) ?? '';
		$slug = trim( $slug, '-' );

		return $slug;
	}

	/**
	 * Decode HTML entities and WordPress utf8_uri_encode percent sequences.
	 */
	public static function decode_uri_artifacts( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( preg_match( '/%[0-9a-fA-F]{2}/', $text ) ) {
			$decoded = rawurldecode( $text );
			if ( is_string( $decoded ) && '' !== $decoded ) {
				$text = $decoded;
			}
		}

		return $text;
	}

	/**
	 * True when slug is only hex bytes (UTF-8 code units) — the bug symptom for Cyrillic titles.
	 */
	public static function looks_like_utf8_hex_dump( string $slug ): bool {
		$compact = strtolower( str_replace( '-', '', trim( $slug ) ) );
		if ( ! preg_match( '/^[0-9a-f]+$/', $compact ) ) {
			return false;
		}
		$len = strlen( $compact );
		// At least 2 UTF-8 bytes (4 hex chars); even length.
		if ( $len < 4 || 0 !== ( $len % 2 ) ) {
			return false;
		}
		// Prefer detecting multi-byte UTF-8 lead bytes (C2–F4) which Cyrillic uses (D0/D1).
		for ( $i = 0; $i + 1 < $len; $i += 2 ) {
			$byte = hexdec( substr( $compact, $i, 2 ) );
			if ( $byte >= 0xC2 && $byte <= 0xF4 ) {
				return true;
			}
		}

		return $len >= 8;
	}

	public static function transliterate( string $text ): string {
		// Unicode escapes keep the map valid even if the file encoding is mishandled on deploy.
		$map = array(
			"\u{0430}" => 'a',
			"\u{0431}" => 'b',
			"\u{0432}" => 'v',
			"\u{0433}" => 'g',
			"\u{0434}" => 'd',
			"\u{0435}" => 'e',
			"\u{0451}" => 'yo',
			"\u{0436}" => 'zh',
			"\u{0437}" => 'z',
			"\u{0438}" => 'i',
			"\u{0439}" => 'y',
			"\u{043A}" => 'k',
			"\u{043B}" => 'l',
			"\u{043C}" => 'm',
			"\u{043D}" => 'n',
			"\u{043E}" => 'o',
			"\u{043F}" => 'p',
			"\u{0440}" => 'r',
			"\u{0441}" => 's',
			"\u{0442}" => 't',
			"\u{0443}" => 'u',
			"\u{0444}" => 'f',
			"\u{0445}" => 'kh',
			"\u{0446}" => 'ts',
			"\u{0447}" => 'ch',
			"\u{0448}" => 'sh',
			"\u{0449}" => 'shch',
			"\u{044A}" => '',
			"\u{044B}" => 'y',
			"\u{044C}" => '',
			"\u{044D}" => 'e',
			"\u{044E}" => 'yu',
			"\u{044F}" => 'ya',
			"\u{0410}" => 'A',
			"\u{0411}" => 'B',
			"\u{0412}" => 'V',
			"\u{0413}" => 'G',
			"\u{0414}" => 'D',
			"\u{0415}" => 'E',
			"\u{0401}" => 'Yo',
			"\u{0416}" => 'Zh',
			"\u{0417}" => 'Z',
			"\u{0418}" => 'I',
			"\u{0419}" => 'Y',
			"\u{041A}" => 'K',
			"\u{041B}" => 'L',
			"\u{041C}" => 'M',
			"\u{041D}" => 'N',
			"\u{041E}" => 'O',
			"\u{041F}" => 'P',
			"\u{0420}" => 'R',
			"\u{0421}" => 'S',
			"\u{0422}" => 'T',
			"\u{0423}" => 'U',
			"\u{0424}" => 'F',
			"\u{0425}" => 'Kh',
			"\u{0426}" => 'Ts',
			"\u{0427}" => 'Ch',
			"\u{0428}" => 'Sh',
			"\u{0429}" => 'Shch',
			"\u{042A}" => '',
			"\u{042B}" => 'Y',
			"\u{042C}" => '',
			"\u{042D}" => 'E',
			"\u{042E}" => 'Yu',
			"\u{042F}" => 'Ya',
			// Ukrainian / Belarusian extras.
			"\u{0454}" => 'ye',
			"\u{0456}" => 'i',
			"\u{0457}" => 'yi',
			"\u{0491}" => 'g',
			"\u{045E}" => 'u',
			"\u{0404}" => 'Ye',
			"\u{0406}" => 'I',
			"\u{0407}" => 'Yi',
			"\u{0490}" => 'G',
			"\u{040E}" => 'U',
		);

		$text = strtr( $text, $map );

		if ( function_exists( 'remove_accents' ) ) {
			$text = remove_accents( $text );
		}

		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text );
			if ( is_string( $converted ) && '' !== $converted ) {
				$text = $converted;
			}
		}

		return $text;
	}

	private static function unique_slug( string $slug, int $post_id ): string {
		if ( '' === $slug ) {
			$slug = 'song';
		}

		if ( function_exists( 'wp_unique_post_slug' ) ) {
			$post   = $post_id > 0 ? get_post( $post_id ) : null;
			$status = $post instanceof WP_Post ? (string) $post->post_status : 'publish';
			$parent = $post instanceof WP_Post ? (int) $post->post_parent : 0;

			return wp_unique_post_slug(
				$slug,
				$post_id,
				$status,
				Choir_Rehearsal_Post_Types::SONG,
				$parent
			);
		}

		return $slug;
	}

	/**
	 * @internal Also used by tests.
	 */
	public static function has_letters( string $text ): bool {
		$text = self::decode_uri_artifacts( $text );
		return (bool) preg_match( '/\p{L}/u', $text );
	}

}
