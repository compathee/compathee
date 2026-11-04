/**
 * Which browsers can start Web Speech recognition for song notes.
 * Run: node --test choir-rehearsal/tests/speech-support.test.js
 */

const test = require('node:test');
const assert = require('node:assert/strict');
const speech = require('../public/js/song-notes.js');

const webkit = function WebkitSpeech() {};
const standard = function StandardSpeech() {};

const android = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36';
const chromeIos = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/131.0.6778.73 Mobile/15E148 Safari/604.1';
const safariIos = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1';
const firefoxIos = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/132.0 Mobile/15E148 Safari/605.1.15';
const edgeIos = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) EdgiOS/131.0.2903.68 Version/18.0 Mobile/15E148 Safari/604.1';
const desktop = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

function env(extra) {
	return Object.assign({
		webkitSpeechRecognition: webkit,
		SpeechRecognition: standard,
		secureContext: true,
		userAgent: android,
		platform: 'Linux armv8l',
		maxTouchPoints: 5
	}, extra);
}

test('Chrome Android uses webkitSpeechRecognition', () => {
	assert.equal(speech.supported(env()), true);
	assert.equal(speech.constructorOf(env()), webkit);
	assert.equal(speech.isIos(android, 'Linux armv8l', 5), false);
});

test('an unprefixed constructor still counts when webkit is missing', () => {
	const box = env({ webkitSpeechRecognition: undefined, userAgent: desktop, platform: 'Win32', maxTouchPoints: 0 });
	assert.equal(speech.supported(box), true);
	assert.equal(speech.constructorOf(box), standard);
});

test('Chrome, Firefox, and Edge on iPhone are not offered a dead microphone', () => {
	assert.equal(speech.supported(env({ userAgent: chromeIos, platform: 'iPhone', maxTouchPoints: 5 })), false);
	assert.equal(speech.supported(env({ userAgent: firefoxIos, platform: 'iPhone', maxTouchPoints: 5 })), false);
	assert.equal(speech.supported(env({ userAgent: edgeIos, platform: 'iPhone', maxTouchPoints: 5 })), false);
	assert.equal(speech.isIosNonSafari(chromeIos), true);
});

test('Safari on iPhone can start recognition', () => {
	assert.equal(speech.supported(env({ userAgent: safariIos, platform: 'iPhone', maxTouchPoints: 5 })), true);
	assert.equal(speech.isIos(safariIos, 'iPhone', 5), true);
	assert.equal(speech.isIosNonSafari(safariIos), false);
});

test('iPad Safari desktop mode stays available and iPad Chrome does not', () => {
	const ipadSafari = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Safari/605.1.15';
	const ipadChrome = 'Mozilla/5.0 (iPad; CPU OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/131.0.6778.73 Mobile/15E148 Safari/604.1';
	assert.equal(speech.supported(env({ userAgent: ipadSafari, platform: 'MacIntel', maxTouchPoints: 5 })), true);
	assert.equal(speech.supported(env({ userAgent: ipadChrome, platform: 'iPad', maxTouchPoints: 5 })), false);
});

test('an http page and a browser with no constructor stay unavailable', () => {
	assert.equal(speech.supported(env({ secureContext: false })), false);
	assert.equal(speech.supported(env({ webkitSpeechRecognition: undefined, SpeechRecognition: undefined, secureContext: undefined })), false);
	assert.equal(speech.supported(env({ secureContext: undefined })), true);
});
