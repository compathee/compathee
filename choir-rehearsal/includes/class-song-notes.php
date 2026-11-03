<?php
/**
 * Pro song notes and per-track recommendations.
 *
 * Voice Leader and Administrator create, edit, and delete. Anyone who can
 * open the song can read. Notes stay on this site; nothing is emailed.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Song_Notes {

	private const DB_OPTION = 'choir_rehearsal_notes_db';

	private const MAX_NOTES = 300;

	/** @var array<int, array<int, int>> */
	private static array $counts = array();

	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_install' ), 6 );
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 20 );
		add_action( 'before_delete_post', array( self::class, 'delete_for_post' ) );
		add_action( 'admin_init', array( self::class, 'register_privacy_policy' ) );
	}

	/**
	 * Extra privacy text only when this file is installed (not the WordPress.org package).
	 */
	public static function register_privacy_policy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) || ! self::is_enabled() ) {
			return;
		}

		wp_add_privacy_policy_content(
			'Compath Choir Rehearsal',
			'<p>' . esc_html__( 'Song notes store the note text and the WordPress user ID of the author on this website. Voice input is transcribed in the browser and the audio is not stored. The plugin does not email singers about notes.', 'compath-choir-rehearsal' ) . '</p>'
		);
	}

	public static function is_enabled(): bool {
		return Choir_Rehearsal_Edition::can_use_song_notes();
	}

	public static function maybe_install(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( '1' === (string) get_option( self::DB_OPTION, '' ) && self::table_exists() ) {
			return;
		}
		self::install();
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			song_id bigint(20) unsigned NOT NULL DEFAULT 0,
			track_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			body text NOT NULL,
			created_gmt datetime NOT NULL,
			updated_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY song_id (song_id),
			KEY song_track (song_id, track_id)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::DB_OPTION, '1', false );
	}

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'choir_song_notes';
	}

	public static function register_routes(): void {
		register_rest_route(
			'choir-rehearsal/v1',
			'/songs/(?P<id>\d+)/notes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'rest_list' ),
					'permission_callback' => array( self::class, 'can_read_request' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'rest_create' ),
					'permission_callback' => array( self::class, 'can_write_request' ),
				),
			)
		);

		register_rest_route(
			'choir-rehearsal/v1',
			'/songs/(?P<id>\d+)/notes/(?P<note_id>\d+)',
			array(
				array(
					'methods'             => 'PUT, PATCH',
					'callback'            => array( self::class, 'rest_update' ),
					'permission_callback' => array( self::class, 'can_write_request' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'rest_delete' ),
					'permission_callback' => array( self::class, 'can_write_request' ),
				),
			)
		);
	}

	public static function can_read_request( WP_REST_Request $request ): bool {
		$song_id = (int) $request['id'];

		return Choir_Rehearsal_Song_Notes_Format::user_can_read(
			self::is_enabled(),
			Choir_Rehearsal_Access::can_view_song( $song_id )
		);
	}

	public static function can_write_request( WP_REST_Request $request ): bool {
		if ( ! self::verify_write_nonce( $request ) ) {
			return false;
		}

		$song_id = (int) $request['id'];

		return Choir_Rehearsal_Song_Notes_Format::user_can_write(
			self::is_enabled(),
			is_user_logged_in(),
			Choir_Rehearsal_Access::can_manage()
		) && Choir_Rehearsal_Access::can_view_song( $song_id );
	}

	public static function rest_list( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! self::can_read_request( $request ) ) {
			return self::forbidden();
		}

		$song_id = (int) $request['id'];
		if ( ! self::song_exists( $song_id ) ) {
			return new WP_Error( 'not_found', __( 'Song not found.', 'compath-choir-rehearsal' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( self::payload( $song_id ), 200 );
	}

	public static function rest_create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! self::can_write_request( $request ) ) {
			return self::forbidden();
		}

		$song_id = (int) $request['id'];
		if ( ! self::song_exists( $song_id ) ) {
			return new WP_Error( 'not_found', __( 'Song not found.', 'compath-choir-rehearsal' ), array( 'status' => 404 ) );
		}

		$body = Choir_Rehearsal_Song_Notes_Format::sanitize_body( (string) $request->get_param( 'body' ) );
		if ( '' === $body ) {
			return new WP_Error( 'empty_note', __( 'Write a note before publishing.', 'compath-choir-rehearsal' ), array( 'status' => 400 ) );
		}

		self::maybe_install();
		if ( self::count_for_song( $song_id ) >= self::MAX_NOTES ) {
			return new WP_Error(
				'note_limit',
				__( 'This song has too many notes. Delete one before adding another.', 'compath-choir-rehearsal' ),
				array( 'status' => 400 )
			);
		}

		$track_id = self::addressed_track_id( $song_id, $body );
		$now      = current_time( 'mysql', true );
		global $wpdb;

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'song_id'     => $song_id,
				'track_id'    => $track_id,
				'user_id'     => get_current_user_id(),
				'body'        => $body,
				'created_gmt' => $now,
				'updated_gmt' => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'note_save_failed', __( 'Could not save the note. Please try again.', 'compath-choir-rehearsal' ), array( 'status' => 500 ) );
		}

		unset( self::$counts[ $song_id ] );
		$note = self::format_note_id( (int) $wpdb->insert_id, $song_id );
		if ( null === $note ) {
			return new WP_Error( 'note_save_failed', __( 'Could not save the note. Please try again.', 'compath-choir-rehearsal' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'note'   => $note,
				'counts' => self::counts_for_script( $song_id ),
			),
			201
		);
	}

	public static function rest_update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! self::can_write_request( $request ) ) {
			return self::forbidden();
		}

		$song_id = (int) $request['id'];
		$note_id = (int) $request['note_id'];
		if ( ! self::song_exists( $song_id ) || null === self::get_row( $note_id, $song_id ) ) {
			return new WP_Error( 'not_found', __( 'Note not found.', 'compath-choir-rehearsal' ), array( 'status' => 404 ) );
		}

		$body = Choir_Rehearsal_Song_Notes_Format::sanitize_body( (string) $request->get_param( 'body' ) );
		if ( '' === $body ) {
			return new WP_Error( 'empty_note', __( 'Write a note before publishing.', 'compath-choir-rehearsal' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$updated = $wpdb->update(
			self::table_name(),
			array(
				'body'        => $body,
				'track_id'    => self::addressed_track_id( $song_id, $body ),
				'updated_gmt' => current_time( 'mysql', true ),
			),
			array(
				'id'      => $note_id,
				'song_id' => $song_id,
			),
			array( '%s', '%d', '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'note_save_failed', __( 'Could not save the note. Please try again.', 'compath-choir-rehearsal' ), array( 'status' => 500 ) );
		}

		unset( self::$counts[ $song_id ] );

		return new WP_REST_Response(
			array(
				'note'   => self::format_note_id( $note_id, $song_id ),
				'counts' => self::counts_for_script( $song_id ),
			),
			200
		);
	}

	public static function rest_delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! self::can_write_request( $request ) ) {
			return self::forbidden();
		}

		$song_id = (int) $request['id'];
		$note_id = (int) $request['note_id'];
		if ( null === self::get_row( $note_id, $song_id ) ) {
			return new WP_Error( 'not_found', __( 'Note not found.', 'compath-choir-rehearsal' ), array( 'status' => 404 ) );
		}

		global $wpdb;
		$deleted = $wpdb->delete(
			self::table_name(),
			array(
				'id'      => $note_id,
				'song_id' => $song_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error( 'note_delete_failed', __( 'Could not delete the note. Please try again.', 'compath-choir-rehearsal' ), array( 'status' => 500 ) );
		}

		unset( self::$counts[ $song_id ] );

		return new WP_REST_Response(
			array(
				'deleted' => $note_id,
				'counts'  => self::counts_for_script( $song_id ),
			),
			200
		);
	}

	public static function delete_for_post( int $post_id ): void {
		if ( $post_id <= 0 || ! self::table_exists() ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		global $wpdb;
		$table = self::table_name();
		if ( Choir_Rehearsal_Post_Types::SONG === $post->post_type ) {
			$wpdb->delete( $table, array( 'song_id' => $post_id ), array( '%d' ) );
			unset( self::$counts[ $post_id ] );
			return;
		}

		if ( Choir_Rehearsal_Post_Types::TRACK === $post->post_type ) {
			$wpdb->delete( $table, array( 'track_id' => $post_id ), array( '%d' ) );
			self::$counts = array();
		}
	}

	public static function enqueue(): void {
		if ( ! self::is_enabled() || ! is_singular( Choir_Rehearsal_Post_Types::SONG ) ) {
			return;
		}

		$song_id = (int) get_queried_object_id();
		if ( ! Choir_Rehearsal_Song_Notes_Format::user_can_read( true, Choir_Rehearsal_Access::can_view_song( $song_id ) ) ) {
			return;
		}

		wp_enqueue_script(
			'choir-rehearsal-song-notes',
			CHOIR_REHEARSAL_URL . 'public/js/song-notes.js',
			array( 'choir-rehearsal-player' ),
			CHOIR_REHEARSAL_VERSION,
			true
		);

		$data   = array(
			'songId'     => $song_id,
			'restUrl'    => rest_url( 'choir-rehearsal/v1/songs/' . $song_id . '/notes' ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'notesNonce' => wp_create_nonce( 'choir_rehearsal_song_notes' ),
			'canWrite'   => Choir_Rehearsal_Song_Notes_Format::user_can_write(
				true,
				is_user_logged_in(),
				Choir_Rehearsal_Access::can_manage()
			),
			'speechLang' => 'en-US',
			'tracks'     => self::track_payload( $song_id ),
			'counts'     => self::counts_map( $song_id ),
			'i18n'       => self::script_i18n(),
		);

		wp_add_inline_script(
			'choir-rehearsal-song-notes',
			'window.choirRehearsalNotes = ' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
		);
	}

	public static function render_header_button( int $song_id ): void {
		if ( ! self::is_enabled() || $song_id <= 0 ) {
			return;
		}
		?>
		<button
			type="button"
			class="choir-notes-open"
			data-notes-mode="song"
			aria-haspopup="dialog"
			aria-controls="choir-song-notes"
			aria-label="<?php esc_attr_e( 'Open song notes', 'compath-choir-rehearsal' ); ?>"
		>
			<span class="choir-notes-open__icon" aria-hidden="true">
				<?php echo self::speech_bubble_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</span>
		</button>
		<?php
	}

	public static function render_track_button( int $song_id, int $track_id, string $label ): void {
		if ( ! self::is_enabled() || $song_id <= 0 || $track_id <= 0 ) {
			return;
		}

		$counts = self::counts_map( $song_id );
		$count  = $counts[ $track_id ] ?? 0;
		?>
		<button
			type="button"
			class="choir-track-notes"
			data-notes-mode="track"
			data-track-id="<?php echo esc_attr( (string) $track_id ); ?>"
			aria-haspopup="dialog"
			aria-controls="choir-song-notes"
			aria-label="<?php echo esc_attr( sprintf( /* translators: %s: voice part label */ __( 'Track recommendations for %s', 'compath-choir-rehearsal' ), $label ) ); ?>"
			<?php echo $count < 1 ? 'hidden' : ''; ?>
		>
			<span class="choir-track-notes__icon" aria-hidden="true">
				<?php echo self::message_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			</span>
		</button>
		<?php
	}

	public static function render_shell( int $song_id ): void {
		if ( ! self::is_enabled() || $song_id <= 0 ) {
			return;
		}

		$can_write = Choir_Rehearsal_Song_Notes_Format::user_can_write(
			true,
			is_user_logged_in(),
			Choir_Rehearsal_Access::can_manage()
		);
		?>
		<div class="choir-notes-layer" id="choir-notes-layer" hidden>
			<div class="choir-notes-backdrop" data-choir-notes-close></div>
			<div
				id="choir-song-notes"
				class="choir-notes-sheet"
				role="dialog"
				aria-modal="true"
				aria-labelledby="choir-notes-title"
				hidden
			>
				<div class="choir-notes-sheet__handle" aria-hidden="true"></div>
				<div class="choir-notes-sheet__header">
					<h2 id="choir-notes-title" class="choir-notes-sheet__title"><?php esc_html_e( 'Song notes', 'compath-choir-rehearsal' ); ?></h2>
					<button type="button" class="choir-notes-close" data-choir-notes-close aria-label="<?php esc_attr_e( 'Close song notes', 'compath-choir-rehearsal' ); ?>">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="choir-notes-sheet__body" tabindex="-1">
					<p class="choir-notes-empty" hidden></p>
					<div class="choir-notes-list"></div>
				</div>
				<?php if ( $can_write ) : ?>
					<form class="choir-notes-form">
						<div class="choir-notes-form__tools">
							<button type="button" class="choir-notes-at"><?php esc_html_e( '@ Part', 'compath-choir-rehearsal' ); ?></button>
							<button type="button" class="choir-notes-time" aria-label="<?php esc_attr_e( 'Insert time mark', 'compath-choir-rehearsal' ); ?>">
								<span aria-hidden="true">&#9201;</span>
							</button>
							<button type="button" class="choir-notes-info" aria-expanded="false" aria-controls="choir-notes-guide" aria-label="<?php esc_attr_e( 'How song notes work', 'compath-choir-rehearsal' ); ?>">
								<span aria-hidden="true">&#9432;</span>
							</button>
							<button type="button" class="choir-notes-mic" hidden aria-pressed="false" aria-expanded="false" aria-controls="choir-notes-langpop" aria-label="<?php esc_attr_e( 'Dictate a note', 'compath-choir-rehearsal' ); ?>">
								<span class="choir-notes-mic__icon" aria-hidden="true"><?php echo self::studio_mic_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							</button>
							<p class="choir-notes-speech-unsupported" role="status" hidden><?php esc_html_e( 'Voice input is not supported in this browser.', 'compath-choir-rehearsal' ); ?></p>
						</div>
						<div id="choir-notes-langpop" class="choir-notes-langpop" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Dictation language', 'compath-choir-rehearsal' ); ?>" hidden>
							<input type="search" class="choir-notes-langsearch" role="combobox" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="choir-notes-langlist" aria-label="<?php esc_attr_e( 'Search languages', 'compath-choir-rehearsal' ); ?>" placeholder="<?php esc_attr_e( 'Search languages', 'compath-choir-rehearsal' ); ?>" />
							<ul id="choir-notes-langlist" class="choir-notes-langlist" role="listbox" aria-label="<?php esc_attr_e( 'Dictation language', 'compath-choir-rehearsal' ); ?>"></ul>
						</div>
						<div id="choir-notes-guide" class="choir-notes-guide" hidden>
							<ol>
								<li><?php esc_html_e( 'Type @ or tap @ Part and choose a track.', 'compath-choir-rehearsal' ); ?></li>
								<li><?php esc_html_e( 'Tap ⏱ to add a time mark.', 'compath-choir-rehearsal' ); ?></li>
								<li><?php esc_html_e( 'Write the recommendation and publish — a red icon appears on the track, and everyone can read it.', 'compath-choir-rehearsal' ); ?></li>
								<li><?php esc_html_e( 'Without @, the note applies to the whole song.', 'compath-choir-rehearsal' ); ?></li>
							</ol>
							<p class="choir-notes-guide__example"><?php esc_html_e( 'Example: @Tenor 1:12 enter softer, crescendo from bar 17', 'compath-choir-rehearsal' ); ?></p>
						</div>
						<div class="choir-notes-picker" hidden></div>
						<label class="screen-reader-text" for="choir-notes-input"><?php esc_html_e( 'Song notes', 'compath-choir-rehearsal' ); ?></label>
						<textarea id="choir-notes-input" class="choir-notes-input" rows="3" maxlength="5000" placeholder="<?php esc_attr_e( 'Type @ and choose a part to address advice to a track', 'compath-choir-rehearsal' ); ?>"></textarea>
						<p class="choir-notes-form__hint"><?php esc_html_e( 'Voice input stays in this field for editing. Nothing is published automatically, and audio is not stored.', 'compath-choir-rehearsal' ); ?></p>
						<div class="choir-notes-form__actions">
							<button type="button" class="choir-notes-cancel" hidden><?php esc_html_e( 'Cancel', 'compath-choir-rehearsal' ); ?></button>
							<button type="submit" class="choir-notes-publish"><?php esc_html_e( 'Publish', 'compath-choir-rehearsal' ); ?></button>
						</div>
						<p class="choir-notes-error" role="alert" hidden></p>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @return array<string, string>
	 */
	private static function script_i18n(): array {
		return array(
			'songTitle'       => __( 'Song notes', 'compath-choir-rehearsal' ),
			'trackTitle'      => __( 'Track recommendations', 'compath-choir-rehearsal' ),
			'emptySong'       => __( 'No song notes yet.', 'compath-choir-rehearsal' ),
			'emptyTrack'      => __( 'No recommendations for this track yet.', 'compath-choir-rehearsal' ),
			'publish'         => __( 'Publish', 'compath-choir-rehearsal' ),
			'save'            => __( 'Save', 'compath-choir-rehearsal' ),
			'edit'            => __( 'Edit note', 'compath-choir-rehearsal' ),
			'delete'          => __( 'Delete note', 'compath-choir-rehearsal' ),
			'confirmDelete'   => __( 'Delete this note?', 'compath-choir-rehearsal' ),
			'saveFailed'      => __( 'Could not save the note. Please try again.', 'compath-choir-rehearsal' ),
			'deleteFailed'    => __( 'Could not delete the note. Please try again.', 'compath-choir-rehearsal' ),
			'choosePart'      => __( 'Choose a voice part', 'compath-choir-rehearsal' ),
			'noTracks'        => __( 'No tracks uploaded yet.', 'compath-choir-rehearsal' ),
			'dictate'         => __( 'Dictate a note', 'compath-choir-rehearsal' ),
			'stopDictation'   => __( 'Stop dictation', 'compath-choir-rehearsal' ),
			'dictationLanguage' => __( 'Dictation language', 'compath-choir-rehearsal' ),
			'searchLanguages' => __( 'Search languages', 'compath-choir-rehearsal' ),
			'speechUnsupported' => __( 'Voice input is not supported in this browser.', 'compath-choir-rehearsal' ),
			'noLanguageMatch' => __( 'No matching language.', 'compath-choir-rehearsal' ),
			'seekTime'        => __( 'Seek to this time', 'compath-choir-rehearsal' ),
			'placeholder'     => __( 'Type @ and choose a part to address advice to a track', 'compath-choir-rehearsal' ),
			'loadFailed'      => __( 'Could not load song notes. Please try again.', 'compath-choir-rehearsal' ),
		);
	}

	/**
	 * @return array{notes:list<array<string, mixed>>,counts:array<string, int>}
	 */
	private static function payload( int $song_id ): array {
		return array(
			'notes'  => self::notes_for_song( $song_id ),
			'counts' => self::counts_for_script( $song_id ),
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function notes_for_song( int $song_id ): array {
		$rows   = self::rows_for_song( $song_id );
		$tracks = self::track_payload( $song_id );
		$notes  = array();
		foreach ( $rows as $row ) {
			$notes[] = self::format_row( $row, $tracks );
		}

		return $notes;
	}

	/**
	 * @param list<array{id:int,label:string,audioUrl:string,title:string}> $tracks
	 * @return array<string, mixed>
	 */
	private static function format_row( object $row, array $tracks ): array {
		$body     = (string) $row->body;
		$user_id  = (int) $row->user_id;
		$track_id = (int) $row->track_id;
		$user     = get_userdata( $user_id );
		$author   = ( $user instanceof WP_User && '' !== $user->display_name )
			? $user->display_name
			: __( 'Choir member', 'compath-choir-rehearsal' );

		return array(
			'id'         => (int) $row->id,
			'song_id'    => (int) $row->song_id,
			'track_id'   => $track_id,
			'author'     => $author,
			'body'       => $body,
			'created_at' => self::gmt_to_iso( (string) $row->created_gmt ),
			'segments'   => Choir_Rehearsal_Song_Notes_Format::segments( $body, $tracks, $track_id ),
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function format_note_id( int $note_id, int $song_id ): ?array {
		$row = self::get_row( $note_id, $song_id );
		if ( null === $row ) {
			return null;
		}

		return self::format_row( $row, self::track_payload( $song_id ) );
	}

	/**
	 * @return list<object>
	 */
	private static function rows_for_song( int $song_id ): array {
		if ( ! self::table_exists() ) {
			return array();
		}

		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the plugin prefix plus a fixed suffix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, song_id, track_id, user_id, body, created_gmt, updated_gmt FROM {$table} WHERE song_id = %d ORDER BY created_gmt ASC, id ASC",
				$song_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	private static function get_row( int $note_id, int $song_id ): ?object {
		if ( $note_id <= 0 || ! self::table_exists() ) {
			return null;
		}

		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the plugin prefix plus a fixed suffix.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, song_id, track_id, user_id, body, created_gmt, updated_gmt FROM {$table} WHERE id = %d AND song_id = %d",
				$note_id,
				$song_id
			)
		);

		return is_object( $row ) ? $row : null;
	}

	private static function count_for_song( int $song_id ): int {
		if ( ! self::table_exists() ) {
			return 0;
		}

		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the plugin prefix plus a fixed suffix.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE song_id = %d",
				$song_id
			)
		);
	}

	/**
	 * @return array<int, int>
	 */
	public static function counts_map( int $song_id ): array {
		if ( isset( self::$counts[ $song_id ] ) ) {
			return self::$counts[ $song_id ];
		}

		$map = array();
		if ( self::table_exists() ) {
			global $wpdb;
			$table = self::table_name();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is the plugin prefix plus a fixed suffix.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT track_id, COUNT(*) AS total FROM {$table} WHERE song_id = %d AND track_id > 0 GROUP BY track_id",
					$song_id
				)
			);
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$map[ (int) $row->track_id ] = (int) $row->total;
				}
			}
		}

		self::$counts[ $song_id ] = $map;

		return $map;
	}

	/**
	 * String keys so JSON objects keep track ids.
	 *
	 * @return array<string, int>
	 */
	private static function counts_for_script( int $song_id ): array {
		$out = array();
		foreach ( self::counts_map( $song_id ) as $track_id => $total ) {
			$out[ (string) $track_id ] = $total;
		}

		return $out;
	}

	private static function addressed_track_id( int $song_id, string $body ): int {
		return Choir_Rehearsal_Song_Notes_Format::resolve_track_id( $body, self::track_payload( $song_id ) );
	}

	/**
	 * @return list<array{id:int,label:string,audioUrl:string,title:string}>
	 */
	private static function track_payload( int $song_id ): array {
		$song_title = get_the_title( $song_id );
		$song_title = is_string( $song_title ) ? $song_title : '';
		$payload    = array();

		foreach ( Choir_Rehearsal_Post_Types::get_tracks_for_song( $song_id ) as $track ) {
			$track_id = (int) $track->ID;
			$label    = Choir_Rehearsal_Post_Types::get_voice_label( $track_id );
			$source   = Choir_Rehearsal_Post_Types::get_track_source( $track_id );
			$audio    = 'audio' === $source ? Choir_Rehearsal_Post_Types::get_audio_url( $track_id ) : '';
			$payload[] = array(
				'id'       => $track_id,
				'label'    => $label,
				'audioUrl' => $audio,
				'title'    => $song_title . ' — ' . $label,
			);
		}

		return $payload;
	}

	private static function song_exists( int $song_id ): bool {
		$song = get_post( $song_id );

		return $song instanceof WP_Post && Choir_Rehearsal_Post_Types::SONG === $song->post_type;
	}

	private static function table_exists(): bool {
		global $wpdb;

		$table = self::table_name();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		return is_string( $found ) && $found === $table;
	}

	private static function verify_write_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'x-choir-notes-nonce' );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$param = $request->get_param( 'notes_nonce' );
			$nonce = is_string( $param ) ? $param : '';
		}

		return (bool) wp_verify_nonce( sanitize_text_field( $nonce ), 'choir_rehearsal_song_notes' );
	}

	private static function forbidden(): WP_Error {
		return new WP_Error( 'forbidden', __( 'You cannot edit song notes.', 'compath-choir-rehearsal' ), array( 'status' => 403 ) );
	}

	private static function gmt_to_iso( string $mysql ): string {
		$stamp = strtotime( $mysql . ' UTC' );
		if ( false === $stamp ) {
			return '';
		}

		return gmdate( 'c', $stamp );
	}

	private static function speech_bubble_svg(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" focusable="false"><path fill="currentColor" d="M5 4h14a2 2 0 0 1 2 2v8.2a2 2 0 0 1-2 2H9.2L5.4 19.7c-.46.37-1.15.04-1.15-.55V6a2 2 0 0 1 2-2z"/></svg>';
	}

	private static function message_svg(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" focusable="false"><path fill="currentColor" d="M4 5.5h16a1.5 1.5 0 0 1 1.5 1.5v8.2a1.5 1.5 0 0 1-1.5 1.5H9.4L5.2 20.2c-.5.4-1.2 0-1.2-.6V7A1.5 1.5 0 0 1 5.5 5.5H4z"/></svg>';
	}

	private static function studio_mic_svg(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" focusable="false"><circle cx="12" cy="9.2" r="6.4" fill="none" stroke="currentColor" stroke-width="1.4"/><path fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" d="M7.4 10.4c.7 2.2 2.2 3.4 4.6 3.4s3.9-1.2 4.6-3.4"/><rect x="9.35" y="4.7" width="5.3" height="8.1" rx="2.65" fill="currentColor"/><path stroke="#fff" stroke-width="0.7" stroke-linecap="round" d="M10 6.7h4M9.8 8.2h4.4M10 9.7h4"/><path fill="currentColor" d="M11.15 15.6h1.7V18h2.35v1.45H8.8V18h2.35z"/></svg>';
	}
}
