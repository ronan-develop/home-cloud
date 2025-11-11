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
    // On force les IDs en string pour éviter les problèmes de type
    let selected = new Set(preselected.map(String));

    function renderPhoto(photo) {
        const figure = document.createElement('figure');
        figure.className = 'glass-card group relative overflow-hidden flex items-center justify-center transition-all duration-300 hover:shadow-purple-400/60 cursor-pointer';
        figure.dataset.photoId = String(photo.id);
        figure.innerHTML = `
            <span class="photo-select-circle" style="position:absolute;top:0.5rem;left:0.5rem;width:25px;height:25px;z-index:200;display:flex;align-items:center;justify-content:center;">
                <!-- Cercle SVG toujours visible, fond semi-transparent et contour gris clair -->
                <svg viewBox="0 0 25 25" style="width:25px;height:25px;" class="z-[201] select-svg pointer-events-none">
                    <circle cx="12.5" cy="12.5" r="11" fill="#fff" fill-opacity="0.7" stroke="#e5e7eb" stroke-width="2" />
                </svg>
                <!-- Coche SVG superposée, masquée par défaut, couleur plus vive -->
                <svg viewBox="0 0 24 24" style="width:25px;height:25px;" class="z-[202] check-svg absolute top-0 left-0 pointer-events-none">
                    <path d="M7 13L12 18L19 8" stroke="#535454ff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" class="check-path transition-all duration-200" style="opacity:0;" />
                </svg>
            </span>
            <img src="${photo.url}" alt="${photo.title || photo.originalName}" loading="lazy" class="w-full h-full object-cover">
        `;

        // Gestion de l'état sélectionné
        const checkSvg = figure.querySelector('.check-svg');
        const checkPath = checkSvg.querySelector('.check-path');
        // Affiche la coche si sélectionné, sinon seulement au hover (CSS)
        const updateVisual = () => {
            if (selected.has(String(photo.id))) {
                figure.classList.add('ring-4', 'ring-blue-500');
                checkPath.style.opacity = '1';
            } else {
                figure.classList.remove('ring-4', 'ring-blue-500');
                checkPath.style.opacity = '0';
            }
        };
        updateVisual();

        // Affichage de la coche au hover (CSS only)
        figure.addEventListener('mouseenter', () => {
            if (!selected.has(String(photo.id))) {
                checkPath.style.opacity = '0.5';
            }
        });
        figure.addEventListener('mouseleave', () => {
            if (!selected.has(String(photo.id))) {
                checkPath.style.opacity = '0';
            }
        });

        // Clic sur la vignette ou le cercle
        figure.addEventListener('click', (e) => {
            console.log('Clic sur la vignette', photo.id, e.target);
            // Ne pas sélectionner si clic sur un lien ou bouton interne
            if (e.target.closest('a,button,[role="button"]')) return;
            const idStr = String(photo.id);
            if (selected.has(idStr)) {
                selected.delete(idStr);
            } else {
                selected.add(idStr);
            }
            input.value = JSON.stringify(Array.from(selected));
            updateVisual();
        });
        // Accessibilité : clic sur le cercle
        figure.querySelector('.photo-select-circle').addEventListener('click', (e) => {
            e.stopPropagation();
            const idStr = String(photo.id);
            if (selected.has(idStr)) {
                selected.delete(idStr);
            } else {
                selected.add(idStr);
            }
            input.value = JSON.stringify(Array.from(selected));
            updateVisual();
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

