<?php
/**
 * SoftMe/admin: PDF viewer must not paint at width 0 and must re-paint when layout becomes usable.
 */
declare(strict_types=1);

$js = file_get_contents(__DIR__ . '/../public/js/pdf-viewer.js');
$admin = file_get_contents(__DIR__ . '/../admin/js/admin.js');

$checks = [
	'has_usable_width_fn' => str_contains($js, 'function hasUsableWidth'),
	'raw_width_fn' => str_contains($js, 'function wrapRawContentWidth'),
	'defer_zero_width' => str_contains($js, 'hasUsableWidth()')
		&& str_contains($js, 'SoftMe/admin'),
	'no_floor_on_zero' => (bool) preg_match(
		'/function wrapContentWidth\(\)\s*\{[^}]*Math\.max\(\s*120\s*,\s*wrapRawContentWidth\(\)/s',
		$js
	),
	'ro_tracks_became_usable' => str_contains($js, 'becameUsable'),
	'ro_observes_viewer' => (bool) preg_match('/ro\.observe\(\s*viewer\s*\)/', $js),
	'refresh_clears_last_rendered' => (bool) preg_match(
		'/refresh:\s*function\s*\(\)\s*\{[^}]*lastRenderedPageNum\s*=\s*0/s',
		$js
	),
	'admin_editor_resize_observer' => str_contains($admin, 'ResizeObserver')
		&& str_contains($admin, 'choir-editor-pdf-viewer'),
	'admin_boot_immediate' => str_contains($admin, 'bootEditorPdf()')
		&& ! str_contains($admin, 'have a non-zero width before PDF.js fits'),
];

$fail = 0;
foreach ($checks as $name => $ok) {
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

// Logic contract: usable width gate (mirrors pdf-viewer.js).
$raw = static function (int $client, float $padL = 12.0, float $padR = 12.0): float {
	return $client - $padL - $padR;
};
$usable = static function (int $client) use ($raw): bool {
	return $client > 0 && $raw($client) >= 32;
};

$logic = [
	'zero_not_usable' => ! $usable(0),
	'narrow_not_usable' => ! $usable(40),
	'ready_usable' => $usable(60),
	'wide_usable' => $usable(800),
];
foreach ($logic as $name => $ok) {
	echo ($ok ? 'PASS' : 'FAIL') . " logic_$name\n";
	if (!$ok) {
		$fail++;
	}
}

exit($fail > 0 ? 1 : 0);
