<?php
declare(strict_types=1);

/**
 * Static checks for YouTube track source helpers (no WP bootstrap).
 */

$src = file_get_contents(__DIR__ . '/../includes/class-post-types.php');
$frontend = file_get_contents(__DIR__ . '/../includes/class-frontend.php');
$admin = file_get_contents(__DIR__ . '/../includes/class-admin.php');
$js = file_get_contents(__DIR__ . '/../public/js/youtube-embed.js');
$songList = file_get_contents(__DIR__ . '/../public/js/song-list.js');
$adminJs = file_get_contents(__DIR__ . '/../admin/js/admin.js');

if (!is_string($src) || '' === $src) {
	fwrite(STDERR, "FAIL cannot_read_post_types\n");
	exit(1);
}

function test_parse_youtube_video_id(string $url): string {
	$url = trim($url);
	if ('' === $url) {
		return '';
	}
	if (!preg_match('#^https?://#i', $url)) {
		$url = 'https://' . ltrim($url, '/');
	}
	$parts = parse_url($url);
	if (!is_array($parts) || empty($parts['host'])) {
		return '';
	}
	$host = strtolower((string) $parts['host']);
	$host = preg_replace('/^www\./', '', $host) ?? $host;
	$path = isset($parts['path']) ? (string) $parts['path'] : '';
	$is_id = static fn(string $id): bool => (bool) preg_match('/^[A-Za-z0-9_-]{11}$/', $id);

	if ('youtu.be' === $host) {
		$segment = trim($path, '/');
		$segment = explode('/', $segment)[0] ?? '';
		return $is_id($segment) ? $segment : '';
	}
	if (!in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com'], true)) {
		return '';
	}
	if (preg_match('#/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#', $path, $matches)) {
		return $matches[1];
	}
	$query = [];
	if (!empty($parts['query'])) {
		parse_str((string) $parts['query'], $query);
	}
	if (isset($query['v']) && is_string($query['v']) && $is_id($query['v'])) {
		return $query['v'];
	}
	return '';
}

$cases = [
	'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
	'short' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
	'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
	'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
	'with_time' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s', 'dQw4w9WgXcQ'],
	'mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
	'bad_host' => ['https://example.com/watch?v=dQw4w9WgXcQ', ''],
	'empty' => ['', ''],
];

$fail = 0;
foreach ($cases as $name => [$input, $expected]) {
	$got = test_parse_youtube_video_id($input);
	$ok = $got === $expected;
	echo ($ok ? 'PASS' : 'FAIL') . " parse_$name expected=$expected got=$got\n";
	if (!$ok) {
		$fail++;
	}
}

$checks = [
	'track_source_meta' => str_contains($src, "'_choir_source'"),
	'track_youtube_meta' => str_contains($src, "'_choir_youtube_url'"),
	'no_song_youtube_meta' => ! preg_match("/register_post_meta\(\s*self::SONG,\s*'_choir_youtube_url'/", $src),
	'parse_fn' => str_contains($src, 'function parse_youtube_video_id'),
	'track_embed_fn' => str_contains($src, 'function get_track_youtube_embed_url'),
	'song_has_yt' => str_contains($src, 'function song_has_youtube_track'),
	'frontend_track_yt' => is_string($frontend) && str_contains($frontend, 'choir-track-item--youtube'),
	'frontend_badge' => is_string($frontend) && str_contains($frontend, 'render_song_youtube_badge'),
	'admin_source_toggle' => is_string($admin) && str_contains($admin, 'choir-track-source'),
	'admin_youtube_input' => is_string($admin) && str_contains($admin, 'choir-youtube-url-input'),
	'admin_js_source' => is_string($adminJs) && str_contains($adminJs, 'syncTrackSource'),
	'js_exists' => is_string($js) && str_contains($js, 'buildIframe'),
	'song_list_badge' => is_string($songList) && str_contains($songList, 'createYoutubeBadge'),
];

foreach ($checks as $name => $ok) {
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

exit($fail > 0 ? 1 : 0);
