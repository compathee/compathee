<?php
/**
 * Microphone recording upload for voice tracks.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Choir_Rehearsal_Recording {

	public static function register(): void {
		if ( ! Choir_Rehearsal_Edition::can_record() ) {
			return;
		}

		add_action( 'wp_ajax_choir_rehearsal_upload_recording', array( self::class, 'handle_upload' ) );
		add_filter( 'upload_mimes', array( self::class, 'allow_audio_mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( self::class, 'fix_recording_filetype' ), 10, 5 );
	}

	/**
	 * @param array<string, string> $mimes
	 * @return array<string, string>
	 */
	public static function allow_audio_mimes( array $mimes ): array {
		$mimes['webm'] = 'audio/webm';
		$mimes['weba'] = 'audio/webm';
		$mimes['ogg']  = 'audio/ogg';
		$mimes['oga']  = 'audio/ogg';
		$mimes['wav']  = 'audio/wav';

		return $mimes;
	}

	/**
	 * Browsers often label microphone captures as video/webm.
	 *
	 * @param array<string, string|false> $data
	 * @param string                      $file
	 * @param string                      $filename
	 * @param string[]|null               $mimes
	 * @param string                      $real_mime
	 * @return array<string, string|false>
	 */
	public static function fix_recording_filetype( array $data, string $file, string $filename, ?array $mimes, string $real_mime = '' ): array {
		unset( $mimes );

		if ( preg_match( '/\.(webm|weba|ogg|oga|wav)$/i', $filename ) ) {
			$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			$map       = array(
				'webm' => 'audio/webm',
				'weba' => 'audio/webm',
				'ogg'  => 'audio/ogg',
				'oga'  => 'audio/ogg',
				'wav'  => 'audio/wav',
			);

			if ( isset( $map[ $extension ] ) ) {
				$data['ext']  = $extension;
				$data['type'] = $map[ $extension ];
			}
		}

		if ( in_array( $real_mime, array( 'video/webm', 'application/octet-stream' ), true ) && preg_match( '/\.webm$/i', $filename ) ) {
			$data['ext']  = 'webm';
			$data['type'] = 'audio/webm';
		}

		return $data;
	}

	public static function handle_upload(): void {
		if ( ! Choir_Rehearsal_Edition::can_record() ) {
			wp_send_json_error(
				array( 'message' => __( 'Microphone recording is available in Choir Rehearsal Pro.', 'choir-rehearsal' ) ),
				403
			);
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ), 'choir_rehearsal_recording' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Reload the page and try again.', 'choir-rehearsal' ) ),
				403
			);
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to upload recordings for this song.', 'choir-rehearsal' ) ),
				403
			);
		}

		if ( empty( $_FILES['recording'] ) || ! is_array( $_FILES['recording'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No recording was uploaded.', 'choir-rehearsal' ) ),
				400
			);
		}

		$file = $_FILES['recording'];
		if ( ! empty( $file['error'] ) ) {
			wp_send_json_error(
				array( 'message' => self::upload_error_message( (int) $file['error'] ) ),
				400
			);
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Recording upload was blocked by the server.', 'choir-rehearsal' ) ),
				400
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$voice_slug = isset( $_POST['voice'] ) ? sanitize_key( wp_unslash( (string) $_POST['voice'] ) ) : 'other';
		$song       = get_post( $post_id );
		$song_title = $song instanceof WP_Post ? $song->post_title : __( 'Song', 'choir-rehearsal' );
		$voice_lbl  = Choir_Rehearsal_Voice_Types::get_label( $voice_slug );
		$extension  = self::extension_from_filename( (string) ( $file['name'] ?? '' ), (string) ( $file['type'] ?? '' ) );
		$filename   = self::build_filename( $song_title, $voice_lbl, $extension );

		$file['name'] = $filename;
		$file['type'] = self::normalize_mime_type( (string) ( $file['type'] ?? '' ), $extension );

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => self::allowed_mimes(),
				'action'    => 'choir_rehearsal_upload_recording',
			)
		);

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error(
				array( 'message' => wp_strip_all_tags( (string) $upload['error'] ) ),
				400
			);
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => (string) ( $upload['type'] ?? 'audio/webm' ),
				'post_title'     => sanitize_text_field( $song_title . ' - ' . $voice_lbl ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
			),
			(string) $upload['file']
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Could not save the recording in the Media Library.', 'choir-rehearsal' ) ),
				500
			);
		}

		$metadata = wp_generate_attachment_metadata( (int) $attachment_id, (string) $upload['file'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( (int) $attachment_id, $metadata );
		}

		wp_send_json_success(
			array(
				'id'       => (int) $attachment_id,
				'filename' => basename( (string) $upload['file'] ),
				'url'      => (string) ( wp_get_attachment_url( (int) $attachment_id ) ?: '' ),
			)
		);
	}

	private static function build_filename( string $song_title, string $voice_label, string $extension ): string {
		$base = sanitize_file_name( $song_title . ' - ' . $voice_label . ' - recording.' . $extension );
		if ( '' !== $base ) {
			return $base;
		}

		return 'choir-recording-' . gmdate( 'Ymd-His' ) . '.' . $extension;
	}

	private static function normalize_mime_type( string $mime, string $extension ): string {
		if ( 'video/webm' === $mime || '' === $mime ) {
			return 'webm' === $extension ? 'audio/webm' : self::mime_from_extension( $extension );
		}

		return $mime;
	}

	private static function extension_from_filename( string $filename, string $mime ): string {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'webm', 'ogg', 'oga', 'wav', 'mp3', 'm4a' ), true ) ) {
			return $ext;
		}

		return self::extension_from_mime( $mime );
	}

	private static function mime_from_extension( string $extension ): string {
		$map = array(
			'webm' => 'audio/webm',
			'ogg'  => 'audio/ogg',
			'oga'  => 'audio/ogg',
			'wav'  => 'audio/wav',
			'mp3'  => 'audio/mpeg',
			'm4a'  => 'audio/mp4',
		);

		return $map[ $extension ] ?? 'audio/webm';
	}

	private static function upload_error_message( int $code ): string {
		return match ( $code ) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __( 'Recording is too large for the server upload limit.', 'choir-rehearsal' ),
			UPLOAD_ERR_PARTIAL  => __( 'Recording was only partially uploaded.', 'choir-rehearsal' ),
			UPLOAD_ERR_NO_FILE  => __( 'No recording file was received.', 'choir-rehearsal' ),
			default             => __( 'Recording upload failed.', 'choir-rehearsal' ),
		};
	}

	/**
	 * @return array<string, string>
	 */
	private static function allowed_mimes(): array {
		return array(
			'webm' => 'audio/webm',
			'weba' => 'audio/webm',
			'ogg'  => 'audio/ogg',
			'oga'  => 'audio/ogg',
			'wav'  => 'audio/wav',
			'mp3'  => 'audio/mpeg',
			'm4a'  => 'audio/mp4',
		);
	}

	private static function extension_from_mime( string $mime ): string {
		return match ( $mime ) {
			'audio/ogg'                => 'ogg',
			'audio/wav', 'audio/x-wav' => 'wav',
			'audio/mpeg'               => 'mp3',
			'audio/mp4'                => 'm4a',
			'video/webm'               => 'webm',
			default                    => 'webm',
		};
	}
}
