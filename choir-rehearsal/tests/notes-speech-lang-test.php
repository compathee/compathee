<?php
/**
 * Dictation defaults to English and starts only after a language is chosen.
 *
 * Run: php choir-rehearsal/tests/notes-speech-lang-test.php
 */

declare(strict_types=1);

$js = (string) file_get_contents(__DIR__ . '/../public/js/song-notes.js');
$php = (string) file_get_contents(__DIR__ . '/../includes/class-song-notes.php');
$css = (string) file_get_contents(__DIR__ . '/../public/css/public.css');

$fail = 0;

function check(string $name, bool $ok): void {
	global $fail;
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}

check('default recognition language is English', str_contains($js, "const SPEECH_DEFAULT = 'en-US'") && str_contains($js, 'return savedSpeechLang() || SPEECH_DEFAULT'));
check('page language is not consulted', !str_contains($js, 'trp_language') && !str_contains($js, 'pll_language') && !str_contains($js, 'function pluginLanguage') && !str_contains($js, 'cfg.wpLocale') && !str_contains($js, 'document.documentElement.getAttribute(\'lang\')'));
check('saved choice is read from localStorage', str_contains($js, "const SPEECH_KEY = 'choir-notes-speech-lang'") && str_contains($js, 'localStorage.getItem(SPEECH_KEY)') && str_contains($js, 'localStorage.setItem(SPEECH_KEY, code)'));

$start = strpos($js, 'function startDictation');
$lang = false === $start ? false : strpos($js, 'recognition.lang = lang || currentSpeechLang();', $start);
$begin = false === $start ? false : strpos($js, 'recognition.start();', $start);
check('lang is set before start', false !== $lang && false !== $begin && $lang < $begin);

$codes = '';
if (preg_match('/const SPEECH_CODES = \[([\s\S]*?)\];/', $js, $match)) {
	$codes = $match[1];
}
preg_match_all("/'([a-z]{2,3}-[A-Z]{2})'/", $codes, $tags);
$unique = array_values(array_unique($tags[1] ?? []));
check('speech catalog has at least 60 languages', count($unique) >= 60);
check('english estonian and russian are pinned', str_contains($js, "const SPEECH_PINNED = ['en-US', 'et-EE', 'ru-RU']") && in_array('en-US', $unique, true) && in_array('et-EE', $unique, true) && in_array('ru-RU', $unique, true));
check('choosing a language starts dictation in that language', str_contains($js, 'startDictation(code)') && str_contains($js, 'function chooseSpeechLang'));

$click = strpos($js, "micBtn.addEventListener('click'");
$click_body = false === $click ? '' : substr($js, $click, 320);
check('microphone opens the language popover', str_contains($click_body, 'openLangPopover()') && !str_contains($click_body, 'startDictation('));
check('keyboard can move, choose, and dismiss', str_contains($js, "'ArrowDown'") && str_contains($js, "'ArrowUp'") && str_contains($js, "'Enter'") && str_contains($js, "'Escape'") && str_contains($js, 'closeLangPopover()'));
check('unsupported browsers get a visible message', str_contains($php, 'choir-notes-speech-unsupported') && str_contains($php, 'Voice input is not supported in this browser.') && str_contains($js, 'function showSpeechUnsupported'));
check('picker is a searchable listbox', str_contains($php, 'id="choir-notes-langpop"') && str_contains($php, 'role="listbox"') && str_contains($php, 'role="combobox"') && str_contains($php, 'Search languages') && str_contains($js, "role', 'option'") && str_contains($js, 'choir-notes-langopt__name'));
check('language names stay readable against themed text', str_contains($css, '.choir-notes-langopt__name') && str_contains($css, 'color: #101828 !important') && str_contains($css, '-webkit-text-fill-color: #101828 !important') && str_contains($css, 'text-indent: 0 !important') && str_contains($css, 'opacity: 1 !important'));

exit($fail > 0 ? 1 : 0);
