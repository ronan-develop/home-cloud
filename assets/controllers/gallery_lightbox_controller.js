import { Controller } from '@hotwired/stimulus';
import Slideshow from './Slideshow.js';

/* Lightbox de la galerie médias : ouvre le média en plein écran au clic
 * sur une vignette, navigation précédent/suivant au clavier et via boutons.
 * Le diaporama (auto-avance, pause/lecture) est délégué à Slideshow. */
export default class extends Controller {
    static targets = ['img', 'prev', 'next', 'play'];

    connect() {
        this.links = Array.from(document.querySelectorAll('[data-lightbox]'));
        this.srcs = this.links.map((el) => el.getAttribute('data-full-src'));
        this.current = -1;
        this.slideshow = new Slideshow(() => this.next());

        this.links.forEach((el, index) => {
            el.addEventListener('click', (e) => {
                if (!this.srcs[index]) return;
                e.preventDefault();
                this.show(index);
            });
        });

        this.boundKeydown = this.onKeydown.bind(this);
        document.addEventListener('keydown', this.boundKeydown);
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundKeydown);
        this.slideshow.stop();
    }

    show(index) {
        if (index < 0 || index >= this.srcs.length) return;
        this.current = index;
        this.imgTarget.src = this.srcs[this.current];
        this.element.style.display = 'flex';
        this.prevTarget.style.visibility = this.current > 0 ? 'visible' : 'hidden';
        this.nextTarget.style.visibility = this.current < this.srcs.length - 1 ? 'visible' : 'hidden';
    }

    prev() {
        this.show(this.current - 1);
    }

    next() {
        this.show(this.current === this.srcs.length - 1 ? 0 : this.current + 1);
    }

    close() {
        this.element.style.display = 'none';
        this.slideshow.stop();
        this.updatePlayTarget();
    }

    togglePlay() {
        this.slideshow.toggle();
        this.updatePlayTarget();
    }

    updatePlayTarget() {
        if (this.hasPlayTarget) {
            this.playTarget.setAttribute('aria-pressed', String(this.slideshow.isPlaying));
        }
    }

    onKeydown(e) {
        if (this.element.style.display !== 'flex') return;
        if (e.key === 'ArrowLeft') this.prev();
        if (e.key === 'ArrowRight') this.next();
        if (e.key === 'Escape') this.close();
        if (e.key === ' ') {
            e.preventDefault();
            this.togglePlay();
        }
    }
}
