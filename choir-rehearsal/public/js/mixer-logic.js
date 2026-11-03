/**
 * Pure mix model for the song-page mixer. No DOM and no network.
 * Browser: window.choirMixerLogic. Node tests: module.exports.
 */
(function (root, factory) {
	var api = factory();
	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	}
	root.choirMixerLogic = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
	'use strict';

	var DEFAULT_VOLUME = 0.7;
	var SKIP_SECONDS = 5;
	var SPEEDS = [0.5, 0.75, 0.9, 1, 1.1, 1.25, 1.5];

	function launchChannel() {
		return { volume: DEFAULT_VOLUME, mute: false, solo: false };
	}

	function launchMix(count) {
		var total = count > 0 ? Math.floor(count) : 0;
		var channels = [];
		var index;
		for (index = 0; index < total; index += 1) {
			channels.push(launchChannel());
		}
		return channels;
	}

	function resetMix(channels) {
		return launchMix(channels && channels.length ? channels.length : 0);
	}

	function soloActive(channels) {
		var index;
		if (!channels) {
			return false;
		}
		for (index = 0; index < channels.length; index += 1) {
			if (channels[index] && channels[index].solo) {
				return true;
			}
		}
		return false;
	}

	function channelGain(channel, anySolo) {
		var volume;
		if (!channel || channel.mute) {
			return 0;
		}
		if (anySolo && !channel.solo) {
			return 0;
		}
		volume = Number(channel.volume);
		if (!isFinite(volume)) {
			return 0;
		}
		return Math.min(1, Math.max(0, volume));
	}

	/**
	 * Offset into one buffer for a shared playhead.
	 * Null means this buffer has already ended at that playhead.
	 */
	function sourceOffset(duration, offset) {
		var start;
		if (!(duration > 0) || !isFinite(duration)) {
			return null;
		}
		start = Number(offset);
		if (!isFinite(start) || start < 0) {
			start = 0;
		}
		if (start >= duration - 0.0005) {
			return null;
		}
		return start;
	}

	function roundTime(time) {
		if (!isFinite(time) || time < 0) {
			return 0;
		}
		return Math.round(time * 1000) / 1000;
	}

	function abAdvance(loop, time) {
		var point = roundTime(time);
		var start = loop && loop.a !== null && loop.a !== undefined ? loop.a : null;
		var end = loop && loop.b !== null && loop.b !== undefined ? loop.b : null;
		if (start !== null && end !== null) {
			return { a: null, b: null };
		}
		if (start === null) {
			return { a: point, b: null };
		}
		if (point === start) {
			return { a: start, b: null };
		}
		if (point < start) {
			return { a: point, b: start };
		}
		return { a: start, b: point };
	}

	function abEnabled(loop) {
		return !!(loop && loop.a !== null && loop.a !== undefined && loop.b !== null && loop.b !== undefined && loop.b > loop.a);
	}

	function wrapLoop(time, loop) {
		if (!abEnabled(loop)) {
			return time;
		}
		if (time >= loop.b) {
			return loop.a;
		}
		return time;
	}

	function nextSpeed(current) {
		var index = SPEEDS.indexOf(current);
		if (index < 0) {
			return 1;
		}
		return SPEEDS[(index + 1) % SPEEDS.length];
	}

	function clampPlayhead(time, duration) {
		var value = Number(time);
		if (!isFinite(value) || value < 0) {
			value = 0;
		}
		if (isFinite(duration) && duration > 0 && value > duration) {
			return duration;
		}
		return value;
	}

	function skipTime(time, delta, duration) {
		var next = (Number(time) || 0) + delta;
		var end = isFinite(duration) && duration > 0 ? duration : 0;
		return Math.min(end, Math.max(0, next));
	}

	function formatTime(seconds) {
		var whole;
		var minutes;
		var remain;
		if (!isFinite(seconds) || seconds < 0) {
			return '0:00';
		}
		whole = Math.floor(seconds);
		minutes = Math.floor(whole / 60);
		remain = whole % 60;
		return minutes + ':' + String(remain).padStart(2, '0');
	}

	return {
		DEFAULT_VOLUME: DEFAULT_VOLUME,
		SKIP_SECONDS: SKIP_SECONDS,
		SPEEDS: SPEEDS,
		launchMix: launchMix,
		resetMix: resetMix,
		soloActive: soloActive,
		channelGain: channelGain,
		sourceOffset: sourceOffset,
		abAdvance: abAdvance,
		abEnabled: abEnabled,
		wrapLoop: wrapLoop,
		nextSpeed: nextSpeed,
		skipTime: skipTime,
		clampPlayhead: clampPlayhead,
		formatTime: formatTime,
	};
});
