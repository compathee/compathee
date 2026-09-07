<?php
/**
 * Demo library seed and wipe helpers (Settings).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Demo_Data {

	public const SONGS_PER_LOAD = 25;

	public const META_DEMO_AUDIO = '_choir_demo_audio';

	private const DEMO_AUDIO_BASENAME = 'choir-rehearsal-demo-voice.mp3';

	/**
	 * Zero-padded title so A–Z list order is Demo Song 01, 02, … 09, 10 (not 1, 10, 2).
	 */
	public static function format_demo_song_title( int $number ): string {
		return sprintf( 'Demo Song %02d', max( 0, $number ) );
	}

	/**
	 * Voice parts for each demo song (exactly Lite max of 4).
	 *
	 * @return list<array{slug: string, label: string}>
	 */
	public static function demo_voices(): array {
		return array(
			array(
				'slug'  => 'soprano-1',
				'label' => 'Soprano 1',
			),
			array(
				'slug'  => 'alto-1',
				'label' => 'Alto 1',
			),
			array(
				'slug'  => 'tenor-1',
				'label' => 'Tenor 1',
			),
			array(
				'slug'  => 'bass-1',
				'label' => 'Bass 1',
			),
		);
	}

	public static function register(): void {
		add_action( 'admin_post_choir_rehearsal_load_demo_songs', array( self::class, 'handle_load_demo_songs' ) );
		add_action( 'admin_post_choir_rehearsal_delete_all_songs', array( self::class, 'handle_delete_all_songs' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_settings_assets' ) );
	}

	public static function enqueue_settings_assets( string $hook ): void {
		if ( 'choir_song_page_choir-rehearsal-settings' !== $hook ) {
			return;
		}

		$handle = 'choir-rehearsal-demo-data';
		wp_register_script( $handle, false, array(), CHOIR_REHEARSAL_VERSION, true );
		wp_enqueue_script( $handle );
		wp_add_inline_script(
			$handle,
			self::settings_inline_js(),
			'after'
		);
	}

	private static function settings_inline_js(): string {
		return <<<'JS'
(function () {
	'use strict';

	var form = document.getElementById('choir-delete-all-songs-form');
	var dialog = document.getElementById('choir-delete-all-dialog');
	var openBtn = document.getElementById('choir-delete-all-songs');
	var yesBtn = document.getElementById('choir-delete-all-yes');
	var noBtn = document.getElementById('choir-delete-all-no');

	if (!form || !openBtn) {
		return;
	}

	var closeDialog = function () {
		if (!dialog) {
			return;
		}
		if (typeof dialog.close === 'function') {
			dialog.close();
			return;
		}
		dialog.setAttribute('hidden', 'hidden');
	};

	var openDialog = function () {
		if (dialog && typeof dialog.showModal === 'function') {
			dialog.showModal();
			return;
		}
		if (dialog) {
			dialog.removeAttribute('hidden');
			return;
		}
		if (window.confirm(openBtn.getAttribute('data-confirm') || '')) {
			form.submit();
		}
	};

	openBtn.addEventListener('click', function (event) {
		event.preventDefault();
		openDialog();
	});

	if (yesBtn) {
		yesBtn.addEventListener('click', function (event) {
			event.preventDefault();
			closeDialog();
			form.submit();
		});
	}

	if (noBtn) {
		noBtn.addEventListener('click', function (event) {
			event.preventDefault();
			closeDialog();
		});
	}

	if (dialog) {
		dialog.addEventListener('cancel', function (event) {
			event.preventDefault();
			closeDialog();
		});
	}
})();
JS;
	}

	public static function get_load_demo_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=choir_rehearsal_load_demo_songs' ),
			'choir_rehearsal_load_demo_songs'
		);
	}

	public static function get_delete_all_url(): string {
		return admin_url( 'admin-post.php' );
	}

	public static function handle_load_demo_songs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage options.', 'choir-rehearsal' ) );
		}

		check_admin_referer( 'choir_rehearsal_load_demo_songs' );

		$result = self::load_demo_songs();
		$args   = array(
			'post_type'              => Choir_Rehearsal_Post_Types::SONG,
			'page'                   => 'choir-rehearsal-settings',
			'choir_demo_loaded'      => '1',
			'choir_demo_songs'       => (string) $result['songs'],
			'choir_demo_tracks'      => (string) $result['tracks'],
			'choir_demo_from'        => (string) $result['from'],
			'choir_demo_to'          => (string) $result['to'],
		);

		if ( '' !== $result['error'] ) {
			$args['choir_demo_error'] = rawurlencode( $result['error'] );
			unset( $args['choir_demo_loaded'] );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
		exit;
	}

	public static function handle_delete_all_songs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage options.', 'choir-rehearsal' ) );
		}

		check_admin_referer( 'choir_rehearsal_delete_all_songs' );

		$result = self::delete_all_songs();
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'             => Choir_Rehearsal_Post_Types::SONG,
					'page'                  => 'choir-rehearsal-settings',
					'choir_demo_deleted'    => '1',
					'choir_demo_del_songs'  => (string) $result['songs'],
					'choir_demo_del_tracks' => (string) $result['tracks'],
					'choir_demo_del_media'  => (string) $result['media'],
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * @return array{songs: int, tracks: int, from: int, to: int, error: string}
	 */
	public static function load_demo_songs(): array {
		$empty = array(
			'songs'  => 0,
			'tracks' => 0,
			'from'   => 0,
			'to'     => 0,
			'error'  => '',
		);

		$audio_id = self::ensure_demo_audio_attachment();
		if ( $audio_id <= 0 ) {
			$empty['error'] = __( 'Could not create the demo audio file in the Media Library.', 'choir-rehearsal' );
			return $empty;
		}

		$start = self::next_demo_song_number();
		$end   = $start + self::SONGS_PER_LOAD - 1;
		$songs = 0;
		$tracks = 0;

		for ( $n = $start; $n <= $end; $n++ ) {
			$song_title = self::format_demo_song_title( $n );
			$song_id    = wp_insert_post(
				array(
					'post_type'   => Choir_Rehearsal_Post_Types::SONG,
					'post_status' => 'publish',
					'post_title'  => $song_title,
				),
				true
			);

			if ( is_wp_error( $song_id ) || $song_id <= 0 ) {
				continue;
			}

			$songs++;
			$order = 0;
			foreach ( self::demo_voices() as $voice ) {
				$track_id = wp_insert_post(
					array(
						'post_type'   => Choir_Rehearsal_Post_Types::TRACK,
						'post_status' => 'publish',
						'post_parent' => (int) $song_id,
						'post_title'  => sprintf( '%s — %s', $song_title, $voice['label'] ),
						'menu_order'  => $order,
					),
					true
				);

				if ( is_wp_error( $track_id ) || $track_id <= 0 ) {
					continue;
				}

				update_post_meta( (int) $track_id, '_choir_voice_slug', $voice['slug'] );
				update_post_meta( (int) $track_id, '_choir_audio_id', $audio_id );
				$tracks++;
				$order++;
			}
		}

		return array(
			'songs'  => $songs,
			'tracks' => $tracks,
			'from'   => $start,
			'to'     => $end,
			'error'  => '',
		);
	}

	/**
	 * @return array{songs: int, tracks: int, media: int}
	 */
	public static function delete_all_songs(): array {
		$attachment_ids = array();

		$track_ids = get_posts(
			array(
				'post_type'              => Choir_Rehearsal_Post_Types::TRACK,
				'posts_per_page'         => -1,
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $track_ids as $track_id ) {
			$audio_id = (int) get_post_meta( (int) $track_id, '_choir_audio_id', true );
			if ( $audio_id > 0 ) {
				$attachment_ids[ $audio_id ] = $audio_id;
			}
		}

		$song_ids = get_posts(
			array(
				'post_type'              => Choir_Rehearsal_Post_Types::SONG,
				'posts_per_page'         => -1,
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $song_ids as $song_id ) {
			$pdf_id = Choir_Rehearsal_Post_Types::get_score_pdf_id( (int) $song_id );
			if ( $pdf_id > 0 ) {
				$attachment_ids[ $pdf_id ] = $pdf_id;
			}
		}

		foreach ( $track_ids as $track_id ) {
			wp_delete_post( (int) $track_id, true );
		}

		foreach ( $song_ids as $song_id ) {
			wp_delete_post( (int) $song_id, true );
		}

		// Also remove orphaned demo audio marked by this plugin.
		$demo_audio_ids = get_posts(
			array(
				'post_type'              => 'attachment',
				'posts_per_page'         => -1,
				'post_status'            => 'inherit',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'meta_key'               => self::META_DEMO_AUDIO,
				'meta_value'             => '1',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( $demo_audio_ids as $demo_id ) {
			$attachment_ids[ (int) $demo_id ] = (int) $demo_id;
		}

		$deleted_media = 0;
		foreach ( $attachment_ids as $attachment_id ) {
			if ( wp_delete_attachment( (int) $attachment_id, true ) ) {
				$deleted_media++;
			}
		}

		return array(
			'songs'  => count( $song_ids ),
			'tracks' => count( $track_ids ),
			'media'  => $deleted_media,
		);
	}

	public static function next_demo_song_number(): int {
		$songs = get_posts(
			array(
				'post_type'              => Choir_Rehearsal_Post_Types::SONG,
				'posts_per_page'         => -1,
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$max = 0;
		foreach ( $songs as $song_id ) {
			$title = (string) get_post_field( 'post_title', (int) $song_id, 'raw' );
			if ( preg_match( '/^Demo Song\s+(\d+)$/i', trim( $title ), $matches ) ) {
				$max = max( $max, (int) $matches[1] );
			}
		}

		return $max + 1;
	}

	public static function demo_audio_source_path(): string {
		return CHOIR_REHEARSAL_PATH . 'assets/demo/demo-voice-track.mp3';
	}

	public static function ensure_demo_audio_attachment(): int {
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => 1,
				'post_status'    => 'inherit',
				'fields'         => 'ids',
				'meta_key'       => self::META_DEMO_AUDIO,
				'meta_value'     => '1',
				'no_found_rows'  => true,
			)
		);

		if ( ! empty( $existing[0] ) ) {
			$existing_id = (int) $existing[0];
			$url         = wp_get_attachment_url( $existing_id );
			if ( is_string( $url ) && '' !== $url ) {
				return $existing_id;
			}
		}

		$source = self::demo_audio_source_path();
		if ( ! is_readable( $source ) ) {
			return 0;
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = wp_tempnam( self::DEMO_AUDIO_BASENAME );
		if ( ! is_string( $tmp ) || '' === $tmp ) {
			return 0;
		}

		if ( ! copy( $source, $tmp ) ) {
			@unlink( $tmp );
			return 0;
		}

		$file_array = array(
			'name'     => self::DEMO_AUDIO_BASENAME,
			'tmp_name' => $tmp,
			'type'     => 'audio/mpeg',
			'error'    => 0,
			'size'     => (int) filesize( $tmp ),
		);

		$attachment_id = media_handle_sideload( $file_array, 0, 'Choir Rehearsal demo voice track' );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return 0;
		}

		update_post_meta( (int) $attachment_id, self::META_DEMO_AUDIO, '1' );
		update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', 'Choir Rehearsal demo voice track' );

		return (int) $attachment_id;
	}

	public static function render_settings_notices(): void {
		if ( isset( $_GET['choir_demo_error'] ) ) {
			$message = rawurldecode( (string) wp_unslash( $_GET['choir_demo_error'] ) );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			return;
		}

		if ( isset( $_GET['choir_demo_loaded'] ) ) {
			$from   = isset( $_GET['choir_demo_from'] ) ? (int) $_GET['choir_demo_from'] : 0;
			$to     = isset( $_GET['choir_demo_to'] ) ? (int) $_GET['choir_demo_to'] : 0;
			$songs  = isset( $_GET['choir_demo_songs'] ) ? (int) $_GET['choir_demo_songs'] : 0;
			$tracks = isset( $_GET['choir_demo_tracks'] ) ? (int) $_GET['choir_demo_tracks'] : 0;
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: first song number, 2: last song number, 3: song count, 4: track count */
					__( 'Loaded demo songs %1$d–%2$d (%3$d songs, %4$d tracks).', 'choir-rehearsal' ),
					$from,
					$to,
					$songs,
					$tracks
				)
			);
			echo '</p></div>';
		}

		if ( isset( $_GET['choir_demo_deleted'] ) ) {
			$songs  = isset( $_GET['choir_demo_del_songs'] ) ? (int) $_GET['choir_demo_del_songs'] : 0;
			$tracks = isset( $_GET['choir_demo_del_tracks'] ) ? (int) $_GET['choir_demo_del_tracks'] : 0;
			$media  = isset( $_GET['choir_demo_del_media'] ) ? (int) $_GET['choir_demo_del_media'] : 0;
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: songs deleted, 2: tracks deleted, 3: media deleted */
					__( 'Deleted %1$d songs, %2$d tracks, and %3$d media files.', 'choir-rehearsal' ),
					$songs,
					$tracks,
					$media
				)
			);
			echo '</p></div>';
		}
	}

	public static function render_settings_buttons(): void {
		?>
		<hr />
		<h2><?php esc_html_e( 'Demo library', 'choir-rehearsal' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: 1: songs created per click, 2: songs shown per library page */
				esc_html__( 'Load %1$d sample songs (4 voice tracks each) so the public library shows pagination (%2$d songs per page), or wipe the whole rehearsal library.', 'choir-rehearsal' ),
				self::SONGS_PER_LOAD,
				Choir_Rehearsal_Frontend::songs_per_page()
			);
			?>
		</p>
		<p>
			<a class="button button-secondary" href="<?php echo esc_url( self::get_load_demo_url() ); ?>">
				<?php esc_html_e( 'Load demo songs', 'choir-rehearsal' ); ?>
			</a>
			<button type="button" class="button button-secondary" id="choir-delete-all-songs" data-confirm="<?php echo esc_attr__( 'All songs will be deleted from the library. Do you agree?', 'choir-rehearsal' ); ?>">
				<?php esc_html_e( 'Delete all songs', 'choir-rehearsal' ); ?>
			</button>
		</p>
		<form id="choir-delete-all-songs-form" method="post" action="<?php echo esc_url( self::get_delete_all_url() ); ?>" style="display:none;">
			<input type="hidden" name="action" value="choir_rehearsal_delete_all_songs" />
			<?php wp_nonce_field( 'choir_rehearsal_delete_all_songs' ); ?>
		</form>
		<dialog id="choir-delete-all-dialog" class="choir-delete-all-dialog">
			<p><?php esc_html_e( 'All songs will be deleted from the library. Do you agree?', 'choir-rehearsal' ); ?></p>
			<p class="choir-delete-all-dialog__actions">
				<button type="button" class="button button-primary" id="choir-delete-all-yes"><?php esc_html_e( 'Yes', 'choir-rehearsal' ); ?></button>
				<button type="button" class="button" id="choir-delete-all-no"><?php esc_html_e( 'No', 'choir-rehearsal' ); ?></button>
			</p>
		</dialog>
		<style>
			.choir-delete-all-dialog {
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 20px 24px;
				max-width: 420px;
				box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
			}
			.choir-delete-all-dialog::backdrop {
				background: rgba(0, 0, 0, 0.35);
			}
			.choir-delete-all-dialog__actions {
				display: flex;
				gap: 8px;
				margin: 16px 0 0;
			}
		</style>
		<?php
	}
}
