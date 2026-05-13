# 📦 HomeCloud Interface – Guide de téléchargement

## 🚀 Démarrage rapide

### 1. **Téléchargez les fichiers**
- ✅ `assets/` — CSS + JS
- ✅ `templates/` — Twig (fichiers HTML)
- ✅ `INTEGRATION.md` — Documentation complète

### 2. **Structure de votre projet Symfony**
```
your-symfony-project/
├── assets/
│   ├── css/
│   │   └── homecloud.css          ← Copier ici
│   └── js/
│       ├── app.js                 ← Copier ici
│       └── icons.js               ← Copier ici
├── templates/
│   └── app/
│       └── index.responsive.html.twig  ← Copier ici
└── src/Controller/
    └── AppController.php
```

### 3. **Configuration Tailwind** (déjà fait ?)
Si vous n'avez **pas** Tailwind installé :

```bash
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init -p
```

**Mettez à jour `tailwind.config.js` :**
```js
module.exports = {
  content: [
    './templates/**/*.twig',
    './assets/**/*.{js,jsx}',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

### 4. **Route Symfony**
Dans `src/Controller/AppController.php` :

```php
<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AppController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('app/index.responsive.html.twig');
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('app/index.responsive.html.twig');
    }
}
```

### 5. **Compilez le CSS**
```bash
npm run build
# ou en watch mode:
npm run watch
```

---

## 📱 Responsive Design

### **Desktop (md+)**
- ✅ Sidebar fixe 248px
- ✅ Topbar avec search
- ✅ Layout à 2 colonnes
- ✅ Full navigation

### **Tablet/Mobile (md-)**
- ✅ Drawer sidebar (bouton burger menu)
- ✅ Bottom tab bar avec 4 onglets
- ✅ Topbar compacte
- ✅ Search cachée
- ✅ Contenu responsive

### Points de rupture Tailwind
```
sm:  640px
md:  768px   ← Principal
lg:  1024px
xl:  1280px
```

---

## 🎨 Fichiers inclus

| Fichier | Rôle |
|---------|------|
| `homecloud.css` | Design tokens + Tailwind layers (glass, buttons, etc.) |
| `app.js` | App shell (navigation, drawer, thème, modales) |
| `icons.js` | SVG icons réutilisables |
| `index.responsive.html.twig` | Layout principal (responsive) |
| `INTEGRATION.md` | Guide technique détaillé |

---

## 🔧 Personnalisation

### Changer les couleurs

Éditer `assets/css/homecloud.css` :
```css
:root {
  --hc-accent: #3b82f6;           /* Bleu → votre couleur */
  --hc-accent-2: #1d4ed8;         /* Dégradé */
  --hc-bg: #ffffff;
  --hc-surface: rgba(255,255,255,0.7);
  /* ... */
}
```

### Ajouter une page

1. Créer un `<div data-page="new-page">` dans le HTML
2. Ajouter dans sidebar : `<a data-nav-item="new-page">Mon page</a>`
3. Ajouter dans tab bar mobile (optional)
4. JS gère le reste automatiquement

### Ajouter une modale

```html
<div data-modal id="my-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
  <div class="glass p-6 rounded-xl max-w-md">
    <h2>Titre</h2>
    <p>Contenu</p>
    <button data-modal-close>Fermer</button>
  </div>
</div>
```

Appeler : `window.homecloud.openModal('my-modal')`

---

## 🌙 Mode sombre

Automatique ! Toggle via le bouton soleil/lune en haut à droite.
Persiste dans `localStorage`.

---

## 🚀 Prochaines étapes recommandées

1. **Intégrer avec votre backend**
   - Fetch API pour charger les données
   - CSRF tokens pour les forms
   - Upload fichiers (drag-drop)

2. **Créer les pages détaillées**
   - `files.html.twig` — Explorateur fichiers
   - `gallery.html.twig` — Galerie photos
   - `shares.html.twig` — Gestion partages
   - `settings.html.twig` — Profil + options

3. **Ajouter du JavaScript par page**
   - `assets/js/pages/files.js`
   - `assets/js/pages/gallery.js`
   - Etc.

4. **Authentification**
   - Symfony Security pour les routes
   - Guard/authenticator
   - Logout

---

## ❓ Aide

**Les styles Tailwind ne s'appliquent pas ?**
→ Vérifiez que `npm run build` a compilé le CSS

**Le drawer ne s'ouvre pas sur mobile ?**
→ Vérifiez que le HTML a un `<div id="sidebar">` et `<div id="drawer-overlay">`

**Les icônes SVG ne s'affichent pas ?**
→ Assurez-vous que `window.Icons` est chargé (vérifiez `icons.js`)

---

## 📄 Licence

Libre d'utilisation. Adaptez à votre marque !

---

**Questions ?** Relisez `INTEGRATION.md` pour plus de détails techniques.
