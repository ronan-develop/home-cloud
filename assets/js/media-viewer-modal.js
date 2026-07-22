import { Modal } from './modal.js';

/**
 * Ouvre la modale de visualisation image/vidéo.
 *
 * Aucune librairie JS de rendu : l'élément <img>/<video> pointe vers
 * app_file_view, qui sert le fichier en Content-Disposition: inline.
 *
 * @param {string} viewUrl URL de app_file_view pour ce fichier
 * @param {string} fileName Nom affiché dans la modale
 * @param {'image'|'video'} type Type de média à afficher
 */
window.openMediaViewerModal = function (viewUrl, fileName, type) {
	const img = document.getElementById('media-viewer-image');
	const video = document.getElementById('media-viewer-video');
	const nameEl = document.getElementById('media-viewer-name');

	if (!img || !video) return;

	if (type === 'video') {
		video.src = viewUrl;
		video.classList.remove('hidden');
		img.classList.add('hidden');
		img.src = '';
	} else {
		img.src = viewUrl;
		img.classList.remove('hidden');
		video.classList.add('hidden');
		video.pause();
		video.removeAttribute('src');
		video.load();
	}

	if (nameEl) {
		nameEl.textContent = fileName || 'ce fichier';
	}

	Modal.open('media-viewer-modal');
};

window.closeMediaViewerModal = function () {
	const img = document.getElementById('media-viewer-image');
	const video = document.getElementById('media-viewer-video');

	if (video) {
		// pause() seul ne suffit pas : sans removeAttribute('src') + load(), le
		// navigateur continue de streamer/décoder la vidéo en arrière-plan.
		video.pause();
		video.removeAttribute('src');
		video.load();
		video.classList.add('hidden');
	}
	if (img) {
		img.src = '';
		img.classList.add('hidden');
	}

	Modal.close('media-viewer-modal');
};
