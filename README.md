# HomeCloud

Cloud photo personnel auto-hébergé. Stocker ses fichiers, organiser ses photos en albums, les partager — sans confier sa bibliothèque à un service tiers.

Pensé pour un photographe : les fichiers RAW (CR2, CR3, NEF, ARW, DNG) sont traités comme des photos à part entière, avec vignettes et affichage plein écran, là où la plupart des solutions les ignorent.

```text
Symfony 8 · PHP 8.4 · API Platform 3 · MariaDB · Tailwind v4 · Stimulus
```

---

## Documentation

| Document                               | Pour qui                                                     |
|----------------------------------------|--------------------------------------------------------------|
| [docs/features.md](docs/features.md)   | Ce que fait l'application, fonctionnalité par fonctionnalité |
| [docs/technique.md](docs/technique.md) | Les grandes directions techniques, pour développer dessus    |
| [.claude/](.claude/)                   | Conventions détaillées, lues par l'assistant de code         |

---

## Démarrage

**Prérequis** — PHP 8.4+ (`gd`, `exif`, `pdo_mysql`), Composer, MariaDB 10.6+, Node 20+.

```bash
composer install
npm install

cp .env .env.local          # puis renseigner DATABASE_URL et APP_ENCRYPTION_KEY
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair

composer build-assets
symfony server:start
```

Créer un compte :

```bash
php bin/console app:create-user 'vous@example.com' '<mot-de-passe>' 'Prénom'
```

## Tests

```bash
composer dev-check            # build des assets + suite PHP complète
./vendor/bin/phpunit          # 738 tests
npm test                      # 17 suites Jest
```

La méthodologie TDD est suivie sans exception : le test échoue d'abord, sinon il ne prouve rien. Voir [.claude/tdd.md](.claude/tdd.md).

---

## Licence

Propriétaire. Le code est public pour consultation ; il n'est pas sous licence libre et n'appelle pas de contribution externe.

Une brique en a été extraite et publiée séparément, sous licence MIT : [`ronanlenouvel/raw-preview-extractor`](https://github.com/ronan-develop/raw-preview-extractor) — extraction de la preview JPEG embarquée dans un fichier RAW, en PHP pur.
