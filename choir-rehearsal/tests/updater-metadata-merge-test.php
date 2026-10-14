<?php
declare(strict_types=1);

/**
 * Simulates pick-highest-version behavior used when multiple update.json sources disagree.
 */
$candidates = [
  ['version' => '0.4.21', 'download_url' => 'https://example.com/old.zip'],
  ['version' => '0.4.49', 'download_url' => 'https://example.com/new.zip'],
  ['version' => '0.4.38', 'download_url' => 'https://example.com/mid.zip'],
];

$best = null;
$best_ver = '';
foreach ($candidates as $item) {
  $version = (string) $item['version'];
  if (null === $best || version_compare($version, $best_ver, '>')) {
    $best = $item;
    $best_ver = $version;
  }
}

$checks = [
  'best_version' => '0.4.49' === ($best['version'] ?? ''),
  'best_package' => str_contains((string) ($best['download_url'] ?? ''), 'new.zip'),
];

$fail = 0;
foreach ($checks as $k => $v) {
  echo ($v ? 'PASS' : 'FAIL') . " $k\n";
  if (!$v) {
    $fail++;
  }
}
echo $fail ? "FAILURES=$fail\n" : "ALL_OK\n";
exit($fail ? 1 : 0);
