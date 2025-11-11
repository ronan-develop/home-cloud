// assets/controllers/album-photos-controller.js
import { initPhotoLazyGrid } from '../photo-lazy-grid.js';

// Auto-init sur les éléments avec data-controller="album-photos"
document.addEventListener('DOMContentLoaded', () => {
    const photoGrid = document.getElementById('photo-grid');
    const selectedInput = document.getElementById('selected_photos');
    const apiUrl = photoGrid?.dataset.apiUrl || '';
    const preselected = photoGrid?.dataset.preselected ? JSON.parse(photoGrid.dataset.preselected) : [];

    if (photoGrid && selectedInput && apiUrl) {
        initPhotoLazyGrid({
            gridSelector: '#photo-grid',
            inputSelector: '#selected_photos',
            apiUrl: apiUrl,
            preselected: preselected
        });
    }
});

