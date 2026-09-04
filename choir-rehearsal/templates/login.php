<?php
/**
 * Frontend login template for rehearsal pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main choir-rehearsal-main">
	<?php Choir_Rehearsal_Frontend::render_login_form(); ?>
</main>
<?php
get_footer();
