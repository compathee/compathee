<?php
/**
 * Abilities registration must use existing REST/access permission helpers.
 *
 * Run: php choir-rehearsal/tests/abilities-contract-test.php
 */

declare(strict_types=1);

$fail = 0;

function check(string $name, bool $ok): void {
	global $fail;
	if ($ok) {
		echo "ok $name\n";
		return;
	}
	$fail++;
	echo "FAIL $name\n";
}

$src = file_get_contents(dirname(__DIR__) . '/includes/class-abilities.php');
check('does not call the missing can_read() helper', !str_contains($src, 'Choir_Rehearsal_REST::can_read('));
check('list-songs uses can_list()', str_contains($src, 'Choir_Rehearsal_REST::can_list('));
check('get-song checks can_view_song() against prepared input', str_contains($src, 'Choir_Rehearsal_Access::can_view_song('));
check('registers on wp_abilities_api_init', str_contains($src, "add_action( 'wp_abilities_api_init'"));

if ($fail > 0) {
	fwrite(STDERR, "$fail check(s) failed\n");
	exit(1);
}

echo "All checks passed\n";
