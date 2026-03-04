# 📋 Plan — Feature Move Folder/File (TDD UI)

## 🎯 Contexte et constat

### Ce qui existe déjà (✅ API 100% fonctionnelle)

| Endpoint                                                    | Comportement        | Tests                                     |
|-------------------------------------------------------------|---------------------|-------------------------------------------|
| `PATCH /api/v1/folders/{id}` + `{ "parentId": "uuid" }`     | Déplace le dossier  | 5/5 ✅ (`FolderCrudTest::testMoveFolder*`) |
| `PATCH /api/v1/folders/{id}` + `{ "parentId": null }`       | Remonte à la racine | 1/1 ✅                                     |
| `PATCH /api/v1/files/{id}` + `{ "targetFolderId": "uuid" }` | Déplace le fichier  | 5/5 ✅ (`FileMoveTest`)                    |

**Règles métier déjà appliquées côté API :**

- 404 si dossier/fichier inexistant
- 403 si le dossier cible appartient à un autre utilisateur
- 400 si déplacement crée un cycle (A → B → A)
- 400 si dossier parent = lui-même

### Ce qui est cassé (❌ Frontend)

1. **`FolderCard.html.twig`** — les boutons ↪️ appellent `openMoveElementModal('move-modal-folder-{uuid}')` mais **aucun élément HTML avec cet id n'existe dans les templates**.
2. **`app.js`** — `openMoveElementModal()` existe mais ne fait qu'un `classList.remove('hidden')` sur un id inexistant → no-op silencieux.
3. **Aucune modal HTML de déplacement** n'existe dans les templates.
4. **Aucune fonction `submitMove()` / `selectMoveTarget()`** n'existe.
5. **`MoveModalWebTest.php`** — 2 failures + 2 errors + 4 risky :
   - Failures : pas de fixtures → aucun dossier en DB → `data-testid^="move-folder-btn-"` introuvable
   - Errors : `InvalidArgumentException: The current node list is empty` (appel `.first()` sur liste vide)
   - Risky : 4 tests vides (stubs sans assertions)
   - De plus, les tests utilisent `$client->executeScript()` qui **ne fonctionne pas** avec `WebTestCase` (pas de moteur JS — requiert Panther/Playwright)

---

## 🏗️ Architecture cible

```
[FolderCard.html.twig]
  Bouton ↪️ onclick="openGlobalMoveModal('folder', '{uuid}', '{name}')"
  Bouton ↪️ onclick="openGlobalMoveModal('file',   '{uuid}', '{name}')"

[layout.html.twig]
  <twig:MoveModal />  ← inséré une seule fois en bas de page

[MoveModal.html.twig]
  <div id="move-modal" class="hidden ...">
    <h2 id="move-modal-title">Déplacer « ... »</h2>
    <div id="move-target-list">  ← liste dossiers chargée en AJAX
      <button class="move-target" data-folder-id="...">📁 Nom dossier</button>
    </div>
    <button id="move-submit-btn" onclick="submitMove()">Confirmer</button>
    <button onclick="closeModal('move-modal')">Annuler</button>
  </div>

[app.js]
  openGlobalMoveModal(type, id, name)  ← ouvre la modal, charge les dossiers
  selectMoveTarget(folderId, btn)       ← met en évidence le dossier cible
  submitMove()                          ← PATCH API + toast + reload
```

---

## ✅ TDD — Étapes dans l'ordre

### ÉTAPE 1 : RED — Réécrire `MoveModalWebTest.php`

**Fichier :** `tests/Web/MoveModalWebTest.php`

Supprimer les tests `executeScript` (impossibles sans Panther).  
Réécrire avec **fixtures réelles** (user + dossier + fichier) + tests **HTML structure**.

Tests à écrire (PHPUnit WebTestCase — NO JavaScript) :

```
testMoveFolderButtonExistsForEachFolder()
  → créer user + login + créer 1 dossier
  → GET /
  → assertSelectorExists('[data-testid^="move-folder-btn-"]')

testMoveFileButtonExistsForEachFile()
  → créer user + login + créer 1 dossier + 1 fichier
  → GET /
  → assertSelectorExists('[data-testid^="move-file-btn-"]')

testGlobalMoveModalExistsInDOM()
  → créer user + login
  → GET /
  → assertSelectorExists('#move-modal')
  → assertSelectorExists('#move-modal.hidden')   ← fermée par défaut

testMoveModalHasFolderListArea()
  → créer user + login
  → GET /
  → assertSelectorExists('#move-target-list')

testMoveModalHasSubmitButton()
  → créer user + login
  → GET /
  → assertSelectorExists('#move-submit-btn')

testMoveModalHasTitle()
  → créer user + login
  → GET /
  → assertSelectorExists('#move-modal-title')

testMoveModalClosedByDefault()
  → créer user + login
  → GET /
  → assertSelectorAttributeContains('#move-modal', 'class', 'hidden')
```

**Ces tests DOIVENT être RED avant l'implémentation.**

---

### ÉTAPE 2 : GREEN — Créer le composant Twig `MoveModal`

#### 2a. Composant PHP

**Fichier :** `src/Twig/Components/MoveModal.php`

```php
<?php
namespace App\Twig\Components;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'MoveModal')]
class MoveModal {}
// Pas de props — modal globale statique
```

#### 2b. Template HTML

**Fichier :** `templates/components/MoveModal.html.twig`

Structure complète (Liquid Glass style, cohérent avec le reste de l'UI) :

```html
{# Modale unique de déplacement — partagée par tous les boutons ↪️ #}
<div id="move-modal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
     tabindex="-1"
     aria-modal="true"
     role="dialog">
  <div class="bg-white/10 backdrop-blur-2xl border border-white/20 rounded-3xl
              shadow-2xl p-6 w-full max-w-md mx-4">

    {# Titre dynamique (rempli par JS) #}
    <h2 id="move-modal-title"
        class="text-white text-xl font-semibold mb-4">
      Déplacer...
    </h2>

    {# Zone de liste des dossiers cibles (remplie en AJAX) #}
    <div id="move-target-list"
         class="max-h-64 overflow-y-auto space-y-2 mb-4">
      {# Chargement initial #}
      <p class="text-white/50 text-sm text-center py-4">Chargement...</p>
    </div>

    {# Champs cachés pour transmettre le contexte au JS #}
    <input type="hidden" id="move-entity-type" value="">
    <input type="hidden" id="move-entity-id" value="">

    {# Actions #}
    <div class="flex justify-end gap-3">
      <button type="button"
              onclick="closeModal('move-modal')"
              class="px-4 py-2 rounded-xl text-white/70 hover:text-white hover:bg-white/10 transition-colors">
        Annuler
      </button>
      <button type="button"
              id="move-submit-btn"
              disabled
              onclick="submitMove()"
              class="px-4 py-2 rounded-xl bg-blue-500 hover:bg-blue-400 text-white
                     font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
        Confirmer
      </button>
    </div>

  </div>
</div>
```

#### 2c. Inclure dans le layout

**Fichier :** `templates/web/layout.html.twig`

Ajouter juste avant `</body>` (ou à côté des autres modales) :

```twig
{# Modal de déplacement — globale, partagée #}
<twig:MoveModal />
```

---

### ÉTAPE 3 : Mettre à jour `FolderCard.html.twig`

**Fichier :** `templates/components/FolderCard.html.twig`

Modifier les deux boutons :

```twig
{# Avant (cassé) : #}
onclick="openMoveElementModal('move-modal-folder-{{ item.id }}')"
onclick="openMoveElementModal('move-modal-file-{{ item.id }}')"

{# Après (correct) : #}
onclick="openGlobalMoveModal('folder', '{{ item.id }}', '{{ item.name|e('js') }}')"
onclick="openGlobalMoveModal('file', '{{ item.id }}', '{{ (item.originalName ?? item.name)|e('js') }}')"
```

---

### ÉTAPE 4 : Implémenter la logique JS

**Fichier :** `assets/app.js`

Ajouter après la fonction `openMoveElementModal` existante (ou la remplacer) :

#### 4a. `openGlobalMoveModal(type, id, name)`

```javascript
window.openGlobalMoveModal = async function(type, id, name) {
    // Remplir les champs cachés
    document.getElementById('move-entity-type').value = type;
    document.getElementById('move-entity-id').value   = id;

    // Titre de la modal
    document.getElementById('move-modal-title').textContent =
        'Déplacer « ' + name + ' »';

    // Désactiver le bouton confirmer jusqu'à sélection
    const submitBtn = document.getElementById('move-submit-btn');
    submitBtn.disabled = true;

    // Afficher la modal
    const modal = document.getElementById('move-modal');
    modal.classList.remove('hidden');
    modal.classList.add('modal-open');

    // Charger la liste des dossiers
    const list = document.getElementById('move-target-list');
    list.innerHTML = '<p class="text-white/50 text-sm text-center py-4">Chargement...</p>';

    try {
        const token = await window.HC.getToken();
        const response = await fetch('/api/v1/folders', {
            headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error('Erreur chargement dossiers');

        const folders = await response.json();
        list.innerHTML = '';

        if (!folders || folders.length === 0) {
            list.innerHTML = '<p class="text-white/50 text-sm text-center py-4">Aucun dossier disponible</p>';
            return;
        }

        folders
            // Si on déplace un dossier, exclure lui-même comme cible
            .filter(f => !(type === 'folder' && f.id === id))
            .forEach(folder => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'move-target w-full text-left px-3 py-2 rounded-xl ' +
                                'text-white hover:bg-white/10 transition-colors flex items-center gap-2';
                btn.dataset.folderId = folder.id;
                btn.innerHTML = '📁 <span>' + folder.name + '</span>';
                btn.addEventListener('click', () => selectMoveTarget(folder.id, btn));
                list.appendChild(btn);
            });

    } catch (err) {
        list.innerHTML = '<p class="text-red-400 text-sm text-center py-4">Erreur de chargement</p>';
        console.error(err);
    }
};
```

#### 4b. `selectMoveTarget(folderId, btn)`

```javascript
window.selectMoveTarget = function(folderId, btn) {
    // Retirer sélection précédente
    document.querySelectorAll('.move-target').forEach(el => {
        el.classList.remove('bg-blue-500/30', 'ring-1', 'ring-blue-400');
    });

    // Marquer la cible sélectionnée
    btn.classList.add('bg-blue-500/30', 'ring-1', 'ring-blue-400');

    // Stocker l'ID du dossier cible
    document.getElementById('move-entity-id').dataset.targetFolderId = folderId;

    // Activer le bouton confirmer
    document.getElementById('move-submit-btn').disabled = false;
};
```

#### 4c. `submitMove()`

```javascript
window.submitMove = async function() {
    const type           = document.getElementById('move-entity-type').value;
    const entityId       = document.getElementById('move-entity-id').value;
    const targetFolderId = document.getElementById('move-entity-id').dataset.targetFolderId;

    if (!targetFolderId) return;

    const submitBtn = document.getElementById('move-submit-btn');
    submitBtn.disabled = true;

    try {
        const token = await window.HC.getToken();

        let url, body;
        if (type === 'folder') {
            url  = '/api/v1/folders/' + entityId;
            body = JSON.stringify({ parentId: targetFolderId });
        } else {
            url  = '/api/v1/files/' + entityId;
            body = JSON.stringify({ targetFolderId: targetFolderId });
        }

        const response = await fetch(url, {
            method:  'PATCH',
            headers: {
                'Authorization':  'Bearer ' + token,
                'Content-Type':   'application/merge-patch+json',
            },
            body: body,
        });

        closeModal('move-modal');

        if (response.ok) {
            showToast('Déplacement réussi ✅', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            const err = await response.json().catch(() => ({}));
            showToast('Erreur : ' + (err.detail || err.message || response.status), 'error');
        }

    } catch (err) {
        closeModal('move-modal');
        showToast('Erreur réseau', 'error');
        console.error(err);
    }
};
```

#### 4d. `showToast(message, type)` (si pas encore présent)

```javascript
window.showToast = function(message, type = 'success') {
    let toast = document.getElementById('hc-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'hc-toast';
        toast.className = 'fixed bottom-4 right-4 z-[100] px-4 py-3 rounded-2xl text-white ' +
                          'text-sm font-medium shadow-xl transition-all duration-300';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.className = toast.className.replace(/bg-\S+/, '');
    toast.classList.add(type === 'success' ? 'bg-green-500' : 'bg-red-500');
    toast.classList.remove('opacity-0', 'translate-y-4');
    toast.classList.add('opacity-100', 'translate-y-0');
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-4');
    }, 3000);
};
```

---

### ÉTAPE 5 : GREEN — Vérifier que `MoveModalWebTest` passe

```bash
php bin/phpunit tests/Web/MoveModalWebTest.php --verbose
```

Tous les tests structurels doivent passer.

---

### ÉTAPE 6 : Régression globale

```bash
php bin/phpunit
```

**Baseline :** FolderCrudTest move 5/5 ✅, FileMoveTest 5/5 ✅ — ne pas les casser.

---

### ÉTAPE 7 : Git — commits atomiques

```bash
git checkout -b feat/move-folder-ui

# Commit 1 : tests RED
git add tests/Web/MoveModalWebTest.php
git commit -m "✅ test(MoveModalWebTest): RED — réécrire tests structure HTML move modal"

# Commit 2 : composant + JS
git add src/Twig/Components/MoveModal.php templates/components/MoveModal.html.twig
git add templates/components/FolderCard.html.twig templates/web/layout.html.twig
git add assets/app.js
git commit -m "✨ feat(MoveModal): GREEN — modal déplacement dossier/fichier + JS submit"

# Commit 3 : avancement
git add .github/avancement.md
git commit -m "📖 docs(avancement): Phase 8 Move Folder/File UI ✅"
```

---

## 🔑 Points d'attention (pour un GPT-4o qui suit ce plan)

1. **`closeModal('move-modal')`** — fonction déjà présente dans `app.js`, ne pas la réécrire.
2. **`window.HC.getToken()`** — fonction JWT déjà présente dans `layout.html.twig`, disponible globalement.
3. **`openMoveElementModal`** dans `app.js` — conserver la fonction existante (d'autres endroits pourraient l'appeler), ajouter `openGlobalMoveModal` en supplément.
4. **`FolderCard.html.twig`** — le filtre Twig `|e('js')` échappe les guillemets dans les noms de dossiers (ex: `O'Brien`).
5. **La liste des dossiers** vient de `GET /api/v1/folders` (retourne un array JSON plat). Vérifier le format exact dans `FolderProvider`.
6. **Tests WebTestCase** — `assertSelectorAttributeContains('#move-modal', 'class', 'hidden')` et non `assertSelectorHasClass` (méthode inexistante).
7. **Pas de Panther** — aucun test de clic JavaScript ne peut être écrit dans WebTestCase. Les tests JS-behavior sont testés via FileMoveTest + FolderCrudTest (API level).
8. **`submitMove` — champ caché vs variable JS** : stocker le `targetFolderId` dans `dataset` de l'input `move-entity-id` est une astuce pratique pour éviter une variable globale.

---

## 📊 État des tests après implémentation attendue

| Suite | Avant | Après |
|-------|-------|-------|
| `FolderCrudTest` (move) | 5/5 ✅ | 5/5 ✅ (inchangé) |
| `FileMoveTest` | 5/5 ✅ | 5/5 ✅ (inchangé) |
| `MoveModalWebTest` | 2F + 2E + 4R ❌ | ~7/7 ✅ |
| Reste | tous ✅ | tous ✅ |
