import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';


console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// --- Patch fix/modal-close ---
window.closeModal = function(id) {
	// Ferme la modal par son id (ex: 'upload-modal')
	const modal = document.getElementById(id);
	if (modal) {
		modal.classList.remove('modal-open');
		modal.classList.add('hidden');
		// Optionnel : ajouter modal-closed si tu utilises cette classe
		modal.classList.add('modal-closed');
		// Optionnel : retirer l'overlay si présent
		const overlay = document.querySelector('.modal-overlay, .backdrop');
		if (overlay) overlay.classList.add('hidden');
	}
	document.dispatchEvent(new CustomEvent('modal:closed'));
};
