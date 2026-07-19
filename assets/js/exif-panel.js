/**
 * Panneau EXIF du lightbox (#268) — construction pure et testable du contenu à
 * partir des attributs data-* d'une vignette photo. Aucune dépendance au DOM du
 * contrôleur : le rendu se teste isolément.
 */

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, (m) => map[m]);
}

/**
 * Réduit un dataset de vignette aux réglages présents (champs vides ignorés).
 *
 * @param {Object} d - { takenAt, camera, aperture, shutter, iso, focal, lens }
 * @returns {Array<{label: string, value: string}>}
 */
export function buildExifItems(d = {}) {
    const items = [];
    if (d.takenAt) items.push({ label: 'Prise le', value: d.takenAt });
    if (d.camera) items.push({ label: 'Appareil', value: d.camera });
    if (d.aperture) items.push({ label: 'Ouverture', value: `f/${d.aperture}` });
    if (d.shutter) items.push({ label: 'Vitesse', value: `${d.shutter}s` });
    if (d.iso) items.push({ label: 'ISO', value: String(d.iso) });
    if (d.focal) items.push({ label: 'Focale', value: `${d.focal} mm` });
    if (d.lens) items.push({ label: 'Objectif', value: d.lens });
    return items;
}

/**
 * Rend le panneau EXIF en HTML, ou une chaîne vide s'il n'y a rien à montrer.
 *
 * @param {Object} d - dataset de la vignette (data-* déjà lus)
 * @returns {string}
 */
export function buildExifPanelHtml(d = {}) {
    const items = buildExifItems(d);
    let html = items
        .map(
            (i) =>
                `<span class="hc-exif-item"><span class="hc-exif-label">${escapeHtml(i.label)}</span> ${escapeHtml(i.value)}</span>`,
        )
        .join('');

    if (d.gpsLat && d.gpsLon) {
        const lat = encodeURIComponent(d.gpsLat);
        const lon = encodeURIComponent(d.gpsLon);
        const url = `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}#map=15/${lat}/${lon}`;
        html += `<a class="hc-exif-item hc-exif-gps" href="${url}" target="_blank" rel="noopener">📍 Voir sur la carte</a>`;
    }

    return html;
}
