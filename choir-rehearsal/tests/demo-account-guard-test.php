<?php
/**
 * Unit checks for the shared-demo account guard (no WP bootstrap).
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__, 2 ) . '/mu-plugins/compath-rehearsal-demo-guard.php';

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

/**
 * @return array<string, mixed>
 */
function sample_payload(): array {
	return array(
		'version'            => Compath_Rehearsal_Demo_Guard::BASELINE_VERSION,
		'table_prefix'       => 'wp_',
		'logins'             => array( 'demosinger', 'demoleader' ),
		'posts'              => array(
			array(
				'ID'          => 10,
				'post_type'   => 'choir_song',
				'post_title'  => 'Demo Song 01',
				'post_author' => 4,
			),
		),
		'postmeta'           => array(
			array(
				'post_id'    => 10,
				'meta_key'   => '_choir_is_public',
				'meta_value' => '',
			),
		),
		'users'              => array(
			array(
				'ID'         => 4,
				'user_login' => 'demosinger',
			),
			array(
				'ID'         => 5,
				'user_login' => 'demoleader',
			),
		),
		'usermeta'           => array(
			array(
				'user_id'    => 4,
				'meta_key'   => 'wp_capabilities',
				'meta_value' => 'a:1:{s:12:"choir_singer";b:1;}',
			),
		),
		'terms'              => array(),
		'term_taxonomy'      => array(),
		'term_relationships' => array(),
		'options'            => array(
			array(
				'option_name'  => 'choir_rehearsal_require_login',
				'option_value' => '1',
				'autoload'     => 'yes',
			),
		),
		'files'              => array( '2026/09/demo.mp3' ),
	);
}

$logins = Compath_Rehearsal_Demo_Guard::logins();
assert_true( 'default logins include singer', in_array( 'demosinger', $logins, true ) );
assert_true( 'default logins include leader', in_array( 'demoleader', $logins, true ) );
assert_same( 'parsed logins', 'demosinger,demoleader', implode( ',', Compath_Rehearsal_Demo_Guard::parse_login_list( ' DemoSinger, demoleader ' ) ) );

$accounts = Compath_Rehearsal_Demo_Guard::default_accounts();
assert_same( 'singer email', 'demouser@shop.compath.ee', (string) $accounts['demosinger']['user_email'] );
assert_same( 'leader email', 'demoleader@rehearsal.compath.ee', (string) $accounts['demoleader']['user_email'] );
assert_same( 'singer role', 'choir_singer', Compath_Rehearsal_Demo_Guard::sanitize_demo_role( (string) $accounts['demosinger']['role'] ) );
assert_same( 'leader role', 'choir_voice_leader', Compath_Rehearsal_Demo_Guard::sanitize_demo_role( (string) $accounts['demoleader']['role'] ) );
assert_same( 'reject administrator role', '', Compath_Rehearsal_Demo_Guard::sanitize_demo_role( 'administrator' ) );

assert_true( 'email matches singer', Compath_Rehearsal_Demo_Guard::is_guarded_login_or_email( 'demouser@shop.compath.ee', $logins, Compath_Rehearsal_Demo_Guard::guarded_emails() ) );
assert_false( 'admin email is not guarded', Compath_Rehearsal_Demo_Guard::is_guarded_login_or_email( 'admin@example.com', $logins, Compath_Rehearsal_Demo_Guard::guarded_emails() ) );

assert_true( 'block demo mutation', Compath_Rehearsal_Demo_Guard::should_block_user_mutation( false, true ) );
assert_false( 'admin may edit demo account', Compath_Rehearsal_Demo_Guard::should_block_user_mutation( true, true ) );
assert_false( 'other users stay editable', Compath_Rehearsal_Demo_Guard::should_block_user_mutation( false, false ) );
assert_true( 'block password reset', Compath_Rehearsal_Demo_Guard::should_block_password_reset( true ) );
assert_false( 'allow reset for other users', Compath_Rehearsal_Demo_Guard::should_block_password_reset( false ) );

$locked = Compath_Rehearsal_Demo_Guard::lock_user_row(
	array(
		'user_email'   => 'evil@example.com',
		'user_pass'    => 'hacked',
		'display_name' => 'Hacker',
		'user_login'   => 'admin',
	),
	array(
		'user_email'   => 'demouser@shop.compath.ee',
		'user_pass'    => 'hash',
		'display_name' => 'Demo Singer',
		'user_login'   => 'demosinger',
	)
);
assert_same( 'locked email', 'demouser@shop.compath.ee', (string) $locked['user_email'] );
assert_same( 'locked pass', 'hash', (string) $locked['user_pass'] );
assert_same( 'locked login', 'demosinger', (string) $locked['user_login'] );
assert_true( 'nickname meta locked', Compath_Rehearsal_Demo_Guard::is_locked_meta_key( 'nickname', 'wp_' ) );
assert_true( 'caps meta locked', Compath_Rehearsal_Demo_Guard::is_locked_meta_key( 'wp_capabilities', 'wp_' ) );
assert_false( 'session tokens stay writable', Compath_Rehearsal_Demo_Guard::is_locked_meta_key( 'session_tokens', 'wp_' ) );

assert_false( 'singer dashboard blocked', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'index.php', array(), false ) );
assert_false( 'leader profile blocked', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'profile.php', array(), true ) );
assert_false( 'leader users screen blocked', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'users.php', array(), true ) );
assert_false( 'plugins screen blocked', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'plugins.php', array(), true ) );
assert_true( 'ajax allowed', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'admin-ajax.php', array(), false ) );
assert_true( 'async upload allowed', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'async-upload.php', array(), true ) );
assert_true( 'leader add song allowed', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'post-new.php', array( 'post_type' => 'choir_song' ), true ) );
assert_false( 'singer add song blocked', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'post-new.php', array( 'post_type' => 'choir_song' ), false ) );
assert_false( 'leader cannot add blog posts', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'post-new.php', array( 'post_type' => 'post' ), true ) );
assert_true( 'leader song list allowed', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'edit.php', array( 'post_type' => 'choir_song' ), true ) );
assert_false( 'settings page blocked', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'edit.php', array( 'post_type' => 'choir_song', 'page' => 'choir-rehearsal-settings' ), true ) );
assert_true( 'edit song allowed', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'post.php', array( 'action' => 'edit' ), true, 'choir_song' ) );
assert_false( 'edit page blocked', Compath_Rehearsal_Demo_Guard::is_admin_screen_allowed( 'post.php', array( 'action' => 'edit' ), true, 'page' ) );

assert_same( 'small upload', '', Compath_Rehearsal_Demo_Guard::upload_block_reason( 1024, 8388608 ) );
assert_true( 'huge upload blocked', '' !== Compath_Rehearsal_Demo_Guard::upload_block_reason( 9000000, 8388608 ) );

assert_same( 'rest read allowed', 'allow', Compath_Rehearsal_Demo_Guard::rest_mutation_decision( '/wp/v2/users/me', 'GET', false, true, true ) );
assert_same( 'rest self update blocked', 'block', Compath_Rehearsal_Demo_Guard::rest_mutation_decision( '/wp/v2/users/me', 'POST', false, true, true ) );
assert_same( 'rest admin update allowed', 'allow', Compath_Rehearsal_Demo_Guard::rest_mutation_decision( '/wp/v2/users/5', 'POST', true, false, true ) );
assert_same( 'rest demo cannot edit admin', 'block', Compath_Rehearsal_Demo_Guard::rest_mutation_decision( '/wp/v2/users/1', 'DELETE', false, true, false ) );
assert_same( 'rest app passwords blocked', 'block', Compath_Rehearsal_Demo_Guard::rest_mutation_decision( '/wp/v2/users/5/application-passwords', 'POST', true, false, true ) );
assert_same( 'rest songs untouched', 'allow', Compath_Rehearsal_Demo_Guard::rest_mutation_decision( '/wp/v2/choir_song/10', 'POST', false, true, false ) );
assert_true( 'xmlrpc profile blocked', Compath_Rehearsal_Demo_Guard::block_xmlrpc_method( 'wp.editProfile', 'demosinger' ) );
assert_false( 'xmlrpc profile allowed for admin', Compath_Rehearsal_Demo_Guard::block_xmlrpc_method( 'wp.editProfile', 'admin' ) );

assert_same( 'estonian notice', 'See on ühine demo. Muudatused lähtestatakse igal ööl.', Compath_Rehearsal_Demo_Guard::translate( Compath_Rehearsal_Demo_Guard::NOTICE, 'et_EE' ) );
assert_same( 'russian notice', 'Это общая демонстрация. Изменения сбрасываются каждую ночь.', Compath_Rehearsal_Demo_Guard::translate( Compath_Rehearsal_Demo_Guard::NOTICE, 'ru_RU' ) );
assert_same( 'english notice', Compath_Rehearsal_Demo_Guard::NOTICE, Compath_Rehearsal_Demo_Guard::translate( Compath_Rehearsal_Demo_Guard::NOTICE, 'en_US' ) );

$role_names = array( 'Singer', 'Voice Leader', 'Administrator' );
foreach ( Compath_Rehearsal_Demo_Guard::catalog() as $lang => $strings ) {
	foreach ( $strings as $source => $translated ) {
		foreach ( $role_names as $role_name ) {
			assert_false( "catalog {$lang} source has {$role_name}", str_contains( (string) $source, $role_name ) );
			assert_false( "catalog {$lang} translation has {$role_name}", str_contains( (string) $translated, $role_name ) );
		}
	}
}

$guard_src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/mu-plugins/compath-rehearsal-demo-guard.php' );
preg_match_all( "/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\\(\\s*'((?:\\\\'|[^'])*)'/", $guard_src, $gettext );
foreach ( $gettext[1] as $string ) {
	foreach ( $role_names as $role_name ) {
		assert_false( "gettext contains {$role_name}", str_contains( $string, $role_name ) );
	}
}

$reset_src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-demo-reset.php' );
assert_true( 'demo reset reuses the guard', str_contains( $reset_src, 'Compath_Rehearsal_Demo_Guard' ) );

$errors = Compath_Rehearsal_Demo_Guard::validate_snapshot_payload( sample_payload(), $logins );
assert_true( 'valid snapshot', array() === $errors );

$poison = sample_payload();
$poison['users'][] = array(
	'ID'         => 1,
	'user_login' => 'admin',
);
$errors = Compath_Rehearsal_Demo_Guard::validate_snapshot_payload( $poison, $logins );
assert_true( 'reject admin user', array() !== $errors );

$poison = sample_payload();
$poison['usermeta'][] = array(
	'user_id'    => 4,
	'meta_key'   => 'wp_capabilities',
	'meta_value' => 'a:1:{s:13:"administrator";b:1;}',
);
$errors = Compath_Rehearsal_Demo_Guard::validate_snapshot_payload( $poison, $logins );
assert_true( 'reject administrator cap', array() !== $errors );
assert_true( 'detect administrator cap', Compath_Rehearsal_Demo_Guard::serialized_grants_role( 'a:1:{s:13:"administrator";b:1;}', 'administrator' ) );

$poison = sample_payload();
$poison['posts'][] = array(
	'ID'        => 7,
	'post_type' => 'page',
);
$errors = Compath_Rehearsal_Demo_Guard::validate_snapshot_payload( $poison, $logins );
assert_true( 'reject page post', array() !== $errors );

$poison = sample_payload();
$poison['files'] = array( '../wp-config.php' );
$errors = Compath_Rehearsal_Demo_Guard::validate_snapshot_payload( $poison, $logins );
assert_true( 'reject path traversal', array() !== $errors );
assert_same( 'safe relative', '2026/09/demo.mp3', (string) Compath_Rehearsal_Demo_Guard::safe_upload_relative( '2026/09/demo.mp3' ) );
assert_true( 'unsafe relative', null === Compath_Rehearsal_Demo_Guard::safe_upload_relative( '../wp-config.php' ) );

$poison = sample_payload();
$poison['options'][] = array(
	'option_name'  => 'choir_rehearsal_demo_reset_key',
	'option_value' => 'secret',
);
$errors = Compath_Rehearsal_Demo_Guard::validate_snapshot_payload( $poison, $logins );
assert_true( 'reject reset key option', array() !== $errors );
assert_false( 'siteurl is not a plugin option', Compath_Rehearsal_Demo_Guard::is_restorable_option( 'siteurl' ) );
assert_true( 'plugin option allowed', Compath_Rehearsal_Demo_Guard::is_restorable_option( 'choir_rehearsal_require_login' ) );

assert_true( 'empty guard list refused', array() !== Compath_Rehearsal_Demo_Guard::validate_snapshot_payload( sample_payload(), array() ) );

$where = Compath_Rehearsal_Demo_Guard::user_restore_where( 1, 'admin', $logins );
assert_true( 'no where clause for admin', null === $where );
$where = Compath_Rehearsal_Demo_Guard::user_restore_where( 4, 'demosinger', $logins );
assert_true( 'where clause keeps login and id', is_array( $where ) && 4 === $where['ID'] && 'demosinger' === $where['user_login'] );

assert_same(
	'author rewritten only for demo login',
	'9',
	(string) Compath_Rehearsal_Demo_Guard::rewrite_post_author( 4, array( 'demosinger' => 4 ), array( 'demosinger' => 9 ) )
);
assert_same(
	'admin author unchanged',
	'1',
	(string) Compath_Rehearsal_Demo_Guard::rewrite_post_author( 1, array( 'demosinger' => 4 ), array( 'demosinger' => 9 ) )
);
assert_same( 'prefix remap', 'wp_capabilities', Compath_Rehearsal_Demo_Guard::remap_user_meta_key( 'wp_capabilities', 'wp_', 'wp_' ) );
assert_same( 'prefix remap other', 'site_capabilities', Compath_Rehearsal_Demo_Guard::remap_user_meta_key( 'wp_capabilities', 'wp_', 'site_' ) );
assert_same( 'nickname not remapped', 'nickname', Compath_Rehearsal_Demo_Guard::remap_user_meta_key( 'nickname', 'wp_', 'site_' ) );

$before = ( new DateTimeImmutable( '2026-09-24 02:30:00', new DateTimeZone( 'Europe/Tallinn' ) ) )->getTimestamp();
$next   = Compath_Rehearsal_Demo_Guard::next_tallinn_three_am( $before );
$local  = ( new DateTimeImmutable( '@' . $next ) )->setTimezone( new DateTimeZone( 'Europe/Tallinn' ) );
assert_same( 'same day 03:00', '2026-09-24 03:00', $local->format( 'Y-m-d H:i' ) );
$after = ( new DateTimeImmutable( '2026-09-24 04:00:00', new DateTimeZone( 'Europe/Tallinn' ) ) )->getTimestamp();
$next  = Compath_Rehearsal_Demo_Guard::next_tallinn_three_am( $after );
$local = ( new DateTimeImmutable( '@' . $next ) )->setTimezone( new DateTimeZone( 'Europe/Tallinn' ) );
assert_same( 'next day 03:00', '2026-09-25 03:00', $local->format( 'Y-m-d H:i' ) );

$tmp = sys_get_temp_dir() . '/compath-demo-guard-' . bin2hex( random_bytes( 4 ) );
$bad = sample_payload();
$bad['users'][] = array(
	'ID'         => 1,
	'user_login' => 'admin',
);
$write_errors = Compath_Rehearsal_Demo_Guard::write_snapshot( $tmp, $bad, $logins );
assert_true( 'refused write', array() !== $write_errors );
assert_false( 'no file for refused snapshot', is_file( $tmp . '/baseline.json' ) );

$write_errors = Compath_Rehearsal_Demo_Guard::write_snapshot( $tmp, sample_payload(), $logins );
assert_true( 'snapshot written', array() === $write_errors && is_file( $tmp . '/baseline.json' ) );
$raw = json_decode( (string) file_get_contents( $tmp . '/baseline.json' ), true );
assert_true( 'written file has no admin login', is_array( $raw ) && ! str_contains( (string) file_get_contents( $tmp . '/baseline.json' ), '"user_login":"admin"' ) );
@unlink( $tmp . '/baseline.json' );
@unlink( $tmp . '/.htaccess' );
@unlink( $tmp . '/index.php' );
@rmdir( $tmp );

echo "OK demo account guard\n";
