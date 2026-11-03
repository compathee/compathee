<?php
/**
 * Readme shipped in the WordPress.org zip.
 *
 * The directory package has no Pro upsell and does not include song notes.
 * Run: php scripts/wporg-readme.php <readme.txt>
 */

declare(strict_types=1);

if ( $argc < 2 ) {
	fwrite( STDERR, "usage: php scripts/wporg-readme.php readme.txt\n" );
	exit( 1 );
}

$path = $argv[1];
$text = file_get_contents( $path );
if ( ! is_string( $text ) ) {
	fwrite( STDERR, "cannot read {$path}\n" );
	exit( 1 );
}

$text = str_replace(
	' Non-secret Pro license identifiers are included only when the separate Choir Rehearsal Pro add-on is installed and a license is active (license, purchase, order, customer, and activation IDs, or a masked key if those IDs are empty). The WordPress.org plugin alone does not send license identifiers. The full license key is not sent.',
	'',
	$text
);

$text = preg_replace( "/\n= What are song notes\\? =\\n\\n.*?(?=\\n= )/s", "\n", $text ) ?? $text;
$text = preg_replace( "/\nSong notes, when the Pro add-on license is active,.*?\\n/", "\n", $text ) ?? $text;

$kept = array();
foreach ( preg_split( "/\r\n|\n|\r/", $text ) as $line ) {
	if ( preg_match( '/^= [0-9]/', $line ) ) {
		$kept[] = $line;
		continue;
	}
	if ( preg_match( '/song notes|@ Part|time mark|bottom sheet|Voice input for Voice Leader|\\bmixer\\b/i', $line ) ) {
		continue;
	}
	if ( preg_match( '/\\bPro\\b|shop\\.compath\\.ee\\/products/i', $line ) ) {
		continue;
	}
	$kept[] = $line;
}

$text = implode( "\n", $kept );
$text = preg_replace( '/(= 0\.4\.71 =\n).*?(?=\n= |\n== )/s', "= 0.4.71 =\n\n* Maintenance release.\n\n", $text ) ?? $text;
$text = preg_replace( "/(= [0-9][^\\n]* =\\n)\\n+(?== )/s", "$1\n* Maintenance release.\n\n", $text ) ?? $text;
$text = rtrim( $text ) . "\n";

if ( ! str_contains( $text, '= 0.4.71 =' ) ) {
	fwrite( STDERR, "wporg readme is missing the 0.4.71 release\n" );
	exit( 1 );
}
if ( preg_match( '/\\bPro\\b|shop\\.compath\\.ee\\/products|song notes|\\bmixer\\b/i', $text ) ) {
	fwrite( STDERR, "wporg readme still mentions Pro, the shop, song notes, or the mixer\n" );
	exit( 1 );
}

file_put_contents( $path, $text );
echo "wporg readme sanitized\n";
