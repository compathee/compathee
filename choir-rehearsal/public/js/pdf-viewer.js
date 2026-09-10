(function () {
	'use strict';

	var PLAYER_RESERVE_PX = 72;
	var MIN_ZOOM = 0.35;
	var MAX_ZOOM = 5;

	function initChoirPdfViewer(viewer) {
		if (!viewer || typeof window.pdfjsLib === 'undefined') {
			return null;
		}

		if (viewer._choirPdfApi) {
			return viewer._choirPdfApi;
		}

		var canvas = viewer.querySelector('.choir-pdf-viewer__canvas');
		var wrap = viewer.querySelector('.choir-pdf-viewer__canvas-wrap');
		var prevBtn = viewer.querySelector('.choir-pdf-prev');
		var nextBtn = viewer.querySelector('.choir-pdf-next');
		var pageLabel = viewer.querySelector('.choir-pdf-page');
		var expandBtn = viewer.querySelector('.choir-pdf-expand');
		var closeFsBtn = viewer.querySelector('.choir-pdf-close-fs');
		var i18n = window.choirRehearsalPdf || {};

		if (!canvas || !wrap || !prevBtn || !nextBtn || !pageLabel) {
			return null;
		}

		if (i18n.workerSrc) {
			window.pdfjsLib.GlobalWorkerOptions.workerSrc = i18n.workerSrc;
		}

		var pdfDoc = null;
		var pageNum = 1;
		var pageRendering = false;
		var pageNumPending = null;
		var loadToken = 0;
		/** User zoom relative to fit-width (1 = page fits container width). */
		var zoom = 1;
		var pinchLiveScale = 1;
		var isFullscreen = false;
		var resizeTimer = null;

		function setStatus(text) {
			pageLabel.textContent = text;
			prevBtn.disabled = true;
			nextBtn.disabled = true;
		}

		function updateControls() {
			if (!pdfDoc) {
				setStatus('—');
				return;
			}

			prevBtn.disabled = pageNum <= 1;
			nextBtn.disabled = pageNum >= pdfDoc.numPages;
			pageLabel.textContent = pageNum + ' / ' + pdfDoc.numPages;
		}

		function wrapContentWidth() {
			var style = window.getComputedStyle(wrap);
			var padL = parseFloat(style.paddingLeft) || 0;
			var padR = parseFloat(style.paddingRight) || 0;
			return Math.max(120, wrap.clientWidth - padL - padR);
		}

		function applyCanvasTransform() {
			var live = pinchLiveScale;
			canvas.style.transformOrigin = 'center top';
			if (Math.abs(live - 1) > 0.001) {
				canvas.style.transform = 'scale(' + live + ')';
			} else {
				canvas.style.transform = '';
			}
		}

		function renderPage(num) {
			if (!pdfDoc) {
				return;
			}

			pageRendering = true;

			pdfDoc.getPage(num).then(function (page) {
				var dpr = Math.min(window.devicePixelRatio || 1, 2);
				var unscaled = page.getViewport({ scale: 1 });
				var fitScale = wrapContentWidth() / unscaled.width;
				var cssScale = fitScale * zoom;
				var viewport = page.getViewport({ scale: cssScale * dpr });
				var context = canvas.getContext('2d');

				canvas.width = Math.floor(viewport.width);
				canvas.height = Math.floor(viewport.height);
				canvas.style.width = Math.floor(viewport.width / dpr) + 'px';
				canvas.style.height = Math.floor(viewport.height / dpr) + 'px';
				canvas.classList.toggle('is-zoomed', zoom > 1.02);
				// Drop live CSS scale only after the new bitmap size is applied.
				pinchLiveScale = 1;
				applyCanvasTransform();

				var renderTask = page.render({
					canvasContext: context,
					viewport: viewport,
				});

				return renderTask.promise.then(function () {
					pageRendering = false;
					updateControls();

					if (pageNumPending !== null) {
						var pending = pageNumPending;
						pageNumPending = null;
						renderPage(pending);
					}
				});
			}).catch(function () {
				pageRendering = false;
				setStatus('—');
			});
		}

		function queueRenderPage(num) {
			if (pageRendering) {
				pageNumPending = num;
				return;
			}

			renderPage(num);
		}

		function goToPage(num) {
			if (!pdfDoc || num < 1 || num > pdfDoc.numPages || num === pageNum) {
				return;
			}
			pageNum = num;
			queueRenderPage(pageNum);
		}

		function setZoom(nextZoom, rerender) {
			var clamped = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, nextZoom));
			if (Math.abs(clamped - zoom) < 0.001 && pinchLiveScale === 1) {
				return;
			}
			zoom = clamped;
			if (rerender !== false) {
				// Keep pinchLiveScale until render paints, so the view does not flash/snap.
				queueRenderPage(pageNum);
			} else {
				pinchLiveScale = 1;
				applyCanvasTransform();
			}
		}

		function clearCanvas() {
			var context = canvas.getContext('2d');
			context.clearRect(0, 0, canvas.width || 1, canvas.height || 1);
			canvas.width = 1;
			canvas.height = 1;
			canvas.style.width = '';
			canvas.style.height = '';
			pinchLiveScale = 1;
			applyCanvasTransform();
		}

		function getDocumentWithTimeout(url, withCredentials, timeoutMs) {
			return Promise.race([
				window.pdfjsLib.getDocument({ url: url, withCredentials: withCredentials }).promise,
				new Promise(function (_, reject) {
					window.setTimeout(function () {
						reject(new Error('pdf-load-timeout'));
					}, timeoutMs);
				}),
			]);
		}

		function loadDocument(url) {
			var token = ++loadToken;
			pdfDoc = null;
			pageNum = 1;
			pageNumPending = null;
			pageRendering = false;
			zoom = 1;
			pinchLiveScale = 1;
			viewer.setAttribute('data-pdf-url', url || '');
			viewer.classList.toggle('is-empty', !url);
			viewer.classList.remove('is-error');
			applyCanvasTransform();

			if (!url) {
				clearCanvas();
				setStatus('—');
				return;
			}

			setStatus('…');

			getDocumentWithTimeout(url, false, 8000)
				.catch(function () {
					return getDocumentWithTimeout(url, true, 8000);
				})
				.then(function (pdf) {
					if (token !== loadToken) {
						return;
					}
					pdfDoc = pdf;
					pageNum = 1;
					renderPage(pageNum);
				})
				.catch(function () {
					if (token !== loadToken) {
						return;
					}
					clearCanvas();
					setStatus('—');
					viewer.classList.add('is-error');
				});
		}

		function syncPlayerReserve() {
			var player = document.getElementById('choir-sticky-player');
			var reserve = PLAYER_RESERVE_PX;
			if (player && !player.classList.contains('is-hidden')) {
				reserve = Math.max(PLAYER_RESERVE_PX, Math.ceil(player.getBoundingClientRect().height) + 8);
			}
			var dockVar = getComputedStyle(document.documentElement).getPropertyValue('--choir-recording-dock-height').trim();
			var dockHeight = dockVar ? parseInt(dockVar, 10) : 0;
			if (!dockHeight && document.body.classList.contains('choir-recording-dock-open')) {
				var dock = document.getElementById('choir-recording-dock');
				if (dock && !dock.hidden) {
					dockHeight = Math.ceil(dock.getBoundingClientRect().height) || 52;
				}
			}
			if (dockHeight > 0) {
				reserve += dockHeight + 8;
			}
			document.documentElement.style.setProperty('--choir-pdf-player-reserve', reserve + 'px');
		}

		function syncToolbarButtons() {
			if (expandBtn) {
				if (isFullscreen) {
					expandBtn.setAttribute('hidden', 'hidden');
					expandBtn.hidden = true;
				} else {
					expandBtn.removeAttribute('hidden');
					expandBtn.hidden = false;
				}
			}
			if (closeFsBtn) {
				if (isFullscreen) {
					closeFsBtn.removeAttribute('hidden');
					closeFsBtn.hidden = false;
				} else {
					closeFsBtn.setAttribute('hidden', 'hidden');
					closeFsBtn.hidden = true;
				}
			}
		}

		function enterFullscreen() {
			if (isFullscreen || viewer.classList.contains('is-empty')) {
				return;
			}
			isFullscreen = true;
			zoom = 1;
			pinchLiveScale = 1;
			syncPlayerReserve();
			viewer.classList.add('is-fullscreen');
			document.body.classList.add('choir-pdf-fullscreen-open');
			syncToolbarButtons();
			// Wait a frame so fullscreen layout sizes are available for fit-width.
			window.requestAnimationFrame(function () {
				queueRenderPage(pageNum);
			});
		}

		function exitFullscreen() {
			if (!isFullscreen) {
				return;
			}
			isFullscreen = false;
			zoom = 1;
			pinchLiveScale = 1;
			viewer.classList.remove('is-fullscreen');
			document.body.classList.remove('choir-pdf-fullscreen-open');
			syncToolbarButtons();
			window.requestAnimationFrame(function () {
				queueRenderPage(pageNum);
			});
		}

		prevBtn.addEventListener('click', function () {
			goToPage(pageNum - 1);
		});

		nextBtn.addEventListener('click', function () {
			goToPage(pageNum + 1);
		});

		if (expandBtn) {
			if (i18n.expand) {
				expandBtn.setAttribute('aria-label', i18n.expand);
			}
			expandBtn.addEventListener('click', function (event) {
				event.preventDefault();
				enterFullscreen();
			});
		}

		if (closeFsBtn) {
			if (i18n.closeFs) {
				closeFsBtn.setAttribute('aria-label', i18n.closeFs);
			}
			closeFsBtn.addEventListener('click', function (event) {
				event.preventDefault();
				exitFullscreen();
			});
		}

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && isFullscreen) {
				exitFullscreen();
			}
		});

		// Single-finger swipe for pages (disabled while zoomed or pinching).
		var pointerId = null;
		var startX = 0;
		var startY = 0;
		var tracking = false;

		wrap.addEventListener('pointerdown', function (event) {
			if (event.pointerType === 'mouse' && event.button !== 0) {
				return;
			}
			if (zoom > 1.02 || pinchLiveScale !== 1) {
				return;
			}
			pointerId = event.pointerId;
			startX = event.clientX;
			startY = event.clientY;
			tracking = true;
			try {
				wrap.setPointerCapture(event.pointerId);
			} catch (err) {
				// Older browsers may not support capture.
			}
		});

		wrap.addEventListener('pointerup', function (event) {
			if (!tracking || event.pointerId !== pointerId) {
				return;
			}
			tracking = false;
			if (zoom > 1.02) {
				return;
			}
			var dx = event.clientX - startX;
			var dy = event.clientY - startY;
			if (Math.abs(dx) < 48 || Math.abs(dx) < Math.abs(dy) * 1.2) {
				return;
			}
			if (dx < 0) {
				goToPage(pageNum + 1);
			} else {
				goToPage(pageNum - 1);
			}
		});

		wrap.addEventListener('pointercancel', function () {
			tracking = false;
		});

		// Pinch-to-zoom (touch). Live CSS scale, then commit to zoom on release.
		var pinchStartDistance = 0;
		var pinchStartZoom = 1;
		var pinching = false;

		function touchDistance(touches) {
			var dx = touches[0].clientX - touches[1].clientX;
			var dy = touches[0].clientY - touches[1].clientY;
			return Math.sqrt(dx * dx + dy * dy);
		}

		wrap.addEventListener(
			'touchstart',
			function (event) {
				if (event.touches.length === 2) {
					pinching = true;
					tracking = false;
					pinchStartDistance = touchDistance(event.touches);
					pinchStartZoom = zoom * pinchLiveScale;
					event.preventDefault();
				}
			},
			{ passive: false }
		);

		wrap.addEventListener(
			'touchmove',
			function (event) {
				if (!pinching || event.touches.length !== 2 || !pinchStartDistance) {
					return;
				}
				event.preventDefault();
				var ratio = touchDistance(event.touches) / pinchStartDistance;
				var next = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, pinchStartZoom * ratio));
				pinchLiveScale = next / zoom;
				applyCanvasTransform();
			},
			{ passive: false }
		);

		function endPinch() {
			if (!pinching) {
				return;
			}
			pinching = false;
			var nextZoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, zoom * pinchLiveScale));
			// Keep the live visual until the re-render paints the committed zoom.
			setZoom(nextZoom, true);
		}

		wrap.addEventListener('touchend', endPinch);
		wrap.addEventListener('touchcancel', endPinch);

		// Desktop wheel zoom with ctrl/cmd (trackpad pinch often sends this).
		wrap.addEventListener(
			'wheel',
			function (event) {
				if (!(event.ctrlKey || event.metaKey)) {
					return;
				}
				event.preventDefault();
				var delta = event.deltaY > 0 ? -0.12 : 0.12;
				setZoom(zoom + delta, true);
			},
			{ passive: false }
		);

		if (typeof ResizeObserver !== 'undefined') {
			var ro = new ResizeObserver(function () {
				if (!pdfDoc) {
					return;
				}
				window.clearTimeout(resizeTimer);
				resizeTimer = window.setTimeout(function () {
					if (isFullscreen) {
						syncPlayerReserve();
					}
					queueRenderPage(pageNum);
				}, 120);
			});
			ro.observe(wrap);
		}

		var api = {
			load: loadDocument,
			reload: loadDocument,
			next: function () {
				goToPage(pageNum + 1);
			},
			prev: function () {
				goToPage(pageNum - 1);
			},
			expand: enterFullscreen,
			collapse: exitFullscreen,
		};

		viewer._choirPdfApi = api;
		syncToolbarButtons();
		loadDocument(viewer.getAttribute('data-pdf-url') || '');
		return api;
	}

	window.choirInitPdfViewer = initChoirPdfViewer;

	document.querySelectorAll('.choir-pdf-viewer').forEach(function (viewer) {
		initChoirPdfViewer(viewer);
	});
})();
