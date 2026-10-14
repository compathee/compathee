<?php
/**
 * Contract: PDF score attach requires rights confirmation (UI + save gate).
 */
declare(strict_types=1);

$admin_js  = file_get_contents( __DIR__ . '/../admin/js/admin.js' );
$admin_php = file_get_contents( __DIR__ . '/../includes/class-admin.php' );
$admin_css = file_get_contents( __DIR__ . '/../admin/css/admin.css' );
$boot_php  = file_get_contents( __DIR__ . '/../includes/class-plugin.php' );

require_once __DIR__ . '/../includes/class-score-rights.php';

$checks = array(
	'helper_allows_unchanged'      => Choir_Rehearsal_Score_Rights::may_attach_pdf( 12, 12, false ),
	'helper_allows_remove'         => Choir_Rehearsal_Score_Rights::may_attach_pdf( 12, 0, false ),
	'helper_blocks_new_without'    => ! Choir_Rehearsal_Score_Rights::may_attach_pdf( 0, 99, false ),
	'helper_allows_new_with'       => Choir_Rehearsal_Score_Rights::may_attach_pdf( 0, 99, true ),
	'helper_blocks_change_without' => ! Choir_Rehearsal_Score_Rights::may_attach_pdf( 12, 99, false ),
	'js_opens_rights_modal'        => str_contains( $admin_js, 'choir-score-rights-modal' )
		&& str_contains( $admin_js, 'openScoreRightsModal' ),
	'js_confirm_opens_media'       => str_contains( $admin_js, 'openPdfMediaFrame' )
		&& str_contains( $admin_js, 'choir-score-rights-confirm' ),
	'js_sets_hidden_flag'          => str_contains( $admin_js, 'choir-score-rights-confirmed' )
		&& str_contains( $admin_js, ".val('1')" ),
	'metabox_has_modal'            => str_contains( $admin_php, 'choir-score-rights-modal' )
		&& str_contains( $admin_php, 'choir_score_rights_confirmed' ),
	'metabox_has_confirm_copy'     => str_contains( $admin_php, 'I confirm that I have the rights to use this musical work' )
		&& str_contains( $admin_php, 'public domain' ),
	'save_uses_helper'             => str_contains( $admin_php, 'Choir_Rehearsal_Score_Rights::may_attach_pdf' ),
	'save_checks_edit_cap'         => str_contains( $admin_php, "current_user_can( 'edit_post', \$post_id )" ),
	'save_stores_audit_meta'       => str_contains( $admin_php, '_choir_score_rights_confirmed_at' )
		&& str_contains( $admin_php, '_choir_score_rights_confirmed_by' ),
	'css_modal'                    => str_contains( $admin_css, '.choir-score-rights-modal' ),
	'boot_loads_helper'            => str_contains( $boot_php, 'class-score-rights.php' ),
);

$fail = 0;
foreach ( $checks as $name => $ok ) {
	echo ( $ok ? 'PASS' : 'FAIL' ) . " $name\n";
	if ( ! $ok ) {
		$fail++;
	}
}

exit( $fail > 0 ? 1 : 0 );
