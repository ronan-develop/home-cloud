import { apiFetch } from './api.js';
import { Modal } from './modal.js';

const INVALID_CHARS = /[\\/:*?"<>|]/u;

class RenameManager {
	constructor() {
		this.handleClick = this.handleClick.bind(this);
	}

	init() {
		document.addEventListener('click', this.handleClick);
	}

	async handleClick(e) {
		const clicked = e.target;
		const el = (clicked && clicked.closest)
			? (clicked.closest('.rename-btn') || clicked.closest('[data-rename-action]') || clicked)
			: clicked;

		if (el && el.classList && (el.classList.contains('rename-btn') || el.classList.contains('inline-rename-btn'))) {
			e.preventDefault(); e.stopPropagation();
			const id = el.dataset.folderId || el.dataset.fileId || el.dataset.id
				|| el.getAttribute('data-folder-id') || el.getAttribute('data-file-id') || el.getAttribute('data-id');
			if (!id) return;
			const current = this._findNameForElement(el) || '';
			const type = (el.dataset.fileId || el.getAttribute('data-file-id')) ? 'file' : 'folder';
			openRenameModal(type, id, current);
			return;
		}

		if (el && el.dataset && el.dataset.renameAction) {
			e.preventDefault(); e.stopPropagation();
			const type = el.dataset.renameAction;
			const id   = el.dataset.id;
			const current = this._findNameForElement(el) || '';
			openRenameModal(type, id, current);
		}
	}

	_findNameForElement(el) {
		const nameEl = el.closest('.folder-card')?.querySelector('.folder-name, .file-name')
			|| el.closest('.file-card')?.querySelector('.file-name')
			|| el.closest('a')?.querySelector('.folder-name')
			|| null;
		return nameEl ? nameEl.textContent.trim() : '';
	}
}

window.openRenameModal = function(type, id, currentName) {
	document.getElementById('rename-entity-type').value = type;
	document.getElementById('rename-entity-id').value   = id;
	document.getElementById('rename-modal-title').textContent =
		(type === 'file' ? 'Renommer le fichier' : 'Renommer le dossier');
	const input = document.getElementById('rename-input');
	input.value = currentName;
	document.getElementById('rename-error').classList.add('hidden');
	Modal.open('rename-modal');
	setTimeout(() => { input.focus(); input.select(); }, 50);

	// Soumettre avec Entrée
	input.onkeydown = function(e) {
		if (e.key === 'Enter') submitRename();
		if (e.key === 'Escape') Modal.close('rename-modal');
	};
};

window.submitRename = async function() {
	const type = document.getElementById('rename-entity-type').value;
	const id   = document.getElementById('rename-entity-id').value;
	const name = document.getElementById('rename-input').value.trim();
	const errorEl = document.getElementById('rename-error');

	errorEl.classList.add('hidden');

	if (!name) {
		errorEl.textContent = 'Le nom ne peut pas être vide.';
		errorEl.classList.remove('hidden');
		return;
	}
	if (name.length > 255) {
		errorEl.textContent = 'Le nom est trop long (255 caractères max).';
		errorEl.classList.remove('hidden');
		return;
	}
	if (INVALID_CHARS.test(name)) {
		errorEl.textContent = 'Caractères invalides : \\ / : * ? " < > |';
		errorEl.classList.remove('hidden');
		return;
	}

	const btn = document.getElementById('rename-submit-btn');
	btn.disabled = true;

	const isFile = type === 'file';
	const url    = isFile ? `/api/v1/files/${encodeURIComponent(id)}` : `/api/v1/folders/${encodeURIComponent(id)}`;
	const body   = JSON.stringify(isFile ? { originalName: name } : { name });

	try {
		const res = await apiFetch(url, {
			method:  'PATCH',
			headers: { 'Content-Type': 'application/merge-patch+json' },
			body,
		});
		Modal.close('rename-modal');
		if (res.ok) {
			showToast('Renommé ✅', 'success');
			setTimeout(() => window.location.reload(), 800);
		} else {
			const err = await res.json().catch(() => ({}));
			showToast('Erreur : ' + (err.detail || err.message || res.status), 'error');
		}
	} catch (err) {
		Modal.close('rename-modal');
		showToast('Erreur réseau', 'error');
		console.error(err);
	} finally {
		btn.disabled = false;
	}
};

const manager = new RenameManager();
manager.init();

export default manager;
