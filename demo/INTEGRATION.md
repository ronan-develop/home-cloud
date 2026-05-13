# HomeCloud - Interface Tailwind + Vanilla JS

## Structure créée

```
assets/
├── css/
│   └── homecloud.css          # Design tokens + Tailwind layers
├── js/
│   ├── app.js                 # App shell, navigation, modales
│   └── icons.js               # SVG icons

templates/app/
└── index.html.twig            # Layout principal (login + app)
```

## Installation

1. **Vérifiez que Tailwind est configuré** (déjà fait selon vous):
```bash
npm install -D tailwindcss postcss autoprefixer
```

2. **Importez le CSS dans votre point d'entrée Tailwind** (`tailwind.config.js`):
```js
module.exports = {
  content: ['./templates/**/*.twig', './assets/**/*.{js,jsx}'],
  theme: { /* ... */ }
}
```

3. **Importez les assets dans Twig** (`base.html.twig` ou votre layout):
```twig
<link rel="stylesheet" href="{{ asset('css/homecloud.css') }}">
<script src="{{ asset('js/icons.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>
```

## Architecture

### Design System
- **Variables CSS custom** : `--hc-accent`, `--hc-surface`, `--hc-border`, etc.
- **Mode clair/sombre** : toggle via `[data-theme="dark"]`
- **Composants Tailwind** : `.glass`, `.btn-primary`, `.input` (composés avec CSS custom)
- **Icônes SVG** : générées via JS (window.Icons.*)

### Navigation
- Cliquez sur un item sidebar → `data-nav-item` déclenche `homecloud.goto(route)`
- Pages cachées/affichées via `[data-page]` + classe `.hidden`

### Modales
- Structure : `<div data-modal id="modal-name">`
- Boutons : `data-modal-close` pour fermer
- Appel : `homecloud.openModal('modal-name')`

### Drag-drop
- Overlay activé au drag, caché au drop
- Prêt pour connecter un backend upload

## Prochaines étapes

1. **Connecter au backend Symfony** :
   - Routes pour chaque page (login, files, gallery, etc.)
   - Fetch API pour charger les données réelles
   - Token CSRF pour les forms

2. **Créer les pages manquantes** :
   - `files.html.twig` avec table + liste fichiers
   - `gallery.html.twig` avec galerie photos
   - `shares.html.twig` avec partages
   - `settings.html.twig` avec profil + options

3. **JavaScript fonctionnel** :
   - Upload fichiers (drag-drop)
   - Modales : partage, preview, settings
   - Pagination, filtering, tri

4. **Responsive** :
   - Sidebar collapsible mobile
   - Drawer au lieu de sidebar sur petit écran
   - Bottom tab bar sur mobile (optionnel)

## Fichiers prêts à utiliser

Vous pouvez servir `templates/app/index.html.twig` directement :
```php
// src/Controller/AppController.php
#[Route('/')]
public function index(): Response {
    return $this->render('app/index.html.twig');
}
```

## Conseils d'intégration

- **Pas de dépendances lourdes** : vanilla JS + Tailwind uniquement
- **Facile à étendre** : ajoutez des pages, des modales, des composants Tailwind
- **Séparez par feature** : créez `assets/js/pages/files.js`, `assets/js/pages/gallery.js`, etc.
- **Réutilisez les CSS** : tous les composants `.glass`, `.btn-*` sont dans `homecloud.css`

---

Avez-vous besoin que je :
1. Crée les pages détaillées (files, gallery, settings) ?
2. Ajoute du JS fonctionnel (upload, modales, filtres) ?
3. Montre comment connecter au backend Symfony ?
4. Optimise pour mobile (responsive design) ?
