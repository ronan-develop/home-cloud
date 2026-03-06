import { Modal } from './modal.js';

/**
 * Ouvre la modale de suppression de dossier.
 *
 * @param {string} folderId   UUID du dossier à supprimer
 * @param {string} folderName Nom affiché dans la modale
 * @param {string} parentId   UUID du dossier parent (pour la redirection post-suppression)
 */
window.openDeleteFolderModal = function (folderId, folderName, parentId) {
	const form     = document.getElementById('delete-folder-form');
	const nameEl   = document.getElementById('delete-folder-name');
	const redirect = document.getElementById('delete-folder-redirect-input');

	if (!form || !nameEl) return;

	form.action    = `/folders/${folderId}/delete`;
	nameEl.textContent = folderName || 'ce dossier';
	redirect.value = parentId || '';

	Modal.open('delete-folder-modal');
};

window.submitDeleteFolder = function (deleteContents) {
	const form  = document.getElementById('delete-folder-form');
	const input = document.getElementById('delete-folder-contents-input');
	if (!form || !input) return;

	input.value = deleteContents ? '1' : '0';
	form.submit();
};
