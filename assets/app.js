import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// Fonction globale pour fermer une modal
window.closeModal = function(modalId) {
	const modal = document.getElementById(modalId);
	if (!modal) return;
	modal.classList.remove('modal-open');
	modal.classList.add('hidden');
	// Optionnel : focus sur le body après fermeture
	document.body.focus();
}
