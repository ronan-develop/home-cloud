# 📄 Documentation – Liste paginée des fichiers utilisateur

## Contrôleur dédié : `UserFilesController`

- **Responsabilité** : Afficher la liste des fichiers uploadés par l’utilisateur connecté, avec pagination et actions associées.
- **Route** : `/mes-fichiers` (nom : `user_files`)
- **Sécurité** : Accès réservé aux utilisateurs authentifiés (`IS_AUTHENTICATED_FULLY`).
- **Pagination** : Utilisation de Pagerfanta (10 fichiers par page, navigation).
- **Dépendances** :
  - `FileRepository` (requête DQL paginée)
  - `Pagerfanta` (pagination Doctrine)

## Vue associée : `file/list.html.twig`

- Affiche la liste paginée des fichiers (nom, taille, date, actions).
- Intègre la navigation de pagination via le helper Twig Pagerfanta.
- Responsive et mobile first (utilisation de Tailwind recommandée).

## Workflow utilisateur

1. L’utilisateur clique sur “Voir mes fichiers” depuis la homepage ou le menu.
2. Il accède à la route `/mes-fichiers` et voit la liste paginée de ses fichiers.
3. Il peut naviguer entre les pages, télécharger ou supprimer ses fichiers.

## Bonnes pratiques

- Séparation stricte des responsabilités (accueil ≠ gestion fichiers)
- Pagination pour préserver les ressources et l’UX
- Sécurité et contrôle d’accès systématiques
- Découpage des templates en partiels réutilisables

---
> Pour toute évolution, documenter ici la structure, les routes et les choix techniques liés à la gestion des fichiers utilisateur.
