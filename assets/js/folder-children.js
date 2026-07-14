import { apiFetch } from './api.js';

function escapeHtml(str) {
	return String(str)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');
}

const ICON_FILE   = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>';
const ICON_IMAGE  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5z"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
const ICON_VIDEO  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>';
const ICON_MUSIC  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>';
const ICON_FOLDER = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/></svg>';
const ICON_PENCIL = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
const ICON_MOVE   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 10 20 15 15 20"/><path d="M4 4v7a4 4 0 0 0 4 4h12"/></svg>';

function mimeToIcon(mimeType) {
if (!mimeType)                      return ICON_FILE;
if (mimeType.startsWith('image/'))  return ICON_IMAGE;
if (mimeType.startsWith('video/'))  return ICON_VIDEO;
if (mimeType.startsWith('audio/'))  return ICON_MUSIC;
if (mimeType === 'application/pdf') return ICON_FILE;
return ICON_FILE;
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
const icon        = item.isFolder ? ICON_FOLDER : mimeToIcon(item.mimeType);
const moveModalId = `move-modal-${item.isFolder ? 'folder' : 'file'}-${item.id}`;
const btnClass    = item.isFolder ? 'move-folder-btn' : 'move-file-btn';
const safeName     = escapeHtml(item.name);
html += `
<div class="folder-card">
<div class="${item.isFolder ? 'folder-icon' : 'file-icon'}">${icon}</div>
<div class="${item.isFolder ? 'folder-name' : 'file-name'}">${safeName}</div>
${!item.isFolder ? `<div class="file-meta">${item.size ? (item.size / 1024).toFixed(2) + ' KB' : ''} · ${item.createdAt || ''}</div>` : ''}
<div class="folder-actions flex justify-end items-center mt-2 gap-2">
${item.isFolder ? `
<button type="button"
data-testid="rename-folder-${item.id}"
class="rename-btn p-2 rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-950/40 transition-colors"
data-folder-id="${item.id}"
title="Renommer">${ICON_PENCIL}</button>
` : ''}
<button type="button"
data-testid="${btnClass}-${item.id}"
class="move-btn p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition-colors"
title="Déplacer"
onclick="openMoveElementModal('${moveModalId}')">${ICON_MOVE}</button>
</div>
</div>
`;
});
html += '</div>';
} else {
html = `
<div class="folders-grid">
<div class="flex flex-col items-center justify-center py-16 px-4 text-center">
<div class="mb-6 opacity-40 flex justify-center"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/></svg></div>
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
