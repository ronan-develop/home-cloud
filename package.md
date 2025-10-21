# Déployer un package Composer sécurisé sur lenouvel.me

## 🗒️ TODO – Déploiement package Composer sécurisé lenouvel.me

- [ ] Préparer le package à publier (composer.json, versionnement, pas de credentials)
- [ ] Créer le dépôt privé sur lenouvel.me et pousser le code
- [ ] Activer l’option Composer repository privé sur lenouvel.me
- [ ] Récupérer l’URL du repository et les identifiants d’accès
- [ ] Ajouter le repository privé dans le composer.json du projet cible
- [ ] Configurer l’authentification sécurisée (composer config --global --auth)
- [ ] Vérifier que auth.json n’est jamais versionné
- [ ] Installer le package sur le serveur mutualisé (composer install ou create-project)
- [ ] Mettre à jour le package (composer update)
- [ ] Appliquer les bonnes pratiques sécurité (env.local, droits, HTTPS/SSH)
- [ ] Vérifier et dépanner en cas d’échec (credentials, URL, droits, version PHP/Composer)
- [ ] Documenter le workflow et les étapes pour l’équipe

Ce guide explique comment publier et déployer un package PHP privé (sécurisé) sur lenouvel.me, puis l’installer sur un projet Symfony hébergé en mutualisé (O2Switch).

---

## 1. Préparer le package à publier

- Structure classique Composer (exemple Symfony) :
  - `composer.json` bien renseigné (name, description, type, require, autoload, etc.)
  - Versionner le code sur un dépôt Git privé (GitHub, GitLab, lenouvel.me)
  - Ne jamais inclure de credentials ou secrets dans le code

## 2. Créer un dépôt privé sur lenouvel.me

- Connecte-toi à ton espace lenouvel.me
- Crée un nouveau dépôt Git privé (ex : `lenouvel/home-cloud-mon-espace`)
- Pousse ton code sur ce dépôt

## 3. Configurer le repository Composer privé

- Sur lenouvel.me, active l’option "Composer repository privé" si disponible
- Récupère l’URL du repository privé (ex : `https://composer.lenouvel.me`) et la documentation d’authentification
- Garde précieusement tes identifiants d’accès (token ou login/password)

## 4. Ajouter le repository privé dans le projet cible

Dans le `composer.json` du projet à déployer :

```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://composer.lenouvel.me"
    }
  ],
  "require": {
    "lenouvel/home-cloud-mon-espace": "^1.0"
  }
}
```

## 5. Authentification sécurisée

- Ne jamais commit les credentials d’accès au repository privé
- Utilise la commande Composer pour stocker le token localement :

```bash
composer config --global --auth http-basic.composer.lenouvel.me <username> <token>
```

- Vérifie que le fichier `auth.json` est bien dans ton home et **jamais** versionné

## 6. Installation du package sur le serveur mutualisé

- Connecte-toi en SSH sur O2Switch
- Place-toi dans le dossier du projet
- Lance l’installation :

```bash
composer install
```

- Ou pour une nouvelle instance :

```bash
composer create-project lenouvel/home-cloud-mon-espace /home/cloud/mon-espace
```

## 7. Mise à jour du package

```bash
cd /home/cloud/mon-espace
composer update
```

## 8. Bonnes pratiques sécurité

- Ne jamais exposer le token dans le code ou les fichiers versionnés
- Utiliser `.env.local` pour toute configuration sensible
- Vérifier les droits sur le dossier (`chmod 750` ou `770` recommandé)
- Sur O2Switch, privilégier l’accès HTTPS et le SSH pour les opérations sensibles

## 9. Dépannage courant

- Si l’installation échoue, vérifier :
  - Les credentials dans `auth.json`
  - L’URL du repository dans `composer.json`
  - Les droits d’accès sur le dossier
  - La version PHP et Composer sur le serveur

---

## Exemple complet de workflow

```bash
# 1. Ajouter le repo privé
composer config repositories.lenouvel composer https://composer.lenouvel.me

# 2. Authentifier
composer config --global --auth http-basic.composer.lenouvel.me <username> <token>

# 3. Installer le package
composer require lenouvel/home-cloud-mon-espace
```

---

**Pour toute question, consulte la documentation lenouvel.me ou contacte le support.**
