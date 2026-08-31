<?php
/**
 * Admin screens and track management.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Admin {

	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Choir_Rehearsal_Post_Types::SONG, array( self::class, 'save_song' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_menu', array( self::class, 'register_settings_page' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_filter( 'manage_' . Choir_Rehearsal_Post_Types::SONG . '_posts_columns', array( self::class, 'song_columns' ) );
		add_action( 'manage_' . Choir_Rehearsal_Post_Types::SONG . '_posts_custom_column', array( self::class, 'render_song_column' ), 10, 2 );
	}

	public static function register_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Choir_Rehearsal_Post_Types::SONG,
			__( 'Settings', 'choir-rehearsal' ),
			__( 'Settings', 'choir-rehearsal' ),
			'manage_options',
			'choir-rehearsal-settings',
			array( self::class, 'render_settings_page' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'choir_rehearsal_settings',
			'choir_rehearsal_require_login',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => static fn( $value ) => (bool) $value,
				'default'           => true,
			)
		);

		Choir_Rehearsal_Pages::register_settings();
		Choir_Rehearsal_Updater::register_settings();
	}

	public static function render_settings_page(): void {
		if ( isset( $_GET['choir_rewrites_flushed'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Permalinks refreshed and rehearsal page verified.', 'choir-rehearsal' ) . '</p></div>';
		}

		$library_page_id = Choir_Rehearsal_Pages::get_page_id();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Choir Rehearsal Settings', 'choir-rehearsal' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'choir_rehearsal_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Plugin version', 'choir-rehearsal' ); ?></th>
						<td>
							<code><?php echo esc_html( CHOIR_REHEARSAL_VERSION ); ?></code>
							<p class="description">
								<?php
								echo wp_kses_post(
									sprintf(
										/* translators: %s: GitHub releases link */
										__( 'Updates are published at %s', 'choir-rehearsal' ),
										'<a href="https://github.com/compathee/compathee/releases" target="_blank" rel="noopener noreferrer">GitHub Releases</a>'
									)
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rehearsal page', 'choir-rehearsal' ); ?></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => Choir_Rehearsal_Pages::OPTION_PAGE_ID,
									'selected'          => $library_page_id,
									'show_option_none'  => __( '— Select —', 'choir-rehearsal' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'WordPress page that shows the song list. Must contain the [choir_rehearsal] shortcode. You can add this page to your site menu under Appearance → Menus.', 'choir-rehearsal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require login', 'choir-rehearsal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="choir_rehearsal_require_login" value="1" <?php checked( Choir_Rehearsal_Access::requires_login() ); ?> />
								<?php esc_html_e( 'Only logged-in users can view rehearsal pages.', 'choir-rehearsal' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Update JSON URL', 'choir-rehearsal' ); ?></th>
						<td>
							<input type="url" class="regular-text" name="choir_rehearsal_update_json_url" value="<?php echo esc_attr( (string) get_option( 'choir_rehearsal_update_json_url', '' ) ); ?>" placeholder="https://raw.githubusercontent.com/compathee/compathee/main/choir-rehearsal/update.json" />
							<p class="description"><?php esc_html_e( 'Optional. If empty, the plugin checks GitHub Releases. Fallback JSON: choir-rehearsal/update.json in the repository.', 'choir-rehearsal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'GitHub repository', 'choir-rehearsal' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="choir_rehearsal_github_repo" value="<?php echo esc_attr( (string) get_option( 'choir_rehearsal_github_repo', 'compathee/compathee' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Used when Update JSON URL is empty. Release asset must be named choir-rehearsal.zip.', 'choir-rehearsal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'License key', 'choir-rehearsal' ); ?></th>
						<td>
							<input type="password" class="regular-text" name="choir_rehearsal_license_key" value="<?php echo esc_attr( (string) get_option( 'choir_rehearsal_license_key', '' ) ); ?>" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Optional. Sent as Bearer token when requesting Update JSON URL (for paid customers).', 'choir-rehearsal' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( Choir_Rehearsal_Updater::get_check_updates_url() ); ?>">
					<?php esc_html_e( 'Check for updates now', 'choir-rehearsal' ); ?>
				</a>
				<a class="button button-secondary" href="<?php echo esc_url( Choir_Rehearsal_Pages::get_flush_rewrites_url() ); ?>">
					<?php esc_html_e( 'Refresh permalinks', 'choir-rehearsal' ); ?>
				</a>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: library page URL */
					esc_html__( 'Song list URL: %s', 'choir-rehearsal' ),
					'<code>' . esc_html( Choir_Rehearsal_Pages::get_library_url() ) . '</code>'
				);
				?>
			</p>
			<?php if ( $library_page_id > 0 ) : ?>
				<p>
					<a href="<?php echo esc_url( get_edit_post_link( $library_page_id, 'raw' ) ?: '' ); ?>"><?php esc_html_e( 'Edit rehearsal page', 'choir-rehearsal' ); ?></a>
					|
					<a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Appearance → Menus', 'choir-rehearsal' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function song_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['choir_tracks'] = __( 'Tracks', 'choir-rehearsal' );
				$new['choir_score']  = __( 'Score', 'choir-rehearsal' );
			}
		}
		return $new;
	}

	public static function render_song_column( string $column, int $post_id ): void {
		if ( 'choir_tracks' === $column ) {
			echo esc_html( (string) count( Choir_Rehearsal_Post_Types::get_tracks_for_song( $post_id ) ) );
			return;
		}

		if ( 'choir_score' === $column ) {
			echo Choir_Rehearsal_Post_Types::get_score_pdf_id( $post_id ) > 0 ? 'PDF' : '—';
		}
	}

	public static function add_meta_boxes(): void {
		add_meta_box(
			'choir-rehearsal-score',
			__( 'Sheet Music (PDF)', 'choir-rehearsal' ),
			array( self::class, 'render_score_metabox' ),
			Choir_Rehearsal_Post_Types::SONG,
			'normal',
			'high'
		);

		add_meta_box(
			'choir-rehearsal-tracks',
			__( 'Voice Tracks', 'choir-rehearsal' ),
			array( self::class, 'render_tracks_metabox' ),
			Choir_Rehearsal_Post_Types::SONG,
			'normal',
			'high'
		);
	}

	public static function enqueue_assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Choir_Rehearsal_Post_Types::SONG !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'choir-rehearsal-admin',
			CHOIR_REHEARSAL_URL . 'admin/css/admin.css',
			array(),
			CHOIR_REHEARSAL_VERSION
		);
		wp_enqueue_script(
			'choir-rehearsal-admin',
			CHOIR_REHEARSAL_URL . 'admin/js/admin.js',
			array( 'jquery', 'wp-util' ),
			CHOIR_REHEARSAL_VERSION,
			true
		);
		wp_localize_script(
			'choir-rehearsal-admin',
			'choirRehearsalAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'postId'         => $screen && 'post' === $screen->base ? (int) get_the_ID() : 0,
				'recordingNonce' => wp_create_nonce( 'choir_rehearsal_recording' ),
				'voices'         => Choir_Rehearsal_Voice_Types::choices(),
				'selectAudio'    => __( 'Upload', 'choir-rehearsal' ),
				'recordAudio'    => __( 'Record', 'choir-rehearsal' ),
				'useAudio'       => __( 'Use this audio', 'choir-rehearsal' ),
				'removeTrack'    => __( 'Remove', 'choir-rehearsal' ),
				'trackLabel'     => __( 'Track', 'choir-rehearsal' ),
				'noAudio'        => __( 'No audio selected', 'choir-rehearsal' ),
				'selectPdf'      => __( 'Select PDF', 'choir-rehearsal' ),
				'usePdf'         => __( 'Use this PDF', 'choir-rehearsal' ),
				'noPdf'          => __( 'No PDF selected', 'choir-rehearsal' ),
				'removePdf'      => __( 'Remove PDF', 'choir-rehearsal' ),
				'startRecording' => __( 'Start recording', 'choir-rehearsal' ),
				'stopRecording'  => __( 'Stop', 'choir-rehearsal' ),
				'useRecording'   => __( 'Use recording', 'choir-rehearsal' ),
				'cancelRecording'=> __( 'Cancel', 'choir-rehearsal' ),
				'recording'      => __( 'Recording…', 'choir-rehearsal' ),
				'readyToRecord'  => __( 'Click start and sing your voice part.', 'choir-rehearsal' ),
				'uploading'      => __( 'Uploading…', 'choir-rehearsal' ),
				'micDenied'      => __( 'Microphone access was denied.', 'choir-rehearsal' ),
				'micUnavailable' => __( 'Microphone recording is not supported in this browser.', 'choir-rehearsal' ),
				'uploadFailed'   => __( 'Upload failed. Please try again.', 'choir-rehearsal' ),
				'saveSongFirst'  => __( 'Save the song first, then you can record voice tracks.', 'choir-rehearsal' ),
			)
		);
	}

	public static function render_score_metabox( WP_Post $post ): void {
		wp_nonce_field( 'choir_rehearsal_save_score', 'choir_rehearsal_score_nonce' );
		$pdf_id   = Choir_Rehearsal_Post_Types::get_score_pdf_id( (int) $post->ID );
		$filename = '';
		if ( $pdf_id > 0 ) {
			$file = get_attached_file( $pdf_id );
			$filename = $file ? basename( $file ) : get_the_title( $pdf_id );
		}
		?>
		<div class="choir-score-wrap">
			<p class="description"><?php esc_html_e( 'Upload a PDF score for this song. Singers will see it with page navigation on the song page.', 'choir-rehearsal' ); ?></p>
			<input type="hidden" id="choir-score-pdf-id" name="choir_score_pdf_id" value="<?php echo esc_attr( (string) $pdf_id ); ?>" />
			<p>
				<span id="choir-score-pdf-name" class="choir-score-pdf-name"><?php echo esc_html( $filename ?: __( 'No PDF selected', 'choir-rehearsal' ) ); ?></span>
			</p>
			<p>
				<button type="button" class="button" id="choir-select-pdf"><?php esc_html_e( 'Upload / Select PDF', 'choir-rehearsal' ); ?></button>
				<button type="button" class="button-link-delete" id="choir-remove-pdf"><?php esc_html_e( 'Remove PDF', 'choir-rehearsal' ); ?></button>
			</p>
		</div>
		<?php
	}

	public static function render_tracks_metabox( WP_Post $post ): void {
		wp_nonce_field( 'choir_rehearsal_save_tracks', 'choir_rehearsal_tracks_nonce' );
		$tracks = Choir_Rehearsal_Post_Types::get_tracks_for_song( (int) $post->ID );
		$voices = Choir_Rehearsal_Voice_Types::choices();
		?>
		<div class="choir-tracks-wrap">
			<p class="description"><?php esc_html_e( 'Add one row per voice part. Upload an audio file, record from your microphone, or pick one from the Media Library.', 'choir-rehearsal' ); ?></p>
			<table class="widefat choir-tracks-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Voice', 'choir-rehearsal' ); ?></th>
						<th><?php esc_html_e( 'Audio', 'choir-rehearsal' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'choir-rehearsal' ); ?></th>
					</tr>
				</thead>
				<tbody id="choir-tracks-body">
					<?php if ( empty( $tracks ) ) : ?>
						<?php self::render_track_row( 0, '', 0, $voices ); ?>
					<?php else : ?>
						<?php foreach ( $tracks as $index => $track ) : ?>
							<?php
							self::render_track_row(
								$index,
								(string) get_post_meta( $track->ID, '_choir_voice_slug', true ),
								(int) get_post_meta( $track->ID, '_choir_audio_id', true ),
								$voices,
								(int) $track->ID
							);
							?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="choir-add-track"><?php esc_html_e( 'Add track', 'choir-rehearsal' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * @param array<string, string> $voices
	 */
	private static function render_track_row( int $index, string $voice_slug, int $audio_id, array $voices, int $track_id = 0 ): void {
		$filename = '';
		if ( $audio_id > 0 ) {
			$file = get_attached_file( $audio_id );
			$filename = $file ? basename( $file ) : '';
		}
		?>
		<tr class="choir-track-row">
			<td>
				<input type="hidden" name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( (string) $track_id ); ?>" />
				<select class="choir-voice-select" name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][voice]">
					<?php foreach ( $voices as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $voice_slug, $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td class="choir-track-audio-cell">
				<input type="hidden" class="choir-audio-id" name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][audio_id]" value="<?php echo esc_attr( (string) $audio_id ); ?>" />
				<div class="choir-track-audio-controls">
					<span class="choir-audio-name"><?php echo esc_html( $filename ?: __( 'No audio selected', 'choir-rehearsal' ) ); ?></span>
					<button type="button" class="button button-small choir-select-audio"><?php esc_html_e( 'Upload', 'choir-rehearsal' ); ?></button>
					<button type="button" class="button button-small choir-record-audio"><?php esc_html_e( 'Record', 'choir-rehearsal' ); ?></button>
				</div>
				<div class="choir-recorder-panel is-hidden" aria-hidden="true">
					<p class="choir-recorder-panel__status"><?php esc_html_e( 'Click start and sing your voice part.', 'choir-rehearsal' ); ?></p>
					<p class="choir-recorder-panel__timer">00:00</p>
					<audio class="choir-recorder-panel__preview" controls hidden></audio>
					<div class="choir-recorder-panel__actions">
						<button type="button" class="button button-primary choir-recorder-start"><?php esc_html_e( 'Start recording', 'choir-rehearsal' ); ?></button>
						<button type="button" class="button choir-recorder-stop" disabled><?php esc_html_e( 'Stop', 'choir-rehearsal' ); ?></button>
						<button type="button" class="button button-primary choir-recorder-use" disabled><?php esc_html_e( 'Use recording', 'choir-rehearsal' ); ?></button>
						<button type="button" class="button choir-recorder-cancel"><?php esc_html_e( 'Cancel', 'choir-rehearsal' ); ?></button>
					</div>
				</div>
			</td>
			<td>
				<button type="button" class="button-link-delete choir-remove-track"><?php esc_html_e( 'Remove', 'choir-rehearsal' ); ?></button>
			</td>
		</tr>
		<?php
	}

	public static function save_song( int $post_id, WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['choir_rehearsal_score_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['choir_rehearsal_score_nonce'] ) ), 'choir_rehearsal_save_score' ) ) {
			$pdf_id = isset( $_POST['choir_score_pdf_id'] ) ? absint( wp_unslash( $_POST['choir_score_pdf_id'] ) ) : 0;
			if ( $pdf_id > 0 && 'application/pdf' === get_post_mime_type( $pdf_id ) ) {
				update_post_meta( $post_id, '_choir_score_pdf_id', $pdf_id );
			} else {
				delete_post_meta( $post_id, '_choir_score_pdf_id' );
			}
		}

		if ( ! isset( $_POST['choir_rehearsal_tracks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['choir_rehearsal_tracks_nonce'] ) ), 'choir_rehearsal_save_tracks' ) ) {
			return;
		}

		$submitted = isset( $_POST['choir_tracks'] ) && is_array( $_POST['choir_tracks'] )
			? wp_unslash( $_POST['choir_tracks'] )
			: array();

		$existing = Choir_Rehearsal_Post_Types::get_tracks_for_song( $post_id );
		$keep_ids = array();

		foreach ( $submitted as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$track_id  = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$voice     = isset( $row['voice'] ) ? sanitize_key( $row['voice'] ) : 'other';
			$audio_id  = isset( $row['audio_id'] ) ? absint( $row['audio_id'] ) : 0;
			$voice_lbl = Choir_Rehearsal_Voice_Types::get_label( $voice );

			if ( $audio_id <= 0 ) {
				continue;
			}

			$track_data = array(
				'post_type'   => Choir_Rehearsal_Post_Types::TRACK,
				'post_status' => 'publish',
				'post_parent' => $post_id,
				'post_title'  => sprintf(
					/* translators: 1: song title, 2: voice label */
					__( '%1$s — %2$s', 'choir-rehearsal' ),
					$post->post_title,
					$voice_lbl
				),
				'menu_order'  => (int) $index,
			);

			if ( $track_id > 0 ) {
				$track_data['ID'] = $track_id;
				$new_id           = wp_update_post( $track_data, true );
			} else {
				$new_id = wp_insert_post( $track_data, true );
			}

			if ( is_wp_error( $new_id ) || ! $new_id ) {
				continue;
			}

			update_post_meta( (int) $new_id, '_choir_voice_slug', $voice );
			update_post_meta( (int) $new_id, '_choir_audio_id', $audio_id );
			$keep_ids[] = (int) $new_id;
		}

		foreach ( $existing as $track ) {
			if ( ! in_array( (int) $track->ID, $keep_ids, true ) ) {
				wp_delete_post( (int) $track->ID, true );
			}
		}
	}
}
