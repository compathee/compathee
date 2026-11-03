/**
 * Mix model for the song-page mixer.
 * Run: node --test choir-rehearsal/tests/mixer-logic.test.js
 */

const test = require('node:test');
const assert = require('node:assert/strict');
const logic = require('../public/js/mixer-logic.js');

test('launch and reset use 70% with mute and solo off', () => {
	const launched = logic.launchMix(3);
	assert.equal(launched.length, 3);
	assert.deepEqual(launched[0], { volume: 0.7, mute: false, solo: false });
	assert.deepEqual(logic.resetMix(launched), logic.launchMix(3));
	assert.equal(logic.DEFAULT_VOLUME, 0.7);
});

test('mute silences a channel and solo silences everyone else', () => {
	const channels = [
		{ volume: 0.7, mute: false, solo: false },
		{ volume: 0.4, mute: true, solo: true },
		{ volume: 1, mute: false, solo: false },
	];
	assert.equal(logic.soloActive(channels), true);
	assert.equal(logic.channelGain(channels[0], true), 0);
	assert.equal(logic.channelGain(channels[1], true), 0);
	assert.equal(logic.channelGain({ volume: 0.4, mute: false, solo: true }, true), 0.4);
	assert.equal(logic.channelGain({ volume: 2, mute: false, solo: false }, false), 1);
	assert.equal(logic.channelGain({ volume: -1, mute: false, solo: false }, false), 0);
	assert.equal(logic.soloActive([{ volume: 0.7, mute: false, solo: false }]), false);
});

test('every channel shares one timeline offset', () => {
	const offset = 12.5;
	const starts = [30, 12.5, 8, 0].map((duration) => logic.sourceOffset(duration, offset));
	assert.deepEqual(starts, [12.5, null, null, null]);
	assert.equal(logic.sourceOffset(40, -3), 0);
});

test('A–B loop is set, swapped, cleared, and wrapped on one clock', () => {
	assert.deepEqual(logic.abAdvance({ a: null, b: null }, 3.2), { a: 3.2, b: null });
	assert.deepEqual(logic.abAdvance({ a: 3.2, b: null }, 8.1), { a: 3.2, b: 8.1 });
	assert.deepEqual(logic.abAdvance({ a: 8, b: null }, 3), { a: 3, b: 8 });
	assert.deepEqual(logic.abAdvance({ a: 3, b: null }, 3), { a: 3, b: null });
	assert.deepEqual(logic.abAdvance({ a: 3.2, b: 8.1 }, 4), { a: null, b: null });
	assert.equal(logic.abEnabled({ a: 3, b: 8 }), true);
	assert.equal(logic.abEnabled({ a: 3, b: null }), false);
	assert.equal(logic.wrapLoop(5, { a: 2, b: 8 }), 5);
	assert.equal(logic.wrapLoop(8, { a: 2, b: 8 }), 2);
	assert.equal(logic.wrapLoop(9, { a: null, b: null }), 9);
});

test('speed cycles the rehearsal rates and skip stays inside the song', () => {
	assert.equal(logic.nextSpeed(1), 1.1);
	assert.equal(logic.nextSpeed(1.5), 0.5);
	assert.equal(logic.nextSpeed(0.25), 1);
	assert.deepEqual(logic.SPEEDS, [0.5, 0.75, 0.9, 1, 1.1, 1.25, 1.5]);
	assert.equal(logic.SKIP_SECONDS, 5);
	assert.equal(logic.skipTime(10, -5, 30), 5);
	assert.equal(logic.skipTime(2, -5, 30), 0);
	assert.equal(logic.skipTime(28, 5, 30), 30);
});

test('the shared playhead never runs past the longest track', () => {
	assert.equal(logic.clampPlayhead(15, 8), 8);
	assert.equal(logic.clampPlayhead(3.5, 8), 3.5);
	assert.equal(logic.clampPlayhead(-2, 8), 0);
	assert.equal(logic.clampPlayhead(4, 0), 4);
});

test('time format is m:ss and does not invent a duration', () => {
	assert.equal(logic.formatTime(0), '0:00');
	assert.equal(logic.formatTime(65), '1:05');
	assert.equal(logic.formatTime(3599), '59:59');
	assert.equal(logic.formatTime(-4), '0:00');
	assert.equal(logic.formatTime(Number.POSITIVE_INFINITY), '0:00');
});
