<?php
/**
 * Demo caps: songs, tracks per song, PDF size.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Demo_Limits {

	public static function register(): void {
		if ( ! Choir_Rehearsal_Distribution::is_demo() ) {
			return;
		}

		add_filter( 'wp_insert_post_empty_content', array( self::class, 'block_new_song_over_limit' ), 10, 2 );
		add_filter( 'wp_handle_upload_prefilter', array( self::class, 'block_oversized_pdf' ) );
		add_action( 'admin_notices', array( self::class, 'render_limit_notices' ) );
		add_action( 'transition_post_status', array( self::class, 'log_song_transition' ), 10, 3 );
	}

	public static function song_count(): int {
		$counts = wp_count_posts( Choir_Rehearsal_Post_Types::SONG );
		$total  = 0;
		foreach ( array( 'publish', 'draft', 'pending', 'private', 'future' ) as $status ) {
			if ( isset( $counts->$status ) ) {
				$total += (int) $counts->$status;
			}
		}

		return $total;
	}

	/**
	 * @param bool                  $maybe_empty
	 * @param array<string, mixed>  $postarr
	 */
	public static function block_new_song_over_limit( bool $maybe_empty, array $postarr ): bool {
		$post_type = isset( $postarr['post_type'] ) ? (string) $postarr['post_type'] : '';
		if ( Choir_Rehearsal_Post_Types::SONG !== $post_type ) {
			return $maybe_empty;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $post_id > 0 ) {
			return $maybe_empty;
		}

		$max = Choir_Rehearsal_Edition::max_songs();
		if ( $max <= 0 || self::song_count() < $max ) {
			return $maybe_empty;
		}

		Choir_Rehearsal_Demo_Log::add(
			'blocked_song_limit',
			sprintf(
				/* translators: %d: song limit */
				__( 'Blocked — song limit (%d) reached', 'compath-choir-rehearsal' ),
				$max
			)
		);

		return true;
	}

	/**
	 * @param array<string, mixed> $file
	 * @return array<string, mixed>
	 */
	public static function block_oversized_pdf( array $file ): array {
		$max = Choir_Rehearsal_Edition::max_pdf_bytes();
		if ( $max <= 0 ) {
			return $file;
		}

		$type = isset( $file['type'] ) ? (string) $file['type'] : '';
		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		$is_pdf = ( 'application/pdf' === $type )
			|| (bool) preg_match( '/\.pdf$/i', $name );

		if ( ! $is_pdf || $size <= $max ) {
			return $file;
		}

		$file['error'] = sprintf(
			/* translators: %s: max size label */
			__( 'Demo PDF uploads must be %s or smaller.', 'compath-choir-rehearsal' ),
			size_format( $max )
		);

		Choir_Rehearsal_Demo_Log::add(
			'blocked_pdf_size',
			__( 'Blocked — PDF over 5 MB', 'compath-choir-rehearsal' ),
			array( 'size' => $size )
		);

		return $file;
	}

	public static function render_limit_notices(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$max = Choir_Rehearsal_Edition::max_songs();
		if ( $max <= 0 ) {
			return;
		}

		$count = self::song_count();
		if ( $count < $max ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Choir_Rehearsal_Post_Types::SONG !== $screen->post_type ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: 1: current count, 2: max songs */
				__( 'Demo song limit reached (%1$d / %2$d). Delete songs or wait for the nightly reset.', 'compath-choir-rehearsal' ),
				$count,
				$max
			)
		);
		echo '</p></div>';
	}

	/**
	 * @param WP_Post $post
	 */
	public static function log_song_transition( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || Choir_Rehearsal_Post_Types::SONG !== $post->post_type ) {
			return;
		}

		if ( 'publish' === $new_status && 'publish' !== $old_status && 'auto-draft' !== $old_status ) {
			Choir_Rehearsal_Demo_Log::add(
				'song_added',
				__( 'Added — 1 song', 'compath-choir-rehearsal' ),
				array( 'song_id' => (int) $post->ID )
			);
		}

		if ( 'trash' === $new_status && 'trash' !== $old_status ) {
			Choir_Rehearsal_Demo_Log::add(
				'song_deleted',
				__( 'Deleted — 1 song', 'compath-choir-rehearsal' ),
				array( 'song_id' => (int) $post->ID )
			);
		}
	}
}
