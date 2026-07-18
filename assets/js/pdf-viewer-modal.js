import { Modal } from './modal.js';

/**
 * Ouvre la modale de visualisation PDF.
 *
 * Aucune librairie JS de rendu PDF : l'iframe pointe vers app_file_view, qui
 * sert le fichier en Content-Disposition: inline — le navigateur l'affiche
 * avec son lecteur natif (pagination, zoom, recherche texte).
 *
 * @param {string} viewUrl URL de app_file_view pour ce fichier
 * @param {string} fileName Nom affiché dans la modale
 */
window.openPdfViewerModal = function (viewUrl, fileName) {
	const iframe = document.getElementById('pdf-viewer-iframe');
	const nameEl = document.getElementById('pdf-viewer-name');

	if (!iframe) return;

	iframe.src = viewUrl;
	if (nameEl) {
		nameEl.textContent = fileName || 'ce document';
	}

	Modal.open('pdf-viewer-modal');
};

window.closePdfViewerModal = function () {
	const iframe = document.getElementById('pdf-viewer-iframe');
	if (iframe) {
		// Libère le document affiché plutôt que de le garder chargé en arrière-plan.
		iframe.src = 'about:blank';
	}
	Modal.close('pdf-viewer-modal');
};
