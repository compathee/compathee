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
			__( 'Settings', 'compath-choir-rehearsal' ),
			__( 'Settings', 'compath-choir-rehearsal' ),
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
		Choir_Rehearsal_Feedback::register_settings();
		if ( Choir_Rehearsal_Distribution::uses_github_updater() ) {
			Choir_Rehearsal_Updater::register_settings();
		}
	}

	public static function render_settings_page(): void {
		// Display-only flag after our own settings redirect (not a form submission).
		if ( isset( $_GET['choir_rewrites_flushed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Permalinks refreshed and rehearsal page verified.', 'compath-choir-rehearsal' ) . '</p></div>';
		}

		Choir_Rehearsal_Demo_Data::render_settings_notices();

		$library_page_id = Choir_Rehearsal_Pages::get_page_id();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Choir Rehearsal Settings', 'compath-choir-rehearsal' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'choir_rehearsal_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Documentation', 'compath-choir-rehearsal' ); ?></th>
						<td>
							<a href="<?php echo esc_url( CHOIR_REHEARSAL_DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer">
								<?php
								echo esc_html(
									Choir_Rehearsal_Distribution::is_wporg()
										? __( 'Plugin documentation and changelog', 'compath-choir-rehearsal' )
										: __( 'Product page: order, install, pricing, changelog', 'compath-choir-rehearsal' )
								);
								?>
							</a>
						</td>
					</tr>
					<?php if ( Choir_Rehearsal_Edition::shows_commercial_upgrade() || Choir_Rehearsal_Edition::is_pro() ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Edition', 'compath-choir-rehearsal' ); ?></th>
						<td>
							<code><?php echo esc_html( Choir_Rehearsal_Edition::edition_label() ); ?></code>
							<?php if ( Choir_Rehearsal_Edition::shows_commercial_upgrade() ) : ?>
								<p class="description">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: maximum track count */
											__( 'Lite: up to %d voice tracks per song; no microphone recording, song search, or editor Play preview.', 'compath-choir-rehearsal' ),
											(int) Choir_Rehearsal_Edition::LITE_MAX_TRACKS
										)
									);
									?>
								</p>
								<p>
									<a class="button button-primary" href="<?php echo esc_url( Choir_Rehearsal_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Buy Pro', 'compath-choir-rehearsal' ); ?>
									</a>
								</p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Unlimited voice tracks, microphone recording, song search, and Play preview in the editor.', 'compath-choir-rehearsal' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Plugin version', 'compath-choir-rehearsal' ); ?></th>
						<td>
							<code><?php echo esc_html( CHOIR_REHEARSAL_VERSION ); ?></code>
							<p class="description">
								<?php
								if ( Choir_Rehearsal_Distribution::is_wporg() ) {
									esc_html_e( 'Updates are delivered through WordPress.org.', 'compath-choir-rehearsal' );
								} else {
									echo wp_kses_post(
										sprintf(
											/* translators: %s: GitHub releases link */
											__( 'Updates are published at %s', 'compath-choir-rehearsal' ),
											'<a href="https://github.com/compathee/compathee/releases" target="_blank" rel="noopener noreferrer">GitHub Releases</a>'
										)
									);
								}
								?>
							</p>
							<?php
							if ( Choir_Rehearsal_Distribution::uses_github_updater() ) :
								$last_check = Choir_Rehearsal_Updater::get_last_check_result();
								if ( ! empty( $last_check['time'] ) && ! empty( $last_check['status'] ) ) :
									$when = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_check['time'] );
									$remote_version = isset( $last_check['remote_version'] ) ? (string) $last_check['remote_version'] : '';
									if ( 'available' === $last_check['status'] && '' !== $remote_version ) {
										$last_summary = sprintf(
											/* translators: 1: datetime, 2: version */
											__( 'Last check: %1$s — update available (%2$s).', 'compath-choir-rehearsal' ),
											$when,
											$remote_version
										);
									} elseif ( 'up_to_date' === $last_check['status'] ) {
										$last_summary = sprintf(
											/* translators: %s: datetime */
											__( 'Last check: %s — up to date.', 'compath-choir-rehearsal' ),
											$when
										);
									} elseif ( 'failed' === $last_check['status'] ) {
										$last_summary = sprintf(
											/* translators: %s: datetime */
											__( 'Last check: %s — could not reach the update server.', 'compath-choir-rehearsal' ),
											$when
										);
									} else {
										$last_summary = sprintf(
											/* translators: %s: datetime */
											__( 'Last check: %s', 'compath-choir-rehearsal' ),
											$when
										);
									}
									?>
									<p class="description"><?php echo esc_html( $last_summary ); ?></p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rehearsal page', 'compath-choir-rehearsal' ); ?></th>
						<td>
							<?php
							$library_pages = get_pages(
								array(
									'sort_column' => 'post_title',
									'sort_order'  => 'ASC',
								)
							);
							?>
							<select name="<?php echo esc_attr( Choir_Rehearsal_Pages::OPTION_PAGE_ID ); ?>" id="<?php echo esc_attr( Choir_Rehearsal_Pages::OPTION_PAGE_ID ); ?>">
								<option value="0"><?php esc_html_e( '— Select —', 'compath-choir-rehearsal' ); ?></option>
								<?php foreach ( $library_pages as $library_page ) : ?>
									<option value="<?php echo esc_attr( (string) $library_page->ID ); ?>" <?php selected( (int) $library_page_id, (int) $library_page->ID ); ?>>
										<?php echo esc_html( $library_page->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'WordPress page that shows the song list. Must contain the [choir_rehearsal] shortcode. You can add this page to your site menu under Appearance → Menus.', 'compath-choir-rehearsal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require login', 'compath-choir-rehearsal' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="choir_rehearsal_require_login" value="1" <?php checked( Choir_Rehearsal_Access::requires_login() ); ?> />
								<?php esc_html_e( 'Only signed-in choir roles can open the full private library.', 'compath-choir-rehearsal' ); ?>
							</label>
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: Singer role name (English, not translated), 2: Voice Leader role name (English, not translated) */
										__( 'Activation adds WordPress roles %1$s (listen) and %2$s (manage songs). Assign them under Users. Settings stay Administrator-only. Public songs remain open to guests.', 'compath-choir-rehearsal' ),
										Choir_Rehearsal_Roles::LABEL_SINGER,
										Choir_Rehearsal_Roles::LABEL_VOICE_LEADER
									)
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Choir roles', 'compath-choir-rehearsal' ); ?></th>
						<td>
							<ul style="margin: 0; list-style: disc; padding-left: 1.25em;">
								<li><strong><?php echo esc_html( Choir_Rehearsal_Roles::LABEL_SINGER ); ?></strong> — <?php esc_html_e( 'browse and listen to the rehearsal library', 'compath-choir-rehearsal' ); ?></li>
								<li><strong><?php echo esc_html( Choir_Rehearsal_Roles::LABEL_VOICE_LEADER ); ?></strong> — <?php esc_html_e( 'listen plus add/edit songs in the admin', 'compath-choir-rehearsal' ); ?></li>
								<li><strong>Administrator</strong> — <?php esc_html_e( 'full access including plugin Settings', 'compath-choir-rehearsal' ); ?></li>
							</ul>
							<p class="description"><?php esc_html_e( 'Role names Singer and Voice Leader are kept in English on purpose.', 'compath-choir-rehearsal' ); ?></p>
						</td>
					</tr>
					<?php Choir_Rehearsal_Feedback::render_settings_rows(); ?>
					<?php if ( Choir_Rehearsal_Distribution::uses_github_updater() ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Update JSON URL', 'compath-choir-rehearsal' ); ?></th>
						<td>
							<input type="url" class="regular-text" name="choir_rehearsal_update_json_url" value="<?php echo esc_attr( (string) get_option( 'choir_rehearsal_update_json_url', '' ) ); ?>" placeholder="https://github.com/compathee/compathee/releases/latest/download/update.json" />
							<p class="description"><?php esc_html_e( 'Optional override. If empty, the plugin checks GitHub Releases, then falls back to the latest release asset update.json.', 'compath-choir-rehearsal' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'GitHub repository', 'compath-choir-rehearsal' ); ?></th>
						<td>
							<input type="text" class="regular-text" name="choir_rehearsal_github_repo" value="<?php echo esc_attr( (string) get_option( 'choir_rehearsal_github_repo', 'compathee/compathee' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Used when Update JSON URL is empty. Each Lite release should include compath-choir-rehearsal.zip and update.json assets.', 'compath-choir-rehearsal' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php if ( Choir_Rehearsal_Edition::shows_commercial_upgrade() ) : ?>
				<div class="choir-buy-pro-banner">
					<p>
						<strong><?php esc_html_e( 'Choir Rehearsal Pro', 'compath-choir-rehearsal' ); ?></strong>
						<?php esc_html_e( 'Unlimited tracks, microphone recording, search by song title, and Play preview in the editor. Keep this Lite plugin installed — Pro is a separate add-on.', 'compath-choir-rehearsal' ); ?>
					</p>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( Choir_Rehearsal_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Buy Pro', 'compath-choir-rehearsal' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
			<p>
				<?php if ( Choir_Rehearsal_Distribution::uses_github_updater() ) : ?>
					<a class="button button-secondary" href="<?php echo esc_url( Choir_Rehearsal_Updater::get_check_updates_url() ); ?>">
						<?php esc_html_e( 'Check for plugin updates', 'compath-choir-rehearsal' ); ?>
					</a>
					<span class="description" style="margin-left:0.5em;"><?php esc_html_e( 'Contacts GitHub Releases and shows the result on this page.', 'compath-choir-rehearsal' ); ?></span>
				<?php endif; ?>
				<a class="button button-secondary" href="<?php echo esc_url( Choir_Rehearsal_Pages::get_flush_rewrites_url() ); ?>">
					<?php esc_html_e( 'Refresh permalinks', 'compath-choir-rehearsal' ); ?>
				</a>
			</p>
			<?php Choir_Rehearsal_Demo_Data::render_settings_buttons(); ?>
			<?php do_action( 'choir_rehearsal_settings_tools' ); ?>
			<p>
				<?php
				printf(
					/* translators: %s: library page URL */
					esc_html__( 'Song list URL: %s', 'compath-choir-rehearsal' ),
					'<code>' . esc_html( Choir_Rehearsal_Pages::get_library_url() ) . '</code>'
				);
				?>
			</p>
			<?php if ( $library_page_id > 0 ) : ?>
				<p>
					<a href="<?php echo esc_url( get_edit_post_link( $library_page_id, 'raw' ) ?: '' ); ?>"><?php esc_html_e( 'Edit rehearsal page', 'compath-choir-rehearsal' ); ?></a>
					|
					<a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Appearance → Menus', 'compath-choir-rehearsal' ); ?></a>
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
			esc_html__( 'Settings', 'compath-choir-rehearsal' )
		);

		$extra = array( 'settings' => $settings );

		if ( Choir_Rehearsal_Edition::shows_commercial_upgrade() ) {
			$extra['buy_pro'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" style="font-weight:600;">%s</a>',
				esc_url( Choir_Rehearsal_Edition::upgrade_url() ),
				esc_html__( 'Buy Pro', 'compath-choir-rehearsal' )
			);
		}

		return array_merge( $extra, $links );
	}

	public static function song_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['choir_tracks']  = __( 'Tracks', 'compath-choir-rehearsal' );
				$new['choir_score']   = __( 'Score', 'compath-choir-rehearsal' );
				$new['choir_public']  = __( 'Visibility', 'compath-choir-rehearsal' );
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
			return;
		}

		if ( 'choir_public' === $column ) {
			echo esc_html(
				Choir_Rehearsal_Post_Types::is_public( $post_id )
					? __( 'Public', 'compath-choir-rehearsal' )
					: __( 'Private', 'compath-choir-rehearsal' )
			);
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

		remove_meta_box( 'authordiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'revisionsdiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'postcustom', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'commentstatusdiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'commentsdiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'trackbacksdiv', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'postexcerpt', Choir_Rehearsal_Post_Types::SONG, 'normal' );
		remove_meta_box( 'postimagediv', Choir_Rehearsal_Post_Types::SONG, 'side' );
		remove_meta_box( 'pageparentdiv', Choir_Rehearsal_Post_Types::SONG, 'side' );
		// Keep slugdiv so permalink edits remain visible and savable.
	}

	public static function render_back_to_list_link( WP_Post $post ): void {
		if ( Choir_Rehearsal_Post_Types::SONG !== $post->post_type ) {
			return;
		}
		?>
		<p class="choir-song-back-link">
			<a class="button choir-back-to-list choir-back-to-list--top" href="<?php echo esc_url( Choir_Rehearsal_Pages::get_library_url() ); ?>">
				&larr; <?php esc_html_e( 'Back to song list', 'compath-choir-rehearsal' ); ?>
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
				&larr; <?php esc_html_e( 'Back to song list', 'compath-choir-rehearsal' ); ?>
			</a>
		</div>
		<?php
	}

	public static function render_edit_intro( WP_Post $post ): void {
		if ( Choir_Rehearsal_Post_Types::SONG !== $post->post_type ) {
			return;
		}

		$is_public = Choir_Rehearsal_Post_Types::is_public( (int) $post->ID );
		?>
		<p class="choir-song-edit-intro description">
			<?php
			if ( Choir_Rehearsal_Edition::can_record() ) {
				esc_html_e( 'Add the song title, upload a PDF score, then record or upload each voice part.', 'compath-choir-rehearsal' );
			} else {
				esc_html_e( 'Add the song title, upload a PDF score, then upload each voice part (up to 4 tracks in Lite).', 'compath-choir-rehearsal' );
			}
			?>
		</p>
		<div class="choir-song-visibility">
			<?php wp_nonce_field( 'choir_rehearsal_save_visibility', 'choir_rehearsal_visibility_nonce' ); ?>
			<input type="hidden" name="choir_is_public" id="choir-is-public" value="<?php echo $is_public ? '1' : '0'; ?>" />
			<button
				type="button"
				id="choir-toggle-public"
				class="button choir-make-public<?php echo $is_public ? ' is-public' : ''; ?>"
				aria-pressed="<?php echo $is_public ? 'true' : 'false'; ?>"
				title="<?php echo esc_attr( $is_public ? __( 'Make private', 'compath-choir-rehearsal' ) : __( 'Make public', 'compath-choir-rehearsal' ) ); ?>"
			>
				<span class="choir-make-public__icon" aria-hidden="true">
					<?php echo self::icon_svg( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="choir-make-public__label">
					<?php echo esc_html( $is_public ? __( 'Make private', 'compath-choir-rehearsal' ) : __( 'Make public', 'compath-choir-rehearsal' ) ); ?>
				</span>
			</button>
			<span class="choir-song-visibility__hint description">
				<?php
				echo esc_html(
					$is_public
						? __( 'Anyone can view and listen without signing in.', 'compath-choir-rehearsal' )
						: __( 'Only signed-in users can access this song (when login is required).', 'compath-choir-rehearsal' )
				);
				?>
			</span>
		</div>
		<?php
	}

	public static function add_meta_boxes(): void {
		add_meta_box(
			'choir-rehearsal-score',
			__( 'Sheet Music (PDF)', 'compath-choir-rehearsal' ),
			array( self::class, 'render_score_metabox' ),
			Choir_Rehearsal_Post_Types::SONG,
			'normal',
			'high'
		);

		add_meta_box(
			'choir-rehearsal-tracks',
			__( 'Voice Tracks', 'compath-choir-rehearsal' ),
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
		wp_enqueue_script(
			'choir-rehearsal-waveform',
			CHOIR_REHEARSAL_URL . 'public/js/waveform.js',
			array(),
			CHOIR_REHEARSAL_VERSION,
			true
		);
		$admin_deps = array( 'jquery', 'wp-util', 'choir-rehearsal-waveform' );
		if ( Choir_Rehearsal_Edition::can_view_score_in_editor() ) {
			wp_enqueue_script(
				'pdfjs',
				Choir_Rehearsal_Distribution::pdfjs_script_url(),
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
					'workerSrc' => Choir_Rehearsal_Distribution::pdfjs_worker_url(),
					'expand'    => __( 'Expand PDF', 'compath-choir-rehearsal' ),
					'closeFs'   => __( 'Close full screen', 'compath-choir-rehearsal' ),
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
				array( 'choir-rehearsal-waveform' ),
				CHOIR_REHEARSAL_VERSION,
				true
			);
			wp_localize_script(
				'choir-rehearsal-player',
				'choirRehearsalPlayer',
				array(
					'nowPlaying' => __( 'Now playing', 'compath-choir-rehearsal' ),
					'play'       => __( 'Play', 'compath-choir-rehearsal' ),
					'pause'      => __( 'Pause', 'compath-choir-rehearsal' ),
					'close'      => __( 'Close player', 'compath-choir-rehearsal' ),
				)
			);
			wp_enqueue_script(
				'choir-rehearsal-youtube',
				CHOIR_REHEARSAL_URL . 'public/js/youtube-embed.js',
				array(),
				CHOIR_REHEARSAL_VERSION,
				true
			);
			wp_localize_script(
				'choir-rehearsal-youtube',
				'choirRehearsalYoutube',
				array(
					'open'  => __( 'Preview video', 'compath-choir-rehearsal' ),
					'close' => __( 'Hide video', 'compath-choir-rehearsal' ),
				)
			);
			wp_enqueue_script(
				'choir-rehearsal-piano',
				CHOIR_REHEARSAL_URL . 'public/js/piano.js',
				array(),
				CHOIR_REHEARSAL_VERSION,
				true
			);
			wp_localize_script(
				'choir-rehearsal-piano',
				'choirRehearsalPiano',
				array(
					'open'  => __( 'Open piano', 'compath-choir-rehearsal' ),
					'close' => __( 'Close piano', 'compath-choir-rehearsal' ),
				)
			);
			wp_enqueue_script(
				'choir-rehearsal-metronome',
				CHOIR_REHEARSAL_URL . 'public/js/metronome.js',
				array(),
				CHOIR_REHEARSAL_VERSION,
				true
			);
			wp_localize_script(
				'choir-rehearsal-metronome',
				'choirRehearsalMetronome',
				array(
					'open'  => __( 'Open metronome', 'compath-choir-rehearsal' ),
					'close' => __( 'Close metronome', 'compath-choir-rehearsal' ),
					'start' => __( 'Start', 'compath-choir-rehearsal' ),
					'stop'  => __( 'Stop', 'compath-choir-rehearsal' ),
				)
			);
		}
		$admin_i18n = array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'postId'         => $screen && 'post' === $screen->base ? (int) get_the_ID() : 0,
				'recordingNonce' => wp_create_nonce( 'choir_rehearsal_recording' ),
				'voices'         => Choir_Rehearsal_Voice_Types::choices(),
				'canRecord'      => Choir_Rehearsal_Edition::can_record(),
				'canPlay'        => Choir_Rehearsal_Edition::can_play_in_editor(),
				'canViewPdf'     => Choir_Rehearsal_Edition::can_view_score_in_editor(),
				'maxTracks'      => Choir_Rehearsal_Edition::max_tracks(),
				'upgradeUrl'     => Choir_Rehearsal_Edition::upgrade_url(),
				'selectAudio'    => __( 'Upload', 'compath-choir-rehearsal' ),
				'recordAudio'    => __( 'Record', 'compath-choir-rehearsal' ),
				'playAudio'      => __( 'Play', 'compath-choir-rehearsal' ),
				'useAudio'       => __( 'Use this audio', 'compath-choir-rehearsal' ),
				'removeTrack'    => __( 'Remove', 'compath-choir-rehearsal' ),
				'trackLabel'     => __( 'Track', 'compath-choir-rehearsal' ),
				'noAudio'        => __( 'No audio selected', 'compath-choir-rehearsal' ),
				'sourceAudio'    => __( 'Audio', 'compath-choir-rehearsal' ),
				'sourceYoutube'  => __( 'YouTube', 'compath-choir-rehearsal' ),
				'youtubeUrl'     => __( 'YouTube URL', 'compath-choir-rehearsal' ),
				'youtubeHint'    => __( 'Official YouTube player opens on the song page (video stays visible). Not for downloading audio.', 'compath-choir-rehearsal' ),
				'previewVideo'   => __( 'Preview video', 'compath-choir-rehearsal' ),
				'selectPdf'      => __( 'Select PDF', 'compath-choir-rehearsal' ),
				'usePdf'         => __( 'Use this PDF', 'compath-choir-rehearsal' ),
				'noPdf'          => __( 'No PDF selected', 'compath-choir-rehearsal' ),
				'removePdf'      => __( 'Remove PDF', 'compath-choir-rehearsal' ),
				'sheetMusic'     => __( 'Sheet music', 'compath-choir-rehearsal' ),
				'prevPage'       => __( 'Previous', 'compath-choir-rehearsal' ),
				'nextPage'       => __( 'Next', 'compath-choir-rehearsal' ),
				'swipeHint'      => __( 'Swipe left or right to change pages', 'compath-choir-rehearsal' ),
				'startRecording' => __( 'Start recording', 'compath-choir-rehearsal' ),
				'stopRecording'  => __( 'Stop', 'compath-choir-rehearsal' ),
				'pauseRecording' => __( 'Pause recording', 'compath-choir-rehearsal' ),
				'resumeRecording'=> __( 'Resume recording', 'compath-choir-rehearsal' ),
				'useRecording'   => __( 'Use recording', 'compath-choir-rehearsal' ),
				'cancelRecording'=> __( 'Cancel', 'compath-choir-rehearsal' ),
				'openPiano'      => __( 'Open piano', 'compath-choir-rehearsal' ),
				'openMetronome'  => __( 'Open metronome', 'compath-choir-rehearsal' ),
				'recording'      => __( 'Recording…', 'compath-choir-rehearsal' ),
				'recordingPaused'=> __( 'Paused', 'compath-choir-rehearsal' ),
				'readyToRecord'  => __( 'Click start and sing your voice part.', 'compath-choir-rehearsal' ),
				'uploading'      => __( 'Uploading…', 'compath-choir-rehearsal' ),
				'micDenied'      => __( 'Microphone access was denied.', 'compath-choir-rehearsal' ),
				'micUnavailable' => __( 'Microphone recording is not supported in this browser.', 'compath-choir-rehearsal' ),
				'uploadFailed'   => __( 'Upload failed. Please try again.', 'compath-choir-rehearsal' ),
				'saveSongFirst'  => __( 'Save the song first, then you can record voice tracks.', 'compath-choir-rehearsal' ),
				'makePublic'     => __( 'Make public', 'compath-choir-rehearsal' ),
				'makePrivate'    => __( 'Make private', 'compath-choir-rehearsal' ),
				'publicHint'     => __( 'Anyone can view and listen without signing in.', 'compath-choir-rehearsal' ),
				'privateHint'    => __( 'Only signed-in users can access this song (when login is required).', 'compath-choir-rehearsal' ),
		);

		if ( Choir_Rehearsal_Edition::shows_commercial_upgrade() ) {
			$admin_i18n['trackLimitMsg'] = sprintf(
				/* translators: %d: maximum track count */
				__( 'Lite edition allows up to %d voice tracks per song. Upgrade to Pro for unlimited tracks, microphone recording, and Play preview in the editor.', 'compath-choir-rehearsal' ),
				Choir_Rehearsal_Edition::LITE_MAX_TRACKS
			);
		}

		wp_localize_script(
			'choir-rehearsal-admin',
			'choirRehearsalAdmin',
			$admin_i18n
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
		?>
		<div class="choir-score-wrap choir-song-card-section">
			<p class="description">
				<?php esc_html_e( 'Upload a PDF score. It appears below like on the public song page — swipe left/right to change pages while you record.', 'compath-choir-rehearsal' ); ?>
			</p>
			<input type="hidden" id="choir-score-pdf-id" name="choir_score_pdf_id" value="<?php echo esc_attr( (string) $pdf_id ); ?>" />
			<input type="hidden" id="choir-score-pdf-url" value="<?php echo esc_url( $pdf_url ); ?>" />
			<div class="choir-score-toolbar">
				<span id="choir-score-pdf-name" class="choir-score-pdf-name"><?php echo esc_html( $filename ?: __( 'No PDF selected', 'compath-choir-rehearsal' ) ); ?></span>
				<div class="choir-score-toolbar__actions">
					<button type="button" class="button" id="choir-select-pdf"><?php esc_html_e( 'Upload / Select PDF', 'compath-choir-rehearsal' ); ?></button>
					<button type="button" class="button-link-delete" id="choir-remove-pdf"><?php esc_html_e( 'Remove PDF', 'compath-choir-rehearsal' ); ?></button>
				</div>
			</div>
			<div
				id="choir-editor-pdf-viewer"
				class="choir-pdf-viewer<?php echo '' === $pdf_url ? ' is-empty' : ''; ?>"
				data-pdf-url="<?php echo esc_url( $pdf_url ); ?>"
			>
				<button type="button" class="choir-pdf-expand" aria-label="<?php esc_attr_e( 'Expand PDF', 'compath-choir-rehearsal' ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Expand PDF', 'compath-choir-rehearsal' ); ?></span>
					<svg class="choir-pdf-toolbar-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="#ffffff" d="M4 9V4h5v2H6v3H4zm10-5h5v5h-2V6h-3V4zM4 15h2v3h3v2H4v-5zm16 0v5h-5v-2h3v-3h2z"/></svg>
				</button>
				<button type="button" class="choir-pdf-close-fs" hidden aria-label="<?php esc_attr_e( 'Close full screen', 'compath-choir-rehearsal' ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Close full screen', 'compath-choir-rehearsal' ); ?></span>
					<svg class="choir-pdf-toolbar-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="#ffffff" d="M6.4 5l5.6 5.6L17.6 5 19 6.4 13.4 12 19 17.6 17.6 19 12 13.4 6.4 19 5 17.6 10.6 12 5 6.4 6.4 5z"/></svg>
				</button>
				<div class="choir-pdf-viewer__canvas-wrap" title="<?php esc_attr_e( 'Swipe to change pages · pinch to zoom', 'compath-choir-rehearsal' ); ?>">
					<canvas class="choir-pdf-viewer__canvas"></canvas>
					<p class="choir-pdf-viewer__empty"><?php esc_html_e( 'No PDF selected yet.', 'compath-choir-rehearsal' ); ?></p>
				</div>
				<div class="choir-pdf-viewer__controls">
					<button type="button" class="choir-pdf-prev" aria-label="<?php esc_attr_e( 'Previous page', 'compath-choir-rehearsal' ); ?>">&larr; <?php esc_html_e( 'Previous', 'compath-choir-rehearsal' ); ?></button>
					<span class="choir-pdf-page">1 / 1</span>
					<button type="button" class="choir-pdf-next" aria-label="<?php esc_attr_e( 'Next page', 'compath-choir-rehearsal' ); ?>"><?php esc_html_e( 'Next', 'compath-choir-rehearsal' ); ?> &rarr;</button>
					<button type="button" class="choir-pdf-exit-fs" hidden aria-label="<?php esc_attr_e( 'Close full screen', 'compath-choir-rehearsal' ); ?>">&times; <?php esc_html_e( 'Close', 'compath-choir-rehearsal' ); ?></button>
				</div>
			</div>
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
					esc_html_e( 'Voice parts look like the public song page. Use the icons on the right to upload, record, or play.', 'compath-choir-rehearsal' );
				} elseif ( Choir_Rehearsal_Edition::shows_commercial_upgrade() ) {
					echo wp_kses_post(
						sprintf(
							/* translators: 1: max tracks, 2: upgrade link HTML */
							__( 'Add one voice part per row (up to %1$d in Lite). Upload audio with the icon on the right. %2$s', 'compath-choir-rehearsal' ),
							Choir_Rehearsal_Edition::LITE_MAX_TRACKS,
							'<a class="button button-small" href="' . esc_url( Choir_Rehearsal_Edition::upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Buy Pro', 'compath-choir-rehearsal' ) . '</a>'
						)
					);
				} else {
					esc_html_e( 'Add one voice part per row. Upload audio with the icon on the right.', 'compath-choir-rehearsal' );
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
							(int) $track->ID,
							Choir_Rehearsal_Post_Types::get_track_source( (int) $track->ID ),
							Choir_Rehearsal_Post_Types::get_track_youtube_url( (int) $track->ID )
						);
						?>
					<?php endforeach; ?>
				<?php endif; ?>
			</ul>
			<p><button type="button" class="button" id="choir-add-track"><?php esc_html_e( 'Add track', 'compath-choir-rehearsal' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * @param array<string, string> $voices
	 */
	private static function render_track_row(
		int $index,
		string $voice_slug,
		int $audio_id,
		array $voices,
		int $track_id = 0,
		string $source = 'audio',
		string $youtube_url = ''
	): void {
		$source      = Choir_Rehearsal_Post_Types::sanitize_track_source( $source );
		$youtube_url = Choir_Rehearsal_Post_Types::sanitize_youtube_url( $youtube_url );
		$is_youtube  = 'youtube' === $source;
		$filename    = '';
		$audio_url   = '';
		if ( $audio_id > 0 ) {
			$file = get_attached_file( $audio_id );
			$filename = $file ? basename( $file ) : '';
			$url      = wp_get_attachment_url( $audio_id );
			$audio_url = is_string( $url ) ? Choir_Rehearsal_Post_Types::align_attachment_url_scheme( $url ) : '';
		}

		$song_title  = get_the_title();
		$voice_label = $voices[ $voice_slug ] ?? $voice_slug;
		$play_title  = trim( (string) $song_title ) !== ''
			? $song_title . ' — ' . $voice_label
			: (string) $voice_label;
		$can_play_audio = '' !== $audio_url;
		$yt_embed       = '' !== $youtube_url
			? Choir_Rehearsal_Post_Types::get_track_youtube_embed_url( $track_id > 0 ? $track_id : 0 )
			: '';
		if ( '' === $yt_embed && '' !== $youtube_url ) {
			$vid = Choir_Rehearsal_Post_Types::parse_youtube_video_id( $youtube_url );
			$yt_embed = '' !== $vid ? 'https://www.youtube.com/embed/' . rawurlencode( $vid ) : '';
		}
		$can_play_youtube = '' !== $yt_embed;
		$row_id           = 'choir-track-yt-' . $index . ( $track_id > 0 ? '-' . $track_id : '' );
		?>
		<li class="choir-track-item choir-track-row" data-source="<?php echo esc_attr( $source ); ?>">
			<input type="hidden" name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( (string) $track_id ); ?>" />
			<input type="hidden" class="choir-audio-id" name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][audio_id]" value="<?php echo esc_attr( (string) $audio_id ); ?>" />
			<div class="choir-track-item__main">
				<select class="choir-voice-select choir-track-voice" name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][voice]" aria-label="<?php esc_attr_e( 'Voice', 'compath-choir-rehearsal' ); ?>">
					<?php foreach ( $voices as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $voice_slug, $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<div class="choir-track-source" role="group" aria-label="<?php esc_attr_e( 'Track source', 'compath-choir-rehearsal' ); ?>">
					<label class="choir-track-source__option">
						<input
							type="radio"
							class="choir-track-source-input"
							name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][source]"
							value="audio"
							<?php checked( ! $is_youtube ); ?>
						/>
						<span><?php esc_html_e( 'Audio', 'compath-choir-rehearsal' ); ?></span>
					</label>
					<label class="choir-track-source__option">
						<input
							type="radio"
							class="choir-track-source-input"
							name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][source]"
							value="youtube"
							<?php checked( $is_youtube ); ?>
						/>
						<span><?php esc_html_e( 'YouTube', 'compath-choir-rehearsal' ); ?></span>
					</label>
				</div>
				<span class="choir-audio-name screen-reader-text"><?php echo esc_html( $filename ?: __( 'No audio selected', 'compath-choir-rehearsal' ) ); ?></span>
			</div>
			<div class="choir-track-audio-pane" <?php echo $is_youtube ? 'hidden' : ''; ?>>
				<div
					class="choir-track-waveform<?php echo '' === $audio_url ? ' is-empty' : ''; ?>"
					data-audio-url="<?php echo esc_url( $audio_url ); ?>"
					title="<?php echo esc_attr( $filename ?: __( 'No audio selected', 'compath-choir-rehearsal' ) ); ?>"
					aria-hidden="true"
				>
					<canvas class="choir-track-waveform__canvas"></canvas>
				</div>
				<div class="choir-track-item__actions choir-track-item__actions--audio">
					<button type="button" class="choir-icon-btn choir-select-audio" title="<?php esc_attr_e( 'Upload', 'compath-choir-rehearsal' ); ?>" aria-label="<?php esc_attr_e( 'Upload', 'compath-choir-rehearsal' ); ?>">
						<?php echo self::icon_svg( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
					<?php if ( Choir_Rehearsal_Edition::can_record() ) : ?>
						<button type="button" class="choir-icon-btn choir-record-audio" title="<?php esc_attr_e( 'Record', 'compath-choir-rehearsal' ); ?>" aria-label="<?php esc_attr_e( 'Record', 'compath-choir-rehearsal' ); ?>">
							<?php echo self::icon_svg( 'record' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
					<?php endif; ?>
					<?php if ( Choir_Rehearsal_Edition::can_play_in_editor() ) : ?>
						<button
							type="button"
							class="choir-icon-btn choir-play-track"
							title="<?php esc_attr_e( 'Play', 'compath-choir-rehearsal' ); ?>"
							aria-label="<?php esc_attr_e( 'Play', 'compath-choir-rehearsal' ); ?>"
							data-track-url="<?php echo esc_url( $audio_url ); ?>"
							data-track-title="<?php echo esc_attr( $play_title ); ?>"
							<?php disabled( ! $can_play_audio ); ?>
						>
							<?php echo self::icon_svg( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
			<div class="choir-track-youtube-pane" <?php echo $is_youtube ? '' : 'hidden'; ?>>
				<label class="screen-reader-text" for="<?php echo esc_attr( $row_id ); ?>-url"><?php esc_html_e( 'YouTube URL', 'compath-choir-rehearsal' ); ?></label>
				<input
					type="url"
					class="choir-youtube-url-input large-text"
					id="<?php echo esc_attr( $row_id ); ?>-url"
					name="choir_tracks[<?php echo esc_attr( (string) $index ); ?>][youtube_url]"
					value="<?php echo esc_attr( $youtube_url ); ?>"
					placeholder="https://www.youtube.com/watch?v=…"
					autocomplete="off"
				/>
				<p class="description choir-track-youtube-hint">
					<?php esc_html_e( 'Official YouTube player opens on the song page (video stays visible). Not for downloading audio.', 'compath-choir-rehearsal' ); ?>
				</p>
				<?php if ( Choir_Rehearsal_Edition::can_play_in_editor() ) : ?>
					<button
						type="button"
						class="button choir-youtube-toggle"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $row_id ); ?>-player"
						data-embed-url="<?php echo esc_url( $yt_embed ); ?>"
						<?php disabled( ! $can_play_youtube ); ?>
					>
						<?php esc_html_e( 'Preview video', 'compath-choir-rehearsal' ); ?>
					</button>
					<div
						id="<?php echo esc_attr( $row_id ); ?>-player"
						class="choir-youtube-player is-hidden"
						hidden
					></div>
				<?php endif; ?>
			</div>
			<div class="choir-track-item__actions choir-track-item__actions--row">
				<button type="button" class="choir-icon-btn choir-icon-btn--danger choir-remove-track" title="<?php esc_attr_e( 'Remove', 'compath-choir-rehearsal' ); ?>" aria-label="<?php esc_attr_e( 'Remove', 'compath-choir-rehearsal' ); ?>">
					<?php echo self::icon_svg( 'remove' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
			</div>
			<?php if ( Choir_Rehearsal_Edition::can_record() ) : ?>
			<div class="choir-recorder-panel is-hidden" aria-hidden="true">
				<p class="choir-recorder-panel__status"><?php esc_html_e( 'Click start and sing your voice part.', 'compath-choir-rehearsal' ); ?></p>
				<p class="choir-recorder-panel__timer">00:00</p>
				<audio class="choir-recorder-panel__preview" controls hidden></audio>
				<div class="choir-recorder-panel__actions">
					<div class="choir-recorder-panel__start-row">
						<button type="button" class="button button-primary choir-recorder-start"><?php esc_html_e( 'Start recording', 'compath-choir-rehearsal' ); ?></button>
						<button
							type="button"
							class="button choir-recorder-piano"
							title="<?php esc_attr_e( 'Open piano', 'compath-choir-rehearsal' ); ?>"
							aria-label="<?php esc_attr_e( 'Open piano', 'compath-choir-rehearsal' ); ?>"
							aria-expanded="false"
							aria-controls="choir-piano-sheet"
						>
							<?php echo self::icon_svg( 'piano' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
						<button
							type="button"
							class="button choir-recorder-metronome"
							title="<?php esc_attr_e( 'Open metronome', 'compath-choir-rehearsal' ); ?>"
							aria-label="<?php esc_attr_e( 'Open metronome', 'compath-choir-rehearsal' ); ?>"
							aria-expanded="false"
							aria-controls="choir-metronome-sheet"
						>
							<?php echo self::icon_svg( 'metronome' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</button>
					</div>
					<button type="button" class="button choir-recorder-stop" disabled><?php esc_html_e( 'Stop', 'compath-choir-rehearsal' ); ?></button>
					<button type="button" class="button button-primary choir-recorder-use" disabled><?php esc_html_e( 'Use recording', 'compath-choir-rehearsal' ); ?></button>
					<button type="button" class="button choir-recorder-cancel"><?php esc_html_e( 'Cancel', 'compath-choir-rehearsal' ); ?></button>
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
			'play'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="#ffffff" d="M7 3.8v16.4L20.2 12 7 3.8z"/></svg>',
			'remove' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.4 6.4l1.2-1.2L12 9.6l4.4-4.4 1.2 1.2L13.2 12l4.4 4.4-1.2 1.2L12 14.4l-4.4 4.4-1.2-1.2L10.8 12 6.4 6.4z"/></svg>',
			'piano'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 16.5v-4.5h1V4.5h2v10.5h1v4.5h-4zM8 19.5H5.5c-.55 0-1-.45-1-1V5.5c0-.55.45-1 1-1H7v10.5h1v4.5zm8-4.5h1V4.5h1.5c.55 0 1 .45 1 1v13c0 .55-.45 1-1 1H16v-4.5z"/></svg>',
			'metronome' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.5 2l7.5 18H5L12.5 2zm0 3.2L7.4 18h10.2L12.5 5.2zM11 10h1.5v5H11v-5zm0 6h1.5v1.5H11V16z"/></svg>',
			'globe'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 100 20 10 10 0 000-20zm6.9 9h-3.1a15.4 15.4 0 00-1.2-5 8.03 8.03 0 014.3 5zM12 4c.9 0 2.2 1.9 2.8 5H9.2C9.8 5.9 11.1 4 12 4zM4 12c0-.7.1-1.4.3-2h3.1a15.4 15.4 0 001.2 5H4.3A8 8 0 014 12zm1.1 3h3.1a15.4 15.4 0 001.2 5 8.03 8.03 0 01-4.3-5zm6.9 5c-.9 0-2.2-1.9-2.8-5h5.6c-.6 3.1-1.9 5-2.8 5zm2.8-2a15.4 15.4 0 001.2-5h3.1a8.03 8.03 0 01-4.3 5zM8.3 10A15.4 15.4 0 017.1 5a8.03 8.03 0 00-4.3 5h3.1z"/></svg>',
		);

		return $icons[ $name ] ?? '';
	}

	public static function save_song( int $post_id, WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['choir_rehearsal_visibility_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['choir_rehearsal_visibility_nonce'] ) ), 'choir_rehearsal_save_visibility' ) ) {
			$is_public = isset( $_POST['choir_is_public'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['choir_is_public'] ) );
			Choir_Rehearsal_Post_Types::set_public( $post_id, $is_public );
		}

		if ( isset( $_POST['choir_rehearsal_score_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['choir_rehearsal_score_nonce'] ) ), 'choir_rehearsal_save_score' ) ) {
			// Only touch PDF meta when the metabox field is present in this request.
			if ( isset( $_POST['choir_score_pdf_id'] ) ) {
				$pdf_id = absint( wp_unslash( $_POST['choir_score_pdf_id'] ) );
				if ( $pdf_id > 0 ) {
					if ( 'application/pdf' === get_post_mime_type( $pdf_id ) ) {
						update_post_meta( $post_id, '_choir_score_pdf_id', $pdf_id );
					}
					// Invalid mime: keep any existing score rather than deleting it.
				} else {
					delete_post_meta( $post_id, '_choir_score_pdf_id' );
				}
			}
		}

		if ( ! isset( $_POST['choir_rehearsal_tracks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['choir_rehearsal_tracks_nonce'] ) ), 'choir_rehearsal_save_tracks' ) ) {
			return;
		}

		$submitted = array();
		if ( isset( $_POST['choir_tracks'] ) && is_array( $_POST['choir_tracks'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-field below.
			$raw_tracks = wp_unslash( $_POST['choir_tracks'] );
			foreach ( $raw_tracks as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$submitted[] = array(
					'id'          => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
					'voice'       => isset( $row['voice'] ) ? sanitize_key( (string) $row['voice'] ) : '',
					'audio_id'    => isset( $row['audio_id'] ) ? absint( $row['audio_id'] ) : 0,
					'source'      => isset( $row['source'] ) ? Choir_Rehearsal_Post_Types::sanitize_track_source( (string) $row['source'] ) : 'audio',
					'youtube_url' => isset( $row['youtube_url'] )
						? Choir_Rehearsal_Post_Types::sanitize_youtube_url( sanitize_text_field( (string) $row['youtube_url'] ) )
						: '',
				);
			}
		}

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

			$track_id    = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$voice       = isset( $row['voice'] ) ? sanitize_key( $row['voice'] ) : 'other';
			$audio_id    = isset( $row['audio_id'] ) ? absint( $row['audio_id'] ) : 0;
			$source      = isset( $row['source'] ) ? Choir_Rehearsal_Post_Types::sanitize_track_source( (string) $row['source'] ) : 'audio';
			$youtube_url = isset( $row['youtube_url'] ) ? Choir_Rehearsal_Post_Types::sanitize_youtube_url( (string) $row['youtube_url'] ) : '';
			$voice_lbl   = Choir_Rehearsal_Voice_Types::get_label( $voice );

			if ( 'youtube' === $source ) {
				if ( '' === $youtube_url ) {
					continue;
				}
				$audio_id = 0;
			} elseif ( $audio_id <= 0 ) {
				continue;
			} else {
				$youtube_url = '';
			}

			$track_data = array(
				'post_type'   => Choir_Rehearsal_Post_Types::TRACK,
				'post_status' => 'publish',
				'post_parent' => $post_id,
				'post_title'  => sprintf(
					/* translators: 1: song title, 2: voice label */
					__( '%1$s — %2$s', 'compath-choir-rehearsal' ),
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
			update_post_meta( (int) $new_id, '_choir_source', $source );
			if ( 'youtube' === $source ) {
				update_post_meta( (int) $new_id, '_choir_youtube_url', $youtube_url );
				delete_post_meta( (int) $new_id, '_choir_audio_id' );
			} else {
				update_post_meta( (int) $new_id, '_choir_audio_id', $audio_id );
				delete_post_meta( (int) $new_id, '_choir_youtube_url' );
			}
			$keep_ids[] = (int) $new_id;
		}

		foreach ( $existing as $track ) {
			if ( ! in_array( (int) $track->ID, $keep_ids, true ) ) {
				wp_delete_post( (int) $track->ID, true );
			}
		}
	}
}
