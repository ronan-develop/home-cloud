import { apiFetch } from './api.js';

function mimeToIcon(mimeType) {
	if (!mimeType)                      return '📎';
	if (mimeType.startsWith('image/'))  return '🖼️';
	if (mimeType.startsWith('video/'))  return '🎬';
	if (mimeType.startsWith('audio/'))  return '🎵';
	if (mimeType === 'application/pdf') return '📄';
	return '📎';
}

window.loadFolderChildren = async function(folderId) {
	try {
		const response = await apiFetch(`/api/v1/folders/${folderId}/children`);
		if (!response.ok) { console.error('Erreur chargement enfants:', response.status); return; }

		const data     = await response.json();
		const fileList = document.querySelector('[data-testid="file-list"]');
		if (!fileList) return;

		let html = '';
		if (data.items?.length) {
			html = '<div class="folders-grid">';
			data.items.forEach(item => {
				const icon        = item.isFolder ? '📁' : mimeToIcon(item.mimeType);
				const moveModalId = `move-modal-${item.isFolder ? 'folder' : 'file'}-${item.id}`;
				const btnClass    = item.isFolder ? 'move-folder-btn' : 'move-file-btn';
				html += `
					<div class="folder-card">
						<div class="${item.isFolder ? 'folder-icon' : 'file-icon'}">${icon}</div>
						<div class="${item.isFolder ? 'folder-name' : 'file-name'}">${item.name}</div>
						${!item.isFolder ? `<div class="file-meta">${item.size ? (item.size / 1024).toFixed(2) + ' KB' : ''} · ${item.createdAt || ''}</div>` : ''}
						<div class="folder-actions flex justify-end items-center mt-2 gap-2">
							<button type="button"
									data-testid="${btnClass}-${item.id}"
									class="move-btn p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition-colors"
									title="Déplacer"
									onclick="openMoveElementModal('${moveModalId}')">↪️</button>
						</div>
					</div>
				`;
			});
			html += '</div>';
		} else {
			html = `
				<div class="folders-grid">
					<div class="flex flex-col items-center justify-center py-16 px-4 text-center">
						<div class="mb-6 text-6xl opacity-40"><span style="font-size:3rem;opacity:0.5;">📂</span></div>
						<h2 class="text-2xl font-semibold text-white mb-2">Aucun fichier ni dossier</h2>
						<p class="text-white/60 mb-6">Glissez un fichier ou créez un dossier pour commencer.</p>
					</div>
				</div>
			`;
		}

		const gridContainer = fileList.querySelector('.folders-grid')?.parentElement || fileList;
		gridContainer.innerHTML = html.replace('<div class="folders-grid">', '');
		if (!gridContainer.querySelector('.folders-grid')) gridContainer.innerHTML = html;
	} catch (err) {
		console.error('Erreur chargement enfants:', err);
	}
};
