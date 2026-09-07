<?php
declare(strict_types=1);

$src = file_get_contents(__DIR__ . '/../includes/class-updater.php');
$checks = [
  'http_status_guard' => str_contains($src, 'wp_remote_retrieve_response_code'),
  'list_guard' => str_contains($src, 'array_is_list'),
  'latest_asset_fallback' => str_contains($src, 'releases/latest/download/update.json'),
  'settings_redirect' => str_contains($src, 'choir-rehearsal-settings'),
  'notices' => str_contains($src, 'render_check_notices'),
  'lite_tag_regex' => str_contains($src, "/^choir-rehearsal-v\\d/"),
];

// Live fallback
$ctx = stream_context_create(['http'=>['follow_location'=>1,'timeout'=>15,'header'=>"User-Agent: Choir-Rehearsal-Test\r\n"]]);
$raw = @file_get_contents('https://github.com/compathee/compathee/releases/latest/download/update.json', false, $ctx);
$json = is_string($raw) ? json_decode($raw, true) : null;
$checks['live_fallback_json'] = is_array($json) && !empty($json['version']) && !empty($json['download_url']);

// Tag filter behavior
$tags = [
  'choir-rehearsal-v0.4.21' => true,
  'choir-rehearsal-pro-v0.4.21' => false,
  'other-v1.0.0' => false,
];
foreach ($tags as $tag=>$expect) {
  $ok = (1 === preg_match('/^choir-rehearsal-v\d/', $tag));
  $checks['tag_'.$tag] = ($ok === $expect);
}

$fail=0;
foreach ($checks as $k=>$v) {
  echo ($v?'PASS':'FAIL')." $k\n";
  if (!$v) $fail++;
}
echo $fail? "FAILURES=$fail\n" : "ALL_OK\n";
exit($fail?1:0);
