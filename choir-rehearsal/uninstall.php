<?php
/**
 * Uninstall cleanup.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$choir_rehearsal_tracks = get_posts(
	array(
		'post_type'      => 'choir_track',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $choir_rehearsal_tracks as $choir_rehearsal_track_id ) {
	wp_delete_post( (int) $choir_rehearsal_track_id, true );
}

$choir_rehearsal_songs = get_posts(
	array(
		'post_type'      => 'choir_song',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $choir_rehearsal_songs as $choir_rehearsal_song_id ) {
	wp_delete_post( (int) $choir_rehearsal_song_id, true );
}

delete_option( 'choir_rehearsal_require_login' );

$choir_rehearsal_terms = get_terms(
	array(
		'taxonomy'   => 'choir_voice_type',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( is_array( $choir_rehearsal_terms ) ) {
	foreach ( $choir_rehearsal_terms as $choir_rehearsal_term_id ) {
		wp_delete_term( (int) $choir_rehearsal_term_id, 'choir_voice_type' );
	}
}
