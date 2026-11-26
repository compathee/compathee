<?php
/**
 * Single song template with voice tracks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main choir-rehearsal-main">
	<?php
	Choir_Rehearsal_Frontend::render_user_bar();
	while ( have_posts() ) :
		the_post();
		$choir_rehearsal_song = get_post();
		if ( $choir_rehearsal_song instanceof WP_Post && ! Choir_Rehearsal_Access::can_view_song( (int) $choir_rehearsal_song->ID ) ) {
			if ( is_user_logged_in() ) {
				Choir_Rehearsal_Frontend::render_song_access_denied();
			} else {
				Choir_Rehearsal_Frontend::render_login_form();
			}
			continue;
		}
		Choir_Rehearsal_Frontend::render_song( $choir_rehearsal_song );
	endwhile;
	?>
</main>
<?php
get_footer();
