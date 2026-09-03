<?php
/**
 * Public-facing templates, shortcodes, and assets.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Frontend {

	private const SONGS_PER_PAGE = 20;

	private static bool $shortcode_rendered = false;

	public static function register(): void {
		add_filter( 'template_include', array( self::class, 'template_include' ) );
		add_shortcode( 'choir_rehearsal', array( self::class, 'render_archive_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function template_include( string $template ): string {
		if ( Choir_Rehearsal_Access::should_show_login() ) {
			if ( Choir_Rehearsal_Pages::is_library_page() ) {
				return $template;
			}

			global $post;
			if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'choir_rehearsal' ) ) {
				return $template;
			}

			$login = CHOIR_REHEARSAL_PATH . 'templates/login.php';
			return file_exists( $login ) ? $login : $template;
		}

		if ( is_singular( Choir_Rehearsal_Post_Types::SONG ) ) {
			$custom = CHOIR_REHEARSAL_PATH . 'templates/single-choir_song.php';
			return file_exists( $custom ) ? $custom : $template;
		}

		return $template;
	}

	public static function enqueue_assets(): void {
		if ( ! self::should_enqueue() ) {
			return;
		}

		wp_enqueue_style(
			'choir-rehearsal-public',
			CHOIR_REHEARSAL_URL . 'public/css/public.css',
			array(),
			CHOIR_REHEARSAL_VERSION
		);

		if ( Choir_Rehearsal_Access::should_show_login() ) {
			return;
		}

		if ( Choir_Rehearsal_Edition::is_pro() && self::is_song_list_page() ) {
			wp_enqueue_script(
				'choir-rehearsal-song-list',
				CHOIR_REHEARSAL_URL . 'public/js/song-list.js',
				array(),
				CHOIR_REHEARSAL_VERSION,
				true
			);
			wp_localize_script(
				'choir-rehearsal-song-list',
				'choirRehearsalSongList',
				array(
					'songs' => self::get_songs_search_index( Choir_Rehearsal_Access::can_manage() ),
					'i18n'  => array(
						'edit'          => __( 'Edit', 'choir-rehearsal' ),
						'trackSingular' => __( '%d track', 'choir-rehearsal' ),
						'trackPlural'   => __( '%d tracks', 'choir-rehearsal' ),
					),
				)
			);
		}

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
			)
		);

		if ( is_singular( Choir_Rehearsal_Post_Types::SONG ) ) {
			$song_id = get_queried_object_id();
			$pdf_url = Choir_Rehearsal_Post_Types::get_score_pdf_url( $song_id );
			if ( '' !== $pdf_url ) {
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
						'prev'      => __( 'Previous page', 'choir-rehearsal' ),
						'next'      => __( 'Next page', 'choir-rehearsal' ),
					)
				);
			}
		}
	}

	private static function is_song_list_page(): bool {
		if ( Choir_Rehearsal_Pages::is_library_page() ) {
			return true;
		}

		if ( is_post_type_archive( Choir_Rehearsal_Post_Types::SONG ) ) {
			return true;
		}

		global $post;
		return $post instanceof WP_Post && Choir_Rehearsal_Pages::page_has_shortcode( (string) $post->post_content );
	}

	private static function should_enqueue(): bool {
		if ( Choir_Rehearsal_Access::should_show_login() ) {
			return true;
		}

		if ( Choir_Rehearsal_Pages::is_library_page() ) {
			return true;
		}

		if ( is_singular( Choir_Rehearsal_Post_Types::SONG ) ) {
			return true;
		}

		global $post;
		return $post instanceof WP_Post && has_shortcode( $post->post_content, 'choir_rehearsal' );
	}

	public static function render_archive_shortcode(): string {
		if ( self::$shortcode_rendered ) {
			return '';
		}

		self::$shortcode_rendered = true;

		ob_start();

		if ( Choir_Rehearsal_Access::should_show_login() ) {
			self::render_login_form();
		} else {
			self::render_user_bar();
			self::render_song_list();
		}

		return (string) ob_get_clean();
	}

	public static function render_login_form(): void {
		$error      = Choir_Rehearsal_Access::get_login_error();
		$redirect   = Choir_Rehearsal_Access::get_requested_redirect_url();
		?>
		<div class="choir-rehearsal-login">
			<div class="choir-rehearsal-login__card">
				<h1 class="choir-rehearsal-title"><?php esc_html_e( 'Rehearsal Library', 'choir-rehearsal' ); ?></h1>
				<p class="choir-rehearsal-login__intro">
					<?php esc_html_e( 'Sign in with your WordPress account to access songs, sheet music, and voice tracks.', 'choir-rehearsal' ); ?>
				</p>

				<?php if ( '' !== $error ) : ?>
					<div class="choir-rehearsal-login__error" role="alert">
						<?php echo esc_html( $error ); ?>
					</div>
				<?php endif; ?>

				<form class="choir-rehearsal-login__form" method="post" action="<?php echo esc_url( Choir_Rehearsal_Access::get_current_rehearsal_url() ); ?>">
					<?php wp_nonce_field( 'choir_rehearsal_login', 'choir_rehearsal_login_nonce' ); ?>
					<input type="hidden" name="choir_rehearsal_login" value="1" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>" />

					<p class="choir-rehearsal-login__field">
						<label for="choir-user-login"><?php esc_html_e( 'Username or email', 'choir-rehearsal' ); ?></label>
						<input type="text" name="log" id="choir-user-login" autocomplete="username" required />
					</p>

					<p class="choir-rehearsal-login__field">
						<label for="choir-user-pass"><?php esc_html_e( 'Password', 'choir-rehearsal' ); ?></label>
						<input type="password" name="pwd" id="choir-user-pass" autocomplete="current-password" required />
					</p>

					<p class="choir-rehearsal-login__remember">
						<label>
							<input type="checkbox" name="rememberme" value="forever" />
							<?php esc_html_e( 'Remember me', 'choir-rehearsal' ); ?>
						</label>
					</p>

					<p class="choir-rehearsal-login__submit">
						<button type="submit" class="choir-rehearsal-login__button"><?php esc_html_e( 'Sign in', 'choir-rehearsal' ); ?></button>
					</p>
				</form>

				<p class="choir-rehearsal-login__help">
					<a href="<?php echo esc_url( wp_lostpassword_url( $redirect ) ); ?>">
						<?php esc_html_e( 'Forgot your password?', 'choir-rehearsal' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	public static function render_user_bar(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}

		$can_manage = Choir_Rehearsal_Access::can_manage();
		?>
		<div class="choir-user-bar">
			<div class="choir-user-bar__identity">
				<span class="choir-user-bar__label"><?php esc_html_e( 'Signed in as', 'choir-rehearsal' ); ?></span>
				<strong class="choir-user-bar__name"><?php echo esc_html( $user->display_name ); ?></strong>
				<?php if ( $can_manage ) : ?>
					<span class="choir-user-bar__role"><?php esc_html_e( 'Editor', 'choir-rehearsal' ); ?></span>
				<?php else : ?>
					<span class="choir-user-bar__role"><?php esc_html_e( 'Singer', 'choir-rehearsal' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="choir-user-bar__actions">
				<?php if ( $can_manage && ! Choir_Rehearsal_Edition::is_pro() ) : ?>
					<a class="choir-user-bar__link choir-user-bar__link--buy" href="<?php echo esc_url( Choir_Rehearsal_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Buy Pro', 'choir-rehearsal' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $can_manage ) : ?>
					<a class="choir-user-bar__link" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Choir_Rehearsal_Post_Types::SONG ) ); ?>">
						<?php esc_html_e( 'Add song', 'choir-rehearsal' ); ?>
					</a>
					<a class="choir-user-bar__link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Choir_Rehearsal_Post_Types::SONG ) ); ?>">
						<?php esc_html_e( 'Manage library', 'choir-rehearsal' ); ?>
					</a>
				<?php endif; ?>
				<a class="choir-user-bar__link choir-user-bar__link--muted" href="<?php echo esc_url( Choir_Rehearsal_Access::get_logout_url() ); ?>">
					<?php esc_html_e( 'Sign out', 'choir-rehearsal' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	public static function render_song_list(): void {
		$total_songs  = (int) wp_count_posts( Choir_Rehearsal_Post_Types::SONG )->publish;
		$total_pages  = max( 1, (int) ceil( $total_songs / self::SONGS_PER_PAGE ) );
		$current_page = min( self::get_current_list_page(), $total_pages );
		$query        = new WP_Query(
			array(
				'post_type'      => Choir_Rehearsal_Post_Types::SONG,
				'posts_per_page' => self::SONGS_PER_PAGE,
				'paged'          => $current_page,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);
		$songs        = $query->posts;
		$can_manage   = Choir_Rehearsal_Access::can_manage();
		$is_pro       = Choir_Rehearsal_Edition::is_pro();
		?>
		<div class="choir-rehearsal-archive">
			<div class="choir-rehearsal-archive__header">
				<h1 class="choir-rehearsal-title"><?php esc_html_e( 'Rehearsal Library', 'choir-rehearsal' ); ?></h1>
				<?php if ( $is_pro && $total_songs > 0 ) : ?>
					<div class="choir-song-search">
						<label class="screen-reader-text" for="choir-song-search"><?php esc_html_e( 'Search songs', 'choir-rehearsal' ); ?></label>
						<input
							type="search"
							id="choir-song-search"
							class="choir-song-search__input"
							placeholder="<?php esc_attr_e( 'Search by title…', 'choir-rehearsal' ); ?>"
							autocomplete="off"
						/>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( 0 === $total_songs ) : ?>
				<p><?php esc_html_e( 'No songs yet.', 'choir-rehearsal' ); ?></p>
				<?php if ( $can_manage ) : ?>
					<p>
						<a class="choir-user-bar__link" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Choir_Rehearsal_Post_Types::SONG ) ); ?>">
							<?php esc_html_e( 'Add the first song', 'choir-rehearsal' ); ?>
						</a>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<?php if ( $is_pro ) : ?>
					<p class="choir-song-list__empty-search" role="status" aria-live="polite">
						<?php esc_html_e( 'No songs match your search.', 'choir-rehearsal' ); ?>
					</p>
				<?php endif; ?>
				<ul class="choir-song-list">
					<?php foreach ( $songs as $song ) : ?>
						<?php self::render_song_list_item( $song, $can_manage ); ?>
					<?php endforeach; ?>
				</ul>
				<?php self::render_song_pagination( $current_page, $total_pages, $total_songs ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function get_current_list_page(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return max( 1, absint( wp_unslash( $_GET['cr_page'] ?? 1 ) ) );
	}

	private static function get_list_page_url(): string {
		if ( Choir_Rehearsal_Pages::is_library_page() ) {
			return Choir_Rehearsal_Pages::get_library_url();
		}

		global $post;
		if ( $post instanceof WP_Post ) {
			$url = get_permalink( $post );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * @return list<array{title: string, url: string, trackCount: int, editUrl: string}>
	 */
	private static function get_songs_search_index( bool $can_manage ): array {
		$songs = get_posts(
			array(
				'post_type'      => Choir_Rehearsal_Post_Types::SONG,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
				'fields'         => 'ids',
			)
		);

		$index = array();
		foreach ( $songs as $song_id ) {
			$song_id = (int) $song_id;
			$title   = get_the_title( $song_id );
			$url     = get_permalink( $song_id );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$item = array(
				'title'       => $title,
				'url'         => $url,
				'trackCount'  => count( Choir_Rehearsal_Post_Types::get_tracks_for_song( $song_id ) ),
				'editUrl'     => '',
			);

			if ( $can_manage ) {
				$edit_url = get_edit_post_link( $song_id, 'raw' );
				$item['editUrl'] = is_string( $edit_url ) ? $edit_url : '';
			}

			$index[] = $item;
		}

		return $index;
	}

	private static function render_song_list_item( WP_Post $song, bool $can_manage ): void {
		$track_count = count( Choir_Rehearsal_Post_Types::get_tracks_for_song( (int) $song->ID ) );
		?>
		<li data-song-title="<?php echo esc_attr( get_the_title( $song ) ); ?>">
			<div class="choir-song-list__main">
				<a href="<?php echo esc_url( get_permalink( $song ) ); ?>">
					<?php echo esc_html( get_the_title( $song ) ); ?>
				</a>
				<span class="choir-track-count">
					<?php
					printf(
						/* translators: %d: number of tracks */
						esc_html( _n( '%d track', '%d tracks', $track_count, 'choir-rehearsal' ) ),
						$track_count
					);
					?>
				</span>
			</div>
			<?php if ( $can_manage ) : ?>
				<a class="choir-song-edit" href="<?php echo esc_url( get_edit_post_link( $song->ID, 'raw' ) ?: '' ); ?>">
					<?php esc_html_e( 'Edit', 'choir-rehearsal' ); ?>
				</a>
			<?php endif; ?>
		</li>
		<?php
	}

	private static function render_song_pagination( int $current_page, int $total_pages, int $total_songs ): void {
		if ( $total_pages <= 1 ) {
			return;
		}

		$links = paginate_links(
			array(
				'base'      => add_query_arg( 'cr_page', '%#%', self::get_list_page_url() ),
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'prev_text' => '&larr; ' . esc_html__( 'Previous', 'choir-rehearsal' ),
				'next_text' => esc_html__( 'Next', 'choir-rehearsal' ) . ' &rarr;',
				'type'      => 'list',
			)
		);

		if ( ! is_string( $links ) || '' === $links ) {
			return;
		}
		?>
		<nav class="choir-song-pagination" aria-label="<?php esc_attr_e( 'Song list pages', 'choir-rehearsal' ); ?>">
			<p class="choir-song-pagination__summary">
				<?php
				printf(
					/* translators: 1: first song number on page, 2: last song number on page, 3: total songs */
					esc_html__( 'Showing %1$d–%2$d of %3$d songs', 'choir-rehearsal' ),
					( ( $current_page - 1 ) * self::SONGS_PER_PAGE ) + 1,
					min( $current_page * self::SONGS_PER_PAGE, $total_songs ),
					$total_songs
				);
				?>
			</p>
			<?php echo wp_kses_post( $links ); ?>
		</nav>
		<?php
	}

	public static function render_song( WP_Post $song ): void {
		$tracks     = Choir_Rehearsal_Post_Types::get_tracks_for_song( (int) $song->ID );
		$pdf_url    = Choir_Rehearsal_Post_Types::get_score_pdf_url( (int) $song->ID );
		$can_manage = Choir_Rehearsal_Access::can_manage();
		?>
		<div class="choir-rehearsal-single" data-song-id="<?php echo esc_attr( (string) $song->ID ); ?>">
			<p class="choir-back-link"><a href="<?php echo esc_url( Choir_Rehearsal_Pages::get_library_url() ); ?>">&larr; <?php esc_html_e( 'All songs', 'choir-rehearsal' ); ?></a></p>
			<div class="choir-song-header">
				<h1 class="choir-rehearsal-title"><?php echo esc_html( get_the_title( $song ) ); ?></h1>
				<?php if ( $can_manage ) : ?>
					<a class="choir-song-edit" href="<?php echo esc_url( get_edit_post_link( $song->ID, 'raw' ) ?: '' ); ?>">
						<?php esc_html_e( 'Edit song', 'choir-rehearsal' ); ?>
					</a>
				<?php endif; ?>
			</div>
			<?php if ( $song->post_content ) : ?>
				<div class="choir-song-notes"><?php echo wp_kses_post( wpautop( $song->post_content ) ); ?></div>
			<?php endif; ?>

			<?php if ( '' !== $pdf_url ) : ?>
				<section class="choir-score-section" aria-label="<?php esc_attr_e( 'Sheet music', 'choir-rehearsal' ); ?>">
					<h2 class="choir-section-title"><?php esc_html_e( 'Sheet music', 'choir-rehearsal' ); ?></h2>
					<div class="choir-pdf-viewer" data-pdf-url="<?php echo esc_url( $pdf_url ); ?>">
						<div class="choir-pdf-viewer__canvas-wrap">
							<canvas class="choir-pdf-viewer__canvas"></canvas>
						</div>
						<div class="choir-pdf-viewer__controls">
							<button type="button" class="choir-pdf-prev" aria-label="<?php esc_attr_e( 'Previous page', 'choir-rehearsal' ); ?>">&larr; <?php esc_html_e( 'Previous', 'choir-rehearsal' ); ?></button>
							<span class="choir-pdf-page">1 / 1</span>
							<button type="button" class="choir-pdf-next" aria-label="<?php esc_attr_e( 'Next page', 'choir-rehearsal' ); ?>"><?php esc_html_e( 'Next', 'choir-rehearsal' ); ?> &rarr;</button>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<section class="choir-tracks-section" aria-label="<?php esc_attr_e( 'Voice tracks', 'choir-rehearsal' ); ?>">
				<h2 class="choir-section-title"><?php esc_html_e( 'Voice tracks', 'choir-rehearsal' ); ?></h2>
				<?php if ( empty( $tracks ) ) : ?>
					<p><?php esc_html_e( 'No tracks uploaded yet.', 'choir-rehearsal' ); ?></p>
				<?php else : ?>
					<ul class="choir-track-list">
						<?php foreach ( $tracks as $track ) : ?>
							<?php
							$audio_url = Choir_Rehearsal_Post_Types::get_audio_url( (int) $track->ID );
							if ( '' === $audio_url ) {
								continue;
							}
							?>
							<li class="choir-track-item">
								<span class="choir-track-voice"><?php echo esc_html( Choir_Rehearsal_Post_Types::get_voice_label( (int) $track->ID ) ); ?></span>
								<button
									type="button"
									class="choir-play-track"
									data-track-id="<?php echo esc_attr( (string) $track->ID ); ?>"
									data-track-title="<?php echo esc_attr( get_the_title( $song ) . ' — ' . Choir_Rehearsal_Post_Types::get_voice_label( (int) $track->ID ) ); ?>"
									data-track-url="<?php echo esc_url( $audio_url ); ?>"
								>
									<?php esc_html_e( 'Play', 'choir-rehearsal' ); ?>
								</button>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>

		<div id="choir-sticky-player" class="choir-sticky-player is-hidden" aria-hidden="true">
			<div class="choir-sticky-player__info">
				<strong class="choir-sticky-player__label"><?php esc_html_e( 'Now playing', 'choir-rehearsal' ); ?></strong>
				<span class="choir-sticky-player__title"></span>
			</div>
			<div class="choir-sticky-player__controls">
				<button type="button" class="choir-sticky-player__play" aria-label="<?php esc_attr_e( 'Play', 'choir-rehearsal' ); ?>">
					<span class="choir-sticky-player__play-icon" aria-hidden="true">▶</span>
				</button>
				<div class="choir-sticky-player__timeline">
					<input type="range" class="choir-sticky-player__seek" min="0" max="100" value="0" step="0.1" aria-label="<?php esc_attr_e( 'Seek', 'choir-rehearsal' ); ?>" />
					<span class="choir-sticky-player__time">0:00 / 0:00</span>
				</div>
				<audio class="choir-sticky-player__audio" preload="none"></audio>
			</div>
		</div>
		<?php
	}
}
