import { apiFetch } from './api.js';

class RenameManager {
	constructor() {
		this.handleClick = this.handleClick.bind(this);
	}

	init() {
		document.addEventListener('click', this.handleClick);
	}

	async handleClick(e) {
		// debug log
		try { console.debug('RenameManager.handleClick event target:', e.target); } catch (err) {}
		// normalize target so clicks on inner icons/buttons bubble to actionable element
		const clicked = e.target;
		const el = (clicked && clicked.closest) ? (clicked.closest('.rename-btn') || clicked.closest('[data-rename-action]') || clicked) : clicked;
		try { console.debug('RenameManager resolved el:', el); } catch (err) {}

		// folder rename buttons (class-based)
		if (el && el.classList && el.classList.contains('rename-btn')) {
			e.preventDefault(); e.stopPropagation();
			const id = el.dataset.folderId || el.getAttribute('data-folder-id');
			try { console.debug('RenameManager folder id:', id); } catch (err) {}
			if (!id) return;
			const current = this._findNameForElement(el) || '';
			this.renameFolderPrompt(id, current);
			return;
		}

		// inline rename buttons (data-rename-action)
		if (el && el.dataset && el.dataset.renameAction) {
			e.preventDefault(); e.stopPropagation();
			const type = el.dataset.renameAction; // 'folder' or 'file'
			const id = el.dataset.id;
			try { console.debug('RenameManager inline action:', type, id); } catch (err) {}
			const current = this._findNameForElement(el) || '';
			if (type === 'folder') this.renameFolderPrompt(id, current);
			if (type === 'file') this.renameFilePrompt(id, current);
		}
	}

	_findNameForElement(el) {
		// try to find nearby element with class folder-name or file-name
		const nameEl = el.closest('.folder-card')?.querySelector('.folder-name')
			|| el.closest('.file-card')?.querySelector('.file-name')
			|| el.closest('a')?.querySelector('.folder-name')
			|| null;
		return nameEl ? nameEl.textContent.trim() : '';
	}

	async renameFolderPrompt(id, currentName) {
		const newName = prompt('Renommer le dossier', currentName);
		if (newName === null) return;
		const trimmed = newName.trim();
		if (!trimmed) { alert('Le nom ne peut pas être vide'); return; }
		if (trimmed.length > 255) { alert('Le nom est trop long (255 caractères max)'); return; }
		if (!/^[^\\/:*?"<>|]+$/u.test(trimmed)) { alert('Caractères invalides dans le nom'); return; }
		try {
			const res = await apiFetch(`/api/v1/folders/${encodeURIComponent(id)}`, {
				method: 'PATCH',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ name: trimmed }),
			});
			if (!res.ok) {
				const txt = await res.text();
				throw new Error(`${res.status}: ${txt}`);
			}
			// simple reload for now; UI will be improved later
			window.location.reload();
		} catch (err) {
			console.error('Rename error', err);
			alert('Erreur lors du renommage: ' + err.message);
		}
	}

	async renameFilePrompt(id, currentName) {
		const newName = prompt('Renommer le fichier', currentName);
		if (newName === null) return;
		const trimmed = newName.trim();
		if (!trimmed) { alert('Le nom ne peut pas être vide'); return; }
		if (trimmed.length > 255) { alert('Le nom est trop long (255 caractères max)'); return; }
		try {
			const res = await apiFetch(`/api/v1/files/${encodeURIComponent(id)}`, {
				method: 'PATCH',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ name: trimmed }),
			});
			if (!res.ok) {
				const txt = await res.text();
				throw new Error(`${res.status}: ${txt}`);
			}
			window.location.reload();
		} catch (err) {
			console.error('Rename file error', err);
			alert('Erreur lors du renommage du fichier: ' + err.message);
		}
	}
}

const manager = new RenameManager();
manager.init();

export default manager;
