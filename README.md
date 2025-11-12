# 🏠 Home Cloud

Documentation générale.

---

## 📚 Sommaire

- [🏠 Home Cloud](#-home-cloud)
  - [📚 Sommaire](#-sommaire)
  - [🚀 Fonctionnalités principales](#-fonctionnalités-principales)
  - [🖼️ Composant UX : Galerie photo](#️-composant-ux--galerie-photo)
  - [🏗️ Architecture avancée : Pattern Factory pour l’upload](#️-architecture-avancée--pattern-factory-pour-lupload)

---

## 🚀 Fonctionnalités principales

- Authentification Symfony de base (fonctionnelle)
- Upload de fichiers (hors images/photos) [à venir]
- Vérification d’email à l’inscription [à venir]
- Pages login/register/reset personnalisées [à venir]

---

- [Mise en place de l’environnement de développement](docs/dev-setup.md)
- [Gestion paginée des fichiers utilisateur](docs/user-files.md)
- [Pattern contrôleur ultra-lean](docs/controller-ultra-lean.md)
- [Endpoints API](docs/api_endpoints.md)
- [Architecture](docs/architecture.md)
- [Pattern Factory pour l’upload](docs/factory-upload.md)
- [Fixtures & jeux de données](docs/fixtures.md)
- [Tests & Qualité](docs/tests.md)
- [Import Google Photos](docs/google-photos-api.md)
- [Sécurisation de la diffusion des photos](docs/photo-securisation.md)

---

## 🖼️ Composant UX : Galerie photo

Voir la documentation dédiée : [docs/photo_gallery_component.md](docs/photo_gallery_component.md)

## 🏗️ Architecture avancée : Pattern Factory pour l’upload

Le projet utilise un **pattern Factory** pour la gestion des uploads (photos, fichiers, etc.).

- Ce choix est motivé par la volonté d’avoir un code professionnel, évolutif et testable.
- La Factory permet de déléguer dynamiquement à l’uploader adapté (`PhotoUploader`, `FileUploader`, etc.) selon le contexte métier ou le type de fichier.
- Cela centralise la logique de sélection, facilite l’ajout de nouveaux types d’upload (ex : vidéo, document), et respecte les principes SOLID.
- Ce pattern n’alourdit pas inutilement l’architecture : il structure le code pour anticiper les évolutions et garantir la clarté métier.
- Il s’agit aussi d’un choix pédagogique pour se (re)former à Symfony à un niveau professionnel.

Consultez la documentation détaillée des services métier dans [Services.md](./Services.md).
