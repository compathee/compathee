<?php
/**
 * Song note parsing, permissions, and speech-language mapping.
 *
 * Run: php choir-rehearsal/tests/song-notes-format-test.php
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);

require dirname(__DIR__) . '/includes/class-song-notes-format.php';

$fail = 0;

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

$tracks = array(
	array('id' => 5, 'label' => 'Tenor'),
	array('id' => 6, 'label' => 'Tenor 1'),
	array('id' => 7, 'label' => 'Soprano'),
);

$song = Choir_Rehearsal_Song_Notes_Format::resolve_track_id('enter softer from bar 17', $tracks);
check('note without @ is the whole song', 0 === $song);

$addressed = Choir_Rehearsal_Song_Notes_Format::resolve_track_id('@Tenor 1:12 enter softer, crescendo from bar 17', $tracks);
check('example addresses Tenor not Tenor 1', 5 === $addressed);

$longer = Choir_Rehearsal_Song_Notes_Format::resolve_track_id('@Tenor 1 enter together', $tracks);
check('longer voice label wins when it is not a clock', 6 === $longer);

$unknown = Choir_Rehearsal_Song_Notes_Format::resolve_track_id('@Nobody 1:12 hello', $tracks);
check('unknown @ is still a song note', 0 === $unknown);

$clean = Choir_Rehearsal_Song_Notes_Format::sanitize_body("  <b>@Tenor</b>\n<script>alert(1)</script> 1:12  ");
check('tags are stripped', '@Tenor' . "\n" . 'alert(1) 1:12' === $clean);

$segments = Choir_Rehearsal_Song_Notes_Format::segments('@Tenor 1:12 enter softer, crescendo from bar 17', $tracks);
$types = array_map(static fn(array $part): string => $part['type'], $segments);
check('example splits mention, time, and text', $types === array('mention', 'text', 'time', 'text'));
$time = null;
foreach ($segments as $part) {
	if ('time' === $part['type']) {
		$time = $part;
	}
}
check('1:12 is 72 seconds', is_array($time) && 72 === ($time['seconds'] ?? -1) && '1:12' === ($time['label'] ?? ''));
$text = '';
foreach ($segments as $part) {
	if ('text' === $part['type']) {
		$text .= $part['text'];
	}
}
check('bar 17 stays plain text', str_contains($text, 'bar 17') && !str_contains($text, '1:12'));

$plain = Choir_Rehearsal_Song_Notes_Format::segments('meet at 1:12', $tracks);
check('clock without a track is not a seek mark', count($plain) === 1 && 'text' === $plain[0]['type']);

$stored = Choir_Rehearsal_Song_Notes_Format::segments('1:12 softer', $tracks, 5);
$stored_time = false;
foreach ($stored as $part) {
	if ('time' === $part['type'] && 72 === ($part['seconds'] ?? -1)) {
		$stored_time = true;
	}
}
check('stored track keeps the time mark seekable', $stored_time);

$long = Choir_Rehearsal_Song_Notes_Format::segments('@Soprano 0:05 then 1:02:03', $tracks);
$clocks = array();
foreach ($long as $part) {
	if ('time' === $part['type']) {
		$clocks[] = $part['seconds'];
	}
}
check('multiple clocks parse', $clocks === array(5, 3723));

check('format 72 seconds', '1:12' === Choir_Rehearsal_Song_Notes_Format::format_clock(72));
check('format one hour', '1:02:03' === Choir_Rehearsal_Song_Notes_Format::format_clock(3723));
check('parse round trip', 72 === Choir_Rehearsal_Song_Notes_Format::parse_clock('1:12'));
check('negative clock clamps', '0:00' === Choir_Rehearsal_Song_Notes_Format::format_clock(-4));

$too_long = str_repeat('a', 5001);
check('body is capped', 5000 === strlen(Choir_Rehearsal_Song_Notes_Format::sanitize_body($too_long)));

check('guests can read when the song is visible', Choir_Rehearsal_Song_Notes_Format::user_can_read(true, true));
check('read stays off without the pro gate', !Choir_Rehearsal_Song_Notes_Format::user_can_read(false, true));
check('read stays off when the song is hidden', !Choir_Rehearsal_Song_Notes_Format::user_can_read(true, false));
check('voice leader can write', Choir_Rehearsal_Song_Notes_Format::user_can_write(true, true, true));
check('singer cannot write', !Choir_Rehearsal_Song_Notes_Format::user_can_write(true, true, false));
check('guest cannot write', !Choir_Rehearsal_Song_Notes_Format::user_can_write(true, false, false));
check('write stays off without the pro gate', !Choir_Rehearsal_Song_Notes_Format::user_can_write(false, true, true));

check('estonian speech tag', 'et-EE' === Choir_Rehearsal_Song_Notes_Format::speech_lang('et'));
check('russian speech tag', 'ru-RU' === Choir_Rehearsal_Song_Notes_Format::speech_lang('ru_RU'));
check('english speech tag', 'en-US' === Choir_Rehearsal_Song_Notes_Format::speech_lang('en_US'));

exit($fail > 0 ? 1 : 0);
