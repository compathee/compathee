(function () {
	'use strict';

	function initChoirPdfViewer(viewer) {
		if (!viewer || typeof window.pdfjsLib === 'undefined') {
			return null;
		}

		if (viewer._choirPdfApi) {
			return viewer._choirPdfApi;
		}

		const canvas = viewer.querySelector('.choir-pdf-viewer__canvas');
		const wrap = viewer.querySelector('.choir-pdf-viewer__canvas-wrap');
		const prevBtn = viewer.querySelector('.choir-pdf-prev');
		const nextBtn = viewer.querySelector('.choir-pdf-next');
		const pageLabel = viewer.querySelector('.choir-pdf-page');

		if (!canvas || !wrap || !prevBtn || !nextBtn || !pageLabel) {
			return null;
		}

		if (window.choirRehearsalPdf && window.choirRehearsalPdf.workerSrc) {
			window.pdfjsLib.GlobalWorkerOptions.workerSrc = window.choirRehearsalPdf.workerSrc;
		}

		let pdfDoc = null;
		let pageNum = 1;
		let pageRendering = false;
		let pageNumPending = null;
		let loadToken = 0;
		const scale = Math.min(window.devicePixelRatio || 1, 2) * 1.2;

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

		function renderPage(num) {
			if (!pdfDoc) {
				return;
			}

			pageRendering = true;

			pdfDoc.getPage(num).then(function (page) {
				const viewport = page.getViewport({ scale: scale });
				const context = canvas.getContext('2d');

				canvas.height = viewport.height;
				canvas.width = viewport.width;

				const renderTask = page.render({
					canvasContext: context,
					viewport: viewport,
				});

				return renderTask.promise.then(function () {
					pageRendering = false;
					updateControls();

					if (pageNumPending !== null) {
						const pending = pageNumPending;
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

		function clearCanvas() {
			const context = canvas.getContext('2d');
			context.clearRect(0, 0, canvas.width || 1, canvas.height || 1);
			canvas.width = 1;
			canvas.height = 1;
		}

		function loadDocument(url) {
			const token = ++loadToken;
			pdfDoc = null;
			pageNum = 1;
			pageNumPending = null;
			pageRendering = false;
			viewer.setAttribute('data-pdf-url', url || '');
			viewer.classList.toggle('is-empty', !url);
			clearCanvas();

			if (!url) {
				setStatus('—');
				return;
			}

			setStatus('…');

			window.pdfjsLib.getDocument({ url: url, withCredentials: true }).promise.then(function (pdf) {
				if (token !== loadToken) {
					return;
				}
				pdfDoc = pdf;
				pageNum = 1;
				renderPage(pageNum);
			}).catch(function () {
				// Retry without credentials (some hosts reject credentialed PDF fetches).
				return window.pdfjsLib.getDocument({ url: url, withCredentials: false }).promise.then(function (pdf) {
					if (token !== loadToken) {
						return;
					}
					pdfDoc = pdf;
					pageNum = 1;
					renderPage(pageNum);
				});
			}).catch(function () {
				if (token !== loadToken) {
					return;
				}
				setStatus('—');
				viewer.classList.add('is-error');
			});
		}

		prevBtn.addEventListener('click', function () {
			goToPage(pageNum - 1);
		});

		nextBtn.addEventListener('click', function () {
			goToPage(pageNum + 1);
		});

		// Swipe / drag left-right to change pages (touch and pointer).
		let pointerId = null;
		let startX = 0;
		let startY = 0;
		let tracking = false;

		wrap.addEventListener('pointerdown', function (event) {
			if (event.pointerType === 'mouse' && event.button !== 0) {
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
			const dx = event.clientX - startX;
			const dy = event.clientY - startY;
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

		const api = {
			load: loadDocument,
			reload: loadDocument,
			next: function () {
				goToPage(pageNum + 1);
			},
			prev: function () {
				goToPage(pageNum - 1);
			},
		};

		viewer._choirPdfApi = api;
		loadDocument(viewer.getAttribute('data-pdf-url') || '');
		return api;
	}

	window.choirInitPdfViewer = initChoirPdfViewer;

	document.querySelectorAll('.choir-pdf-viewer').forEach(function (viewer) {
		initChoirPdfViewer(viewer);
	});
})();
