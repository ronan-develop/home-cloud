import { Controller } from '@hotwired/stimulus';

/* Drawer mobile (sidebar) + bascule du thème clair/sombre — posé sur le
 * conteneur racine de web/layout.html.twig. Tous les boutons l'appellent via
 * data-action="click->drawer-theme#..." (aucun onclick="" global). */
export default class extends Controller {
    static targets = ['sidebar', 'overlay', 'sunIcon', 'moonIcon'];

    connect() {
        this._onKeydown = (e) => {
            if (e.key === 'Escape') this.closeDrawer();
        };
        document.addEventListener('keydown', this._onKeydown);

        this.applyThemeIcons();
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKeydown);
    }

    openDrawer() {
        this.sidebarTarget.classList.add('open');
        this.overlayTarget.classList.add('open');
    }

    closeDrawer() {
        this.sidebarTarget.classList.remove('open');
        this.overlayTarget.classList.remove('open');
    }

    toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = current === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('hc-theme', next);
        this.applyThemeIcons();
    }

    applyThemeIcons() {
        const theme = document.documentElement.getAttribute('data-theme') || 'light';
        this.sunIconTarget.classList.toggle('hidden', theme === 'dark');
        this.moonIconTarget.classList.toggle('hidden', theme !== 'dark');
    }
}
