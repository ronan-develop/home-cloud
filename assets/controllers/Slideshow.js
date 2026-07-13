const DEFAULT_INTERVAL_MS = 4000;

/**
 * Minuteur d'auto-avance pour un diaporama. Ne connaît rien du DOM ni de
 * Stimulus : reçoit juste une fonction "advance" à appeler à intervalle
 * régulier. Séparé du contrôleur pour rester testable isolément et éviter
 * un contrôleur monolithique.
 */
export default class Slideshow {
    constructor(onAdvance, intervalMs = DEFAULT_INTERVAL_MS) {
        this.onAdvance = onAdvance;
        this.intervalMs = intervalMs;
        this.timer = null;
    }

    get isPlaying() {
        return this.timer !== null;
    }

    start() {
        if (this.isPlaying) return;
        this.timer = setInterval(this.onAdvance, this.intervalMs);
    }

    stop() {
        if (!this.isPlaying) return;
        clearInterval(this.timer);
        this.timer = null;
    }

    toggle() {
        this.isPlaying ? this.stop() : this.start();
    }
}
