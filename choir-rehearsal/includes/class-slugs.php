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
		add_filter( 'sanitize_title', array( self::class, 'filter_sanitize_title' ), 9, 3 );
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

		if ( '' === $slug || ! self::is_latin_slug( $slug ) ) {
			$source = '' !== $slug && self::has_letters( $slug ) ? $slug : $title;
			$slug   = self::latin_slug( $source );
		} else {
			$slug = self::latin_slug( $slug );
		}

		if ( '' === $slug ) {
			$slug = 'song';
		}

		$data['post_name'] = self::unique_slug( $slug, $post_id );

		return $data;
	}

	/**
	 * Keep sample-permalink / AJAX slug edits Latin for songs.
	 *
	 * @param string $title     Sanitized title.
	 * @param string $raw_title Raw title before sanitizing.
	 * @param string $context   Sanitization context.
	 */
	public static function filter_sanitize_title( string $title, string $raw_title = '', string $context = 'save' ): string {
		if ( 'save' !== $context || ! self::is_song_slug_context() ) {
			return $title;
		}

		$source = '' !== $raw_title ? $raw_title : $title;
		$latin  = self::latin_slug( $source );

		return '' !== $latin ? $latin : $title;
	}

	/**
	 * Safety net if core still stored a non-Latin slug.
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

		if ( self::is_latin_slug( (string) $post->post_name ) && '' !== $post->post_name ) {
			return;
		}

		$source = '' !== (string) $post->post_name ? (string) $post->post_name : (string) $post->post_title;
		$slug   = self::unique_slug( self::latin_slug( $source ) ?: 'song', $post_id );

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
	 * One-time repair of existing non-Latin song slugs.
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

			if ( self::is_latin_slug( (string) $song->post_name ) && '' !== $song->post_name ) {
				continue;
			}

			$source = '' !== (string) $song->post_title ? (string) $song->post_title : 'song';
			$slug   = self::unique_slug( self::latin_slug( $source ) ?: 'song', (int) $song->ID );
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

	public static function is_latin_slug( string $slug ): bool {
		$slug = trim( $slug );
		if ( '' === $slug ) {
			return false;
		}

		return (bool) preg_match( '/^[a-z0-9\-]+$/', $slug );
	}

	public static function latin_slug( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = wp_strip_all_tags( $text );
		$text = self::transliterate( $text );
		$text = strtolower( $text );
		// Do not call sanitize_title() here — it re-enters our filter.
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $text ) ?? '';
		$slug = preg_replace( '/-+/', '-', $slug ) ?? '';
		$slug = trim( $slug, '-' );

		return $slug;
	}

	public static function transliterate( string $text ): string {
		$map = array(
			'а' => 'a',
			'б' => 'b',
			'в' => 'v',
			'г' => 'g',
			'д' => 'd',
			'е' => 'e',
			'ё' => 'yo',
			'ж' => 'zh',
			'з' => 'z',
			'и' => 'i',
			'й' => 'y',
			'к' => 'k',
			'л' => 'l',
			'м' => 'm',
			'н' => 'n',
			'о' => 'o',
			'п' => 'p',
			'р' => 'r',
			'с' => 's',
			'т' => 't',
			'у' => 'u',
			'ф' => 'f',
			'х' => 'kh',
			'ц' => 'ts',
			'ч' => 'ch',
			'ш' => 'sh',
			'щ' => 'shch',
			'ъ' => '',
			'ы' => 'y',
			'ь' => '',
			'э' => 'e',
			'ю' => 'yu',
			'я' => 'ya',
			'А' => 'A',
			'Б' => 'B',
			'В' => 'V',
			'Г' => 'G',
			'Д' => 'D',
			'Е' => 'E',
			'Ё' => 'Yo',
			'Ж' => 'Zh',
			'З' => 'Z',
			'И' => 'I',
			'Й' => 'Y',
			'К' => 'K',
			'Л' => 'L',
			'М' => 'M',
			'Н' => 'N',
			'О' => 'O',
			'П' => 'P',
			'Р' => 'R',
			'С' => 'S',
			'Т' => 'T',
			'У' => 'U',
			'Ф' => 'F',
			'Х' => 'Kh',
			'Ц' => 'Ts',
			'Ч' => 'Ch',
			'Ш' => 'Sh',
			'Щ' => 'Shch',
			'Ъ' => '',
			'Ы' => 'Y',
			'Ь' => '',
			'Э' => 'E',
			'Ю' => 'Yu',
			'Я' => 'Ya',
			// Ukrainian / Belarusian extras.
			'є' => 'ye',
			'і' => 'i',
			'ї' => 'yi',
			'ґ' => 'g',
			'ў' => 'u',
			'Є' => 'Ye',
			'І' => 'I',
			'Ї' => 'Yi',
			'Ґ' => 'G',
			'Ў' => 'U',
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
			$post = $post_id > 0 ? get_post( $post_id ) : null;
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

	private static function has_letters( string $text ): bool {
		return (bool) preg_match( '/\p{L}/u', $text );
	}

	private static function is_song_slug_context(): bool {
		if ( isset( $_POST['post_type'] ) && Choir_Rehearsal_Post_Types::SONG === (string) wp_unslash( $_POST['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}

		$post_id = 0;
		if ( isset( $_POST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_id = (int) $_POST['post_id']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_REQUEST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = (int) $_REQUEST['post_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post && Choir_Rehearsal_Post_Types::SONG === $post->post_type ) {
				return true;
			}
		}

		return false;
	}
}
