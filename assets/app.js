// Fonction globale pour ouvrir une modal de déplacement (file ou folder)
window.openMoveElementModal = function(modalId) {
	const modal = document.getElementById(modalId);
	if (!modal) return;
	modal.classList.remove('hidden');
	modal.classList.add('modal-open');
	// Optionnel : focus sur la modal
	modal.focus();
}
import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// Fonction globale pour fermer une modal
window.closeModal = function(modalId) {
	const modal = document.getElementById(modalId);
	if (!modal) return;
	modal.classList.add('hidden');
	modal.classList.remove('modal-open');
	// Optionnel : focus sur le body après fermeture
	document.body.focus();
}

// Fonction pour charger les enfants d'un dossier en AJAX
window.loadFolderChildren = async function(folderId) {
	try {
		const token = await window.HC.getToken();
		const response = await fetch(`/api/v1/folders/${folderId}/children`, {
			headers: { 'Authorization': `Bearer ${token}` }
		});

		if (!response.ok) {
			console.error('Erreur lors du chargement des enfants:', response.status);
			return;
		}

		const data = await response.json();
		const fileList = document.querySelector('[data-testid="file-list"]');
		
		if (!fileList) return;

		// Construit le HTML pour chaque item
		let html = '';
		if (data.items && data.items.length > 0) {
			html = '<div class="folders-grid">';
			data.items.forEach(item => {
				const iconClass = item.isFolder ? '📁' : 
					(item.mimeType?.startsWith('image/') ? '🖼️' :
					 item.mimeType?.startsWith('video/') ? '🎬' :
					 item.mimeType?.startsWith('audio/') ? '🎵' :
					 item.mimeType === 'application/pdf' ? '📄' : '📎');

				const moveModalId = item.isFolder ? `move-modal-folder-${item.id}` : `move-modal-file-${item.id}`;
				const btnClass = item.isFolder ? 'move-folder-btn' : 'move-file-btn';

				html += `
					<div class="folder-card">
						<div class="${item.isFolder ? 'folder-icon' : 'file-icon'}">${iconClass}</div>
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

		// Met à jour la grille
		const gridContainer = fileList.querySelector('.folders-grid')?.parentElement || fileList;
		gridContainer.innerHTML = html.replace('<div class="folders-grid">', '');
		if (!gridContainer.querySelector('.folders-grid')) {
			gridContainer.innerHTML = html;
		}
	} catch (error) {
		console.error('Erreur lors du chargement des enfants:', error);
	}
};
