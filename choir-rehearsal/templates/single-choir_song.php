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
		Choir_Rehearsal_Frontend::render_song( get_post() );
	endwhile;
	?>
</main>
<?php
get_footer();
