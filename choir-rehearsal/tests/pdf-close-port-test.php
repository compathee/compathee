<?php
/**
 * Smoke-check: fullscreen close is ported out of the PDF viewer stacking context.
 */
$js = file_get_contents(__DIR__ . '/../public/js/pdf-viewer.js');
$css = file_get_contents(__DIR__ . '/../public/css/public.css');
$frontend = file_get_contents(__DIR__ . '/../includes/class-frontend.php');
$admin = file_get_contents(__DIR__ . '/../includes/class-admin.php');

$checks = [
	'port_fn' => str_contains($js, 'function portCloseButton'),
	'port_to_body' => str_contains($js, 'document.body.appendChild(closeFsBtn)'),
	'floating_class' => str_contains($js, 'choir-pdf-close-fs--floating'),
	'exit_btn_js' => str_contains($js, 'choir-pdf-exit-fs'),
	'floating_css' => str_contains($css, '.choir-pdf-close-fs.choir-pdf-close-fs--floating'),
	'z_above_dock' => (bool) preg_match('/choir-pdf-close-fs--floating[^{]*\{[^}]*z-index:\s*100300/s', $css),
	'exit_frontend' => str_contains($frontend, 'choir-pdf-exit-fs'),
	'exit_admin' => str_contains($admin, 'choir-pdf-exit-fs'),
];

$failed = array_keys(array_filter($checks, fn($ok) => !$ok));
echo json_encode(['ok' => !$failed, 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT), "\n";
exit($failed ? 1 : 0);
