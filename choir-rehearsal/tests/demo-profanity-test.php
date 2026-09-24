<?php
/**
 * Profanity filter checks (no WordPress bootstrap).
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/includes/class-profanity.php';

if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {
		public function __construct( public string $code = '', public string $message = '', public array $data = array() ) {}
	}
}

function assert_true( string $label, bool $got ): void {
	if ( ! $got ) {
		fwrite( STDERR, "FAIL $label expected=true\n" );
		exit( 1 );
	}
	echo "PASS $label\n";
}

function assert_false( string $label, bool $got ): void {
	if ( $got ) {
		fwrite( STDERR, "FAIL $label expected=false\n" );
		exit( 1 );
	}
	echo "PASS $label\n";
}

function assert_same( string $label, string $expected, string $got ): void {
	if ( $expected !== $got ) {
		fwrite( STDERR, "FAIL $label expected={$expected} got={$got}\n" );
		exit( 1 );
	}
	echo "PASS $label\n";
}

function assert_blocked( string $label, string $text ): void {
	assert_true( $label, Choir_Rehearsal_Profanity::contains( $text ) );
}

function assert_clean( string $label, string $text ): void {
	assert_false( $label, Choir_Rehearsal_Profanity::contains( $text ) );
}

$blocked = array(
	'ru хуй'            => 'хуй',
	'ru ХУЙ'            => 'ХУЙ',
	'ru ху.у.й'         => 'х.у.й',
	'ru хуууй'          => 'хуууй',
	'ru latin y'        => 'хyй',
	'ru spaced'         => 'х у й',
	'ru пизда'          => 'пизда',
	'ru п1зда'          => 'п1зда',
	'ru пи3да'          => 'пи3да',
	'ru пиздец'         => 'пиздец',
	'ru PIZDA'          => 'PIZDA',
	'ru ебать'          => 'ебать',
	'ru ёб'             => 'ёб',
	'ru долбоёб'        => 'долбоёб',
	'ru mixed eбaть'    => 'eбaть',
	'ru блядь'          => 'блядь',
	'ru блять'          => 'блять',
	'ru spaced бля'     => 'б л я д ь',
	'ru сука'           => 'сука',
	'ru мудак'          => 'мудак',
	'ru залупа'         => 'залупа',
	'ru гандон'         => 'гандон',
	'ru похуй'          => 'похуй',
	'et perse'          => 'perse',
	'et persse'         => 'persse',
	'et türa'           => 'türa',
	'et TYRA'           => 'TYRA',
	'et munn'           => 'munn',
	'et munni'          => 'munni',
	'et vitt'           => 'vitt',
	'et vittu'          => 'vittu',
	'et lits'           => 'lits',
	'et raisk'          => 'raisk',
	'et pede'           => 'pede',
	'et p3rse'          => 'p3rse',
	'en fuck'           => 'fuck',
	'en FUCK'           => 'FUCK',
	'en f*u*c*k'        => 'f*u*c*k',
	'en f.u.c.k'        => 'f.u.c.k',
	'en fuuuuck'        => 'fuuuuck',
	'en fuuck'          => 'fuuck',
	'en spaced fuck'    => 'f u c k',
	'en $hit'           => '$hit',
	'en sh1t'           => 'sh1t',
	'en sh!t'           => 'sh!t',
	'en @ss'            => '@ss',
	'en a$$'            => 'a$$',
	'en b1tch'          => 'b1tch',
	'en asshole'        => 'asshole',
	'en bitch'          => 'bitch',
	'en cunt'           => 'cunt',
	'en homoglyph ass'  => 'асс',
	'en cyrillic у'     => 'fуck',
	'en fucking'        => 'fucking',
	'en bullshit'       => 'bullshit',
	'file fuck.mp3'     => 'fuck.mp3',
	'file хуй.mp3'      => 'х.у.й.mp3',
	'html notes'        => '<p>fuck</p>',
);

foreach ( $blocked as $label => $text ) {
	assert_blocked( 'blocked ' . $label, $text );
}

$clean = array(
	'Hallelujah'       => 'Hallelujah',
	'Alleluia'         => 'Alleluia',
	'Ave Maria'        => 'Ave Maria',
	'Gloria'           => 'Gloria',
	'Kyrie eleison'    => 'Kyrie eleison',
	'Panis angelicus'  => 'Panis angelicus',
	'Regina caeli'     => 'Regina caeli',
	'Ave verum corpus' => 'Ave verum corpus',
	'Dies irae'        => 'Dies irae',
	'Õhtu laul'        => 'Õhtu laul',
	'Mu isamaa'        => 'Mu isamaa, mu õnn ja rõõm',
	'Tuljak'           => 'Tuljak',
	'Lauliku lapsepõli'=> 'Lauliku lapsepõli',
	'Истребитель'      => 'Истребитель',
	'Рубля'            => 'Рубля',
	'Оскорблять'       => 'Оскорблять',
	'Сукно'            => 'Сукно',
	'Персик'           => 'Персик',
	'учебник'          => 'учебник',
	'хлеб'             => 'хлеб',
	'небо'             => 'небо',
	'себе'             => 'себе',
	'тебя'             => 'тебя',
	'лебедь'           => 'лебедь',
	'хребет'           => 'хребет',
	'ребро'            => 'ребро',
	'мудрость'         => 'мудрость',
	'хулиган'          => 'хулиган',
	'художник'         => 'художник',
	'Евангелие'        => 'Евангелие',
	'ребенок'          => 'ребенок',
	'потребность'      => 'потребность',
	'употреблять'      => 'употреблять',
	'гребля'           => 'гребля',
	'влюбляться'       => 'влюбляться',
	'корабля'          => 'корабля',
	'сучок'            => 'сучок',
	'Вечерняя песнь'   => 'Вечерняя песнь',
	'Херувимская'      => 'Херувимская',
	'Scunthorpe'       => 'Scunthorpe',
	'shiitake'         => 'shiitake',
	'Dickinson'        => 'Dickinson',
	'Dickens'          => 'Dickens',
	'Moby Dick'        => 'Moby Dick',
	'Hello'            => 'Hello',
	'Document'         => 'Document',
	'Bass'             => 'Bass',
	'Classic'          => 'Classic',
	'Assumption'       => 'Assumption',
	'cocktail'         => 'cocktail',
	'peacock'          => 'peacock',
	'passage'          => 'passage',
	'class'            => 'class',
	'ambassador'       => 'ambassador',
	'constitution'     => 'constitution',
	'Title'            => 'Title',
	'Titus'            => 'Titus',
	'cumulative'       => 'cumulative',
	'circumstance'     => 'circumstance',
	'litsents'         => 'litsents',
	'raiskama'         => 'raiskama',
	'raiskamine'       => 'raiskamine',
	'raiskab'          => 'raiskab',
	'perseverance'     => 'perseverance',
	'Turandot'         => 'Turandot',
	'Vittoria'         => 'Vittoria',
	'pedestal'         => 'pedestal',
	'bass.mp3'         => 'bass.mp3',
	'scunthorpe.pdf'   => 'scunthorpe.pdf',
	'youtube only'     => 'https://www.youtube.com/watch?v=abc123',
	'youtube short'    => 'https://youtu.be/dQw4w9wgxcq',
	'liturgy plus url' => 'Ave Maria https://youtu.be/abc123',
	'hell'             => 'hell',
	'damn'             => 'damn',
);

foreach ( $clean as $label => $text ) {
	assert_clean( 'clean ' . $label, $text );
}

$lists = Choir_Rehearsal_Profanity::default_lists();
$lists['allow_tokens'] = array_values(
	array_filter(
		$lists['allow_tokens'],
		static fn( $word ) => 'scunthorpe' !== $word && 'scunthorpes' !== $word
	)
);
assert_true( 'scunthorpe blocked without allow token', Choir_Rehearsal_Profanity::contains( 'Scunthorpe', $lists ) );
assert_clean( 'scunthorpe allowed by default list', 'Scunthorpe' );

$extended = Choir_Rehearsal_Profanity::default_lists();
$extended['block_tokens'][] = 'hallelujah';
assert_true( 'extended list blocks Hallelujah', Choir_Rehearsal_Profanity::contains( 'Hallelujah', $extended ) );
assert_clean( 'default list keeps Hallelujah', 'Hallelujah' );

assert_true( 'channel demo defaults on', Choir_Rehearsal_Profanity::enabled_for_channel( 'demo' ) );
assert_false( 'channel github defaults off', Choir_Rehearsal_Profanity::enabled_for_channel( 'github' ) );
assert_false( 'lite setting off without storage', Choir_Rehearsal_Profanity::setting_enabled() );
assert_false( 'not enforced before demo register', Choir_Rehearsal_Profanity::should_block() );

$scrub = Choir_Rehearsal_Profanity::scrub_fields(
	array(
		'post_title'   => 'Fuck the title',
		'post_content' => 'Ave Maria',
		'post_excerpt' => 'Gloria',
	),
	array(
		'post_title'   => 'Hallelujah',
		'post_content' => 'old notes',
		'post_excerpt' => 'old excerpt',
	)
);
assert_same( 'update keeps previous title', 'Hallelujah', $scrub['fields']['post_title'] );
assert_same( 'update keeps notes', 'Ave Maria', $scrub['fields']['post_content'] );
assert_same( 'update keeps excerpt', 'Gloria', $scrub['fields']['post_excerpt'] );
assert_same( 'update error is the title', 'title', implode( ',', $scrub['errors'] ) );

$created = Choir_Rehearsal_Profanity::scrub_fields(
	array(
		'post_title'   => 'блядь',
		'post_content' => 'Kyrie eleison',
	)
);
assert_same( 'create blanks only the title', '', $created['fields']['post_title'] );
assert_same( 'create keeps the notes', 'Kyrie eleison', $created['fields']['post_content'] );

assert_same( 'english title message', 'Please use appropriate language in the song title.', Choir_Rehearsal_Profanity::message( 'title', 'en_US' ) );
assert_same( 'estonian title message', 'Palun kasuta laulu pealkirjas sobivat keelt.', Choir_Rehearsal_Profanity::message( 'title', 'et_EE' ) );
assert_same( 'russian title message', 'Пожалуйста, используйте приемлемые выражения в названии песни.', Choir_Rehearsal_Profanity::message( 'title', 'ru_RU' ) );
assert_same( 'estonian file message', 'Palun kasuta failinimes sobivat keelt.', Choir_Rehearsal_Profanity::message( 'file', 'et' ) );
assert_same( 'russian notes message', 'Пожалуйста, используйте приемлемые выражения в описании.', Choir_Rehearsal_Profanity::message( 'notes', 'ru' ) );

$role_names = array( 'Singer', 'Voice Leader', 'Administrator' );
foreach ( Choir_Rehearsal_Profanity::catalog() as $lang => $strings ) {
	foreach ( $strings as $source => $translated ) {
		foreach ( $role_names as $role_name ) {
			assert_false( "catalog {$lang} source has {$role_name}", str_contains( (string) $source, $role_name ) );
			assert_false( "catalog {$lang} translation has {$role_name}", str_contains( (string) $translated, $role_name ) );
		}
	}
}

assert_false( 'pages are not watched', Choir_Rehearsal_Profanity::watches_post_type( 'page' ) );
assert_false( 'no playlist type', Choir_Rehearsal_Profanity::watches_post_type( 'choir_playlist' ) );
assert_true( 'songs are watched', Choir_Rehearsal_Profanity::watches_post_type( 'choir_song' ) );
assert_true( 'tracks are watched', Choir_Rehearsal_Profanity::watches_post_type( 'choir_track' ) );
assert_true( 'attachments are watched', Choir_Rehearsal_Profanity::watches_post_type( 'attachment' ) );
assert_true( 'voice terms are watched', Choir_Rehearsal_Profanity::watches_taxonomy( 'choir_voice_type' ) );
assert_false( 'core categories are not watched', Choir_Rehearsal_Profanity::watches_taxonomy( 'category' ) );

Choir_Rehearsal_Profanity::register_demo();
assert_true( 'demo forces the check', Choir_Rehearsal_Profanity::should_block() );
assert_true( 'demo enforced flag', Choir_Rehearsal_Profanity::demo_enforced() );

Choir_Rehearsal_Profanity::set_test_actor( true );
assert_false( 'site manager bypasses the demo check', Choir_Rehearsal_Profanity::should_block() );
Choir_Rehearsal_Profanity::set_test_actor( false );

$saved = Choir_Rehearsal_Profanity::filter_insert_post_data(
	array(
		'post_type'    => 'choir_song',
		'post_title'   => 'f*u*c*k',
		'post_content' => 'Panis angelicus',
		'post_excerpt' => '',
		'post_name'    => 'fuck',
	),
	array( 'post_type' => 'choir_song' )
);
assert_same( 'insert blanks the title', '', (string) $saved['post_title'] );
assert_same( 'insert keeps the notes', 'Panis angelicus', (string) $saved['post_content'] );
assert_same( 'insert blanks the slug', '', (string) $saved['post_name'] );

$page = Choir_Rehearsal_Profanity::filter_insert_post_data(
	array(
		'post_type'  => 'page',
		'post_title' => 'fuck',
	),
	array( 'post_type' => 'page' )
);
assert_same( 'pages are left alone', 'fuck', (string) $page['post_title'] );

$upload = Choir_Rehearsal_Profanity::filter_upload(
	array(
		'name'  => 'sh1t.mp3',
		'error' => 0,
	)
);
assert_same( 'upload error is localized in english', Choir_Rehearsal_Profanity::message( 'file', 'en_US' ), (string) $upload['error'] );

$clean_upload = Choir_Rehearsal_Profanity::filter_upload(
	array(
		'name'  => 'soprano-1.mp3',
		'error' => 0,
	)
);
assert_same( 'clean upload has no error', '0', (string) $clean_upload['error'] );

$term = Choir_Rehearsal_Profanity::filter_pre_insert_term( 'блядь', 'choir_voice_type' );
assert_true( 'part name is rejected', $term instanceof WP_Error );
assert_same( 'part message', Choir_Rehearsal_Profanity::message( 'part', 'en_US' ), $term->message );

$ok_term = Choir_Rehearsal_Profanity::filter_pre_insert_term( 'Soprano', 'choir_voice_type' );
assert_same( 'clean part name is kept', 'Soprano', (string) $ok_term );

$core_term = Choir_Rehearsal_Profanity::filter_pre_insert_term( 'fuck', 'category' );
assert_same( 'core categories are not filtered', 'fuck', (string) $core_term );

$rest = Choir_Rehearsal_Profanity::filter_rest_pre_insert(
	(object) array(
		'post_title'   => 'perse',
		'post_content' => 'Gloria',
	)
);
assert_true( 'rest insert is rejected', $rest instanceof WP_Error );

$plugin_copy = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-profanity.php' );
$mu_copy     = (string) file_get_contents( dirname( __DIR__, 2 ) . '/mu-plugins/compath-rehearsal-profanity/class-profanity.php' );
assert_true( 'mu-plugin ships the same class', $plugin_copy === $mu_copy );

echo "OK demo profanity\n";
