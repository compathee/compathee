<?php
/**
 * Archive template for rehearsal songs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main choir-rehearsal-main">
	<?php
	Choir_Rehearsal_Frontend::render_user_bar();
	Choir_Rehearsal_Frontend::render_song_list();
	?>
</main>
<?php
get_footer();
