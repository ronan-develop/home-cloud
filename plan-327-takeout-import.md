# Plan — #327 : import Google Photos Takeout (suite du fingerprint)

Complète `plan-327-fingerprint-doublons.md` (PR #449, détection de doublons déjà en place). Ce plan couvre le reste du ticket #327 : upload du/des ZIP, dézip, lecture des métadonnées `.json`, rangement.

## Décisions actées (cette session)

| Question | Décision |
|---|---|
| Sync vs async | **Asynchrone via Messenger** — pattern `MediaProcessMessage`/`MediaProcessHandler` déjà en place, réutilisé |
| Rangement | **Dossier "Import Google Photos \<date>" à la racine**, pas de reproduction des albums Google |
| Métadonnées `.json` | **Lues dès la v1** — plus fidèle que l'EXIF embarqué (Google peut le perdre au réencodage) |
| Dézip | **Extraction sur disque temporaire** (`ZipArchive::extractTo()`), pas de streaming — cohérent avec `FolderZipArchiver` déjà dans le projet |
| Multi-ZIP | **Géré dès la v1** — `takeout-*-001.zip`, `-002.zip`, etc. traités comme un seul import logique |

## Flux complet

```
1. Upload du/des ZIP (multipart, un ou plusieurs fichiers takeout-*.zip)
   → nouveau endpoint dédié, pas CreateFileService/FileUploadService
   (le ZIP n'est pas un média final, juste un conteneur temporaire)

2. Création d'un TakeoutImport (nouvelle entité, statut pending)
   → dispatch TakeoutImportMessage(takeoutImportId) vers Messenger

3. TakeoutImportHandler (async, hors requête HTTP) :
   a. Extrait chaque ZIP sur disque temporaire (dossier dédié par import)
   b. Parcourt l'arborescence extraite, ignore les non-médias
      (metadata.json, print-subscriptions.json, dossiers album vides)
   c. Pour chaque média + son .json associé (<nom>.supplemental-metadata.json) :
      - Lit le hash de contenu → vérifie ContentFingerprint (déjà en place,
        PR #449) → skip silencieux si doublon (pas d'erreur, c'est un import
        de masse, pas un upload unitaire)
      - Résout/crée le Folder "Import Google Photos <date>" (une fois par import,
        pas par fichier)
      - Crée le File + Media via les services existants (CreateFileService
        ou équivalent), en enrichissant les métadonnées depuis le .json
        (date de prise de vue, géoloc) si présentes et différentes de l'EXIF
      - Enregistre le ContentFingerprint pour ce nouveau média
   d. Nettoie le disque temporaire (ZIP + extraction) une fois terminé
   e. Marque TakeoutImport terminé, notifie l'utilisateur (réutilise
      BatchCompletionNotifier ou équivalent adapté)

4. Rapport de fin d'import : nombre de médias importés, doublons ignorés,
   fichiers non reconnus ignorés — visible par email et/ou dans l'UI
```

## Nouvelles entités/composants

### `TakeoutImport` (entité)
Remplace `UploadBatch` pour ce flux — différent car le nombre de médias n'est connu qu'après dézip (pas à l'upload comme pour `UploadBatch`) :
```php
class TakeoutImport
{
    private Uuid $id;
    private User $owner;
    private string $status; // pending, extracting, processing, completed, failed
    private ?int $mediaImportedCount = null;
    private ?int $duplicatesSkippedCount = null;
    private ?int $unrecognizedFilesCount = null;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $completedAt = null;
    private ?string $errorMessage = null; // si failed
}
```

### `TakeoutImportMessage` / `TakeoutImportHandler`
Symétrique à `MediaProcessMessage`/`MediaProcessHandler`. Le message transporte uniquement `takeoutImportId` (pas les chemins de fichiers, cf. contrainte sérialisation déjà connue).

**SRP — le Handler ne fait qu'orchestrer**, il ne contient aucune logique d'import d'un média individuel. Rôle limité à : dispatcher l'extraction, appeler `TakeoutStructureParser`, boucler sur les entrées via `TakeoutMediaImporter` (ci-dessous), gérer les tranches de flush/clear, marquer le statut final. Sans cette séparation, le Handler reproduirait le problème déjà corrigé sur `FileWebController` (#439/#440/#446) : une classe qui mélange orchestration HTTP/async et règles métier.

### `TakeoutMediaImporter` (service, nouveau — extrait du Handler dès l'écriture)
Responsabilité unique : **un** média + son `.json` optionnel → `File`/`Media` créé, ou signalé comme doublon (aucun effet). Ne gère ni les tranches, ni la boucle sur l'ensemble du lot — ça reste au Handler. Appelle `MediaDateResolver` (ci-dessous) pour la fusion EXIF/métadonnées Google.

### `MediaDateResolver` (Strategy léger)
La règle "date du `.json` Google si présente et fiable, sinon EXIF embarqué" est une vraie logique de priorité/fallback, pas un simple `if`. Interface minimale :
```php
interface MediaDateResolverInterface
{
    public function resolve(?\DateTimeImmutable $exifDate, ?\DateTimeImmutable $takeoutDate): ?\DateTimeImmutable;
}
```
Isolé pour rester testable indépendamment et pour qu'une 3e source future (nom de fichier daté, métadonnées de dossier) s'ajoute sans toucher à `TakeoutMediaImporter`.

### `TakeoutZipExtractor` (service)
Extraction ZIP → disque temporaire. Pattern proche de `FolderZipArchiver` mais inverse (dézip au lieu de zip). Gère le multi-fichiers (plusieurs ZIP extraits dans le même dossier temporaire de travail).

### `TakeoutMetadataReader` (service)
Parse un fichier `<nom>.supplemental-metadata.json` (structure Google Takeout : `photoTakenTime.timestamp`, `geoData.latitude/longitude`, `title`). Retourne `null`/valeurs partielles si absent ou malformé — jamais bloquant, l'EXIF reste le filet de sécurité.

### `TakeoutStructureParser` (service)
Parcourt l'arborescence extraite, distingue médias / métadonnées / fichiers à ignorer (`metadata.json` d'album, `print-subscriptions.json`, etc.), associe chaque média à son `.json` s'il existe.

## Nouveau endpoint upload

`POST /api/v1/takeout-imports` (ou route web équivalente) : accepte un ou plusieurs fichiers `takeout-*.zip` en multipart, crée le `TakeoutImport`, stocke temporairement les ZIP (pas via `StorageService` — ce n'est pas un fichier finalisé), dispatch le message, retourne l'ID de l'import immédiatement (202 Accepted).

**Limite de taille** : à vérifier en conditions réelles sur o2switch (`upload_max_filesize`/`post_max_size` actuels), potentiellement à relever spécifiquement pour cette route.

## Efficacité I/O et DB (contrainte transverse, pas une étape isolée)

Un export Google Photos complet peut contenir plusieurs milliers de fichiers — chaque opération "par fichier" doit être auditée pour ne pas devenir un goulot d'étranglement (allers-retours disque et requêtes SQL redondants).

**Un seul parcours du système de fichiers.** `TakeoutStructureParser` associe média + son `.json` en un seul passage (`RecursiveIteratorIterator` ou équivalent), pas un passage "classification" suivi d'un passage "lecture" séparé qui relirait deux fois l'arborescence.

**Vérification des doublons par lot, pas fichier par fichier.** `ContentFingerprintRepository::existsForOwner()` (actuel, PR #449) fait une requête par appel — correct pour un upload unitaire, inadapté à un import de milliers de fichiers. Ajouter :
```php
/** @return string[] Les hashs de $hashes qui existent déjà pour cet owner */
public function findExistingHashes(User $owner, array $hashes): array
```
Une seule requête `WHERE owner_id = :owner AND content_hash IN (:hashes)` (par tranches de ~1000 si la liste dépasse la limite de paramètres SQL) au lieu d'une requête par fichier. Le handler calcule d'abord tous les hashs (lecture disque, aucune requête), interroge une fois, puis traite chaque fichier avec le résultat déjà en mémoire.

**Écriture en lot, pas un flush par média.** `ContentFingerprintRepository::save()` (actuel) fait `persist()` + `flush()` par appel — à éviter ici. Le handler doit `persist()` chaque nouveau `ContentFingerprint`/`File`/`Media` sans flush intermédiaire, puis flush par tranches (ex. tous les 50-100 fichiers) pour limiter la taille de l'UnitOfWork Doctrine en mémoire sans payer un aller-retour DB par fichier. Un flush unique en toute fin serait risqué sur un import de plusieurs milliers de fichiers (UnitOfWork trop volumineux, perte totale si crash juste avant le flush final) — d'où le compromis par tranches.

**`EntityManager::clear()` périodique** à envisager entre les tranches pour éviter l'accumulation d'entités en mémoire sur un très gros import (pattern déjà observé dans le projet pour des opérations de masse — cf. tests avec `$em->clear()`).

## Sécurité (TDD attendu, cf. critère d'acceptation du ticket)

- **Zip bomb** : limiter la taille décompressée totale (ratio compression suspect, ou plafond absolu en octets) avant/pendant l'extraction — rejeter et nettoyer si dépassement
- **Path traversal dans le ZIP** : un entry nommé `../../etc/passwd` ne doit jamais s'extraire hors du dossier temporaire dédié — vérifier que `ZipArchive::extractTo()` protège déjà contre ça nativement (PHP le fait depuis une version donnée, à confirmer) ou ajouter une validation explicite des noms d'entrée
- **Contenu malveillant dans les médias extraits** : les protections existantes (`CreateFileService::validateExecutable`, neutralisation SVG/HTML) s'appliquent normalement puisqu'on repasse par les mêmes services de création de fichier
- **JSON malformé/hostile** : `TakeoutMetadataReader` doit être défensif (json_decode avec gestion d'erreur, jamais de `eval` ou désérialisation dangereuse)

## Patterns — passe explicite avant implémentation

- **Pas de Factory** : `TakeoutZipExtractor`/`TakeoutMetadataReader`/`TakeoutStructureParser`/`TakeoutMediaImporter` ont chacun une seule implémentation, rien à sélectionner dynamiquement — une Factory serait de l'abstraction prématurée
- **Strategy retenu** : `MediaDateResolverInterface` (EXIF vs métadonnées Google) — vraie règle de priorité/fallback, isolée pour rester testable et extensible sans toucher à l'appelant
- **SRP forcé dès l'écriture** : `TakeoutMediaImporter` extrait du `TakeoutImportHandler` pour que ce dernier reste un pur orchestrateur (boucle + tranches + statut), jamais une classe à 10+ dépendances mélangeant HTTP/async et métier — le problème déjà corrigé sur `FileWebController` ne doit pas se reproduire ici en le découvrant après coup
- **Compromis assumé sur `TakeoutStructureParser`** : classification + association média↔json restent dans le même service, malgré deux responsabilités proches, pour respecter la contrainte "un seul parcours disque" (les séparer forcerait soit un double parcours, soit un couplage inter-services pire que la fusion actuelle)

## TDD prévu (ordre RED→GREEN)

1. `ContentFingerprintRepository::findExistingHashes()` — requête par lot, RED sur un test qui vérifie qu'un seul aller-retour DB est fait pour N hashs (pas N requêtes)
2. `MediaDateResolver` — Strategy isolé, testable sans dépendance (EXIF seul, Google seul, les deux présents, aucun des deux)
3. `TakeoutMetadataReader` — parse un `.json` valide, gère absence/malformation
4. `TakeoutStructureParser` — distingue médias/métadonnées/à ignorer en un seul parcours sur une arborescence de test (assertion sur le nombre d'accès disque si testable, sinon au minimum sur le résultat correct)
5. `TakeoutZipExtractor` — extrait un ZIP de test, rejette un zip bomb (ratio anormal), rejette un path traversal
6. `TakeoutImport` (entité) — constructeur, transitions de statut
7. `TakeoutMediaImporter` — un média + son .json → File/Media créé ou doublon signalé, appelle `MediaDateResolver`, aucune connaissance des tranches/boucle
8. `TakeoutImportHandler` — orchestration complète sur un ZIP de test réaliste (mocks des dépendances, dont `TakeoutMediaImporter` mocké) : hashs calculés une fois, doublons filtrés via `findExistingHashes` en un seul appel, flush par tranches vérifié (mock `EntityManager` avec assertion sur le nombre d'appels à `flush()`)
9. Test fonctionnel : upload d'un petit ZIP réel → import complet → fichiers dans le bon dossier

## Effort estimé

Toujours L — ce plan ne réduit pas la complexité annoncée dans le ticket original, il la détaille. Probablement 3-4 sous-chantiers distincts (extraction/sécurité, métadonnées, orchestration Handler, endpoint+UI) à committer séparément comme fait pour #439/#440/#446.

## Gestion d'échec partiel (tranché)

**Pas de rollback automatique.** Si l'import échoue en cours de route (ex. 500 médias importés puis crash au 501e), les médias déjà créés restent valides et utilisables tels quels. `TakeoutImport::status` passe à `failed`, `errorMessage` renseigné, `mediaImportedCount` reflète ce qui a réellement été importé jusqu'au crash. L'utilisateur peut relancer un nouvel import du même ZIP sans risque de doublon (fingerprint déjà en place, PR #449) ou supprimer manuellement le dossier créé.

## Hors scope (reste hors ticket même après ce plan)

- Détection de doublons *entre* deux médias du même ZIP (seulement doublon contre l'historique déjà importé, pas dédoublonnage interne à l'archive elle-même)

## Progress bar frontend (ajout post-plan, demandé explicitement)

Le plan initial s'arrêtait au contrat API (GET exposant le statut). Ajout demandé après les 9 étapes : suivi visuel de la progression pendant le traitement asynchrone.

- `TakeoutImport::totalMediaCount`/`processedCount` : le premier posé par `markProcessing()` une fois le parsing terminé, le second incrémenté à chaque média traité (import ou doublon) dans la boucle du Handler
- Migration `Version20260926215944` : ALTER TABLE ajoutant ces deux colonnes
- Endpoint `GET /api/v1/takeout-imports/{id}` (`TakeoutImportProvider`) expose les deux compteurs
- Page web `/import/takeout` (`TakeoutImportWebController` + `templates/web/takeout_import.html.twig`) : sélection de fichier(s) ZIP, upload multipart, barre de progression
- `assets/controllers/takeout_import_controller.js` : Stimulus controller réutilisant `createBatchPoller` (déjà existant pour le suivi de lot d'upload classique) plutôt que d'inventer un second mécanisme de polling
- `createBatchPoller` étendu pour s'arrêter aussi sur le statut `failed` (pas seulement `completed`) — nécessaire pour Takeout qui peut échouer (zip bomb, disque plein), contrairement à `UploadBatch` qui n'a pas cet état
- Barre "indéterminée" (animation CSS striée) tant que `totalMediaCount` est `null` (extraction en cours, avant la fin du parsing)
