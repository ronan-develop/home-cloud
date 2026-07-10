# CLAUDE.md — HomeCloud

Sommaire de référence. Lire avant toute intervention — suivre les liens pour le détail.

---

## Index

| Sujet                        | Fichier                                                  |
|------------------------------|----------------------------------------------------------|
| Architecture `src/`, UUID    | [.claude/architecture.md](.claude/architecture.md)       |
| Commits, branches, `/git`    | [.claude/git-conventions.md](.claude/git-conventions.md) |
| Commandes bin/console, tests | [.claude/commands.md](.claude/commands.md)               |
| Méthodologie TDD             | [.claude/tdd.md](.claude/tdd.md)                         |
| Design frontend              | [.claude/frontend.md](.claude/frontend.md)               |
| Layout CSS, grille, scroll   | [.claude/layout.md](.claude/layout.md)                   |
| CI/CD, déploiement, suivi    | [.claude/cicd.md](.claude/cicd.md)                       |
| Guide déploiement o2switch   | [.claude/deploiement.md](.claude/deploiement.md)         |

---

## Règles critiques — toujours actives

### Secrets

- Ne jamais commiter de secrets — relire le diff stagé avant chaque commit
- `.env` : valeurs génériques uniquement ; `.env.local` / `.env.test.local` : valeurs sensibles

### Git

- Jamais de commit direct sur `main`
- Commits atomiques — jamais `git add .` en un bloc
- Confirmation obligatoire avant : `git push`, merge, ouvrir/fermer une PR, supprimer une branche

### TDD

- Toujours RED → GREEN → REFACTOR — ne jamais écrire le code avant le test
- Couverture attendue : accès non autorisé, happy path, cas limites, rollback

### UUID Doctrine

Chaque entité **doit** initialiser l'ID dans son constructeur :

```php
#[ORM\Id]
#[ORM\Column(type: 'uuid', unique: true)]
private Uuid $id;

public function __construct()
{
    $this->id = Uuid::v7();
}
```

Ne jamais utiliser `#[ORM\GeneratedValue]` ni `private ?Uuid $id = null;`.

---

## Entités Doctrine

| Entité   | Rôle                            |
|----------|---------------------------------|
| `User`   | Compte utilisateur (JWT auth)   |
| `Folder` | Répertoire virtuel              |
| `File`   | Fichier uploadé                 |
| `Media`  | Métadonnées média (image/vidéo) |

Stack : Symfony 7 / API Platform 3, PHP 8.4, MariaDB, Tailwind v4, Stimulus, PWA.

---

## Grille CSS & layout

```text
Desktop (≥768px) : grid-template-columns: 248px 1fr
Mobile (<768px)  : grid-template-columns: 1fr + tab-bar fixe en bas
```

- **Tab-bar** : `height: 64px`, positionnée en `fixed bottom-0`
- **Scroll mobile** : `.hc-content { overflow: auto; height: calc(100vh - 64px) }`
- **Scroll desktop** : `.hc-content { overflow: auto }` + `.hc-sidebar { overflow-y: auto }`
- **Breakpoint** : `@media (max-width: 767px)`
- Variables : `--hc-accent`, `--hc-bg`, `--hc-surface`, `--hc-border`, `--hc-text` (dans `assets/styles/layout.css`)

---

## Commandes essentielles

```bash
./vendor/bin/phpunit --colors=always          # tous les tests
./vendor/bin/phpunit --filter NomDuTest       # test ciblé
php bin/console doctrine:migrations:migrate   # appliquer les migrations
php bin/console make:migration                # générer une migration
```

---

## Suivi d'avancement

Mettre à jour `.github/avancement.md` après chaque tâche complétée.
