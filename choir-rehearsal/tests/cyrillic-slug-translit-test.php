<?php
/**
 * Static checks for Latin song slug transliteration (no WP bootstrap).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
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

function assert_true( string $label, bool $got ): void {
	if ( ! $got ) {
		fwrite( STDERR, "FAIL $label expected=true got=false\n" );
		exit( 1 );
	}
	echo "PASS $label\n";
}

function assert_false( string $label, bool $got ): void {
	if ( $got ) {
		fwrite( STDERR, "FAIL $label expected=false got=true\n" );
		exit( 1 );
	}
	echo "PASS $label\n";
}

assert_same( 'pesnya', 'pesnya', Choir_Rehearsal_Slugs::latin_slug( 'Песня' ) );
assert_same( 'vesennyaya-groza', 'vesennyaya-groza', Choir_Rehearsal_Slugs::latin_slug( 'Весенняя гроза' ) );
assert_same( 'yolka', 'yolka', Choir_Rehearsal_Slugs::latin_slug( 'Ёлка' ) );

// WordPress sanitize_title_with_dashes encodes Cyrillic as %d0%9f… — must not become hex dump.
$encoded = '%d0%9f%d0%b5%d1%81%d0%bd%d1%8f'; // Песня
assert_same( 'encoded_to_pesnya', 'pesnya', Choir_Rehearsal_Slugs::latin_slug( $encoded ) );

// Simulate filter source selection: encoded slug + Cyrillic title.
$title = 'Песня';
$slug  = $encoded;
if ( '' === $slug || ! Choir_Rehearsal_Slugs::is_latin_slug( $slug ) ) {
	$source = '' !== $slug && Choir_Rehearsal_Slugs::has_letters( $slug ) ? $slug : $title;
	// After fix, has_letters on encoded should decode → true, source=decoded OR we prefer title.
	// Either way latin_slug(source) must be pesnya — also test via resolve helper if present.
	$resolved = method_exists( 'Choir_Rehearsal_Slugs', 'resolve_slug_source' )
		? Choir_Rehearsal_Slugs::resolve_slug_source( $slug, $title )
		: ( ( '' !== $slug && Choir_Rehearsal_Slugs::has_letters( $slug ) ) ? $slug : $title );
	assert_same( 'filter_source_result', 'pesnya', Choir_Rehearsal_Slugs::latin_slug( $resolved ) );
}

// Broken hex-dump slugs already stored must be rejected as non-latin usable.
$hexdump = 'd09fd0b5d181d0bdd18f';
assert_false( 'hexdump_not_good_latin', Choir_Rehearsal_Slugs::is_usable_latin_slug( $hexdump ) );
assert_same( 'hexdump_from_title', 'pesnya', Choir_Rehearsal_Slugs::latin_slug_from_title_or_slug( $title, $hexdump ) );

assert_same( 'ohtu', 'ohtu-laul', Choir_Rehearsal_Slugs::latin_slug( 'Õhtu laul' ) );
assert_same( 'fallback_id', 'song-42', Choir_Rehearsal_Slugs::fallback_slug( 42 ) );
assert_same( 'fallback_new', 'song', Choir_Rehearsal_Slugs::fallback_slug( 0 ) );

$cjk = Choir_Rehearsal_Slugs::latin_slug( '合唱' );
$ar  = Choir_Rehearsal_Slugs::latin_slug( 'مرحبا' );
if ( class_exists( 'Transliterator', false ) ) {
	assert_true( 'cjk_romanized', '' !== $cjk && Choir_Rehearsal_Slugs::is_latin_slug( $cjk ) );
	assert_true( 'arabic_romanized', '' !== $ar && Choir_Rehearsal_Slugs::is_latin_slug( $ar ) );
	echo "INFO cjk=$cjk arabic=$ar\n";
} else {
	assert_same( 'cjk_empty_without_intl', '', $cjk );
	assert_same( 'arabic_empty_without_intl', '', $ar );
}

echo "OK slug transliteration tests\n";
