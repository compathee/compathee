<?php
declare(strict_types=1);

$php = file_get_contents(__DIR__ . '/../includes/class-backup.php');
$js  = file_get_contents(__DIR__ . '/../admin/js/backup-import.js');

$checks = [
	'ajax_chunk_action'   => str_contains($php, 'wp_ajax_choir_rehearsal_pro_import_chunk'),
	'ajax_finish_action'  => str_contains($php, 'wp_ajax_choir_rehearsal_pro_import_finish'),
	'chunk_bytes_helper'  => str_contains($php, 'function chunk_bytes'),
	'post_max_helper'     => str_contains($php, 'function post_max_bytes'),
	'content_length_hint' => str_contains($php, 'CONTENT_LENGTH'),
	'enqueue_script'      => str_contains($php, 'backup-import.js'),
	'js_chunk_upload'     => str_contains($js, 'choir_rehearsal_pro_import_chunk'),
	'js_finish'           => str_contains($js, 'choir_rehearsal_pro_import_finish'),
	'js_intercept_submit' => str_contains($js, "event.preventDefault()"),
];

$fail = 0;
foreach ($checks as $name => $ok) {
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}
exit($fail > 0 ? 1 : 0);
