<?php
declare(strict_types=1);

$pages = file_get_contents(__DIR__ . '/../includes/class-pages.php');
$cpt   = file_get_contents(__DIR__ . '/../includes/class-post-types.php');

$checks = [
	'library_slug_const' => str_contains($pages, "LIBRARY_SLUG = 'rehearsal'"),
	'force_on_save_filter' => str_contains($pages, 'force_library_page_slug_on_save'),
	'ensure_after_save' => str_contains($pages, 'ensure_library_page_slug_after_save'),
	'ensure_slug_fn' => str_contains($pages, 'function ensure_library_page_slug'),
	'create_uses_const' => str_contains($pages, "'post_name'    => self::LIBRARY_SLUG"),
	'cpt_rewrite_rehearsal' => str_contains($cpt, "'slug'       => 'rehearsal'"),
	'no_localized_create_slug' => ! preg_match("/'post_name'\\s*=>\\s*__\\(/", $pages),
];

$fail = 0;
foreach ($checks as $name => $ok) {
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}
exit($fail > 0 ? 1 : 0);
