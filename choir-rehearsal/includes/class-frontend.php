<?php
/**
 * Public-facing templates, shortcodes, and assets.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Frontend {

	public static function register(): void {
		add_filter( 'template_include', array( self::class, 'template_include' ) );
		add_shortcode( 'choir_rehearsal', array( self::class, 'render_archive_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function template_include( string $template ): string {
		if ( is_post_type_archive( Choir_Rehearsal_Post_Types::SONG ) ) {
			$custom = CHOIR_REHEARSAL_PATH . 'templates/archive-choir_song.php';
			return file_exists( $custom ) ? $custom : $template;
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
	}

	private static function should_enqueue(): bool {
		if ( is_singular( Choir_Rehearsal_Post_Types::SONG ) || is_post_type_archive( Choir_Rehearsal_Post_Types::SONG ) ) {
			return true;
		}

		global $post;
		return $post instanceof WP_Post && has_shortcode( $post->post_content, 'choir_rehearsal' );
	}

	public static function render_archive_shortcode(): string {
		ob_start();
		self::render_song_list();
		return (string) ob_get_clean();
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
		?>
		<div class="choir-rehearsal-archive">
			<h1 class="choir-rehearsal-title"><?php esc_html_e( 'Rehearsal Library', 'choir-rehearsal' ); ?></h1>
			<?php if ( empty( $songs ) ) : ?>
				<p><?php esc_html_e( 'No songs yet.', 'choir-rehearsal' ); ?></p>
			<?php else : ?>
				<ul class="choir-song-list">
					<?php foreach ( $songs as $song ) : ?>
						<li>
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
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_song( WP_Post $song ): void {
		$tracks = Choir_Rehearsal_Post_Types::get_tracks_for_song( (int) $song->ID );
		?>
		<div class="choir-rehearsal-single" data-song-id="<?php echo esc_attr( (string) $song->ID ); ?>">
			<p class="choir-back-link"><a href="<?php echo esc_url( get_post_type_archive_link( Choir_Rehearsal_Post_Types::SONG ) ); ?>">&larr; <?php esc_html_e( 'All songs', 'choir-rehearsal' ); ?></a></p>
			<h1 class="choir-rehearsal-title"><?php echo esc_html( get_the_title( $song ) ); ?></h1>
			<?php if ( $song->post_content ) : ?>
				<div class="choir-song-notes"><?php echo wp_kses_post( wpautop( $song->post_content ) ); ?></div>
			<?php endif; ?>

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
