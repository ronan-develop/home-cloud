// ────────────── MoveModal — Déplacement dossier/fichier ──────────────
window.openGlobalMoveModal = async function(type, id, name) {
	document.getElementById('move-entity-type').value = type;
	document.getElementById('move-entity-id').value   = id;
	document.getElementById('move-modal-title').textContent = 'Déplacer « ' + name + ' »';
	const submitBtn = document.getElementById('move-submit-btn');
	submitBtn.disabled = true;
	const modal = document.getElementById('move-modal');
	modal.classList.remove('hidden');
	modal.classList.add('modal-open');
	const list = document.getElementById('move-target-list');
	list.innerHTML = '<p class="text-white/50 text-sm text-center py-4">Chargement...</p>';
	try {
		const token = await window.HC.getToken();
		const response = await fetch('/api/v1/folders', {
			headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
		});
		if (!response.ok) throw new Error('Erreur chargement dossiers');
		const folders = await response.json();
		list.innerHTML = '';
		if (!folders || folders.length === 0) {
			list.innerHTML = '<p class="text-white/50 text-sm text-center py-4">Aucun dossier disponible</p>';
			return;
		}
		folders
			.filter(f => !(type === 'folder' && f.id === id))
			.forEach(folder => {
				const btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'move-target w-full text-left px-3 py-2 rounded-xl text-white hover:bg-white/10 transition-colors flex items-center gap-2';
				btn.dataset.folderId = folder.id;
				btn.innerHTML = '📁 <span>' + folder.name + '</span>';
				btn.addEventListener('click', () => selectMoveTarget(folder.id, btn));
				list.appendChild(btn);
			});
	} catch (err) {
		list.innerHTML = '<p class="text-red-400 text-sm text-center py-4">Erreur de chargement</p>';
		console.error(err);
	}
};

window.selectMoveTarget = function(folderId, btn) {
	document.querySelectorAll('.move-target').forEach(el => {
		el.classList.remove('bg-blue-500/30', 'ring-1', 'ring-blue-400');
	});
	btn.classList.add('bg-blue-500/30', 'ring-1', 'ring-blue-400');
	document.getElementById('move-entity-id').dataset.targetFolderId = folderId;
	document.getElementById('move-submit-btn').disabled = false;
};

window.submitMove = async function() {
	const type           = document.getElementById('move-entity-type').value;
	const entityId       = document.getElementById('move-entity-id').value;
	const targetFolderId = document.getElementById('move-entity-id').dataset.targetFolderId;
	if (!targetFolderId) return;
	const submitBtn = document.getElementById('move-submit-btn');
	submitBtn.disabled = true;
	try {
		const token = await window.HC.getToken();
		let url, body;
		if (type === 'folder') {
			url  = '/api/v1/folders/' + entityId;
			body = JSON.stringify({ parentId: targetFolderId });
		} else {
			url  = '/api/v1/files/' + entityId;
			body = JSON.stringify({ targetFolderId: targetFolderId });
		}
		const response = await fetch(url, {
			method:  'PATCH',
			headers: {
				'Authorization':  'Bearer ' + token,
				'Content-Type':   'application/merge-patch+json',
			},
			body: body,
		});
		closeModal('move-modal');
		if (response.ok) {
			showToast('Déplacement réussi ✅', 'success');
			setTimeout(() => window.location.reload(), 800);
		} else {
			const err = await response.json().catch(() => ({}));
			showToast('Erreur : ' + (err.detail || err.message || response.status), 'error');
		}
	} catch (err) {
		closeModal('move-modal');
		showToast('Erreur réseau', 'error');
		console.error(err);
	}
};

window.showToast = function(message, type = 'success') {
	let toast = document.getElementById('hc-toast');
	if (!toast) {
		toast = document.createElement('div');
		toast.id = 'hc-toast';
		toast.className = 'fixed bottom-4 right-4 z-[100] px-4 py-3 rounded-2xl text-white text-sm font-medium shadow-xl transition-all duration-300';
		document.body.appendChild(toast);
	}
	toast.textContent = message;
	toast.className = toast.className.replace(/bg-\S+/, '');
	toast.classList.add(type === 'success' ? 'bg-green-500' : 'bg-red-500');
	toast.classList.remove('opacity-0', 'translate-y-4');
	toast.classList.add('opacity-100', 'translate-y-0');
	setTimeout(() => {
		toast.classList.add('opacity-0', 'translate-y-4');
	}, 3000);
};
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
