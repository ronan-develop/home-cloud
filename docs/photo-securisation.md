# 📸 Sécurisation de la diffusion des photos utilisateur

## Objectif

Garantir que les fichiers photos uploadés ne soient **jamais exposés directement** via le web, même en environnement mutualisé, tout en permettant leur affichage sécurisé dans l’application.

---

## Logique d'accès sécurisé

- **Aucun accès direct** au dossier `uploads/photos` (hors de `public/`).
- Les images sont servies **uniquement via un contrôleur Symfony** dédié (`PhotoServeController`).
- Ce contrôleur :
  1. Vérifie l’authentification de l’utilisateur
  2. Récupère la photo en base (par son id)
  3. Vérifie les droits d’accès (voter ou logique métier)
  4. Lit le fichier sur le disque (jamais exposé en public)
  5. Retourne le fichier en streaming HTTP avec le bon Content-Type et le nom d’origine

---

## Exemple d’URL d’accès sécurisé

```
/photo/view/{id}
```

Dans le composant Twig, chaque image est affichée ainsi :

```twig
<img src="{{ path('photo_view', {id: photo.id}) }}" ... >
```

---

## Avantages

- **Sécurité maximale** : contrôle d’accès à chaque requête
- **Aucune copie temporaire** ni symlink
- **Compatible mutualisé**
- **Traçabilité** et extension facile (logs, quotas, watermark, etc)

---

## À retenir

- Le dossier d’upload (`uploads/photos`) reste privé.
- Toute tentative d’accès direct à une photo est impossible.
- Le contrôleur peut être enrichi (voter, logs, quotas, etc).

---

## Référence

Voir le contrôleur : `src/Controller/PhotoServeController.php`

---

## Pour aller plus loin

- Ajouter des tests fonctionnels sur l’accès sécurisé
- Personnaliser le voter d’accès selon la logique métier
- Ajouter un watermark ou une limitation de bande passante
