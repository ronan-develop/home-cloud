# Architecture `src/`

## Structure

```
src/
├── Controller/     ← HTTP uniquement, délègue aux services
├── Dto/            ← Data Transfer Objects
├── Entity/         ← entités Doctrine (UUID v7 dans le constructeur)
├── Factory/        ← FolderTreeFactory
├── Interface/      ← 14 contrats DIP (repositories, services, security…)
├── Repository/     ← accès données (implémentent les interfaces)
├── Security/       ← AuthenticationResolver, AuthorizationChecker, OwnershipChecker, ShareAccessChecker
├── Service/        ← logique métier
├── State/          ← processors/providers API Platform
└── Message/        ← messages Symfony Messenger (ex: suppression async)
```

## Principes appliqués

- **SRP** — Controller = dispatcher HTTP pur, logique métier dans Service
- **DIP** — 14 interfaces dans `src/Interface/`, zéro dépendance concrète dans les processors
- **DRY** — auth, ownership, IRI extraction chacun centralisé une fois

## Règle critique — UUID Doctrine

Chaque entité avec UUID **doit** initialiser l'ID dans son constructeur :

```php
#[ORM\Id]
#[ORM\Column(type: 'uuid', unique: true)]
private Uuid $id;

public function __construct(...)
{
    $this->id = Uuid::v7(); // OBLIGATOIRE
}
```

**À ne jamais faire :**
- `#[ORM\GeneratedValue(strategy: 'CUSTOM')]`
- `private ?Uuid $id = null;`

## Génération de fichiers

| Fichier          | Méthode                                                                 |
|------------------|-------------------------------------------------------------------------|
| Migration        | `make:migration` **obligatoire** (diff schema/entités automatique)      |
| Entité/Controller/Service/Test | Claude génère directement (maker interactif incompatible) |
