/**
 * Move the Mixer control onto the Voice Tracks metabox heading.
 * WordPress prints that heading; the button is rendered by the tracks metabox.
 */
(function () {
	'use strict';

	function placeChoirMixerLaunch() {
		var box = document.getElementById('choir-rehearsal-tracks');
		if (!box) {
			return;
		}
		var button = box.querySelector('.choir-mixer-launch, .choir-mixer-note');
		if (!button || button.getAttribute('data-choir-mixer-placed') === '1') {
			return;
		}
		var header = box.querySelector('.postbox-header');
		var title = header ? header.querySelector('h2, .hndle') : null;
		if (!title) {
			return;
		}
		title.insertAdjacentElement('afterend', button);
		button.setAttribute('data-choir-mixer-placed', '1');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', placeChoirMixerLaunch);
	} else {
		placeChoirMixerLaunch();
	}
})();
