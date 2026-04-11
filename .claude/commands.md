# Commandes utiles

## Cache

```bash
php bin/console cache:clear
```

## Base de données

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate          # appliquer les migrations
php bin/console make:migration                       # générer une migration (après modif d'entité)
```

## JWT

```bash
php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

## Tests

```bash
./vendor/bin/phpunit --configuration phpunit.dist.xml --colors=always
./vendor/bin/phpunit --filter NomDuTest              # test ciblé
npm test                                             # tests JS (Jest)
```

## État actuel des tests

312 tests, 659 assertions — 0 failures, 0 errors (au 2026-03-31).  
Détail : `.github/avancement.md`
