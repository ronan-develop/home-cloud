import { Controller } from '@hotwired/stimulus';

/* Cloche topbar : dropdown de la pile unifiée de notifications (messages
 * directs + changelog, #373). Modèle : new_menu_controller.js. */
export default class extends Controller {
    static targets = ['button', 'panel', 'badge', 'item'];

    connect() {
        this._onDocumentClick = (e) => {
            if (!this.buttonTarget.contains(e.target) && !this.panelTarget.contains(e.target)) {
                this.closePanel();
            }
        };
        document.addEventListener('click', this._onDocumentClick);
    }

    disconnect() {
        document.removeEventListener('click', this._onDocumentClick);
    }

    toggle(event) {
        event.stopPropagation();
        this.isOpen() ? this.closePanel() : this.openPanel();
    }

    openPanel() {
        const rect = this.buttonTarget.getBoundingClientRect();
        this.panelTarget.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        this.panelTarget.style.right = (window.innerWidth - rect.right) + 'px';
        this.panelTarget.style.display = 'block';
    }

    closePanel() {
        this.panelTarget.style.display = 'none';
    }

    isOpen() {
        return this.panelTarget.style.display === 'block';
    }

    // Une entrée par type de notification : chacune sait comment se marquer
    // lue (requête réseau) et si le clic doit être annulé (pas de navigation
    // pour un message direct, qui n'a pas de page de détail). Ajouter un
    // futur type de notification n'implique plus de faire grossir un
    // if/else dans markRead(), seulement d'ajouter une entrée ici.
    static markReadStrategies = {
        direct_message: {
            // Le lien d'un message direct ne pointe vers aucune page réelle
            // (pas de vue de détail) — seule l'action de lecture est utile ici.
            preventNavigation: true,
            markAsRead: (event) => fetch(`/direct-messages/${event.params.id}/read`, { method: 'POST' }),
        },
        changelog: {
            // target="_blank" ouvre la PR GitHub dans un nouvel onglet — le
            // marquage se joue en fond dans l'onglet d'origine, laissant le
            // temps de voir l'animation de retrait sans revenir sur la page.
            preventNavigation: false,
            markAsRead: () => fetch('/changelog/mark-viewed', { method: 'POST' }),
        },
    };

    markRead(event) {
        const item = event.currentTarget;
        const strategy = this.constructor.markReadStrategies[event.params.type];

        // target="_blank" (entrée changelog) ouvre un nouvel onglet sans
        // jamais faire remonter de "click" sur document dans l'onglet
        // d'origine — _onDocumentClick n'a donc jamais l'occasion de fermer
        // le panel, qui reste display:block indéfiniment (position:fixed,
        // par-dessus la cloche elle-même) tant qu'on ne le referme pas ici
        // explicitement. Bug réel signalé en prod le 2026-09-13.
        this.closePanel();

        if (strategy.preventNavigation) {
            event.preventDefault();
        }

        // lastChangelogViewedAt reste global côté serveur (met à jour le
        // badge de comptage), mais l'affichage ne retire que l'entrée
        // cliquée : puisqu'un clic redirige de toute façon vers GitHub, il
        // n'y a pas besoin de vider les autres entrées non lues du panel
        // pour rester cohérent avec cet état global (#450 — bug réel
        // signalé : lire une entrée changelog vidait tout le panel).
        this._removeAfter(strategy.markAsRead(event), [item]);
        this._decrementBadge();
    }

    // #533 : "Tout marquer comme lu" — orchestre les deux mécanismes de
    // lecture déjà en place (lastChangelogViewedAt + DirectMessage.readAt)
    // en un seul appel, puis vide le panel entier d'un coup plutôt qu'un
    // item à la fois (_removeAfter accepte déjà une liste d'items).
    markAllRead(event) {
        event.stopPropagation();

        const items = [...this.itemTargets];
        this._removeAfter(fetch('/notifications/mark-all-read', { method: 'POST' }), items);
        if (this.hasBadgeTarget) {
            this.badgeTarget.remove();
        }
    }

    // La pastille (#468) est rendue une seule fois côté serveur au
    // chargement de la page (NotificationDropdown.html.twig) — sans ce
    // décrément, elle restait bloquée à sa valeur initiale jusqu'au
    // prochain rechargement, incohérente avec le retrait visuel de
    // l'entrée du panel.
    _decrementBadge() {
        if (!this.hasBadgeTarget) {
            return;
        }

        const remaining = Math.max(0, parseInt(this.badgeTarget.textContent, 10) - 1);
        if (remaining === 0) {
            this.badgeTarget.remove();
        } else {
            this.badgeTarget.textContent = String(remaining);
        }
    }

    _removeAfter(fetchPromise, items) {
        fetchPromise
            .then(() => {
                items.forEach((item) => {
                    item.removeAttribute('data-action');
                    item.classList.add('hc-notif-item--removing');
                    // Filet de sécurité : un navigateur suspend les transitions
                    // CSS d'un onglet en arrière-plan (cas target="_blank" du
                    // changelog) — transitionend ne se déclenche alors jamais
                    // tant qu'on reste sur le nouvel onglet, laissant l'item
                    // bloqué au DOM indéfiniment. Le timeout couvre ce cas
                    // sans changer le comportement normal (transition ~0.2s).
                    let removed = false;
                    const remove = () => {
                        if (removed) return;
                        removed = true;
                        item.remove();
                    };
                    item.addEventListener('transitionend', remove, { once: true });
                    setTimeout(remove, 1000);
                });
            })
            .catch(() => {});
    }
}
