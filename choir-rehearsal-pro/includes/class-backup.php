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

	/** @var bool */
	private static $section_rendered = false;

	public static function register(): void {
		add_action( 'choir_rehearsal_settings_tools', array( self::class, 'render_settings_section' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'maybe_register_settings_fallback' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_settings_assets' ) );
		add_action( 'admin_notices', array( self::class, 'maybe_notice_lite_too_old' ) );
		add_action( 'admin_post_choir_rehearsal_pro_export_songs', array( self::class, 'handle_export' ) );
		add_action( 'admin_post_choir_rehearsal_pro_import_songs', array( self::class, 'handle_import' ) );
		add_action( 'wp_ajax_choir_rehearsal_pro_import_chunk', array( self::class, 'ajax_import_chunk' ) );
		add_action( 'wp_ajax_choir_rehearsal_pro_import_finish', array( self::class, 'ajax_import_finish' ) );
	}

	/**
	 * Old Lite builds (< 0.4.45) never fire choir_rehearsal_settings_tools.
	 * Render the same section in admin_footer on the Settings screen as a fallback.
	 */
	public static function maybe_register_settings_fallback( string $hook ): void {
		if ( 'choir_song_page_choir-rehearsal-settings' !== $hook ) {
			return;
		}

		add_action( 'admin_footer', array( self::class, 'render_settings_section_fallback' ) );
	}

	public static function enqueue_settings_assets( string $hook ): void {
		if ( 'choir_song_page_choir-rehearsal-settings' !== $hook || ! self::can_manage_backup() ) {
			return;
		}

		$script = CHOIR_REHEARSAL_PRO_PATH . 'admin/js/backup-import.js';
		if ( ! is_readable( $script ) ) {
			return;
		}

		wp_enqueue_script(
			'choir-rehearsal-pro-backup-import',
			plugins_url( 'admin/js/backup-import.js', CHOIR_REHEARSAL_PRO_FILE ),
			array( 'jquery' ),
			defined( 'CHOIR_REHEARSAL_PRO_VERSION' ) ? (string) CHOIR_REHEARSAL_PRO_VERSION : '1',
			true
		);
		wp_localize_script(
			'choir-rehearsal-pro-backup-import',
			'choirRehearsalProBackup',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'choir_rehearsal_pro_import_chunks' ),
				'chunkBytes' => self::chunk_bytes(),
				'postMax'    => self::post_max_bytes(),
				'uploadMax'  => self::upload_max_bytes(),
				'i18n'       => array(
					'uploading' => __( 'Uploading…', 'choir-rehearsal-pro' ),
					'importing' => __( 'Importing…', 'choir-rehearsal-pro' ),
					'failed'    => __( 'Import failed.', 'choir-rehearsal-pro' ),
					'noFile'    => __( 'Choose a backup .zip file.', 'choir-rehearsal-pro' ),
				),
			)
		);
	}

	public static function render_settings_section_fallback(): void {
		if ( self::$section_rendered ) {
			return;
		}

		echo '<div class="wrap" style="max-width:720px;">';
		self::render_settings_section();
		echo '</div>';
	}

	public static function maybe_notice_lite_too_old(): void {
		if ( ! self::can_manage_backup() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'choir_song_page_choir-rehearsal-settings' !== $screen->id ) {
			return;
		}

		if ( ! defined( 'CHOIR_REHEARSAL_VERSION' ) ) {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Backup songs needs Compath Choir Rehearsal (Lite) active and fully loaded.', 'choir-rehearsal-pro' );
			echo '</p></div>';
			return;
		}

		if ( version_compare( (string) CHOIR_REHEARSAL_VERSION, '0.4.45', '<' ) ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: installed Lite version */
					__( 'Backup songs needs Lite 0.4.45 or newer (you have %s). Update Compath Choir Rehearsal, then reload Settings.', 'choir-rehearsal-pro' ),
					(string) CHOIR_REHEARSAL_VERSION
				)
			);
			echo '</p></div>';
		}
	}

	private static function can_manage_backup(): bool {
		return current_user_can( 'manage_options' ) && class_exists( 'Choir_Rehearsal_Edition', false ) && Choir_Rehearsal_Edition::is_pro();
	}

	public static function render_settings_section(): void {
		if ( self::$section_rendered ) {
			return;
		}

		if ( ! self::can_manage_backup() ) {
			return;
		}

		if ( ! defined( 'CHOIR_REHEARSAL_VERSION' ) || ! class_exists( 'Choir_Rehearsal_Post_Types', false ) ) {
			return;
		}

		self::$section_rendered = true;

		self::render_notices();

		$zip_ok = class_exists( 'ZipArchive' );
		$song_count = self::count_songs();
		?>
		<hr />
		<h2><?php esc_html_e( 'Backup songs', 'choir-rehearsal-pro' ); ?></h2>
		<?php if ( ! $zip_ok ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'ZIP backup requires the PHP Zip extension (ZipArchive). Ask your host to enable it.', 'choir-rehearsal-pro' ); ?></p></div>
		<?php else : ?>
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
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: post_max_size, 2: upload_max_filesize */
						__( 'Large backups upload in small chunks (avoids PHP post_max_size / upload_max_filesize limits). Server single-request limits: post_max_size %1$s, upload_max_filesize %2$s.', 'choir-rehearsal-pro' ),
						size_format( self::post_max_bytes() ),
						size_format( self::upload_max_bytes() )
					)
				);
				?>
			</p>
			<p id="choir-rehearsal-pro-import-status" class="notice inline" style="display:none;padding:8px 12px;"></p>
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
		<?php endif; ?>
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

		$content_length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		$post_max       = self::post_max_bytes();
		if ( $content_length > 0 && $post_max > 0 && $content_length > $post_max ) {
			self::redirect_error(
				sprintf(
					/* translators: 1: uploaded bytes, 2: post_max_size */
					__( 'Backup is too large for a single upload (%1$s > post_max_size %2$s). Use the Import button with JavaScript enabled — large files upload in chunks automatically.', 'choir-rehearsal-pro' ),
					size_format( $content_length ),
					size_format( $post_max )
				)
			);
		}

		if ( empty( $_FILES['backup_zip']['tmp_name'] ) || ! is_uploaded_file( (string) $_FILES['backup_zip']['tmp_name'] ) ) {
			$err = isset( $_FILES['backup_zip']['error'] ) ? (int) $_FILES['backup_zip']['error'] : UPLOAD_ERR_NO_FILE;
			if ( UPLOAD_ERR_INI_SIZE === $err || UPLOAD_ERR_FORM_SIZE === $err ) {
				self::redirect_error(
					sprintf(
						/* translators: %s: upload_max_filesize */
						__( 'Backup exceeds the server upload_max_filesize (%s). Large files upload in chunks automatically when JavaScript is enabled.', 'choir-rehearsal-pro' ),
						size_format( self::upload_max_bytes() )
					)
				);
			}
			self::redirect_error( __( 'No backup file was uploaded.', 'choir-rehearsal-pro' ) );
		}

		$mode = isset( $_POST['match_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['match_mode'] ) ) : 'skip';
		if ( ! in_array( $mode, array( 'skip', 'replace' ), true ) ) {
			$mode = 'skip';
		}

		$result = self::import_zip_path( (string) $_FILES['backup_zip']['tmp_name'], $mode );
		if ( is_wp_error( $result ) ) {
			self::redirect_error( $result->get_error_message() );
		}

		self::redirect_imported( $result );
	}

	public static function ajax_import_chunk(): void {
		if ( ! self::can_manage_backup() ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to import songs.', 'choir-rehearsal-pro' ) ), 403 );
		}
		check_ajax_referer( 'choir_rehearsal_pro_import_chunks', 'nonce' );

		$upload_id = isset( $_POST['upload_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['upload_id'] ) ) : '';
		$index     = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;
		$total     = isset( $_POST['total'] ) ? (int) $_POST['total'] : 0;
		if ( ! preg_match( '/^[a-f0-9]{16,64}$/', $upload_id ) || $index < 0 || $total < 1 || $index >= $total ) {
			wp_send_json_error( array( 'message' => __( 'Invalid upload chunk.', 'choir-rehearsal-pro' ) ), 400 );
		}

		if ( empty( $_FILES['chunk']['tmp_name'] ) || ! is_uploaded_file( (string) $_FILES['chunk']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Chunk upload failed.', 'choir-rehearsal-pro' ) ), 400 );
		}

		$dir = self::import_temp_dir();
		if ( '' === $dir ) {
			wp_send_json_error( array( 'message' => __( 'Could not create a temporary upload folder.', 'choir-rehearsal-pro' ) ), 500 );
		}

		$part = $dir . '/' . $upload_id . '.part';
		$meta = $dir . '/' . $upload_id . '.json';
		if ( 0 === $index && file_exists( $part ) ) {
			wp_delete_file( $part );
		}

		$chunk_bin = file_get_contents( (string) $_FILES['chunk']['tmp_name'] );
		if ( false === $chunk_bin ) {
			wp_send_json_error( array( 'message' => __( 'Could not read uploaded chunk.', 'choir-rehearsal-pro' ) ), 500 );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $part, $chunk_bin, FILE_APPEND );
		if ( false === $written ) {
			wp_send_json_error( array( 'message' => __( 'Could not save upload chunk.', 'choir-rehearsal-pro' ) ), 500 );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents(
			$meta,
			wp_json_encode(
				array(
					'total'   => $total,
					'received'=> $index + 1,
					'updated' => time(),
				)
			)
		);

		wp_send_json_success(
			array(
				'index' => $index,
				'total' => $total,
			)
		);
	}

	public static function ajax_import_finish(): void {
		if ( ! self::can_manage_backup() ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to import songs.', 'choir-rehearsal-pro' ) ), 403 );
		}
		check_ajax_referer( 'choir_rehearsal_pro_import_chunks', 'nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_send_json_error( array( 'message' => __( 'ZIP import is not available on this server.', 'choir-rehearsal-pro' ) ), 500 );
		}

		$upload_id = isset( $_POST['upload_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['upload_id'] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{16,64}$/', $upload_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid upload id.', 'choir-rehearsal-pro' ) ), 400 );
		}

		$mode = isset( $_POST['match_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['match_mode'] ) ) : 'skip';
		if ( ! in_array( $mode, array( 'skip', 'replace' ), true ) ) {
			$mode = 'skip';
		}

		$dir  = self::import_temp_dir();
		$part = $dir . '/' . $upload_id . '.part';
		$meta = $dir . '/' . $upload_id . '.json';
		$zip_path = $dir . '/' . $upload_id . '.zip';
		if ( ! is_readable( $part ) ) {
			wp_send_json_error( array( 'message' => __( 'Uploaded backup not found. Try again.', 'choir-rehearsal-pro' ) ), 400 );
		}

		if ( file_exists( $zip_path ) ) {
			wp_delete_file( $zip_path );
		}
		if ( ! @rename( $part, $zip_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_send_json_error( array( 'message' => __( 'Could not finalize the uploaded backup.', 'choir-rehearsal-pro' ) ), 500 );
		}

		$result = self::import_zip_path( $zip_path, $mode );
		wp_delete_file( $zip_path );
		if ( is_readable( $meta ) ) {
			wp_delete_file( $meta );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'redirect' => self::imported_redirect_url( $result ),
			)
		);
	}

	/**
	 * @return array{songs:int,tracks:int,skipped:int,replaced:int}|WP_Error
	 */
	private static function import_zip_path( string $zip_path, string $mode ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return new WP_Error( 'choir_backup_open', __( 'Could not open the backup archive.', 'choir-rehearsal-pro' ) );
		}

		$manifest_raw = $zip->getFromName( 'manifest.json' );
		if ( ! is_string( $manifest_raw ) || '' === $manifest_raw ) {
			$zip->close();
			return new WP_Error( 'choir_backup_manifest', __( 'Invalid backup: manifest.json is missing.', 'choir-rehearsal-pro' ) );
		}

		$manifest = json_decode( $manifest_raw, true );
		if ( ! is_array( $manifest ) || ( $manifest['format'] ?? '' ) !== self::FORMAT_ID ) {
			$zip->close();
			return new WP_Error( 'choir_backup_format', __( 'Invalid backup format.', 'choir-rehearsal-pro' ) );
		}

		$result = self::import_from_manifest( $zip, $manifest, $mode );
		$zip->close();
		return $result;
	}

	/**
	 * @param array{songs:int,tracks:int,skipped:int,replaced:int} $result
	 */
	private static function redirect_imported( array $result ): void {
		wp_safe_redirect( self::imported_redirect_url( $result ) );
		exit;
	}

	/**
	 * @param array{songs:int,tracks:int,skipped:int,replaced:int} $result
	 */
	private static function imported_redirect_url( array $result ): string {
		return add_query_arg(
			array(
				'post_type'             => Choir_Rehearsal_Post_Types::SONG,
				'page'                  => 'choir-rehearsal-settings',
				'choir_backup_imported' => '1',
				'choir_backup_songs'    => (string) $result['songs'],
				'choir_backup_tracks'   => (string) $result['tracks'],
				'choir_backup_skipped'  => (string) $result['skipped'],
				'choir_backup_replaced' => (string) $result['replaced'],
			),
			admin_url( 'edit.php' )
		);
	}

	private static function chunk_bytes(): int {
		$limit = min( self::post_max_bytes(), self::upload_max_bytes() );
		if ( $limit <= 0 ) {
			return 2 * MB_IN_BYTES;
		}
		// Leave headroom for multipart fields/overhead.
		$safe = (int) max( 256 * KB_IN_BYTES, min( 2 * MB_IN_BYTES, (int) floor( $limit * 0.5 ) ) );
		return $safe;
	}

	private static function post_max_bytes(): int {
		return self::ini_bytes( (string) ini_get( 'post_max_size' ) );
	}

	private static function upload_max_bytes(): int {
		return self::ini_bytes( (string) ini_get( 'upload_max_filesize' ) );
	}

	private static function ini_bytes( string $value ): int {
		$value = trim( $value );
		if ( '' === $value || '0' === $value ) {
			return 0;
		}
		$unit = strtolower( substr( $value, -1 ) );
		$num  = (float) $value;
		switch ( $unit ) {
			case 'g':
				$num *= GB_IN_BYTES;
				break;
			case 'm':
				$num *= MB_IN_BYTES;
				break;
			case 'k':
				$num *= KB_IN_BYTES;
				break;
			default:
				$num = (float) $value;
		}
		return (int) $num;
	}

	private static function import_temp_dir(): string {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return '';
		}
		$dir = trailingslashit( $upload['basedir'] ) . 'choir-rehearsal-import';
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Deny from all\n" );
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		return $dir;
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
