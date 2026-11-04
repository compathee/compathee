<?php
/**
 * Note actions stay readable when a theme paints button:hover text white.
 *
 * Run: php choir-rehearsal/tests/notes-button-hover-contrast-test.php
 */

declare(strict_types=1);

$css = (string) file_get_contents(__DIR__ . '/../public/css/public.css');

$fail = 0;

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

$hover = '';
if (preg_match('/body \.choir-notes-layer \.choir-notes-sheet button\.choir-note__edit:hover,[\s\S]*?button\.choir-notes-mic:focus-visible \{([\s\S]*?)\}/', $css, $match)) {
	$hover = $match[1];
}

check('hover rule is scoped to the notes sheet', $hover !== '' && str_contains($hover, 'background-color: #1f4fd8 !important'));
check('edit and delete use the publish blue with white text', str_contains($css, 'button.choir-note__edit:hover') && str_contains($css, 'button.choir-note__edit:focus-visible') && str_contains($css, 'button.choir-note__delete:hover') && str_contains($css, 'button.choir-note__delete:focus-visible') && str_contains($hover, 'color: #fff !important') && str_contains($hover, '-webkit-text-fill-color: #fff !important'));
check('part, time, info, and mic share that hover', str_contains($css, 'button.choir-notes-at:hover') && str_contains($css, 'button.choir-notes-at:focus-visible') && str_contains($css, 'button.choir-notes-time:hover') && str_contains($css, 'button.choir-notes-time:focus-visible') && str_contains($css, 'button.choir-notes-info:hover') && str_contains($css, 'button.choir-notes-info:focus-visible') && str_contains($css, 'button.choir-notes-mic:hover') && str_contains($css, 'button.choir-notes-mic:focus-visible'));
check('hover beats a theme button color', str_contains($hover, 'background: #1f4fd8 !important') && str_contains($hover, 'border-color: #1f4fd8 !important') && str_contains($hover, 'opacity: 1 !important') && str_contains($hover, 'text-indent: 0 !important'));

$glyphs = '';
if (preg_match('/body \.choir-notes-layer \.choir-notes-sheet button\.choir-notes-time:hover span,[\s\S]*?button\.choir-notes-mic:focus-visible svg \{([\s\S]*?)\}/', $css, $glyph_match)) {
	$glyphs = $glyph_match[1];
}
check('toolbar glyphs turn white on the blue hover', $glyphs !== '' && str_contains($glyphs, 'color: #fff !important') && str_contains($glyphs, '-webkit-text-fill-color: #fff !important') && str_contains($glyphs, 'opacity: 1 !important'));

exit($fail > 0 ? 1 : 0);
