# Architecture serveur

## Déploiement et configuration du projet

### 1. Installation des outils et prérequis

- Installation de la **Symfony CLI** pour faciliter le développement et la gestion du projet.
- Vérification de la configuration PHP et des extensions nécessaires via `symfony check:requirements`.

### 2. Gestion des bases de données

- Désinstallation de MySQL/MariaDB pour repartir sur une base propre.
- Installation de **PostgreSQL** (sous Manjaro en local, CloudLinux côté serveur).
- Création de la base de données `r_homecloud`.

### 3. Déploiement sur le serveur

- Connexion SSH à l’hébergement mutualisé (o2switch).
- Téléchargement et installation locale de **FrankenPHP** dans le dossier du site.
- Création d’un fichier `Caddyfile` pour servir l’application Symfony via FrankenPHP.
- Lancement de FrankenPHP avec la configuration adaptée.

### 4. Utilisation de FrankenPHP avec Docker

- FrankenPHP est utilisé comme serveur web principal (remplace Apache).
- Le dossier public de l’API Symfony est monté dans le conteneur Docker avec :

  ```yaml
  volumes:
    - ./api/public:/app/public:ro
  ```

- Les ports 80 et 443 sont exposés pour l’accès HTTP/HTTPS.
- Voir compose.yaml pour la configuration complète.

### 5. Lancement de FrankenPHP en tâche de fond

- Pour que FrankenPHP continue de tourner après fermeture de la session SSH, utilise :

  ```bash
  nohup ./frankenphp run --config ./Caddyfile > frankenphp.log 2>&1 &
  ```

- Les logs sont enregistrés dans `frankenphp.log`.

### 6. Relancer FrankenPHP en tâche de fond

- Après avoir arrêté FrankenPHP (ex : `pkill frankenphp`), relance-le en tâche de fond avec :

  ```bash
  nohup ./frankenphp run --config ./Caddyfile > frankenphp.log 2>&1 &
  ```

- Pour vérifier qu'il tourne :

  ```bash
  ps aux | grep frankenphp
  ```

### 7. Installation de Certbot et génération d’un certificat SSL

Pour générer un certificat SSL avec Let’s Encrypt en ligne de commande, il faut installer **Certbot** :

- Sur CentOS/CloudLinux :

  ```bash
  sudo dnf install certbot
  ```

Ensuite, pour générer un certificat pour votre domaine :

```bash
sudo certbot certonly --standalone -d votredomaine.fr
```

- Suivez les instructions affichées pour valider le domaine et récupérer les fichiers du certificat.
- Sur un hébergement mutualisé, il faut généralement importer manuellement le certificat via l’interface d’administration.

## Gestion de FrankenPHP en tâche de fond

Pour lancer FrankenPHP en tâche de fond (service web Symfony) :

```bash
nohup ./frankenphp run --config ./Caddyfile > frankenphp.log 2>&1 &
```

- Cette commande démarre FrankenPHP en arrière-plan et enregistre les logs dans `frankenphp.log`.
- **Important** : Ne lance qu’une seule instance à la fois pour éviter les conflits.

### Vérifier les processus FrankenPHP

Pour vérifier qu’une seule instance tourne :

```bash
ps aux | grep frankenphp
```

### Arrêter toutes les instances FrankenPHP

Pour arrêter toutes les instances en cours :

```bash
pkill frankenphp
```

Puis relance proprement une seule instance avec la commande ci-dessus.

---

_Ne jamais lancer plusieurs fois la commande de démarrage sans avoir arrêté les instances précédentes._

## Architecture multi-tenant Home Cloud

- Cœur applicatif unique Symfony (7.3.2)
- Un sous-domaine = un tenant = une base MySQL dédiée
- Détection du tenant à chaque requête via le sous-domaine
- Doctrine sélectionne dynamiquement la connexion à la base du tenant
- Isolation stricte des données, mais logique métier partagée
- Le domaine racine lenouvel.me n’est jamais accessible directement : seules les requêtes vers des sous-domaines déclarés sont autorisées.

Voir le schéma Mermaid dans `.github/diagram-multitenant.md` pour le flux détaillé.
