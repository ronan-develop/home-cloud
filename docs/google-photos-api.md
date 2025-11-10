# [⬅️ Retour au README](../README.md)

# 📸 Importer des photos depuis Google Photos – Documentation officielle

## 1. Prérequis Google Cloud

- Créer un projet sur [Google Cloud Console](https://console.cloud.google.com/)
- Activer l’API Google Photos Library
- Créer des identifiants OAuth 2.0 (type « Application Web »)
- Récupérer le client_id et client_secret

## 2. Authentification OAuth 2.0

- L’utilisateur doit consentir à l’accès à ses photos via le flow OAuth
- Scopes à demander :
  - `https://www.googleapis.com/auth/photoslibrary.readonly` (lecture seule)
  - `https://www.googleapis.com/auth/photoslibrary.appendonly` (ajout)
- Rediriger l’utilisateur vers l’URL d’autorisation Google
- Récupérer le code d’autorisation, puis l’échanger contre un access_token

## 3. Appels API principaux

- **Lister les albums**  
  `GET https://photoslibrary.googleapis.com/v1/albums`
- **Lister les médias**  
  `GET https://photoslibrary.googleapis.com/v1/mediaItems`
- **Télécharger une photo**  
  Récupérer l’URL de base du média (`baseUrl`), puis ajouter `=d` pour forcer le téléchargement

## 4. Points d’attention

- Les quotas d’API sont limités (voir [quota](https://developers.google.com/photos/library/guides/usage-limits))
- L’API ne permet pas d’accéder à toutes les métadonnées EXIF
- L’import nécessite de gérer le rafraîchissement du token OAuth

## 5. Liens utiles

- [Guide officiel démarrage](https://developers.google.com/photos/library/guides/get-started)
- [Référence API REST](https://developers.google.com/photos/library/reference/rest)
- [Exemples de code](https://developers.google.com/photos/library/guides/code-samples)

---

**Étapes d’intégration dans Symfony** :

1. Intégrer le flow OAuth (ex : `knpuniversity/oauth2-client-bundle`)
2. Stocker le token d’accès utilisateur
3. Appeler l’API pour lister et importer les photos
4. Gérer les erreurs et quotas

---

_N’hésite pas à demander un exemple de flow OAuth ou d’appel API en PHP/Symfony pour démarrer l’intégration technique._
