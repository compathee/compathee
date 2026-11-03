<?php
/**
 * Multi-track mixer UI. Omitted from the WordPress.org package.
 *
 * Present for licensed Pro and the Demo distribution. GitHub Lite without a
 * license shows an upgrade link. The Plugin Directory build deletes this file
 * so it does not ship gated mixer code.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Mixer {

	public static function enqueue_scripts(): void {
		wp_enqueue_script(
			'choir-rehearsal-mixer-logic',
			CHOIR_REHEARSAL_URL . 'public/js/mixer-logic.js',
			array(),
			CHOIR_REHEARSAL_VERSION,
			true
		);
		wp_enqueue_script(
			'choir-rehearsal-mixer',
			CHOIR_REHEARSAL_URL . 'public/js/mixer.js',
			array( 'choir-rehearsal-mixer-logic' ),
			CHOIR_REHEARSAL_VERSION,
			true
		);
		wp_localize_script(
			'choir-rehearsal-mixer',
			'choirRehearsalMixer',
			array(
				'play'         => __( 'Play', 'compath-choir-rehearsal' ),
				'pause'        => __( 'Pause', 'compath-choir-rehearsal' ),
				'open'         => __( 'Open mixer', 'compath-choir-rehearsal' ),
				'close'        => __( 'Close mixer', 'compath-choir-rehearsal' ),
				'back'         => __( 'Back 5 seconds', 'compath-choir-rehearsal' ),
				'forward'      => __( 'Forward 5 seconds', 'compath-choir-rehearsal' ),
				'ab'           => __( 'A–B loop', 'compath-choir-rehearsal' ),
				'clearAb'      => __( 'Clear A–B loop', 'compath-choir-rehearsal' ),
				/* translators: 1: loop start, 2: loop end */
				'loopRange'    => __( 'Loop %1$s–%2$s', 'compath-choir-rehearsal' ),
				/* translators: %s: playback rate such as 0.9× */
				'speed'        => __( 'Speed %s', 'compath-choir-rehearsal' ),
				'reset'        => __( 'Reset', 'compath-choir-rehearsal' ),
				/* translators: %s: track name */
				'mute'         => __( 'Mute %s', 'compath-choir-rehearsal' ),
				/* translators: %s: track name */
				'unmute'       => __( 'Unmute %s', 'compath-choir-rehearsal' ),
				/* translators: %s: track name */
				'solo'         => __( 'Solo %s', 'compath-choir-rehearsal' ),
				/* translators: %s: track name */
				'unsolo'       => __( 'Unsolo %s', 'compath-choir-rehearsal' ),
				/* translators: 1: loaded tracks, 2: total tracks */
				'loadingCount' => __( 'Loading %1$d of %2$d', 'compath-choir-rehearsal' ),
				'loadError'    => __( 'Could not load one or more tracks.', 'compath-choir-rehearsal' ),
				'startError'   => __( 'Could not start audio. Tap Play again.', 'compath-choir-rehearsal' ),
			)
		);
	}

	/**
	 * Launch control beside Voice tracks / Voice Tracks.
	 *
	 * @param array<int, array{id:int, url:string, label:string}> $mix_tracks Audio tracks.
	 */
	public static function render_launch( array $mix_tracks ): void {
		if ( empty( $mix_tracks ) ) {
			return;
		}
		if ( ! Choir_Rehearsal_Edition::can_use_mixer() && ! Choir_Rehearsal_Edition::shows_commercial_upgrade() ) {
			return;
		}
		$show_pro = ! Choir_Rehearsal_Distribution::is_wporg();
		if ( Choir_Rehearsal_Edition::can_use_mixer() ) {
			?>
			<button type="button" class="choir-mixer-launch" aria-expanded="false" aria-controls="choir-mixer" aria-label="<?php esc_attr_e( 'Open mixer', 'compath-choir-rehearsal' ); ?>">
				<span class="choir-mixer-launch__icon" aria-hidden="true">🎚</span>
				<?php esc_html_e( 'Mixer', 'compath-choir-rehearsal' ); ?>
				<?php if ( $show_pro ) : ?>
					<span class="choir-mixer-launch__pro">PRO</span>
				<?php endif; ?>
			</button>
			<?php
			return;
		}
		?>
		<a class="choir-mixer-launch" href="<?php echo esc_url( Choir_Rehearsal_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
			<span class="choir-mixer-launch__icon" aria-hidden="true">🎚</span>
			<?php esc_html_e( 'Mixer', 'compath-choir-rehearsal' ); ?>
			<?php if ( $show_pro ) : ?>
				<span class="choir-mixer-launch__pro">PRO</span>
			<?php endif; ?>
		</a>
		<?php
	}

	/**
	 * Audio-file tracks the mixer can play. YouTube stays on the single player.
	 *
	 * @param WP_Post[] $tracks Track posts.
	 * @return array<int, array{id:int, url:string, label:string}>
	 */
	public static function mixable_audio_tracks( array $tracks ): array {
		$audio = array();
		foreach ( $tracks as $track ) {
			if ( ! $track instanceof WP_Post ) {
				continue;
			}
			$track_id = (int) $track->ID;
			if ( 'youtube' === Choir_Rehearsal_Post_Types::get_track_source( $track_id ) ) {
				continue;
			}
			$url = Choir_Rehearsal_Post_Types::get_audio_url( $track_id );
			if ( '' === $url ) {
				continue;
			}
			$audio[] = array(
				'id'    => $track_id,
				'url'   => $url,
				'label' => Choir_Rehearsal_Post_Types::get_voice_label( $track_id ),
			);
		}

		return $audio;
	}

	public static function song_has_mixable_audio( int $song_id ): bool {
		if ( $song_id <= 0 ) {
			return false;
		}

		return array() !== self::mixable_audio_tracks( Choir_Rehearsal_Post_Types::get_tracks_for_song( $song_id ) );
	}

	/**
	 * Mixer shell. Channel count follows the song's audio tracks.
	 *
	 * @param array<int, array{id:int, url:string, label:string}> $mix_tracks Audio tracks.
	 */
	public static function render( array $mix_tracks ): void {
		if ( empty( $mix_tracks ) || ! Choir_Rehearsal_Edition::can_use_mixer() ) {
			return;
		}
		?>
		<div id="choir-mixer" class="choir-mixer" hidden data-default-volume="70">
			<h3 class="screen-reader-text" id="choir-mixer-title"><?php esc_html_e( 'Mixer', 'compath-choir-rehearsal' ); ?></h3>
			<p class="choir-mixer__hint"><?php esc_html_e( 'All tracks play together.', 'compath-choir-rehearsal' ); ?></p>
			<div class="choir-mixer__timeline">
				<span class="choir-mixer__time">0:00</span>
				<input type="range" class="choir-mixer__seek" min="0" max="1000" value="0" step="1" aria-label="<?php esc_attr_e( 'Seek', 'compath-choir-rehearsal' ); ?>" />
				<span class="choir-mixer__duration">0:00</span>
			</div>
			<div class="choir-mixer__channels" role="list">
				<?php foreach ( $mix_tracks as $mix_track ) : ?>
					<?php
					$mix_id    = (int) $mix_track['id'];
					$mix_label = (string) $mix_track['label'];
					$label_id  = 'choir-mixer-label-' . $mix_id;
					?>
					<div class="choir-mixer__channel" role="listitem" data-track-id="<?php echo esc_attr( (string) $mix_id ); ?>" data-url="<?php echo esc_url( (string) $mix_track['url'] ); ?>">
						<span class="choir-mixer__name" id="<?php echo esc_attr( $label_id ); ?>"><?php echo esc_html( $mix_label ); ?></span>
						<div
							class="choir-mixer__fader"
							role="slider"
							tabindex="0"
							aria-valuemin="0"
							aria-valuemax="100"
							aria-valuenow="70"
							aria-valuetext="70%"
							aria-orientation="vertical"
							aria-labelledby="<?php echo esc_attr( $label_id ); ?>"
						>
							<span class="choir-mixer__fader-track" aria-hidden="true"><span class="choir-mixer__fader-fill"></span></span>
							<span class="choir-mixer__fader-thumb" aria-hidden="true"></span>
						</div>
						<div class="choir-mixer__flags">
							<button type="button" class="choir-mixer__mute" aria-pressed="false" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: track name */ __( 'Mute %s', 'compath-choir-rehearsal' ), $mix_label ) ); ?>">M</button>
							<button type="button" class="choir-mixer__solo" aria-pressed="false" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: track name */ __( 'Solo %s', 'compath-choir-rehearsal' ), $mix_label ) ); ?>">S</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="choir-mixer__controls">
				<button type="button" class="choir-mixer__back" aria-label="<?php esc_attr_e( 'Back 5 seconds', 'compath-choir-rehearsal' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M11 6.5v11L4.2 12 11 6.5zm8.8 0v11L13 12l6.8-5.5z"/></svg>
				</button>
				<button type="button" class="choir-mixer__play" aria-label="<?php esc_attr_e( 'Play', 'compath-choir-rehearsal' ); ?>">
					<svg class="choir-mixer__play-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 5.2v13.6L19.5 12 8 5.2z"/></svg>
					<svg class="choir-mixer__pause-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7 5h3.5v14H7V5zm6.5 0H17v14h-3.5V5z"/></svg>
				</button>
				<button type="button" class="choir-mixer__forward" aria-label="<?php esc_attr_e( 'Forward 5 seconds', 'compath-choir-rehearsal' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4.2 6.5L11 12l-6.8 5.5v-11zm8.8 0L20 12l-7 5.5v-11z"/></svg>
				</button>
				<button type="button" class="choir-mixer__ab" aria-pressed="false"><?php esc_html_e( 'A–B loop', 'compath-choir-rehearsal' ); ?></button>
				<button type="button" class="choir-mixer__speed"><?php echo esc_html( sprintf( /* translators: %s: playback rate such as 0.9× */ __( 'Speed %s', 'compath-choir-rehearsal' ), '1×' ) ); ?></button>
				<button type="button" class="choir-mixer__reset"><?php esc_html_e( 'Reset', 'compath-choir-rehearsal' ); ?></button>
			</div>
			<p class="choir-mixer__status" role="status" aria-live="polite"></p>
		</div>
		<?php
	}
}
