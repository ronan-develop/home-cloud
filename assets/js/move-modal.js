import { apiFetch } from './api.js';
import { Modal } from './modal.js';

function makeFolderRadio({ value, label, separator = false }) {
	const el = document.createElement('label');
	el.className = 'move-target flex items-center gap-3 px-3 py-2 rounded-xl cursor-pointer hover:bg-white/10 transition-colors'
		+ (separator ? ' border-b border-white/10 mb-1' : '');
	el.innerHTML = `
		<input type="radio" name="move-target-folder" value="${value}"
		       class="w-4 h-4 accent-blue-400 cursor-pointer flex-shrink-0">
		<span class="text-white flex items-center gap-2">${label}</span>
	`;
	el.querySelector('input').addEventListener('change', () => {
		document.getElementById('move-submit-btn').disabled = false;
	});
	return el;
}

window.openGlobalMoveModal = async function(type, id, name) {
	document.getElementById('move-entity-type').value = type;
	document.getElementById('move-entity-id').value   = id;
	document.getElementById('move-modal-title').textContent = 'Déplacer « ' + name + ' »';
	document.getElementById('move-submit-btn').disabled = true;

	Modal.open('move-modal');

	const list = document.getElementById('move-target-list');
	list.innerHTML = '<p class="text-white/50 text-sm text-center py-4">Chargement...</p>';

	try {
		const response = await apiFetch('/api/v1/folders');
		if (!response.ok) throw new Error('Erreur chargement dossiers');
		const folders = await response.json();

		list.innerHTML = '';
		const rootText = type === 'file' ? '📤 Uploads (dossier par défaut)' : '🏠 Racine (niveau principal)';
		list.appendChild(makeFolderRadio({ value: '__root__', label: rootText, separator: true }));

		if (!folders?.length) {
			list.innerHTML = '<p class="text-white/50 text-sm text-center py-4">Aucun dossier disponible</p>';
			return;
		}
		folders
			.filter(f => !(type === 'folder' && f.id === id))
			.forEach(f => list.appendChild(makeFolderRadio({ value: f.id, label: '📁 ' + f.name })));
	} catch (err) {
		list.innerHTML = '<p class="text-red-400 text-sm text-center py-4">Erreur de chargement</p>';
		console.error(err);
	}
};

window.submitMove = async function() {
	const type     = document.getElementById('move-entity-type').value;
	const entityId = document.getElementById('move-entity-id').value;
	const selected = document.querySelector('input[name="move-target-folder"]:checked');
	if (!selected) return;

	const targetFolderId = selected.value === '__root__' ? null : selected.value;
	document.getElementById('move-submit-btn').disabled = true;

	const isFolder = type === 'folder';
	const url  = isFolder ? '/api/v1/folders/' + entityId : '/api/v1/files/' + entityId;
	const body = JSON.stringify(isFolder ? { parentId: targetFolderId } : { targetFolderId });

	try {
		const response = await apiFetch(url, {
			method:  'PATCH',
			headers: { 'Content-Type': 'application/merge-patch+json' },
			body,
		});
		Modal.close('move-modal');
		if (response.ok) {
			showToast('Déplacement réussi ✅', 'success');
			setTimeout(() => window.location.reload(), 800);
		} else {
			const err = await response.json().catch(() => ({}));
			showToast('Erreur : ' + (err.detail || err.message || response.status), 'error');
		}
	} catch (err) {
		Modal.close('move-modal');
		showToast('Erreur réseau', 'error');
		console.error(err);
	}
};

// Backward-compatible helper: accept calls from older code that pass a modal id like `move-modal-file-<id>`
window.openMoveElementModal = function(modalId) {
	try {
		const m = String(modalId).match(/^move-modal-(file|folder)-(.+)$/);
		if (m) {
			const type = m[1];
			const id = m[2];
			// try to derive a readable name from the DOM
			let name = '';
			const btn = document.querySelector(`[data-testid="move-${type}-btn-${id}"]`) || document.querySelector(`[data-testid="move-${type}-${id}"]`);
			if (btn) {
				const card = btn.closest('.folder-card') || btn.closest('.file-card') || btn.parentElement;
				name = (card && (card.querySelector('.folder-name') || card.querySelector('.file-name')))?.textContent?.trim() || '';
			}
			if (typeof window.openGlobalMoveModal === 'function') {
				return window.openGlobalMoveModal(type, id, name);
			}
		}
		// fallback to simple modal open
		if (typeof Modal !== 'undefined' && typeof Modal.open === 'function') Modal.open(modalId);
	} catch (err) {
		console.error('openMoveElementModal helper error', err);
	}
};
