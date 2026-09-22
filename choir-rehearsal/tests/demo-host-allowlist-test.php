<?php
/**
 * Unit checks for Demo host allowlist (no WP bootstrap).
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/includes/class-distribution.php';
require_once dirname( __DIR__ ) . '/includes/class-demo-host.php';

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

assert_true( 'apex', Choir_Rehearsal_Demo_Host::is_allowed_host( 'compath.ee' ) );
assert_true( 'demo_sub', Choir_Rehearsal_Demo_Host::is_allowed_host( 'demo.rehearsal.compath.ee' ) );
assert_true( 'shop', Choir_Rehearsal_Demo_Host::is_allowed_host( 'shop.compath.ee' ) );
assert_true( 'case', Choir_Rehearsal_Demo_Host::is_allowed_host( 'Demo.Rehearsal.Compath.EE' ) );
assert_false( 'other_tld', Choir_Rehearsal_Demo_Host::is_allowed_host( 'example.com' ) );
assert_false( 'localhost', Choir_Rehearsal_Demo_Host::is_allowed_host( 'localhost' ) );
assert_false( 'spoof', Choir_Rehearsal_Demo_Host::is_allowed_host( 'compath.ee.evil.com' ) );
assert_false( 'empty', Choir_Rehearsal_Demo_Host::is_allowed_host( '' ) );

echo "OK demo host allowlist\n";
