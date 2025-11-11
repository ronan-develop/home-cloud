// Contrôleur universel pour lazy grid (wizard album ou galerie)
import { initPhotoLazyGrid } from '../photo-lazy-grid.js';

document.addEventListener('DOMContentLoaded', () => {
    const photoGrid = document.getElementById('photo-grid');
    if (!photoGrid) return;
    const apiUrl = photoGrid.dataset.apiUrl || '';
    const preselected = photoGrid.dataset.preselected ? JSON.parse(photoGrid.dataset.preselected) : [];
    const input = document.getElementById('selected_photos');
    if (!input || !apiUrl) return;
    initPhotoLazyGrid({
        gridSelector: '#photo-grid',
        inputSelector: '#selected_photos',
        apiUrl,
        preselected
    });
});

