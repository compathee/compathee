<?php
/**
 * Contract: suggested privacy policy text, readme copyright/privacy, and translation catalogs.
 *
 * Run: php choir-rehearsal/tests/privacy-policy-test.php
 */
declare(strict_types=1);

$plugin = (string) file_get_contents( __DIR__ . '/../includes/class-plugin.php' );
$readme = (string) file_get_contents( __DIR__ . '/../readme.txt' );
$admin  = (string) file_get_contents( __DIR__ . '/../includes/class-admin.php' );

$privacy_strings = array(
	'Compath Choir Rehearsal stores rehearsal songs on this website.',
	'When a user confirms they may attach a PDF score, the plugin stores that WordPress user ID and the confirmation time in the song post meta (_choir_score_rights_confirmed_by and _choir_score_rights_confirmed_at). When a user confirms they may make a song public, it stores the same kind of record (_choir_public_rights_confirmed_by and _choir_public_rights_confirmed_at). These records show who accepted the copyright confirmation and when. Compath does not verify that the person holds those rights.',
	'Uploaded PDF scores and audio tracks are stored in the site media library and linked to the song.',
	'The optional feedback form sends the message, name, email address, and technical site information to rehearsal.compath.ee (Compath OÜ). Non-secret Pro license identifiers are included only when a separate Pro add-on license is active. The full license key is not sent.',
);

$confirmation_strings = array(
	'Confirm public access',
	'Confirm rights to this score',
	'I confirm',
	'I confirm that I have the rights to use this musical work for rehearsal on this site, or that the work is in the public domain.',
	'The PDF score was not saved. Confirm that you have the rights to use this work, or that it is in the public domain, before attaching a score.',
	'The song was not made public. Confirm that the score is in the public domain, or that you have the rights to publish it, before sharing without a password.',
	'Warning: making this song public lets anyone view the PDF score and listen without signing in. Publishing copyrighted sheet music without permission may infringe copyright. I confirm that this score is in the public domain, or that I have the rights to publish it.',
);

preg_match( '/^Tags:\s*(.+)$/m', $readme, $tag_match );
$tags = array_values( array_filter( array_map( 'trim', explode( ',', $tag_match[1] ?? '' ) ) ) );

$short = '';
if ( preg_match( '/^License URI:.*\R\R(.+)\R\R== Description ==/m', $readme, $short_match ) ) {
	$short = trim( $short_match[1] );
}

$checks = array(
	'registers_privacy_policy' => str_contains( $plugin, 'wp_add_privacy_policy_content' )
		&& str_contains( $plugin, 'admin_init' ),
	'policy_names_score_meta'  => str_contains( $plugin, '_choir_score_rights_confirmed_by' )
		&& str_contains( $plugin, '_choir_score_rights_confirmed_at' ),
	'policy_names_public_meta' => str_contains( $plugin, '_choir_public_rights_confirmed_by' )
		&& str_contains( $plugin, '_choir_public_rights_confirmed_at' ),
	'policy_mentions_uploads'  => str_contains( $plugin, 'media library' ),
	'save_checks_edit_cap'     => str_contains( $admin, "current_user_can( 'edit_post', \$post_id )" ),
	'readme_upgrade_notice'    => str_contains( $readme, "== Upgrade Notice ==\n\n= 0.4.67 =" ),
	'readme_copyright_section' => str_contains( $readme, '= Sheet music and PDF copyright =' )
		&& str_contains( $readme, 'Compath does not verify those rights.' ),
	'readme_privacy_section'   => str_contains( $readme, "== Privacy ==\n" )
		&& str_contains( $readme, '_choir_score_rights_confirmed_' )
		&& str_contains( $readme, '_choir_public_rights_confirmed_' ),
	'readme_pdf_faq'           => str_contains( $readme, '= Can I upload PDF sheet music? =' ),
	'readme_voice_part_phrase' => str_contains( $short, 'voice part rehearsal' )
		&& str_contains( $short, 'Choir' )
		&& str_contains( $readme, '= Voice part rehearsal =' ),
	'readme_short_length'      => strlen( $short ) > 0 && strlen( $short ) <= 150,
	'readme_five_tags'         => 5 === count( $tags ),
);

foreach ( array( 'et', 'et_EE', 'ru_RU' ) as $locale ) {
	$base = __DIR__ . '/../languages/compath-choir-rehearsal-' . $locale;
	$po   = (string) file_get_contents( $base . '.po' );
	$l10n = (string) file_get_contents( $base . '.l10n.php' );
	$mo   = (string) file_get_contents( $base . '.mo' );
	foreach ( array_merge( $privacy_strings, $confirmation_strings ) as $msgid ) {
		$key = $locale . ' catalogs ' . substr( $msgid, 0, 42 );
		$checks[ $key ] = str_contains( $po, 'msgid "' . $msgid . '"' )
			&& str_contains( $l10n, $msgid )
			&& str_contains( $mo, $msgid );
	}
}

$fail = 0;
foreach ( $checks as $name => $ok ) {
	echo ( $ok ? 'PASS' : 'FAIL' ) . " $name\n";
	if ( ! $ok ) {
		$fail++;
	}
}

if ( '' !== $short ) {
	echo 'SHORT (' . strlen( $short ) . ') ' . $short . "\n";
}

exit( $fail > 0 ? 1 : 0 );
