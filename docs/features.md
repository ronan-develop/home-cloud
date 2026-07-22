# Fonctionnalités

Ce que fait HomeCloud, du point de vue de celui qui l'utilise.

---

## Fichiers

Un explorateur classique — arborescence de dossiers, upload par glisser-déposer, renommage, déplacement, suppression.

- **Upload** : plusieurs fichiers à la fois, avec barre de progression et file d'attente. Le dépôt d'un fichier sur un dossier l'y range directement — y compris un dossier entier glissé depuis le disque, avec sa structure.
- **Types acceptés** : tout, sauf les exécutables (liste noire, pas liste blanche — un cloud personnel n'a pas à deviner ce que vous stockez). Les fichiers dangereux (scripts, HTML/SVG actifs, PDF avec JavaScript embarqué) sont neutralisés plutôt que rejetés : conservés, mais non exécutables/prévisualisables.
- **Organisation** : dossiers imbriqués, un nom de fichier étant unique au sein d'un même dossier.
- **Visualisation** : un PDF, une image ou une vidéo s'ouvre directement dans le navigateur, sans téléchargement préalable — affichage à la demande, un fichier à la fois (pas de navigation vers les fichiers voisins).
- **Téléchargement** : un dossier entier se télécharge en une seule archive ZIP.

## Galerie

Les photos et vidéos, sorties de leur arborescence et présentées par date.

- **Vignettes** générées automatiquement à l'upload, en tâche de fond.
- **Lightbox** plein écran, navigation au clavier, diaporama.
- **Filtres** par type (photo / vidéo) et tri par date.
- **Métadonnées EXIF** extraites automatiquement : date de prise de vue, modèle d'appareil, coordonnées GPS, et pour un photographe — ouverture, vitesse, ISO, focale, objectif (JPEG et RAW, sauf CR3).

### Fichiers RAW

Les RAW (CR2, CR3, NEF, ARW, DNG) sont traités comme des photos, pas comme des fichiers inertes.

Un navigateur ne sait pas afficher un RAW, et un fichier de 50 Mo n'a rien à faire dans une page web. HomeCloud extrait donc la **preview JPEG que l'appareil a déjà embarquée** dans le fichier : vignette dans la galerie, image redressée et allégée en plein écran, RAW d'origine conservé intact pour le téléchargement.

Une photo prise en portrait est automatiquement remise à l'endroit — l'appareil se contente d'enregistrer la rotation, il ne l'applique pas.

> Limite connue : les CR3 (Canon, encodage ISO-BMFF) n'exposent pas encore ces métadonnées — champs vides, sans erreur. Vignette et affichage fonctionnent pour tous les formats RAW supportés.

## Albums

Des sélections de médias, indépendantes de l'arborescence : une même photo peut appartenir à plusieurs albums sans être dupliquée.

- **Import** depuis la galerie, en sélection multiple.
- **Ordre libre** : réorganisation par glisser-déposer.
- **Couverture** : choisie explicitement, ou à défaut le premier média disposant d'une vignette.

## Partage

Deux mécanismes, pour deux besoins différents.

### Partage par compte

Partager avec quelqu'un par son email.

- Si la personne n'a pas de compte, un **compte invité** est créé automatiquement et elle reçoit un email d'activation — pas de formulaire d'inscription à lui imposer.
- Si elle en a déjà un, elle reçoit une **notification** avec le nom de la ressource et le lien direct.
- Un compte invité voit ce qu'on lui a partagé, et rien d'autre : il ne peut ni créer, ni téléverser.
- Les albums sont partagés **en lecture seule**.

### Partage par lien

Un lien public, sans compte à créer pour le destinataire.

- **Durée de vie** : 1 jour, 7 jours, 30 jours ou permanent.
- **Révocable** à tout moment.
- La ressource doit être explicitement autorisée au partage par lien — un dossier privé ne peut pas fuiter par mégarde.
- Les pages de partage sont exclues de l'indexation par les moteurs de recherche.

## Invités

Une page dédiée à la gestion des comptes invités : les créer directement, renommer, supprimer. Les actions sont instantanées, sans rechargement de page.

## Recherche

Recherche par nom sur les fichiers et les dossiers, depuis la barre supérieure.

## Compte et interface

- **Authentification** par session pour l'interface web, par JWT pour l'API.
- **Mot de passe oublié** : réinitialisation par email.
- **Thème clair / sombre**, suivant par défaut la préférence du système.
- **Responsive** : barre latérale sur desktop, tab-bar sur mobile.
- **Changelog** : page dédiée listant les grandes évolutions du projet, alimentée automatiquement depuis les PR mergées sur GitHub.

## API

Une API REST (API Platform) expose les fichiers, dossiers, médias, albums et partages — authentification par JWT, documentation OpenAPI générée sur `/api/docs`.

---

## Ce que HomeCloud ne fait pas

Assumé, pour éviter les malentendus :

- **Pas de développement RAW** — on extrait la preview de l'appareil, on ne décode pas le capteur.
- **Pas d'édition d'image** en ligne.
- **Pas de multi-tenant** : une instance, un propriétaire, ses invités.
- **Pas de synchronisation** type client de bureau.
