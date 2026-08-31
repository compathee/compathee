<?php
/**
 * Public-facing templates, shortcodes, and assets.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Frontend {

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
		$songs = get_posts(
			array(
				'post_type'      => Choir_Rehearsal_Post_Types::SONG,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);
		$can_manage = Choir_Rehearsal_Access::can_manage();
		?>
		<div class="choir-rehearsal-archive">
			<h1 class="choir-rehearsal-title"><?php esc_html_e( 'Rehearsal Library', 'choir-rehearsal' ); ?></h1>
			<?php if ( empty( $songs ) ) : ?>
				<p><?php esc_html_e( 'No songs yet.', 'choir-rehearsal' ); ?></p>
				<?php if ( $can_manage ) : ?>
					<p>
						<a class="choir-user-bar__link" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Choir_Rehearsal_Post_Types::SONG ) ); ?>">
							<?php esc_html_e( 'Add the first song', 'choir-rehearsal' ); ?>
						</a>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<ul class="choir-song-list">
					<?php foreach ( $songs as $song ) : ?>
						<li>
							<div class="choir-song-list__main">
								<a href="<?php echo esc_url( get_permalink( $song ) ); ?>">
									<?php echo esc_html( get_the_title( $song ) ); ?>
								</a>
								<span class="choir-track-count">
									<?php
									printf(
										/* translators: %d: number of tracks */
										esc_html( _n( '%d track', '%d tracks', count( Choir_Rehearsal_Post_Types::get_tracks_for_song( (int) $song->ID ) ), 'choir-rehearsal' ) ),
										count( Choir_Rehearsal_Post_Types::get_tracks_for_song( (int) $song->ID ) )
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
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
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
			<audio class="choir-sticky-player__audio" controls preload="none"></audio>
		</div>
		<?php
	}
}
