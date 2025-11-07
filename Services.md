# Documentation des services métier – Home Cloud

Ce document recense et décrit les principaux services métier utilisés dans le projet Home Cloud. Il est accessible depuis le README principal pour garantir la traçabilité et la compréhension de l’architecture SOLID.

## Sommaire

- [Documentation des services métier – Home Cloud](#documentation-des-services-métier--home-cloud)
  - [Sommaire](#sommaire)
  - [FileUploader](#fileuploader)
  - [FileManager](#filemanager)
  - [FileUploadValidator](#fileuploadvalidator)
  - [UploadFeedbackManager](#uploadfeedbackmanager)
  - [ErrorHandler](#errorhandler)
  - [UploadLogger](#uploadlogger)

---

## FileUploader

**Responsabilité** : Gère l’enregistrement physique des fichiers uploadés sur le serveur.

- Délègue la gestion des chemins, des collisions et des droits.
- Retourne les informations du fichier uploadé (nom, chemin, taille, etc).

## FileManager

**Responsabilité** : Gère la création et la persistance de l’entité File en base.

- Associe le fichier à l’utilisateur propriétaire.
- Gère la suppression et la récupération des fichiers.

## FileUploadValidator

**Responsabilité** : Valide les fichiers uploadés selon les règles métier.

- Vérifie le type MIME, la taille, l’extension, etc.
- Lance une exception en cas de non-conformité.

## UploadFeedbackManager

**Responsabilité** : Centralise la gestion des retours utilisateur (flash, rendu Twig).

- Affiche les messages de succès ou d’erreur.
- Utilise RequestStack et Twig pour l’affichage.

## ErrorHandler

**Responsabilité** : Centralise la gestion des erreurs et exceptions.

- Loggue les erreurs via LoggerInterface.
- Rend une page d’erreur personnalisée via Twig.
- Utilisé dans les contrôleurs pour garantir la robustesse métier.

## UploadLogger

**Responsabilité** : Trace tous les événements critiques liés à l’upload.

- Loggue la validation, le succès et les erreurs d’upload.
- Utilise le LoggerInterface pour la traçabilité métier.
- Permet l’audit et le monitoring des opérations sensibles.

---

> Pour toute évolution ou ajout de service, merci de documenter ici et de mettre à jour le README principal.
