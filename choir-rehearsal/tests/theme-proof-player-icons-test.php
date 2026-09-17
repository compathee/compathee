<?php
declare(strict_types=1);
$css = file_get_contents(__DIR__ . '/../public/css/public.css');
$php = file_get_contents(__DIR__ . '/../includes/class-frontend.php');
$checks = [
	'piano_button_selector' => str_contains($css, 'button.choir-sticky-player__piano'),
	'close_button_selector' => str_contains($css, 'button.choir-sticky-player__close'),
	'piano_text_fill' => str_contains($css, '.choir-sticky-player__piano') && str_contains($css, '-webkit-text-fill-color: #fff !important'),
	'close_text_fill' => preg_match('/choir-sticky-player__close[\s\S]{0,400}-webkit-text-fill-color:\s*#fff\s*!important/', $css) === 1,
	'piano_path_fill' => str_contains($css, '.choir-sticky-player__piano-icon path') && str_contains($css, 'fill: #ffffff !important'),
	'piano_svg_hardcoded' => str_contains($php, 'choir-sticky-player__piano-icon') && str_contains($php, 'fill="#ffffff"'),
];
$fail = 0;
foreach ($checks as $name => $ok) {
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}
exit($fail > 0 ? 1 : 0);
