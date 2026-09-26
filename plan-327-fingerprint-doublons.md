# Plan — #327 : détection de doublons par fingerprint persistant

Sous-partie du ticket #327 (import Google Photos Takeout), isolée à la demande explicite : "vérifier l'unicité d'upload des photos avec traitement des doublons", indépendamment du reste du chantier Takeout (dézip, rangement, métadonnées .json — hors scope ici).

## Contexte

Un utilisateur peut uploader la même photo plusieurs fois (upload individuel, puis réimport d'un export Google Photos qui la contient à nouveau). Sans fingerprint persistant, deux problèmes :
1. Doublon silencieux si l'utilisateur réimporte un export qui chevauche des fichiers déjà uploadés
2. Un média déjà importé **puis supprimé volontairement** reviendrait à chaque nouvel export si la détection ne compare qu'aux fichiers actuellement présents

D'où l'exigence du commentaire du ticket : le hash doit survivre à la suppression du `File`/`Media` — jamais purgé, stocké indépendamment de leur cycle de vie.

## Décisions actées

- **Nouvelle entité `ContentFingerprint`**, table dédiée, séparée de `File`/`Media` — pas de colonne hash sur `File` (qui serait perdue à la suppression)
- **Hash SHA-256 du contenu binaire complet** du fichier (pas nom+taille — trop de faux positifs/négatifs)
- Persistance **jamais purgée**, même si le `File` associé est supprimé

## Modèle de données

```php
#[ORM\Entity(repositoryClass: ContentFingerprintRepository::class)]
#[ORM\Table(name: 'content_fingerprints')]
#[ORM\UniqueConstraint(columns: ['owner_id', 'content_hash'])]
#[ORM\Index(columns: ['content_hash'], name: 'idx_content_fingerprints_hash')]
class ContentFingerprint
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(length: 64)]
    private string $contentHash; // sha256 hex, 64 caractères

    #[ORM\Column]
    private \DateTimeImmutable $firstSeenAt;

    // PAS de relation vers File — volontaire, doit survivre à sa suppression
}
```

**Scope du doublon : par utilisateur (`owner_id` + `content_hash`)**, pas global. Deux utilisateurs différents peuvent uploader le même fichier sans se gêner — cohérent avec l'isolation des données déjà en place partout ailleurs (ownership check systématique).

## Où brancher la détection

`CreateFileService::createFromUpload()` (API) et `FileUploadService::createFromUpload()` (web) sont les deux points d'entrée existants d'upload de fichier — cf. #440/#441 travaillés cette session, ce sont les services déjà responsables de la validation avant persistance.

Ajout d'une étape : calculer le hash du fichier uploadé (`hash_file('sha256', ...)`), vérifier l'existence d'un `ContentFingerprint` pour `(owner, hash)`.

## Comportement à la détection (tranché)

**Rejet strict de l'upload** si un `ContentFingerprint` existe déjà pour `(owner, hash)` : `BadRequestHttpException` explicite ("Ce fichier a déjà été importé"), aucune création de `File`/`Media`, aucun enregistrement du fingerprint (déjà présent).

Cohérent avec le pattern déjà en place dans `CreateFileService`/`FileUploadService` : validations qui lèvent avant toute persistance (extension bloquée, MIME exécutable).

## Impact perf à anticiper

`hash_file()` lit tout le fichier — coût non négligeable sur de gros médias (vidéos, RAW). À calculer **après** le stockage physique (`StorageService::store()` a déjà déplacé/copié le fichier), pas en double lecture séparée si évitable — à vérifier si `UploadedFile` expose déjà un moyen de hasher pendant le déplacement, sinon lecture simple du fichier stocké.

## TDD prévu

- RED : test d'un deuxième upload du même contenu par le même owner → comportement choisi (rejet/warning/silencieux)
- RED : test qu'un fingerprint survit à la suppression du File associé (requête directe sur `ContentFingerprintRepository`)
- RED : test qu'un même contenu uploadé par deux users différents n'est PAS un doublon (scope par owner)
- Migration Doctrine pour la nouvelle table

## Hors scope (reste du ticket #327, pas traité ici)

- Dézip de l'archive Google Takeout
- Lecture des métadonnées `.json` (date, géoloc)
- Rangement en dossiers/albums
- Traitement asynchrone du lot Takeout complet
