<?php
/**
 * Slug transliteration + empty fallback (no WP bootstrap beyond stubs).
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( $text ) {
		$map = array(
			'õ' => 'o',
			'ä' => 'a',
			'ö' => 'o',
			'ü' => 'u',
			'Õ' => 'O',
			'Ä' => 'A',
			'Ö' => 'O',
			'Ü' => 'U',
		);
		return strtr( (string) $text, $map );
	}
}

if ( ! class_exists( 'Choir_Rehearsal_Post_Types', false ) ) {
	class Choir_Rehearsal_Post_Types {
		public const SONG = 'choir_song';
	}
}

require_once dirname( __DIR__ ) . '/includes/class-slugs.php';

function assert_same( string $label, string $expected, string $got ): void {
	if ( $expected !== $got ) {
		fwrite( STDERR, "FAIL $label expected=$expected got=$got\n" );
		exit( 1 );
	}
	echo "PASS $label\n";
}

assert_same( 'pesnya', 'pesnya', Choir_Rehearsal_Slugs::latin_slug( 'Песня' ) );
assert_same( 'ohtu-laul', 'ohtu-laul', Choir_Rehearsal_Slugs::latin_slug( 'Õhtu laul' ) );

$cjk = Choir_Rehearsal_Slugs::latin_slug( '合唱' );
if ( class_exists( 'Transliterator', false ) ) {
	if ( '' === $cjk ) {
		fwrite( STDERR, "FAIL cjk_with_intl empty\n" );
		exit( 1 );
	}
	echo "PASS cjk_with_intl ($cjk)\n";
} else {
	assert_same( 'cjk_without_intl_empty', '', $cjk );
}

$ar = Choir_Rehearsal_Slugs::latin_slug( 'مرحبا' );
if ( class_exists( 'Transliterator', false ) ) {
	if ( '' === $ar ) {
		fwrite( STDERR, "FAIL arabic_with_intl empty\n" );
		exit( 1 );
	}
	echo "PASS arabic_with_intl ($ar)\n";
} else {
	assert_same( 'arabic_without_intl_empty', '', $ar );
}

echo "OK slug intl transliteration\n";
