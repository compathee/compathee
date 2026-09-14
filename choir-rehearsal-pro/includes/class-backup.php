<?php
/**
 * Pro-only song library export / import (Settings).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Pro_Backup {

	private const FORMAT_ID = 'compath-choir-rehearsal-backup';

	private const FORMAT_VERSION = 1;

	public static function register(): void {
		add_action( 'choir_rehearsal_settings_tools', array( self::class, 'render_settings_section' ) );
		add_action( 'admin_post_choir_rehearsal_pro_export_songs', array( self::class, 'handle_export' ) );
		add_action( 'admin_post_choir_rehearsal_pro_import_songs', array( self::class, 'handle_import' ) );
	}

	private static function can_manage_backup(): bool {
		return current_user_can( 'manage_options' ) && class_exists( 'Choir_Rehearsal_Edition', false ) && Choir_Rehearsal_Edition::is_pro();
	}

	public static function render_settings_section(): void {
		if ( ! self::can_manage_backup() ) {
			return;
		}

		self::render_notices();

		if ( ! class_exists( 'ZipArchive' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'ZIP backup requires the PHP Zip extension (ZipArchive). Ask your host to enable it.', 'choir-rehearsal-pro' ) . '</p></div>';
			return;
		}

		$song_count = self::count_songs();
		?>
		<hr />
		<h2><?php esc_html_e( 'Backup songs', 'choir-rehearsal-pro' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Export the full rehearsal library (songs, voice tracks, audio, and PDF scores) to a .zip file on your computer. Import adds songs from a backup without removing unrelated songs.', 'choir-rehearsal-pro' ); ?>
		</p>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of songs in the library */
					__( 'Current library: %d songs.', 'choir-rehearsal-pro' ),
					$song_count
				)
			);
			?>
		</p>
		<p>
			<a class="button button-secondary" href="<?php echo esc_url( self::get_export_url() ); ?>">
				<?php esc_html_e( 'Export songs (.zip)', 'choir-rehearsal-pro' ); ?>
			</a>
		</p>
		<form id="choir-rehearsal-pro-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-top:12px;">
			<input type="hidden" name="action" value="choir_rehearsal_pro_import_songs" />
			<?php wp_nonce_field( 'choir_rehearsal_pro_import_songs' ); ?>
			<p>
				<label for="choir-rehearsal-pro-import-file"><?php esc_html_e( 'Import backup (.zip)', 'choir-rehearsal-pro' ); ?></label><br />
				<input type="file" id="choir-rehearsal-pro-import-file" name="backup_zip" accept=".zip,application/zip" required />
			</p>
			<fieldset style="border:0;margin:0;padding:0;">
				<legend><?php esc_html_e( 'What to do with matches?', 'choir-rehearsal-pro' ); ?></legend>
				<p class="description" style="margin-top:4px;">
					<?php esc_html_e( 'A match is a song with the same permalink slug. Non-matching songs in the library are never deleted.', 'choir-rehearsal-pro' ); ?>
				</p>
				<label style="display:block;margin:6px 0;">
					<input type="radio" name="match_mode" value="skip" checked="checked" />
					<?php esc_html_e( 'Skip — keep the existing song, do not import the duplicate', 'choir-rehearsal-pro' ); ?>
				</label>
				<label style="display:block;margin:6px 0;">
					<input type="radio" name="match_mode" value="replace" />
					<?php esc_html_e( 'Replace — delete the existing song, then import from the backup', 'choir-rehearsal-pro' ); ?>
				</label>
			</fieldset>
			<p>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Import', 'choir-rehearsal-pro' ); ?></button>
			</p>
		</form>
		<?php
	}

	private static function render_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['choir_backup_error'] ) ) {
			$message = rawurldecode( sanitize_text_field( wp_unslash( (string) $_GET['choir_backup_error'] ) ) );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			return;
		}

		if ( isset( $_GET['choir_backup_imported'] ) ) {
			$songs    = isset( $_GET['choir_backup_songs'] ) ? absint( $_GET['choir_backup_songs'] ) : 0;
			$tracks   = isset( $_GET['choir_backup_tracks'] ) ? absint( $_GET['choir_backup_tracks'] ) : 0;
			$skipped  = isset( $_GET['choir_backup_skipped'] ) ? absint( $_GET['choir_backup_skipped'] ) : 0;
			$replaced = isset( $_GET['choir_backup_replaced'] ) ? absint( $_GET['choir_backup_replaced'] ) : 0;
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: songs imported, 2: tracks imported, 3: songs skipped, 4: songs replaced */
					__( 'Imported %1$d songs and %2$d tracks (%3$d skipped, %4$d replaced).', 'choir-rehearsal-pro' ),
					$songs,
					$tracks,
					$skipped,
					$replaced
				)
			);
			echo '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	private static function get_export_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=choir_rehearsal_pro_export_songs' ),
			'choir_rehearsal_pro_export_songs'
		);
	}

	public static function handle_export(): void {
		if ( ! self::can_manage_backup() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export songs.', 'choir-rehearsal-pro' ) );
		}

		check_admin_referer( 'choir_rehearsal_pro_export_songs' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			self::redirect_error( __( 'ZIP export is not available on this server.', 'choir-rehearsal-pro' ) );
		}

		$payload = self::build_export_payload();
		$tmp     = wp_tempnam( 'choir-rehearsal-export' );
		if ( ! $tmp ) {
			self::redirect_error( __( 'Could not create a temporary export file.', 'choir-rehearsal-pro' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $tmp );
			self::redirect_error( __( 'Could not create the export archive.', 'choir-rehearsal-pro' ) );
		}

		$zip->addFromString( 'manifest.json', wp_json_encode( $payload['manifest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		foreach ( $payload['files'] as $zip_path => $source_path ) {
			if ( is_string( $source_path ) && '' !== $source_path && is_readable( $source_path ) ) {
				$zip->addFile( $source_path, $zip_path );
			}
		}
		$zip->close();

		$filename = 'choir-rehearsal-backup-' . gmdate( 'Y-m-d-His' ) . '.zip';
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) filesize( $tmp ) );
		readfile( $tmp );
		wp_delete_file( $tmp );
		exit;
	}

	public static function handle_import(): void {
		if ( ! self::can_manage_backup() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import songs.', 'choir-rehearsal-pro' ) );
		}

		check_admin_referer( 'choir_rehearsal_pro_import_songs' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			self::redirect_error( __( 'ZIP import is not available on this server.', 'choir-rehearsal-pro' ) );
		}

		if ( empty( $_FILES['backup_zip']['tmp_name'] ) || ! is_uploaded_file( (string) $_FILES['backup_zip']['tmp_name'] ) ) {
			self::redirect_error( __( 'No backup file was uploaded.', 'choir-rehearsal-pro' ) );
		}

		$mode = isset( $_POST['match_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['match_mode'] ) ) : 'skip';
		if ( ! in_array( $mode, array( 'skip', 'replace' ), true ) ) {
			$mode = 'skip';
		}

		$zip_path = (string) $_FILES['backup_zip']['tmp_name'];
		$zip      = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			self::redirect_error( __( 'Could not open the backup archive.', 'choir-rehearsal-pro' ) );
		}

		$manifest_raw = $zip->getFromName( 'manifest.json' );
		if ( ! is_string( $manifest_raw ) || '' === $manifest_raw ) {
			$zip->close();
			self::redirect_error( __( 'Invalid backup: manifest.json is missing.', 'choir-rehearsal-pro' ) );
		}

		$manifest = json_decode( $manifest_raw, true );
		if ( ! is_array( $manifest ) || ( $manifest['format'] ?? '' ) !== self::FORMAT_ID ) {
			$zip->close();
			self::redirect_error( __( 'Invalid backup format.', 'choir-rehearsal-pro' ) );
		}

		$result = self::import_from_manifest( $zip, $manifest, $mode );
		$zip->close();

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'              => Choir_Rehearsal_Post_Types::SONG,
					'page'                   => 'choir-rehearsal-settings',
					'choir_backup_imported'  => '1',
					'choir_backup_songs'     => (string) $result['songs'],
					'choir_backup_tracks'    => (string) $result['tracks'],
					'choir_backup_skipped'   => (string) $result['skipped'],
					'choir_backup_replaced'  => (string) $result['replaced'],
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * @return array{manifest: array<string, mixed>, files: array<string, string>}
	 */
	private static function build_export_payload(): array {
		$songs = get_posts(
			array(
				'post_type'              => Choir_Rehearsal_Post_Types::SONG,
				'posts_per_page'         => -1,
				'post_status'            => 'any',
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
			)
		);

		$export_songs = array();
		$files        = array();

		foreach ( $songs as $song ) {
			if ( ! $song instanceof WP_Post ) {
				continue;
			}

			$song_slug = (string) $song->post_name;
			if ( '' === $song_slug ) {
				$song_slug = 'song-' . (int) $song->ID;
			}

			$pdf_entry = null;
			$pdf_id    = Choir_Rehearsal_Post_Types::get_score_pdf_id( (int) $song->ID );
			if ( $pdf_id > 0 ) {
				$pdf_zip = 'media/pdf/' . sanitize_file_name( $song_slug ) . '.pdf';
				$pdf_src = get_attached_file( $pdf_id );
				if ( is_string( $pdf_src ) && '' !== $pdf_src && is_readable( $pdf_src ) ) {
					$files[ $pdf_zip ] = $pdf_src;
					$pdf_entry         = array( 'zip_path' => $pdf_zip );
				}
			}

			$tracks_out = array();
			$tracks     = Choir_Rehearsal_Post_Types::get_tracks_for_song( (int) $song->ID );
			$track_idx  = 0;
			foreach ( $tracks as $track ) {
				++$track_idx;
				$audio_id = (int) get_post_meta( $track->ID, '_choir_audio_id', true );
				$audio    = null;
				if ( $audio_id > 0 ) {
					$src = get_attached_file( $audio_id );
					if ( is_string( $src ) && '' !== $src && is_readable( $src ) ) {
						$ext       = pathinfo( $src, PATHINFO_EXTENSION );
						$ext       = '' !== $ext ? $ext : 'bin';
						$audio_zip = 'media/audio/' . sanitize_file_name( $song_slug ) . '-' . $track_idx . '.' . sanitize_file_name( $ext );
						$files[ $audio_zip ] = $src;
						$audio               = array( 'zip_path' => $audio_zip );
					}
				}

				$tracks_out[] = array(
					'title'      => (string) $track->post_title,
					'menu_order' => (int) $track->menu_order,
					'voice_slug' => (string) get_post_meta( $track->ID, '_choir_voice_slug', true ),
					'audio'      => $audio,
				);
			}

			$export_songs[] = array(
				'title'      => (string) $song->post_title,
				'slug'       => $song_slug,
				'status'     => (string) $song->post_status,
				'is_public'  => Choir_Rehearsal_Post_Types::is_public( (int) $song->ID ),
				'pdf'        => $pdf_entry,
				'tracks'     => $tracks_out,
			);
		}

		$manifest = array(
			'format'         => self::FORMAT_ID,
			'format_version' => self::FORMAT_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'plugin_version' => defined( 'CHOIR_REHEARSAL_VERSION' ) ? CHOIR_REHEARSAL_VERSION : '',
			'song_count'     => count( $export_songs ),
			'songs'          => $export_songs,
		);

		return array(
			'manifest' => $manifest,
			'files'    => $files,
		);
	}

	/**
	 * @return array{songs: int, tracks: int, skipped: int, replaced: int}
	 */
	private static function import_from_manifest( ZipArchive $zip, array $manifest, string $match_mode ): array {
		$songs    = 0;
		$tracks   = 0;
		$skipped  = 0;
		$replaced = 0;
		$list     = isset( $manifest['songs'] ) && is_array( $manifest['songs'] ) ? $manifest['songs'] : array();

		foreach ( $list as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$slug = sanitize_title( (string) ( $entry['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$existing_id = self::get_song_id_by_slug( $slug );
			if ( $existing_id > 0 ) {
				if ( 'replace' !== $match_mode ) {
					++$skipped;
					continue;
				}
				self::delete_song_by_id( $existing_id );
				++$replaced;
			}

			$title  = sanitize_text_field( (string) ( $entry['title'] ?? $slug ) );
			$status = sanitize_key( (string) ( $entry['status'] ?? 'publish' ) );
			if ( ! in_array( $status, array( 'publish', 'draft', 'private' ), true ) ) {
				$status = 'publish';
			}

			$song_id = wp_insert_post(
				array(
					'post_type'   => Choir_Rehearsal_Post_Types::SONG,
					'post_status' => $status,
					'post_title'  => $title,
					'post_name'   => $slug,
				),
				true
			);

			if ( is_wp_error( $song_id ) || $song_id <= 0 ) {
				continue;
			}

			++$songs;
			Choir_Rehearsal_Post_Types::set_public( (int) $song_id, ! empty( $entry['is_public'] ) );

			$pdf = $entry['pdf'] ?? null;
			if ( is_array( $pdf ) && ! empty( $pdf['zip_path'] ) ) {
				$pdf_id = self::import_zip_media( $zip, (string) $pdf['zip_path'], $title . ' — PDF' );
				if ( $pdf_id > 0 ) {
					update_post_meta( (int) $song_id, '_choir_score_pdf_id', $pdf_id );
				}
			}

			$track_entries = isset( $entry['tracks'] ) && is_array( $entry['tracks'] ) ? $entry['tracks'] : array();
			foreach ( $track_entries as $track_entry ) {
				if ( ! is_array( $track_entry ) ) {
					continue;
				}

				$track_title = sanitize_text_field( (string) ( $track_entry['title'] ?? __( 'Track', 'choir-rehearsal-pro' ) ) );
				$track_id    = wp_insert_post(
					array(
						'post_type'   => Choir_Rehearsal_Post_Types::TRACK,
						'post_status' => 'publish',
						'post_parent' => (int) $song_id,
						'post_title'  => $track_title,
						'menu_order'  => (int) ( $track_entry['menu_order'] ?? 0 ),
					),
					true
				);

				if ( is_wp_error( $track_id ) || $track_id <= 0 ) {
					continue;
				}

				$voice = sanitize_key( (string) ( $track_entry['voice_slug'] ?? '' ) );
				if ( '' !== $voice ) {
					update_post_meta( (int) $track_id, '_choir_voice_slug', $voice );
				}

				$audio = $track_entry['audio'] ?? null;
				if ( is_array( $audio ) && ! empty( $audio['zip_path'] ) ) {
					$audio_id = self::import_zip_media( $zip, (string) $audio['zip_path'], $track_title );
					if ( $audio_id > 0 ) {
						update_post_meta( (int) $track_id, '_choir_audio_id', $audio_id );
					}
				}

				++$tracks;
			}
		}

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		return array(
			'songs'    => $songs,
			'tracks'   => $tracks,
			'skipped'  => $skipped,
			'replaced' => $replaced,
		);
	}

	private static function import_zip_media( ZipArchive $zip, string $zip_path, string $title ): int {
		$zip_path = ltrim( str_replace( '\\', '/', $zip_path ), '/' );
		$contents = $zip->getFromName( $zip_path );
		if ( ! is_string( $contents ) || '' === $contents ) {
			return 0;
		}

		$tmp = wp_tempnam( basename( $zip_path ) );
		if ( ! $tmp ) {
			return 0;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $tmp, $contents ) ) {
			wp_delete_file( $tmp );
			return 0;
		}

		$file_array = array(
			'name'     => basename( $zip_path ),
			'tmp_name' => $tmp,
			'type'     => self::mime_for_filename( basename( $zip_path ) ),
			'error'    => 0,
			'size'     => strlen( $contents ),
		);

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_sideload( $file_array, 0, $title );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}

		return (int) $attachment_id;
	}

	private static function mime_for_filename( string $filename ): string {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		return match ( $ext ) {
			'pdf' => 'application/pdf',
			'mp3' => 'audio/mpeg',
			'wav' => 'audio/wav',
			'm4a' => 'audio/mp4',
			'ogg' => 'audio/ogg',
			default => 'application/octet-stream',
		};
	}

	private static function get_song_id_by_slug( string $slug ): int {
		$existing = get_posts(
			array(
				'post_type'              => Choir_Rehearsal_Post_Types::SONG,
				'name'                   => $slug,
				'posts_per_page'         => 1,
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			)
		);

		return ! empty( $existing[0] ) ? (int) $existing[0] : 0;
	}

	private static function delete_song_by_id( int $song_id ): void {
		if ( $song_id <= 0 ) {
			return;
		}

		$tracks = Choir_Rehearsal_Post_Types::get_tracks_for_song( $song_id );
		$attachment_ids = array();

		foreach ( $tracks as $track ) {
			$audio_id = (int) get_post_meta( (int) $track->ID, '_choir_audio_id', true );
			if ( $audio_id > 0 ) {
				$attachment_ids[ $audio_id ] = $audio_id;
			}
			wp_delete_post( (int) $track->ID, true );
		}

		$pdf_id = Choir_Rehearsal_Post_Types::get_score_pdf_id( $song_id );
		if ( $pdf_id > 0 ) {
			$attachment_ids[ $pdf_id ] = $pdf_id;
		}

		wp_delete_post( $song_id, true );

		foreach ( $attachment_ids as $attachment_id ) {
			wp_delete_attachment( (int) $attachment_id, true );
		}
	}

	private static function count_songs(): int {
		$counts = wp_count_posts( Choir_Rehearsal_Post_Types::SONG );
		if ( ! $counts instanceof stdClass ) {
			return 0;
		}

		$total = 0;
		foreach ( (array) $counts as $status => $count ) {
			if ( 'auto-draft' === $status || 'trash' === $status ) {
				continue;
			}
			$total += (int) $count;
		}

		return $total;
	}

	private static function redirect_error( string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'          => Choir_Rehearsal_Post_Types::SONG,
					'page'               => 'choir-rehearsal-settings',
					'choir_backup_error' => rawurlencode( $message ),
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
