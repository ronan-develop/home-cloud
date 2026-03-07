# Déploiement sécurisé — bin/deploy.sh

Ce document décrit le workflow du script `bin/deploy.sh` et fournit un exemple sécurisé de workflow GitHub Actions (manuel) pour déclencher le déploiement sans risquer d'écraser une base existante.

---

## Vue d'ensemble

Le script automatise le déploiement d'une instance Symfony sur un serveur distant via SSH. Il supporte deux modes :

- Mode initial (création) — prudence élevée : si une base existante ou des utilisateurs sont détectés, le script s'interrompt pour éviter un écrasement.
- Mode update (mise à jour) — exécuté avec `--update` ou `UPDATE_MODE=true`, autorise l'exécution de migrations sur une base existante.

Le guide inclut : prérequis, étapes principales, protections de sécurité, et un exemple de workflow GitHub Actions `workflow_dispatch` (manuel) conçu pour minimiser les risques.

---

## Prérequis

- Accès SSH vers la machine cible (clé privée, accès utilisateur, port).
- Variables/Secrets définis dans GitHub repository secrets (voir section "Secrets recommandés").
- `php`, `composer`, `mysql` (ou client), et outils JS si nécessaires côté serveur, ou la capacité d'exécuter les commandes depuis le dépôt.
- Idéalement, un runner self-hosted pour respecter un IP whitelist si l'hébergeur l'exige (ex. o2switch).

---

## Secrets recommandés

- SSH_PRIVATE_KEY — clé SSH privée (PEM) de l'utilisateur autorisé sur le serveur.
- KNOWN_HOSTS — entrée(s) `ssh-keyscan` pour l'hôte cible (ou copie du `known_hosts`).
- SSH_USER — utilisateur SSH (ex: `deploy`).
- SSH_HOST — hôte cible (ex: `example.com`).
- SSH_PORT — port SSH (par défaut 22).
- DB_USER, DB_PASSWORD, DB_NAME — (optionnel) pour la vérification distante de la DB.
- FORCE_DEPLOY — (optionnel) string `1` pour bypasser la protection (toujours utiliser avec extrême prudence).

> Note : Ne jamais committer de secrets dans le dépôt. Stocker ces valeurs dans GitHub Secrets ou un vault sécurisé.

---

## Étapes principales du script

1. Génération / assemblage local du fichier `.env.local` et autres presets.
2. Upload sécurisé de `.env.local` vers `${DEPLOY_PATH}` (permissions restreintes).
3. Génération des clés JWT via `bin/console lexik:jwt:generate-keypair --overwrite --no-interaction --env=prod` sur le serveur distant.
4. Vérification MySQL distante (mode initial) :
   - Test de connexion MySQL.
   - Si la DB existe, exécution d'un `COUNT(*)` sur la table `users`.
   - Si `users > 0` et mode initial (non `--update`), le script s'arrête et demande d'utiliser `--update` ou `FORCE_DEPLOY=1`.
5. Migrations Doctrine (`doctrine:migrations:migrate`) si accepté ou si `UPDATE_MODE=true`.
6. Installation dépendances, build d'assets, cache warmup, réglage permissions.
7. Nettoyage et statut final.

---

## Précautions importantes

- Le script est conservateur pour éviter d'écraser une instance existante : il refuse l'initial deploy si une table users contient des enregistrements.
- Si l'hôte nécessite une whitelist IP, **utiliser un self-hosted runner** dont l'IP est autorisée par l'hébergeur, ou déclencher le déploiement depuis une machine autorisée.
- Toujours tester le flux sur une instance staging avant production et prendre un dump DB avant d'exécuter les migrations.

---

## Exemple de workflow GitHub Actions (manuel + protégé)

Le workflow suivant est conçu pour être déclenché manuellement (`workflow_dispatch`) et exécuté dans l'environnement `production` (permettant de configurer des règles d'approbation). Il utilise une clé SSH stockée en secret et écrit les known_hosts depuis un secret `KNOWN_HOSTS`.

```yaml
name: Manual Deploy to production

on:
  workflow_dispatch:

jobs:
  deploy:
    name: Deploy (manual)
    # Pour usage en environnement protégé (ajoutez protections/protection rules sur l'environnement)
    environment: production
    runs-on: ubuntu-latest # Remplacer par 'self-hosted' si l'hôte exige whitelist IP

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup SSH key
        uses: webfactory/ssh-agent@v0.8.1
        with:
          ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}

      - name: Write known_hosts
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.KNOWN_HOSTS }}" > ~/.ssh/known_hosts
          chmod 600 ~/.ssh/known_hosts

      - name: Make deploy script executable
        run: chmod +x ./bin/deploy.sh

      - name: Run deploy script (update mode recommended)
        env:
          SSH_USER: ${{ secrets.SSH_USER }}
          SSH_HOST: ${{ secrets.SSH_HOST }}
          SSH_PORT: ${{ secrets.SSH_PORT }}
          DB_USER: ${{ secrets.DB_USER }}
          DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
          DB_NAME: ${{ secrets.DB_NAME }}
          FORCE_DEPLOY: ${{ secrets.FORCE_DEPLOY }}
        run: |
          # Déploiement en mode update (moins risqué pour une instance existante)
          ./bin/deploy.sh --update

      - name: Output deploy status
        if: ${{ success() }}
        run: echo "Deploy completed successfully"

      - name: Notify failure
        if: ${{ failure() }}
        run: echo "Deploy failed — check logs and do not assume DB was migrated"
```

Remarques :
- Utiliser `runs-on: self-hosted` si l'hébergeur impose une whitelist IP. Ajouter le runner dans la machine autorisée et marquer le label correspondant.
- Pour exiger une approbation humaine avant exécution, configurer des règles de protection sur l'environnement `production` (Settings → Environments → Add required reviewers).

---

## Bonnes pratiques recommandées

- Préparer un job `staging` identique qui déploie sur une instance de test pour valider migrations et assets.
- Toujours prendre un backup DB avant migrations (dump SQL) et stocker le dump hors-lieu.
- Ajouter des checks préalables dans le workflow pour valider la présence obligatoire des secrets et échouer si l'un d'eux manque.
- Si automatisation complète nécessaire malgré la whitelist, configurer un self-hosted runner placé dans un réseau autorisé.

---

Si vous voulez, je peux aussi :
- Ajouter ce fichier dans le dépôt (fait) et/ou
- Créer le workflow réel ` .github/workflows/deploy.yml` basé sur l'exemple (je peux le committer sur une branche dédiée), ou
- Adapter le workflow pour qu'il exécute `./bin/deploy.sh` en mode `initial` avec un contrôle interactif plus fin.

Indiquez la prochaine action souhaitée.