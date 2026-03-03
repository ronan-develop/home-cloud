# 📋 TODO technique — Live Component FolderBrowser

> Branche : feat/FolderBrowserComponent
> Objectif : Explorateur de fichiers HomeCloud (arborescence, navigation, UX Material)

---

## 1. Structure du composant

- [ ] Créer le Live Component `FolderBrowser` dans `src/Twig/Component/FolderBrowserComponent.php`
- [ ] Créer le template Twig associé `templates/components/folder_browser.html.twig`
- [ ] Définir les props : dossier courant, liste enfants, navigation

## 2. Récupération des données

- [ ] Intégrer le provider FolderProvider pour charger l’arborescence
- [ ] Afficher la racine, les sous-dossiers, et le chemin courant
- [ ] Pagination si > 50 dossiers enfants

## 3. Navigation

- [ ] Implémenter la navigation dans l’arborescence (click sur dossier → maj props)
- [ ] Afficher le fil d’Ariane (breadcrumb)
- [ ] Gérer le retour à la racine

## 4. UX/UI

- [ ] Style Material Design + Liquid Glass (voir palette avancement.md)
- [ ] Icônes dossiers, hover, sélection
- [ ] Responsive mobile/desktop

## 5. Intégration avec FileList

- [ ] Sur sélection d’un dossier, afficher la liste des fichiers (FileList)
- [ ] Passer l’ID du dossier sélectionné au composant FileList

## 6. Tests

- [ ] Créer un test fonctionnel `tests/Web/FolderBrowserComponentTest.php`
- [ ] Vérifier navigation, affichage, UX

## 7. Documentation

- [ ] Documenter le composant, props, usage dans avancement.md

---

> Chaque étape doit être validée par commit atomique et suivie dans la branche feat/FolderBrowserComponent.
