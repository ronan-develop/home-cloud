#!/usr/bin/env bash
# =============================================================================
# Définitions communes aux scripts de déploiement (#570) — SOURCÉ par
# deploy-nightly.sh, deploy-all.sh et deploy.sh, jamais exécuté directement.
#
# Une seule définition de la commande composer et de la vérification de
# vendor/, pour qu'un correctif ne puisse plus être appliqué à un script et
# oublié dans les deux autres (le correctif 61ba614 n'avait touché que
# deploy-nightly.sh).
# =============================================================================

# Chemins absolus obligatoires : un cron cPanel s'exécute avec un PATH minimal
# (pas celui du profil shell interactif) — "composer"/"php" seuls ne résolvent
# à rien et font échouer le déploiement en silence (#421, échec réel constaté
# la nuit du 2026-09-12 : « composer : commande introuvable »).
HC_PHP="${DEPLOY_NIGHTLY_PHP_BIN:-/usr/local/bin/php}"
# -d memory_limit=512M : sur le mutualisé o2switch (LVE CloudLinux), le
# memory_limit par défaut du php.ini fait tuer cache:clear --env=prod même
# isolé dans son propre process SSH (vécu 2026-09-27) — la valeur par défaut
# est trop juste pour la compilation du container Symfony en prod.
HC_PHP_BIN="${HC_PHP} -d memory_limit=512M"

# composer est un script `#!/usr/bin/env php` : sous le PATH minimal du cron,
# `env` résout "php" vers le CGI, pas le CLI. Composer affiche alors son aide
# et SORT EN 0 sans rien installer (constaté le 2026-10-02 : vendor/ resté
# sans symfony/monolog-bundle, 6 instances en HTTP 500). Invoquer composer.phar
# via le PHP CLI explicite court-circuite ce shebang. Valeur de plusieurs mots :
# toujours l'utiliser NON quoté ($HC_COMPOSER_BIN), comme $HC_PHP_BIN.
HC_COMPOSER_BIN="${HC_PHP_BIN} ${DEPLOY_NIGHTLY_COMPOSER_PHAR:-/usr/local/bin/composer}"
HC_COMPOSER_INSTALL_ARGS="install --no-interaction --prefer-dist --no-progress --no-dev --no-scripts"

# Vérifie que vendor/ correspond exactement à composer.lock : après un
# install réussi, un dry-run ne doit plus rien avoir à faire. Un composer qui
# a affiché son aide (CGI) ou s'est arrêté en route ne produit pas ce message.
# Snippet de shell (et non une fonction seule) pour pouvoir aussi être envoyé
# tel quel dans une commande SSH distante (deploy-all.sh).
HC_VERIFY_VENDOR_SNIPPET='out=$('"${HC_COMPOSER_BIN}"' install --dry-run --no-dev --no-scripts --no-interaction 2>&1); case "$out" in *"Nothing to install, update or remove"*) ;; *) echo "vendor/ ne correspond pas à composer.lock (composer non exécuté en CLI ?) :" >&2; printf "%s\n" "$out" | head -8 >&2; exit 1 ;; esac'

# À appeler dans un sous-shell (run_step) : sort en 1 si vendor/ est incomplet.
hc_verify_vendor() {
    eval "$HC_VERIFY_VENDOR_SNIPPET"
}
