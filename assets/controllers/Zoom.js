const MIN_SCALE = 1;
const MAX_SCALE = 3;
const STEP = 1;

/**
 * État de zoom d'une image (échelle + point d'origine du zoom). Ne connaît
 * rien du DOM ni de Stimulus : reçoit juste les coordonnées du clic (en %).
 * Séparé du contrôleur pour rester testable isolément et éviter un
 * contrôleur monolithique (même approche que Slideshow.js).
 *
 * Zoom par paliers : chaque clic avance d'un cran (1x → 2x → 3x), un clic
 * au-delà du plafond revient à l'état non zoomé. L'origine reste celle du
 * premier clic tant qu'on progresse dans les paliers — seul un retour à 1x
 * (ou reset()) la recentre, pour éviter un "saut" visuel à chaque clic.
 */
export default class Zoom {
    constructor() {
        this.scale = MIN_SCALE;
        this.originX = 50;
        this.originY = 50;
    }

    get isZoomed() {
        return this.scale > MIN_SCALE;
    }

    /* Palier maximum atteint : le prochain clic réinitialisera au lieu de zoomer davantage. */
    get isAtMaxZoom() {
        return this.scale >= MAX_SCALE;
    }

    /* originX/originY en % (0-100), position du clic relative à l'image. */
    toggleAt(originX, originY) {
        if (!this.isZoomed) {
            this.originX = originX;
            this.originY = originY;
        }

        this.scale += STEP;

        if (this.scale > MAX_SCALE) {
            this.reset();
        }
    }

    reset() {
        this.scale = MIN_SCALE;
        this.originX = 50;
        this.originY = 50;
    }
}
