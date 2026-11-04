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

$short = 'Choir rehearsal library for voice part rehearsal: songs, up to 4 voice tracks per song, and sheet music.';
if ( strlen( $short ) > 150 ) {
	fwrite( STDERR, "wporg short description is longer than 150 characters\n" );
	exit( 1 );
}
$text = preg_replace(
	"/^(Choir rehearsal library[^\n]*)/m",
	$short,
	$text,
	1
) ?? $text;

$description = <<<'TXT'
Choir Rehearsal is a private choir rehearsal library for voice part rehearsal. Singers learn their line with practice tracks and follow a PDF score on the song page.

= Features =

* Create songs and attach up to 4 voice tracks per song
* Built-in voice parts: backing track, bass, baritone, tenor, alto, and soprano, each with 1 and 2, plus Other
* Upload an MP3 or WAV from the Media Library, or paste a YouTube link on that track
* The song page plays the official YouTube video in place. Nothing is downloaded
* Read a PDF score on the song page, turn pages, and expand it to full screen
* The song editor shows that PDF score and turns its pages
* Sticky player with play, pause, a waveform, seek, close, and a two-octave piano
* Each voice track shows a waveform next to the part name
* Song list at /rehearsal/, 20 songs per page, with a badge for a public song and a badge for YouTube
* Keep a song private, or mark it public so guests can listen
* Copy a share link from the song page
* Place the library on any page with the shortcode [choir_rehearsal]
* Singer listens. Voice Leader manages songs. Administrator opens Settings. Role names stay in English
* Optional login-only access for the private library
* The interface is in English, Estonian, and Russian
* Signed-in members can send feedback from the song list. A confirmation email includes the case number
* Optional appearance under Settings → Appearance
* REST API for listing and reading songs

= Sheet music and PDF copyright =

Upload only scores you have the rights to use, or that are in the public domain. The plugin asks for confirmation when you attach a PDF and again when you make a song public. It records the WordPress user who confirmed and the time of that confirmation. Compath does not verify those rights. An arrangement or a published edition can still be copyrighted even when the composition is in the public domain.
TXT;

$text = preg_replace(
	"/== Description ==\n.*?(?=\n== External services ==)/s",
	"== Description ==\n\n" . $description . "\n\n",
	$text,
	1
) ?? $text;

$text = preg_replace( "/\n= What are song notes\\? =\\n\\n.*?(?=\\n= )/s", "\n", $text ) ?? $text;
$text = preg_replace( "/\nSong notes, when the Pro add-on license is active,.*?\\n/", "\n", $text ) ?? $text;

$kept = array();
foreach ( preg_split( "/\r\n|\n|\r/", $text ) as $line ) {
	if ( preg_match( '/^= [0-9]/', $line ) ) {
		$kept[] = $line;
		continue;
	}
	if ( preg_match( '/song notes|@ Part|time mark|bottom sheet|Voice input|dictation language|voice-part names|\\bmixer\\b/i', $line ) ) {
		continue;
	}
	if ( preg_match( '/\\bPro\\b|SureCart|shop\\.compath\\.ee/i', $line ) ) {
		continue;
	}
	if ( preg_match( '/unlimited voice|no Lite track cap/i', $line ) ) {
		continue;
	}
	$kept[] = $line;
}

$text = implode( "\n", $kept );
$text = preg_replace( '/(= 0\.4\.73 =\n).*?(?=\n= |\n== )/s', "= 0.4.73 =\n\n* Maintenance release.\n\n", $text ) ?? $text;
$text = preg_replace( '/(= 0\.4\.71 =\n).*?(?=\n= |\n== )/s', "= 0.4.71 =\n\n* Maintenance release.\n\n", $text ) ?? $text;
$text = preg_replace( "/(= [0-9][^\\n]* =\\n)\\n+(?== )/s", "$1\n* Maintenance release.\n\n", $text ) ?? $text;

$changelog_at = strpos( $text, "== Changelog ==\n" );
if ( false === $changelog_at ) {
	fwrite( STDERR, "wporg readme is missing the changelog\n" );
	exit( 1 );
}
$changelog = substr( $text, $changelog_at );
foreach ( array( '0.4.70', '0.4.69' ) as $version ) {
	if ( ! str_contains( $changelog, "= {$version} =\n" ) ) {
		$changelog = str_replace(
			"= 0.4.68 =\n",
			"= {$version} =\n\n* Maintenance release.\n\n= 0.4.68 =\n",
			$changelog
		);
	}
}
$text = substr( $text, 0, $changelog_at ) . $changelog;
$text = rtrim( $text ) . "\n";

foreach ( array( '0.4.74', '0.4.73', '0.4.72', '0.4.71', '0.4.70', '0.4.69', '0.4.68', '0.4.67' ) as $version ) {
	if ( ! str_contains( $text, "= {$version} =\n" ) ) {
		fwrite( STDERR, "wporg readme is missing the {$version} release\n" );
		exit( 1 );
	}
}
if ( ! str_contains( $text, 'Create songs and attach up to 4 voice tracks per song' ) ) {
	fwrite( STDERR, "wporg readme is missing the directory feature list\n" );
	exit( 1 );
}
if ( str_contains( $text, 'unlimited voice' ) ) {
	fwrite( STDERR, "wporg readme still claims unlimited voice tracks\n" );
	exit( 1 );
}
if ( preg_match( '/\\bPro\\b|SureCart|shop\\.compath\\.ee|song notes|\\bmixer\\b/i', $text ) ) {
	fwrite( STDERR, "wporg readme still mentions Pro, SureCart, the shop, song notes, or the mixer\n" );
	exit( 1 );
}

file_put_contents( $path, $text );
echo "wporg readme sanitized\n";
