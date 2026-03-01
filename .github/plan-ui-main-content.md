# 📐 Plan UI HomeCloud — Zone principale aérée

## Structure HTML/Twig (hors sidebar)

```html
<div class="main-content">
  <!-- Barre de recherche et actions -->
  <div class="main-toolbar">
    <input type="search" class="main-search" placeholder="Rechercher..." />
    <div class="main-actions">
      <!-- icônes actions -->
    </div>
  </div>

  <!-- Breadcrumbs -->
  <nav class="main-breadcrumbs">
    <a href="#">Tous les fichiers</a> &gt; <span>Mes Documents</span>
  </nav>

  <!-- Zone d’import -->
  <div class="import-card">
    <div class="import-icon">☁️</div>
    <div class="import-text">Cliquez pour importer un fichier<br>ou glissez-déposez ici</div>
    <button class="import-btn">Parcourir</button>
  </div>

  <!-- Section dossiers -->
  <h2 class="section-title">Dossiers</h2>
  <div class="folders-grid">
    <!-- Cartes dossier/fichier -->
    <div class="folder-card">...</div>
    <div class="folder-card">...</div>
    <!-- ... -->
  </div>
</div>
```

---

## 🎨 Extraits CSS essentiels

```css
.main-content {
  flex: 1;
  padding: 2.5rem 2.5rem 2rem 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 2.5rem;
  background: rgba(24,31,42,0.98);
  min-height: 100vh;
}

.main-toolbar {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 1.2rem;
}

.main-search {
  flex: 1;
  border-radius: 1.5rem;
  border: none;
  padding: 0.9rem 1.5rem;
  font-size: 1.1rem;
  background: rgba(255,255,255,0.15);
  box-shadow: 0 2px 12px 0 rgba(31,38,135,0.10);
  color: #fff;
}

.main-breadcrumbs {
  color: #a5b4fc;
  font-size: 1rem;
  margin-bottom: 1.5rem;
}

.import-card {
  margin: 0 auto;
  max-width: 480px;
  background: rgba(255,255,255,0.10);
  border-radius: 2rem;
  box-shadow: 0 8px 32px 0 rgba(31,38,135,0.18);
  padding: 2.5rem 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.2rem;
  backdrop-filter: blur(18px) saturate(180%);
}

.import-icon {
  font-size: 2.5rem;
  color: #a5b4fc;
  margin-bottom: 0.5rem;
}

.import-text {
  color: #e0e7ef;
  font-size: 1.15rem;
  text-align: center;
  margin-bottom: 0.5rem;
}

.import-btn {
  border: none;
  border-radius: 1.2rem;
  background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%);
  color: #222;
  font-weight: 600;
  font-size: 1.1rem;
  padding: 0.8rem 2.2rem;
  box-shadow: 0 2px 12px 0 rgba(31,38,135,0.10);
  cursor: pointer;
}

.section-title {
  color: #fff;
  font-size: 1.35rem;
  font-weight: 700;
  margin-bottom: 1.2rem;
}

.folders-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 2rem;
}

.folder-card {
  background: rgba(255,255,255,0.10);
  border-radius: 1.5rem;
  box-shadow: 0 4px 18px 0 rgba(31,38,135,0.10);
  padding: 1.5rem 1rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  transition: box-shadow 0.18s, border 0.18s;
}
.folder-card:hover {
  box-shadow: 0 0 0 3px #a5b4fc, 0 8px 32px 0 rgba(31,38,135,0.18);
}
```

---

## Conseils d’intégration

- Garde ta sidebar telle quelle.
- Applique la classe `.main-content` à ton `<main>`.
- Ajoute les nouveaux blocs (toolbar, import, grid) dans main-content.
- Utilise les classes CSS proposées pour chaque composant.
- Ajuste les couleurs/gaps selon ta charte.

---

*Ce plan est prêt à être copié-collé et adapté dans le projet HomeCloud.*
