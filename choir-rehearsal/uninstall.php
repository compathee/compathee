<?php
/**
 * Uninstall cleanup.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$tracks = get_posts(
	array(
		'post_type'      => 'choir_track',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $tracks as $track_id ) {
	wp_delete_post( (int) $track_id, true );
}

$songs = get_posts(
	array(
		'post_type'      => 'choir_song',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $songs as $song_id ) {
	wp_delete_post( (int) $song_id, true );
}

delete_option( 'choir_rehearsal_require_login' );

$terms = get_terms(
	array(
		'taxonomy'   => 'choir_voice_type',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( is_array( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( (int) $term_id, 'choir_voice_type' );
	}
}
