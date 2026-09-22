<?php
/**
 * Admin screen: Demo data (cron instructions + 24h log).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Demo_Admin {

	public static function register(): void {
		if ( ! Choir_Rehearsal_Distribution::is_demo() ) {
			return;
		}

		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_init', array( self::class, 'maybe_generate_reset_key' ) );
	}

	public static function maybe_generate_reset_key(): void {
		if ( defined( 'CHOIR_REHEARSAL_DEMO_RESET_KEY' ) && CHOIR_REHEARSAL_DEMO_RESET_KEY ) {
			return;
		}

		$existing = (string) get_option( 'choir_rehearsal_demo_reset_key', '' );
		if ( '' !== $existing ) {
			return;
		}

		update_option( 'choir_rehearsal_demo_reset_key', wp_generate_password( 32, false, false ), false );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Choir_Rehearsal_Post_Types::SONG,
			__( 'Demo data', 'compath-choir-rehearsal' ),
			__( 'Demo data', 'compath-choir-rehearsal' ),
			'manage_options',
			'choir-rehearsal-demo-data',
			array( self::class, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['choir_demo_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ok  = '1' === (string) wp_unslash( $_GET['choir_demo_reset'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$msg = isset( $_GET['choir_demo_msg'] ) ? sanitize_text_field( rawurldecode( (string) wp_unslash( $_GET['choir_demo_msg'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				$ok ? 'success' : 'error',
				esc_html( $msg !== '' ? $msg : ( $ok ? __( 'Reset completed.', 'compath-choir-rehearsal' ) : __( 'Reset failed.', 'compath-choir-rehearsal' ) ) )
			);
		}

		$key     = Choir_Rehearsal_Demo_Reset::reset_key();
		$curl    = Choir_Rehearsal_Demo_Reset::cron_curl_example();
		$entries = array_reverse( Choir_Rehearsal_Demo_Log::entries() );
		$tz      = wp_timezone();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Demo data', 'compath-choir-rehearsal' ); ?></h1>

			<h2><?php esc_html_e( 'Limits', 'compath-choir-rehearsal' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: max songs, 2: max tracks, 3: max PDF size */
						__( 'This Demo build allows up to %1$d songs, %2$d tracks per song, and PDF scores up to %3$s.', 'compath-choir-rehearsal' ),
						Choir_Rehearsal_Edition::DEMO_MAX_SONGS,
						Choir_Rehearsal_Edition::DEMO_MAX_TRACKS,
						size_format( Choir_Rehearsal_Edition::DEMO_MAX_PDF_BYTES )
					)
				);
				?>
			</p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: current songs, 2: max songs */
						__( 'Current library: %1$d / %2$d songs.', 'compath-choir-rehearsal' ),
						Choir_Rehearsal_Demo_Limits::song_count(),
						Choir_Rehearsal_Edition::DEMO_MAX_SONGS
					)
				);
				?>
			</p>

			<h2><?php esc_html_e( 'Nightly reset setup', 'compath-choir-rehearsal' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Open your hosting Cron Jobs panel (cPanel, Zone, etc.).', 'compath-choir-rehearsal' ); ?></li>
				<li><?php esc_html_e( 'Create a daily job at 03:00 with timezone Europe/Tallinn:', 'compath-choir-rehearsal' ); ?>
					<code>0 3 * * *</code>
				</li>
				<li><?php esc_html_e( 'Command (copy):', 'compath-choir-rehearsal' ); ?>
					<p><code id="choir-demo-curl" style="display:block;padding:8px;background:#f6f7f7;word-break:break-all;"><?php echo esc_html( $curl ); ?></code></p>
					<p>
						<button type="button" class="button" id="choir-demo-copy-curl"><?php esc_html_e( 'Copy command', 'compath-choir-rehearsal' ); ?></button>
					</p>
				</li>
				<li>
					<?php esc_html_e( 'Preferred: set a secret in wp-config.php:', 'compath-choir-rehearsal' ); ?>
					<pre style="background:#f6f7f7;padding:8px;">define( 'CHOIR_REHEARSAL_DEMO_RESET_KEY', '<?php echo esc_html( $key !== '' ? $key : 'your-long-random-string' ); ?>' );</pre>
					<?php if ( ! defined( 'CHOIR_REHEARSAL_DEMO_RESET_KEY' ) ) : ?>
						<p class="description"><?php esc_html_e( 'Until then, the plugin stores a generated key in the database (shown above in the curl command).', 'compath-choir-rehearsal' ); ?></p>
					<?php endif; ?>
				</li>
				<li><?php esc_html_e( 'Optional WP-CLI alternative:', 'compath-choir-rehearsal' ); ?>
					<code>wp choir-rehearsal demo-reset</code>
				</li>
			</ol>

			<p>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=choir_rehearsal_demo_reset_now' ), 'choir_rehearsal_demo_reset_now' ) ); ?>">
					<?php esc_html_e( 'Run reset now', 'compath-choir-rehearsal' ); ?>
				</a>
			</p>

			<h2><?php esc_html_e( 'Library actions', 'compath-choir-rehearsal' ); ?></h2>
			<?php Choir_Rehearsal_Demo_Data::render_settings_buttons(); ?>

			<h2><?php esc_html_e( 'Event log (last 24 hours)', 'compath-choir-rehearsal' ); ?></h2>
			<?php if ( array() === $entries ) : ?>
				<p><?php esc_html_e( 'No demo events in the last 24 hours.', 'compath-choir-rehearsal' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time (Europe/Tallinn)', 'compath-choir-rehearsal' ); ?></th>
							<th><?php esc_html_e( 'Event', 'compath-choir-rehearsal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $entry ) : ?>
							<?php
							$dt = ( new DateTimeImmutable( '@' . (int) $entry['ts'] ) )->setTimezone( $tz );
							?>
							<tr>
								<td><?php echo esc_html( $dt->format( 'Y-m-d H:i' ) ); ?></td>
								<td><?php echo esc_html( (string) $entry['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<script>
		(function () {
			var btn = document.getElementById('choir-demo-copy-curl');
			var el = document.getElementById('choir-demo-curl');
			if (!btn || !el || !navigator.clipboard) { return; }
			btn.addEventListener('click', function () {
				navigator.clipboard.writeText(el.textContent || '').then(function () {
					btn.textContent = <?php echo wp_json_encode( __( 'Copied', 'compath-choir-rehearsal' ) ); ?>;
				});
			});
		})();
		</script>
		<?php
	}
}
