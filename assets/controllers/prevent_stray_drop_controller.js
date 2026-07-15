import { Controller } from '@hotwired/stimulus';

/* Empêche le navigateur d'ouvrir/naviguer sur un fichier lâché en dehors
 * d'une dropzone applicative (ex. /albums, /gallery, /shares — pages sans
 * zone de drop dédiée). Les pages qui ont leur propre dropzone (explorer,
 * home) gèrent déjà preventDefault() sur dragover/drop ; ce contrôleur reste
 * une garde globale utile ailleurs. */
export default class extends Controller {
    connect() {
        this._prevent = (e) => e.preventDefault();
        window.addEventListener('dragover', this._prevent, true);
        window.addEventListener('drop', this._prevent, true);
    }

    disconnect() {
        window.removeEventListener('dragover', this._prevent, true);
        window.removeEventListener('drop', this._prevent, true);
    }
}
