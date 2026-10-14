<?php
declare(strict_types=1);

$edition = file_get_contents(__DIR__ . '/../includes/class-edition.php');
$admin = file_get_contents(__DIR__ . '/../includes/class-admin.php');

$checks = [
	'view_score_always_true' => (bool) preg_match(
		'/function can_view_score_in_editor\(\):\s*bool\s*\{\s*return true;/s',
		$edition
	),
	'view_score_not_pro_gated' => ! preg_match(
		'/function can_view_score_in_editor\(\):\s*bool\s*\{\s*return self::is_full_edition\(\);/s',
		$edition
	),
	'metabox_always_renders_viewer' => str_contains($admin, 'id="choir-editor-pdf-viewer"')
		&& ! str_contains($admin, 'Pro embeds the score here in the editor.'),
	'lite_copy_drops_pdf_gate' => ! str_contains($admin, 'or embedded PDF preview.'),
];

$fail = 0;
foreach ($checks as $name => $ok) {
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}
exit($fail > 0 ? 1 : 0);
