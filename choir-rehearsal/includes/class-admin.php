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
		add_action( 'init', array( self::class, 'remove_song_editor_support' ), 20 );
		add_filter( 'use_block_editor_for_post_type', array( self::class, 'disable_block_editor' ), 10, 2 );
		add_action( 'add_meta_boxes', array( self::class, 'add_meta_boxes' ), 10 );
		add_action( 'add_meta_boxes', array( self::class, 'remove_meta_boxes' ), 100 );
		add_filter( 'admin_body_class', array( self::class, 'admin_body_class' ) );
		add_action( 'edit_form_top', array( self::class, 'render_back_to_list_link' ) );
		add_action( 'edit_form_after_title', array( self::class, 'render_edit_intro' ) );
		add_action( 'post_submitbox_start', array( self::class, 'render_submitbox_back_link' ) );
		add_action( 'save_post_' . Choir_Rehearsal_Post_Types::SONG, array( self::class, 'save_song' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( self::class, 'render_song_editor_player' ) );
		add_action( 'admin_menu', array( self::class, 'register_settings_page' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CHOIR_REHEARSAL_FILE ), array( self::class, 'plugin_action_links' ) );
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

		Choir_Rehearsal_Demo_Data::render_settings_notices();

		$library_page_id = Choir_Rehearsal_Pages::get_page_id();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Choir Rehearsal Settings', 'choir-rehearsal' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'choir_rehearsal_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Documentation', 'choir-rehearsal' ); ?></th>
						<td>
							<a href="<?php echo esc_url( CHOIR_REHEARSAL_DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Product page: order, install, pricing, changelog', 'choir-rehearsal' ); ?>
							</a>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Edition', 'choir-rehearsal' ); ?></th>
						<td>
							<code><?php echo esc_html( Choir_Rehearsal_Edition::edition_label() ); ?></code>
							<?php if ( ! Choir_Rehearsal_Edition::is_pro() ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %d: maximum track count */
									esc_html__( 'Lite: up to %d voice tracks per song; no microphone recording, song search, editor Play, or embedded PDF preview.', 'choir-rehearsal' ),
									Choir_Rehearsal_Edition::LITE_MAX_TRACKS
									);
									?>
								</p>
								<p>
									<a class="button button-primary" href="<?php echo esc_url( Choir_Rehearsal_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Buy Pro', 'choir-rehearsal' ); ?>
									</a>
								</p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Unlimited voice tracks, microphone recording, song search, Play preview, and embedded PDF in the editor.', 'choir-rehearsal' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
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
				</table>
				<?php submit_button(); ?>
			</form>
			<?php if ( ! Choir_Rehearsal_Edition::is_pro() ) : ?>
				<div class="choir-buy-pro-banner">
					<p>
						<strong><?php esc_html_e( 'Choir Rehearsal Pro', 'choir-rehearsal' ); ?></strong>
						<?php esc_html_e( 'Unlimited tracks, microphone recording, search by song title, Play preview, and embedded PDF in the editor. Keep this Lite plugin installed — Pro is a separate add-on.', 'choir-rehearsal' ); ?>
					</p>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( Choir_Rehearsal_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Buy Pro', 'choir-rehearsal' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( Choir_Rehearsal_Updater::get_check_updates_url() ); ?>">
					<?php esc_html_e( 'Check for updates now', 'choir-rehearsal' ); ?>
				</a>
				<a class="button button-secondary" href="<?php echo esc_url( Choir_Rehearsal_Pages::get_flush_rewrites_url() ); ?>">
					<?php esc_html_e( 'Refresh permalinks', 'choir-rehearsal' ); ?>
				</a>
			</p>
			<?php Choir_Rehearsal_Demo_Data::render_settings_buttons(); ?>
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

	/**
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public static function plugin_action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'edit.php?post_type=' . Choir_Rehearsal_Post_Types::SONG . '&page=choir-rehearsal-settings' ) ),
			esc_html__( 'Settings', 'choir-rehearsal' )
		);

		$extra = array( 'settings' => $settings );

		if ( ! Choir_Rehearsal_Edition::is_pro() ) {
			$extra['buy_pro'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" style="font-weight:600;">%s</a>',
				esc_url( Choir_Rehearsal_Edition::upgrade_url() ),
				esc_html__( 'Buy Pro', 'choir-rehearsal' )
			);
		}

		return array_merge( $extra, $links );
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

	public static function remove_song_editor_support(): void {
		$remove = array( 'editor', 'excerpt', 'thumbnail', 'comments', 'trackbacks', 'custom-fields', 'revisions', 'author' );
		foreach ( $remove as $feature ) {
			remove_post_type_support( Choir_Rehearsal_Post_Types::SONG, $feature );
		}
	}

	public static function disable_block_editor( bool $use_block_editor, string $post_type ): bool {
		if ( Choir_Rehearsal_Post_Types::SONG === $post_type ) {
			return false;
		}

		return $use_block_editor;
	}

	public static function admin_body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && Choir_Rehearsal_Post_Types::SONG === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			$classes .= ' choir-rehearsal-song-edit';
		}

		return $classes;
	}

	public static function remove_meta_boxes(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Choir_Rehearsal_Post_Types::SONG !== $screen->post_type ) {
			return;
		}

		remove_meta_box( 'slugdiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'authordiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'revisionsdiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'postcustom', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'commentstatusdiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'commentsdiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'trackbacksdiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'postexcerpt', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'postimagediv', Choir_Rehearsal_Post_Types::SONG, 'side' );
		remove_meta_box( 'pageparentdiv', Choir_Rehearsal_Post_Types::SONG, 'side' );
	}

	public static function render_back_to_list_link( WP_Post $post ): void {
		if ( Choir_Rehearsal_Post_Types::SONG !== $post->post_type ) {
			return;
		}
		?>
		<p class="choir-song-back-link">
			<a class="button choir-back-to-list choir-back-to-list--top" href="<?php echo esc_url( Choir_Rehearsal_Pages::get_library_url() ); ?>">
				&larr; <?php esc_html_e( 'Back to song list', 'choir-rehearsal' ); ?>
			</a>
		</p>
		<?php
	}

	public static function render_submitbox_back_link( WP_Post $post ): void {
		if ( Choir_Rehearsal_Post_Types::SONG !== $post->post_type ) {
			return;
		}
		?>
		<div class="choir-submitbox-back">
			<a class="button choir-back-to-list choir-back-to-list--sticky" href="<?php echo esc_url( Choir_Rehearsal_Pages::get_library_url() ); ?>">
				&larr; <?php esc_html_e( 'Back to song list', 'choir-rehearsal' ); ?>
			</a>
		</div>
		<?php
	}

	public static function render_edit_intro( WP_Post $post ): void {
		if ( Choir_Rehearsal_Post_Types::SONG !== $post->post_type ) {
			return;
		}
		?>
		<p class="choir-song-edit-intro description">
			<?php
			if ( Choir_Rehearsal_Edition::can_record() ) {
				esc_html_e( 'Add the song title, upload a PDF score, then record or upload each voice part.', 'choir-rehearsal' );
			} else {
				esc_html_e( 'Add the song title, upload a PDF score, then upload each voice part (up to 4 tracks in Lite).', 'choir-rehearsal' );
			}
			?>
		</p>
		<?php
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

		if ( in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			wp_enqueue_style(
				'choir-rehearsal-admin',
				CHOIR_REHEARSAL_URL . 'admin/css/admin.css',
				array(),
				CHOIR_REHEARSAL_VERSION
			);
		}

		if ( 'post' !== $screen->base && 'post-new' !== $screen->base ) {
			return;
		}

		wp_enqueue_media();
		if ( ! wp_style_is( 'choir-rehearsal-admin', 'enqueued' ) ) {
			wp_enqueue_style(
				'choir-rehearsal-admin',
				CHOIR_REHEARSAL_URL . 'admin/css/admin.css',
				array(),
				CHOIR_REHEARSAL_VERSION
			);
		}
		// Shared song-card styles (track list, PDF viewer) on the editor screen.
		wp_enqueue_style(
			'choir-rehearsal-public',
			CHOIR_REHEARSAL_URL . 'public/css/public.css',
			array( 'choir-rehearsal-admin' ),
			CHOIR_REHEARSAL_VERSION
		);
		$admin_deps = array( 'jquery', 'wp-util' );
		if ( Choir_Rehearsal_Edition::can_view_score_in_editor() ) {
			wp_enqueue_script(
				'pdfjs',
				'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
				array(),
				'3.11.174',
				true
			);
			wp_enqueue_script(
				'choir-rehearsal-pdf',
				CHOIR_REHEARSAL_URL . 'public/js/pdf-viewer.js',
				array( 'pdfjs' ),
				CHOIR_REHEARSAL_VERSION,
				true
			);
			wp_localize_script(
				'choir-rehearsal-pdf',
				'choirRehearsalPdf',
				array(
					'workerSrc' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
				)
			);
			$admin_deps[] = 'choir-rehearsal-pdf';
		}
		wp_enqueue_script(
			'choir-rehearsal-admin',
			CHOIR_REHEARSAL_URL . 'admin/js/admin.js',
			$admin_deps,
			CHOIR_REHEARSAL_VERSION,
			true
		);
		if ( Choir_Rehearsal_Edition::can_play_in_editor() ) {
			wp_enqueue_script(
				'choir-rehearsal-player',
				CHOIR_REHEARSAL_URL . 'public/js/player.js',
				array(),
				CHOIR_REHEARSAL_VERSION,
				true
			);
			wp_localize_script(
				'choir-rehearsal-player',
				'choirRehearsalPlayer',
				array(
					'nowPlaying' => __( 'Now playing', 'choir-rehearsal' ),
					'play'       => __( 'Play', 'choir-rehearsal' ),
					'pause'      => __( 'Pause', 'choir-rehearsal' ),
					'close'      => __( 'Close player', 'choir-rehearsal' ),
				)
			);
		}
		wp_localize_script(
			'choir-rehearsal-admin',
			'choirRehearsalAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'postId'         => $screen && 'post' === $screen->base ? (int) get_the_ID() : 0,
				'recordingNonce' => wp_create_nonce( 'choir_rehearsal_recording' ),
				'voices'         => Choir_Rehearsal_Voice_Types::choices(),
				'isPro'          => Choir_Rehearsal_Edition::is_pro(),
				'canPlay'        => Choir_Rehearsal_Edition::can_play_in_editor(),
				'canViewPdf'     => Choir_Rehearsal_Edition::can_view_score_in_editor(),
				'maxTracks'      => Choir_Rehearsal_Edition::max_tracks(),
				'upgradeUrl'     => Choir_Rehearsal_Edition::upgrade_url(),
				'trackLimitMsg'  => sprintf(
					/* translators: %d: maximum track count */
					__( 'Lite edition allows up to %d voice tracks per song. Upgrade to Pro for unlimited tracks, microphone recording, Play preview, and embedded PDF in the editor.', 'choir-rehearsal' ),
					Choir_Rehearsal_Edition::LITE_MAX_TRACKS
				),
				'selectAudio'    => __( 'Upload', 'choir-rehearsal' ),
				'recordAudio'    => __( 'Record', 'choir-rehearsal' ),
				'playAudio'      => __( 'Play', 'choir-rehearsal' ),
				'useAudio'       => __( 'Use this audio', 'choir-rehearsal' ),
				'removeTrack'    => __( 'Remove', 'choir-rehearsal' ),
				'trackLabel'     => __( 'Track', 'choir-rehearsal' ),
				'noAudio'        => __( 'No audio selected', 'choir-rehearsal' ),
				'selectPdf'      => __( 'Select PDF', 'choir-rehearsal' ),
				'usePdf'         => __( 'Use this PDF', 'choir-rehearsal' ),
				'noPdf'          => __( 'No PDF selected', 'choir-rehearsal' ),
				'removePdf'      => __( 'Remove PDF', 'choir-rehearsal' ),
				'sheetMusic'     => __( 'Sheet music', 'choir-rehearsal' ),
				'prevPage'       => __( 'Previous', 'choir-rehearsal' ),
				'nextPage'       => __( 'Next', 'choir-rehearsal' ),
				'swipeHint'      => __( 'Swipe left or right to change pages', 'choir-rehearsal' ),
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

	public static function render_song_editor_player(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Choir_Rehearsal_Post_Types::SONG !== $screen->post_type ) {
			return;
		}
		if ( ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return;
		}
		if ( ! Choir_Rehearsal_Edition::can_play_in_editor() ) {
			return;
		}

		Choir_Rehearsal_Frontend::render_sticky_player();
	}

	public static function render_score_metabox( WP_Post $post ): void {
		wp_nonce_field( 'choir_rehearsal_save_score', 'choir_rehearsal_score_nonce' );
		$pdf_id   = Choir_Rehearsal_Post_Types::get_score_pdf_id( (int) $post->ID );
		$pdf_url  = Choir_Rehearsal_Post_Types::get_score_pdf_url( (int) $post->ID );
		$filename = '';
		if ( $pdf_id > 0 ) {
			$file = get_attached_file( $pdf_id );
			$filename = $file ? basename( $file ) : get_the_title( $pdf_id );
		}
		$can_view = Choir_Rehearsal_Edition::can_view_score_in_editor();
		?>
		<div class="choir-score-wrap choir-song-card-section">
			<p class="description">
				<?php
				if ( $can_view ) {
					esc_html_e( 'Upload a PDF score. It appears below like on the public song page — swipe left/right to change pages while you record.', 'choir-rehearsal' );
				} else {
					esc_html_e( 'Upload a PDF score for this song. Singers will see it with page navigation on the song page. Pro embeds the score here in the editor.', 'choir-rehearsal' );
				}
				?>
			</p>
			<input type="hidden" id="choir-score-pdf-id" name="choir_score_pdf_id" value="<?php echo esc_attr( (string) $pdf_id ); ?>" />
			<input type="hidden" id="choir-score-pdf-url" value="<?php echo esc_url( $pdf_url ); ?>" />
			<div class="choir-score-toolbar">
				<span id="choir-score-pdf-name" class="choir-score-pdf-name"><?php echo esc_html( $filename ?: __( 'No PDF selected', 'choir-rehearsal' ) ); ?></span>
				<div class="choir-score-toolbar__actions">
					<button type="button" class="button" id="choir-select-pdf"><?php esc_html_e( 'Upload / Select PDF', 'choir-rehearsal' ); ?></button>
					<button type="button" class="button-link-delete" id="choir-remove-pdf"><?php esc_html_e( 'Remove PDF', 'choir-rehearsal' ); ?></button>
				</div>
			</div>
			<?php if ( $can_view ) : ?>
				<div
					id="choir-editor-pdf-viewer"
					class="choir-pdf-viewer<?php echo '' === $pdf_url ? ' is-empty' : ''; ?>"
					data-pdf-url="<?php echo esc_url( $pdf_url ); ?>"
				>
					<div class="choir-pdf-viewer__canvas-wrap" title="<?php esc_attr_e( 'Swipe left or right to change pages', 'choir-rehearsal' ); ?>">
						<canvas class="choir-pdf-viewer__canvas"></canvas>
						<p class="choir-pdf-viewer__empty"><?php esc_html_e( 'No PDF selected yet.', 'choir-rehearsal' ); ?></p>
					</div>
					<div class="choir-pdf-viewer__controls">
						<button type="button" class="choir-pdf-prev" aria-label="<?php esc_attr_e( 'Previous page', 'choir-rehearsal' ); ?>">&larr; <?php esc_html_e( 'Previous', 'choir-rehearsal' ); ?></button>
						<span class="choir-pdf-page">1 / 1</span>
						<button type="button" class="choir-pdf-next" aria-label="<?php esc_attr_e( 'Next page', 'choir-rehearsal' ); ?>"><?php esc_html_e( 'Next', 'choir-rehearsal' ); ?> &rarr;</button>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_tracks_metabox( WP_Post $post ): void {
		wp_nonce_field( 'choir_rehearsal_save_tracks', 'choir_rehearsal_tracks_nonce' );
		$tracks = Choir_Rehearsal_Post_Types::get_tracks_for_song( (int) $post->ID );
		$voices = Choir_Rehearsal_Voice_Types::choices();
		?>
		<div class="choir-tracks-wrap choir-song-card-section">
			<p class="description">
				<?php
				if ( Choir_Rehearsal_Edition::can_record() ) {
					esc_html_e( 'Voice parts look like the public song page. Use the icons on the right to upload, record, or play.', 'choir-rehearsal' );
				} else {
					echo wp_kses_post(
						sprintf(
							/* translators: 1: max tracks, 2: upgrade link HTML */
							__( 'Add one voice part per row (up to %1$d in Lite). Upload audio with the icon on the right. %2$s', 'choir-rehearsal' ),
							Choir_Rehearsal_Edition::LITE_MAX_TRACKS,
							'<a class="button button-small" href="' . esc_url( Choir_Rehearsal_Edition::upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Buy Pro', 'choir-rehearsal' ) . '</a>'
						)
					);
				}
				?>
			</p>
			<ul class="choir-track-list choir-track-list--editor" id="choir-tracks-body">
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
			</ul>
			<p><button type="button" class="button" id="choir-add-track"><?php esc_html_e( 'Add track', 'choir-rehearsal' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * @param array<string, string> $voices
	 */
	private static function render_track_row( int $index, string $voice_slug, int $audio_id, array $voices, int $track_id = 0 ): void {
		$filename = '';
		$audio_url = '';
		if ( $audio_id > 0 ) {
			$file = get_attached_file( $audio_id );
			$filename = $file ? basename( $file ) : '';
			$url      = wp_get_attachment_url( $audio_id );
			$audio_url = is_string( $url ) ? $url : '';
		}

		$song_title  = get_the_title();
		$voice_label = $voices[ $voice_slug ] ?? $voice_slug;
		$play_title  = trim( (string) $song_title ) !== ''
			? $song_title . ' — ' . $voice_label
			: (string) $voice_label;
		$can_play    = '' !== $audio_url;
		?>
		<li class="choir-track-item choir-track-row">
			<input type="hidden" name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( (string) $track_id ); ?>" />
			<input type="hidden" class="choir-audio-id" name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][audio_id]" value="<?php echo esc_attr( (string) $audio_id ); ?>" />
			<div class="choir-track-item__main">
				<select class="choir-voice-select choir-track-voice" name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][voice]" aria-label="<?php esc_attr_e( 'Voice', 'choir-rehearsal' ); ?>">
					<?php foreach ( $voices as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $voice_slug, $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="choir-audio-name"><?php echo esc_html( $filename ?: __( 'No audio selected', 'choir-rehearsal' ) ); ?></span>
			</div>
			<div class="choir-track-item__actions">
				<button type="button" class="choir-icon-btn choir-select-audio" title="<?php esc_attr_e( 'Upload', 'choir-rehearsal' ); ?>" aria-label="<?php esc_attr_e( 'Upload', 'choir-rehearsal' ); ?>">
					<?php echo self::icon_svg( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
				<?php if ( Choir_Rehearsal_Edition::can_record() ) : ?>
					<button type="button" class="choir-icon-btn choir-record-audio" title="<?php esc_attr_e( 'Record', 'choir-rehearsal' ); ?>" aria-label="<?php esc_attr_e( 'Record', 'choir-rehearsal' ); ?>">
						<?php echo self::icon_svg( 'record' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
				<?php endif; ?>
				<?php if ( Choir_Rehearsal_Edition::can_play_in_editor() ) : ?>
					<button
						type="button"
						class="choir-icon-btn choir-play-track"
						title="<?php esc_attr_e( 'Play', 'choir-rehearsal' ); ?>"
						aria-label="<?php esc_attr_e( 'Play', 'choir-rehearsal' ); ?>"
						data-track-url="<?php echo esc_url( $audio_url ); ?>"
						data-track-title="<?php echo esc_attr( $play_title ); ?>"
						<?php disabled( ! $can_play ); ?>
					>
						<?php echo self::icon_svg( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
				<?php endif; ?>
				<button type="button" class="choir-icon-btn choir-icon-btn--danger choir-remove-track" title="<?php esc_attr_e( 'Remove', 'choir-rehearsal' ); ?>" aria-label="<?php esc_attr_e( 'Remove', 'choir-rehearsal' ); ?>">
					<?php echo self::icon_svg( 'remove' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
			</div>
			<?php if ( Choir_Rehearsal_Edition::can_record() ) : ?>
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
			<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * Inline SVG icons for editor track actions (trusted static markup).
	 */
	private static function icon_svg( string $name ): string {
		$icons = array(
			'upload' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 3l4.5 4.5h-3V14h-3V7.5h-3L12 3zm-7 14h14v2H5v-2z"/></svg>',
			'record' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="7" fill="currentColor"/></svg>',
			'play'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 5v14l11-7L8 5z"/></svg>',
			'remove' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.4 6.4l1.2-1.2L12 9.6l4.4-4.4 1.2 1.2L13.2 12l4.4 4.4-1.2 1.2L12 14.4l-4.4 4.4-1.2-1.2L10.8 12 6.4 6.4z"/></svg>',
		);

		return $icons[ $name ] ?? '';
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

		$max_tracks = Choir_Rehearsal_Edition::max_tracks();
		if ( $max_tracks > 0 && count( $submitted ) > $max_tracks ) {
			$submitted = array_slice( $submitted, 0, $max_tracks );
			set_transient(
				'choir_rehearsal_track_limit_' . get_current_user_id(),
				1,
				30
			);
		}

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
