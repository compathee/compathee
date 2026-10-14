<?php
/**
 * Contract: making a song public requires rights confirmation (globe toggle).
 */
declare(strict_types=1);

$admin_js  = file_get_contents( __DIR__ . '/../admin/js/admin.js' );
$admin_php = file_get_contents( __DIR__ . '/../includes/class-admin.php' );
$admin_css = file_get_contents( __DIR__ . '/../admin/css/admin.css' );

require_once __DIR__ . '/../includes/class-score-rights.php';

$checks = array(
	'helper_allows_stay_private'     => Choir_Rehearsal_Score_Rights::may_publish_public( false, false, false ),
	'helper_allows_stay_public'      => Choir_Rehearsal_Score_Rights::may_publish_public( true, true, false ),
	'helper_allows_make_private'     => Choir_Rehearsal_Score_Rights::may_publish_public( true, false, false ),
	'helper_blocks_public_without'   => ! Choir_Rehearsal_Score_Rights::may_publish_public( false, true, false ),
	'helper_allows_public_with'      => Choir_Rehearsal_Score_Rights::may_publish_public( false, true, true ),
	'js_opens_public_modal'          => str_contains( $admin_js, 'choir-public-rights-modal' )
		&& str_contains( $admin_js, 'openPublicRightsModal' ),
	'js_confirm_sets_public'         => str_contains( $admin_js, 'choir-public-rights-confirm' )
		&& str_contains( $admin_js, 'choir-public-rights-confirmed' ),
	'js_globe_gated'                 => str_contains( $admin_js, "choir-toggle-public" )
		&& str_contains( $admin_js, 'openPublicRightsModal' ),
	'ui_has_modal'                   => str_contains( $admin_php, 'choir-public-rights-modal' )
		&& str_contains( $admin_php, 'choir_public_rights_confirmed' ),
	'ui_has_copyright_warning'       => str_contains( $admin_php, 'copyright' )
		&& str_contains( $admin_php, 'public domain' )
		&& str_contains( $admin_php, 'publish' ),
	'save_uses_helper'               => str_contains( $admin_php, 'Choir_Rehearsal_Score_Rights::may_publish_public' ),
	'save_checks_edit_cap'           => str_contains( $admin_php, "current_user_can( 'edit_post', \$post_id )" ),
	'save_stores_audit_meta'         => str_contains( $admin_php, '_choir_public_rights_confirmed_at' )
		&& str_contains( $admin_php, '_choir_public_rights_confirmed_by' ),
	'css_modal'                      => str_contains( $admin_css, '.choir-public-rights-modal' )
		|| str_contains( $admin_css, '.choir-score-rights-modal' ),
);

$fail = 0;
foreach ( $checks as $name => $ok ) {
	echo ( $ok ? 'PASS' : 'FAIL' ) . " $name\n";
	if ( ! $ok ) {
		$fail++;
	}
}

exit( $fail > 0 ? 1 : 0 );
