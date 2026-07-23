import { Modal } from './modal.js';

/**
 * Ouvre la modale de suppression de fichier (#246).
 *
 * L'option « conserver dans mes albums » n'est affichée que si le fichier
 * a un Media rattaché à au moins un album (inAlbum) — sinon confirmation
 * simple, jamais de confirm() natif.
 *
 * @param {string}  fileId   UUID du fichier à supprimer
 * @param {string}  fileName Nom affiché dans la modale
 * @param {string}  folderId Dossier courant (pour la redirection post-suppression)
 * @param {boolean} inAlbum  Le Media associé appartient à au moins un album
 */
window.openDeleteFileModal = function (fileId, fileName, folderId, inAlbum) {
	const form         = document.getElementById('delete-file-form');
	const nameEl       = document.getElementById('delete-file-name');
	const folderInput  = document.getElementById('delete-file-folder-input');
	const albumOption  = document.getElementById('delete-file-album-option');
	const simpleConfirm = document.getElementById('delete-file-simple-confirm');

	if (!form || !nameEl) return;

	form.action = `/files/${fileId}/delete`;
	nameEl.textContent = fileName || 'ce fichier';
	folderInput.value = folderId || '';

	albumOption.classList.toggle('hidden', !inAlbum);
	simpleConfirm.classList.toggle('hidden', !!inAlbum);

	Modal.open('delete-file-modal');
};

window.submitDeleteFile = function (keepInAlbums) {
	const form  = document.getElementById('delete-file-form');
	const input = document.getElementById('delete-file-keep-in-albums-input');
	if (!form || !input) return;

	input.value = keepInAlbums ? '1' : '0';
	form.submit();
};
