# Architecture `src/`

## Structure

```txt
src/
├── Controller/     ← HTTP uniquement, délègue aux services
├── Dto/            ← Data Transfer Objects
├── Entity/         ← entités Doctrine (UUID v7 dans le constructeur)
├── Factory/        ← FolderTreeFactory
├── Interface/      ← contrats DIP (repositories, services, security…)
├── Repository/     ← accès données (implémentent les interfaces)
├── Security/       ← AuthenticationResolver, AuthorizationChecker, OwnershipChecker, ShareAccessChecker
├── Service/        ← logique métier
├── State/          ← processors/providers API Platform
└── Message/        ← messages Symfony Messenger (ex: suppression async)
```

## Principes appliqués

- **SRP** — Controller = dispatcher HTTP pur, logique métier dans Service
- **DIP** — contrats dans `src/Interface/`, zéro dépendance concrète dans les processors
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

| Fichier                        | Méthode                                                            |
|--------------------------------|--------------------------------------------------------------------|
| Migration                      | `make:migration` **obligatoire** (diff schema/entités automatique) |
| Entité/Controller/Service/Test | Claude génère directement (maker interactif incompatible)          |

## Pipeline média

Une photo produit trois artefacts distincts — original, vignette, preview — dont un seul est référencé en base. Les fichiers RAW ont un chemin à part : GD ne sait pas les décoder, on extrait la preview JPEG que l'appareil y embarque.

| Service                    | Rôle                                                                                         |
|----------------------------|----------------------------------------------------------------------------------------------|
| `MediaProcessor`           | Crée le `Media` (EXIF + vignette). Reconnaît les RAW à l'extension, leur mimeType étant muet |
| `ThumbnailService`         | Vignette de galerie, stockée dans `var/storage/thumbs/`                                      |
| `MediaFullResponseFactory` | Affichage plein écran (lightbox, diaporama, partages)                                        |
| `RawPreviewCache`          | Cache disque des previews RAW, ~1 s à générer                                                |
| `MediaCacheHeaders`        | En-têtes de cache navigateur des 4 routes d'images                                           |

**Deux pièges à connaître avant de toucher à l'orientation d'une image** (signe de la rotation, ordre rotation/redimensionnement) : voir [medias.md](medias.md), qui documente aussi les choix de cache et ce qui a été écarté.
