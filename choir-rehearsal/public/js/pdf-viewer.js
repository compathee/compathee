(function () {
	'use strict';

	const viewer = document.querySelector('.choir-pdf-viewer');
	if (!viewer || typeof window.pdfjsLib === 'undefined') {
		return;
	}

	const pdfUrl = viewer.getAttribute('data-pdf-url');
	const canvas = viewer.querySelector('.choir-pdf-viewer__canvas');
	const prevBtn = viewer.querySelector('.choir-pdf-prev');
	const nextBtn = viewer.querySelector('.choir-pdf-next');
	const pageLabel = viewer.querySelector('.choir-pdf-page');

	if (!pdfUrl || !canvas || !prevBtn || !nextBtn || !pageLabel) {
		return;
	}

	window.pdfjsLib.GlobalWorkerOptions.workerSrc = choirRehearsalPdf.workerSrc;

	let pdfDoc = null;
	let pageNum = 1;
	let pageRendering = false;
	let pageNumPending = null;
	const scale = Math.min(window.devicePixelRatio || 1, 2) * 1.2;

	function updateControls() {
		if (!pdfDoc) {
			return;
		}

		prevBtn.disabled = pageNum <= 1;
		nextBtn.disabled = pageNum >= pdfDoc.numPages;
		pageLabel.textContent = pageNum + ' / ' + pdfDoc.numPages;
	}

	function renderPage(num) {
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

			renderTask.promise.then(function () {
				pageRendering = false;
				updateControls();

				if (pageNumPending !== null) {
					renderPage(pageNumPending);
					pageNumPending = null;
				}
			});
		});
	}

	function queueRenderPage(num) {
		if (pageRendering) {
			pageNumPending = num;
			return;
		}

		renderPage(num);
	}

	prevBtn.addEventListener('click', function () {
		if (!pdfDoc || pageNum <= 1) {
			return;
		}

		pageNum -= 1;
		queueRenderPage(pageNum);
	});

	nextBtn.addEventListener('click', function () {
		if (!pdfDoc || pageNum >= pdfDoc.numPages) {
			return;
		}

		pageNum += 1;
		queueRenderPage(pageNum);
	});

	window.pdfjsLib.getDocument({ url: pdfUrl, withCredentials: true }).promise.then(function (pdf) {
		pdfDoc = pdf;
		renderPage(pageNum);
	}).catch(function () {
		pageLabel.textContent = '—';
	});
})();
