// assets/photo-lazy-grid.js
// Module réutilisable pour lazy loading + sélection de photos

export function initPhotoLazyGrid({ gridSelector, inputSelector, apiUrl, preselected = [] }) {
    const grid = document.querySelector(gridSelector);
    const input = document.querySelector(inputSelector);
    
    if (!grid || !input) {
        console.error('Grid ou input selector non trouvé');
        return;
    }

    let page = 1;
    let loading = false;
    let allLoaded = false;
    let selected = new Set(preselected);

    function renderPhoto(photo) {
        const figure = document.createElement('figure');
        figure.className = 'glass-card group relative overflow-hidden flex items-center justify-center transition-all duration-300 hover:shadow-purple-400/60 cursor-pointer';
        figure.dataset.photoId = photo.id;
        figure.innerHTML = `<img src="${photo.url}" alt="${photo.title || photo.originalName}" loading="lazy" class="w-full h-full object-cover">`;
        
        if (selected.has(photo.id)) {
            figure.classList.add('ring-4', 'ring-blue-500');
        }
        
        figure.addEventListener('click', () => {
            if (selected.has(photo.id)) {
                selected.delete(photo.id);
                figure.classList.remove('ring-4', 'ring-blue-500');
            } else {
                selected.add(photo.id);
                figure.classList.add('ring-4', 'ring-blue-500');
            }
            input.value = JSON.stringify(Array.from(selected));
        });
        
        return figure;
    }

    async function loadPhotos() {
        if (loading || allLoaded) return;
        loading = true;
        
        try {
            const res = await fetch(`${apiUrl}?page=${page}`);
            if (!res.ok) {
                console.error('Erreur API photos:', res.status);
                return;
            }
            
            const data = await res.json();
            if (!data.photos || data.photos.length === 0) {
                allLoaded = true;
                return;
            }
            
            data.photos.forEach(photo => {
                // Empêche les doublons : ne pas ajouter si déjà présent dans le DOM
                if (!grid.querySelector(`[data-photo-id="${photo.id}"]`)) {
                    grid.appendChild(renderPhoto(photo));
                }
            });
            page++;
        } catch (error) {
            console.error('Erreur lors du chargement des photos:', error);
        } finally {
            loading = false;
        }
    }

    // Infinite scroll
    window.addEventListener('scroll', () => {
        if (allLoaded) return;
        const rect = grid.getBoundingClientRect();
        if (rect.bottom < window.innerHeight + 200) {
            loadPhotos();
        }
    });

    // Initial load
    loadPhotos();
}

