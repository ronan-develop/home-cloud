import './photo-lazy-grid.js';
import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

import './styles/main.css';
import './styles/custom-gallery.css';

// Contrôleurs et initialiseurs
import './controllers/album-photos-controller.js';
document.addEventListener('DOMContentLoaded', () => {
	const html = document.documentElement;
	const btn = document.getElementById('dark-mode-toggle');
	const iconDark = document.getElementById('icon-dark');
	const iconLight = document.getElementById('icon-light');
	if (!btn || !iconDark || !iconLight) return;

	// Init: applique le mode selon localStorage ou préférence système
	const userPref = localStorage.getItem('darkMode');
	const systemPref = window.matchMedia('(prefers-color-scheme: dark)').matches;
	if (userPref === 'dark' || (!userPref && systemPref)) {
		html.classList.add('dark');
		iconDark.classList.remove('hidden');
		iconLight.classList.add('hidden');
	} else {
		html.classList.remove('dark');
		iconDark.classList.add('hidden');
		iconLight.classList.remove('hidden');
	}

	btn.addEventListener('click', () => {
		const isDark = html.classList.toggle('dark');
		if (isDark) {
			localStorage.setItem('darkMode', 'dark');
			iconDark.classList.remove('hidden');
			iconLight.classList.add('hidden');
		} else {
			localStorage.setItem('darkMode', 'light');
			iconDark.classList.add('hidden');
			iconLight.classList.remove('hidden');
		}
	});
});
