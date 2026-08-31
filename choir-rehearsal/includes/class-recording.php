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
		add_action( 'wp_ajax_choir_rehearsal_upload_recording', array( self::class, 'handle_upload' ) );
		add_filter( 'upload_mimes', array( self::class, 'allow_audio_mimes' ) );
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

		return $mimes;
	}

	public static function handle_upload(): void {
		check_ajax_referer( 'choir_rehearsal_recording', 'nonce' );

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
				array( 'message' => __( 'Recording upload failed.', 'choir-rehearsal' ) ),
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
		$extension  = self::extension_from_mime( (string) ( $file['type'] ?? '' ) );
		$filename   = sanitize_file_name( $song_title . ' — ' . $voice_lbl . ' — recording.' . $extension );

		$upload = wp_handle_upload(
			array(
				'name'     => $filename,
				'type'     => (string) ( $file['type'] ?? 'audio/webm' ),
				'tmp_name' => (string) ( $file['tmp_name'] ?? '' ),
				'error'    => (int) ( $file['error'] ?? 0 ),
				'size'     => (int) ( $file['size'] ?? 0 ),
			),
			array(
				'test_form' => false,
				'mimes'     => self::allowed_mimes(),
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
				'post_title'     => sanitize_text_field( $song_title . ' — ' . $voice_lbl ),
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

		wp_update_attachment_metadata( (int) $attachment_id, wp_generate_attachment_metadata( (int) $attachment_id, (string) $upload['file'] ) );

		wp_send_json_success(
			array(
				'id'       => (int) $attachment_id,
				'filename' => basename( (string) $upload['file'] ),
			)
		);
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
			'audio/ogg' => 'ogg',
			'audio/wav', 'audio/x-wav' => 'wav',
			'audio/mpeg' => 'mp3',
			'audio/mp4' => 'm4a',
			default => 'webm',
		};
	}
}
