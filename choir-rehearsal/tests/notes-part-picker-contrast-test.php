<?php
/**
 * Voice-part picker labels stay visible when a theme paints buttons white.
 *
 * Run: php choir-rehearsal/tests/notes-part-picker-contrast-test.php
 */

declare(strict_types=1);

$css = (string) file_get_contents(__DIR__ . '/../public/css/public.css');
$js = (string) file_get_contents(__DIR__ . '/../public/js/song-notes.js');

$fail = 0;

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

$picker = '';
if (preg_match('/\.choir-notes-sheet button\.choir-notes-picker__item,[\s\S]*?\.choir-notes-picker__name \{([\s\S]*?)\}/', $css, $match)) {
	$picker = $match[1];
}

check('picker rule is scoped to the sheet button and name', $picker !== '');
check('picker sets text color', str_contains($picker, 'color: #101828 !important'));
check('picker sets background', str_contains($picker, 'background-color: #ffffff !important'));
check('picker sets webkit text fill', str_contains($picker, '-webkit-text-fill-color: #101828 !important'));
check('picker sets font size and line height', str_contains($picker, 'font-size: 16px !important') && str_contains($picker, 'line-height: 1.3 !important'));
check('picker resets text indent', str_contains($picker, 'text-indent: 0 !important'));
check('picker does not hide the label', str_contains($picker, 'opacity: 1 !important') && !preg_match('/opacity:\s*0/', $picker));

$icons = '';
if (preg_match('/\.choir-notes-sheet button\.choir-notes-time,[\s\S]*?\.choir-notes-at \{([\s\S]*?)\}/', $css, $icon_match)) {
	$icons = $icon_match[1];
}
check('toolbar icons set their own color', str_contains($icons, 'color: #344054 !important') && str_contains($icons, '-webkit-text-fill-color: #344054 !important'));
check('toolbar icons stay opaque', str_contains($icons, 'opacity: 1 !important'));

check('picker writes the track label into the DOM', str_contains($js, "name.className = 'choir-notes-picker__name'") && str_contains($js, 'name.textContent = label'));

exit($fail > 0 ? 1 : 0);
