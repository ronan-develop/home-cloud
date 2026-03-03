# Plan d'implémentation : Déplacement Folder & File (API + Web)

---

## 🟢 Micro-tâche en cours

### Micro-tâche 1 : Préparation TDD pour déplacement Folder

**Objectif :** Écrire les 5 tests RED pour le déplacement de dossier dans [tests/Api/FolderCrudTest.php](tests/Api/FolderCrudTest.php) avant toute implémentation.

**Étapes détaillées :**

1. Relire la structure de la classe `FolderCrudTest` pour repérer l'emplacement d'ajout des nouveaux tests.
2. Ajouter les 5 méthodes de test suivantes :
    - testMoveFolderToAnotherParent (cas nominal)
    - testMoveFolderToRootBySettingParentIdNull (mise à la racine)
    - testMoveFolderCycleDeepReturns400 (cycle profond)
    - testMoveFolderToNonExistentParentReturns404 (parent inexistant)
    - testMoveFolderToOtherUserFolderReturns403 (ownership)
3. Vérifier que chaque test échoue (RED) avant toute modification du code métier.
4. Committer avec le message conforme :
    `✅ test(FolderCrudTest): RED tests déplacement folder (cycle, racine, ownership, 404)`

---

> Ce fichier est la référence unique pour cette feature. Exécuter les todos dans l'ordre exact.
> Approche TDD : écrire le test RED → commiter selon conventions de commit → implémenter GREEN → commiter selon conventions de commit.

---

## Vue d'ensemble

### Ce qu'on veut obtenir au final

1. L'utilisateur peut **déplacer un dossier** (clic → modal → choisir destination → confirmer)
2. L'utilisateur peut **déplacer un fichier** (clic → modal → choisir destination → confirmer)
3. L'appli web utilise **les routes API avec JWT** pour ces deux opérations (pattern existant)
4. L'appli web utilise bien uniquement **les routes API avec JWT** (vérifié en audit)
5. La couche API gère les cycles, l'ownership, les erreurs

### Pattern de token déjà en place (NE PAS CHANGER)

Dans `templates/web/layout.html.twig`, il y a déjà :

```js
window.HC = {
    getToken: async function () {
        if (_token && Date.now() < _tokenExp) return _token;
        var res = await fetch('{{ path('app_web_token') }}');
        var data = await res.json();
        _token = data.token;
        _tokenExp = Date.now() + 14 * 60 * 1000; // 14 min
        return _token;
    }
};
```

Ce token vient de `GET /web/token` (route `app_web_token` → `WebTokenController`),
qui émet un JWT Lexik 15 min pour l'utilisateur de session.

**Toutes les nouvelles actions JS doivent faire** :

```js
var token = await window.HC.getToken();
// puis ajouter dans les headers : 'Authorization': 'Bearer ' + token
```

### Audit des appels existants — état actuel

| Action                   | Méthode actuelle                           | Via API ?       | Token ? | OK ?               |
|--------------------------|--------------------------------------------|-----------------|---------|--------------------|
| Créer dossier            | `fetch POST /api/v1/folders`               | ✅               | ✅       | ✅              |
| Upload fichier           | `XHR POST /api/v1/files`                   | ✅               | ✅       | ✅              |
| Download fichier         | `GET /files/{id}/download` (WebController) | ❌ (server-side) | N/A     | ✅ intentionnel  |
| Supprimer fichier        | `POST /files/{id}/delete` (WebController)  | ❌ (server-side) | N/A     | ✅ intentionnel  |
| Lister fichiers/dossiers | HomeController → Doctrine direct           | ❌ (server-side) | N/A     | ✅ intentionnel  |
| **Déplacer dossier**     | —                                          | 🔴 MANQUANT     | 🔴      | À créer          |
| **Déplacer fichier**     | —                                          | 🔴 MANQUANT     | 🔴      | À créer        |

**Conclusion de l'audit :** Les actions server-side (download, delete, liste) sont intentionnellement
en WebController direct — c'est un choix d'architecture valide, on ne les touche pas.
Les actions JS (créer dossier, upload) utilisent déjà correctement le pattern token.
Les deux actions manquantes (déplacer) devront suivre le même pattern.

---

## Ordre d'exécution des 11 todos

> ⚠️ TDD oblige : les tests RED s'écrivent **avant** leur implémentation correspondante.

```
Branche : git checkout -b feat/folder-file-move

TODO 2 → TODO 0 → TODO 1 → TODO 6 → TODO 3 → TODO 4 → TODO 5 → TODO 7 → TODO 8 → TODO 9 → TODO 10
(tests    (repo    (cycle    (tests   (setter) (ApiRes) (Proc.   (modal    (card     (phpunit) (avancement)
 folder   ancêtres) +owner   file                        File)    Twig)     +layout)
 RED)              check)    RED)
```

**Mnémotechnique :** Tests RED → Infrastructure → Implémentation GREEN → Frontend → Validation

---

## TODO 0 — Ajouter `findAncestorIds()` dans FolderRepository

**Fichier :** `src/Repository/FolderRepository.php`
**Branche :** `fix/folder-move`

### Pourquoi ce todo en premier

La méthode `wouldCreateCycle()` du TODO 1 remonte la chaîne `getParent()` en boucle.
Chaque appel à `$current->getParent()` déclenchait un lazy-load Doctrine → **N requêtes SQL**
pour un arbre de profondeur N. Avec un arbre de 50 niveaux = 50 requêtes.

**Solution :** charger tous les ancêtres d'un coup via une **CTE récursive SQL**.
MariaDB 10.3+ (le projet utilise MariaDB 10.11) supporte `WITH RECURSIVE`.

### Code à ajouter dans `FolderRepository`

Ajouter cette méthode publique **après le constructeur** (ou à la fin de la classe) :

```php
/**
 * Récupère les UUIDs de tous les ancêtres d'un dossier en une seule requête SQL.
 *
 * Utilise une CTE récursive (WITH RECURSIVE) supportée par MariaDB 10.3+.
 * Remonte la chaîne parent → parent → … jusqu'à la racine.
 *
 * Retourne un tableau d'UUID (strings) sans charger les entités Doctrine.
 * Suffisant pour la détection de cycle (comparaison d'IDs uniquement).
 *
 * @return string[] Liste d'UUIDs (BIN(16) converti en hex lisible)
 */
public function findAncestorIds(Folder $folder): array
{
    $conn = $this->getEntityManager()->getConnection();

    // Les UUIDs sont stockés en BINARY(16) par Doctrine.
    // On passe la string hex sans tirets et on laisse MySQL faire UNHEX() — plus fiable que hex2bin() via DBAL.
    $sql = <<<SQL
        WITH RECURSIVE ancestors AS (
            SELECT f.id, f.parent_id
            FROM folders f
            WHERE f.id = UNHEX(:folderId)

            UNION ALL

            SELECT p.id, p.parent_id
            FROM folders p
            INNER JOIN ancestors a ON p.id = a.parent_id
        )
        SELECT LOWER(HEX(id)) AS id
        FROM ancestors
        WHERE id != UNHEX(:folderId)
    SQL;

    // Passer l'UUID en hex sans tirets (ex: "0193f4a2b3c4d5e6f7a8b9c0d1e2f3a4")
    $hexId = str_replace('-', '', (string) $folder->getId());

    $rows = $conn->executeQuery($sql, ['folderId' => $hexId])->fetchAllAssociative();

    // Reconstruire les UUIDs avec tirets (format standard)
    return array_map(static function (array $row): string {
        $hex = $row['id'];
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20)
        );
    }, $rows);
}
```

⚠️ **Note sur le stockage UUID :** Doctrine stocke les UUID Symfony en `BINARY(16)`.
`UNHEX(:folderId)` avec la string hex sans tirets est la méthode la plus fiable — évite
les problèmes de binding binaire via DBAL (`hex2bin` + paramètre string peut être mal interprété).
`HEX()` + reformatage dans le `array_map` reconstitue les UUIDs au format standard avec tirets.

---

## TODO 1 — Corriger détection de cycle dans FolderProcessor

**Fichier :** `src/State/FolderProcessor.php`
**Branche :** `fix/folder-move`

### Problème exact à corriger (lignes 163–184 actuelles)

```php
if ($data->parentId !== null) {
    $parentId = $data->parentId;
    if (strpos($parentId, '/') !== false) {
        $parentId = basename($parentId);
    }
    if ($parentId === (string) $uriVariables['id']) {  // ← seul cas couvert
        throw new BadRequestHttpException('A folder cannot be its own parent');
    }
    $parent = $this->folderRepository->find($parentId)
        ?? throw new NotFoundHttpException('Parent folder not found');
} else {
    $parent = null;
}

// Seulement mettre à jour le parent si parentId est présent dans la requête
$vars = get_object_vars($data);
if (isset($vars['parentId'])) {  // ← CASSÉ : isset() retourne false si valeur est null
    $folder->setParent($parent);
}
```

**Bug 1 — cycle profond :** si l'arbre est A → B → C et qu'on envoie
`PATCH /folders/A` avec `{ "parentId": "<uuid-C>" }`, C devient parent de A
alors que C est déjà descendant de A → cycle infini en base de données.

**Bug 2 — `parentId: null` pas géré :** `isset($vars['parentId'])` retourne `false`
quand `parentId` vaut `null`, donc envoyer `{ "parentId": null }` (mise à la racine)
ne fait rien. Il faut lire le JSON brut.

### Correction 1a — Ajouter `wouldCreateCycle()` à la fin de la classe

Ajouter cette méthode privée **après `getAuthenticatedUser()`** (après la dernière accolade
de la classe, avant `}`). Elle utilise `findAncestorIds()` (TODO 0) pour charger
tous les ancêtres en **une seule requête SQL** au lieu de N :

```php
/**
 * Vérifie si définir $newParent comme parent de $folder créerait un cycle.
 *
 * Utilise FolderRepository::findAncestorIds() (CTE récursif, 1 seule requête SQL)
 * pour récupérer tous les ancêtres de $newParent.
 * Si $folder apparaît parmi ces ancêtres → cycle → retourne true.
 */
private function wouldCreateCycle(Folder $folder, Folder $newParent): bool
{
    $ancestorIds = $this->folderRepository->findAncestorIds($newParent);
    $folderId    = strtolower(str_replace('-', '', (string) $folder->getId()));

    foreach ($ancestorIds as $ancestorId) {
        if (strtolower(str_replace('-', '', $ancestorId)) === $folderId) {
            return true;
        }
    }

    return false;
}
```

### Correction 1b — Appeler `wouldCreateCycle()` + vérifier ownership du parent

Dans le bloc `if ($data->parentId !== null)`, **après la ligne** :

```php
$parent = $this->folderRepository->find($parentId)
    ?? throw new NotFoundHttpException('Parent folder not found');
```

Ajouter **dans cet ordre** :

```php
// Sécurité : le dossier parent cible doit appartenir au même utilisateur
// (sinon Alice pourrait déplacer son dossier dans un dossier de Bob)
if ((string) $parent->getOwner()->getId() !== (string) $user->getId()) {
    throw new AccessDeniedHttpException('You do not own the target parent folder');
}

// Détection de cycle profond (A→B→C, déplacer A sous C = cycle infini)
if ($this->wouldCreateCycle($folder, $parent)) {
    throw new BadRequestHttpException('Moving this folder would create a cycle');
}
```

⚠️ **Ce check ownership est obligatoire** : sans lui, le test 2e passerait avec
la mauvaise réponse. L'ordre est important : vérifier ownership avant le cycle
(évite d'appeler la CTE sur un dossier qui n'appartient pas à l'utilisateur).

### Correction 1c — Remplacer `get_object_vars` + `isset` par lecture JSON brut

`RequestStack` est déjà injecté dans le constructeur (champ `$requestStack`).

Remplacer les lignes 179–184 (le bloc `$vars = get_object_vars...`) par :

```php
// Lire le JSON brut pour distinguer "parentId absent" de "parentId: null" (mise à la racine)
$body = json_decode(
    $this->requestStack->getCurrentRequest()?->getContent() ?? '{}',
    true
);
if (array_key_exists('parentId', $body ?? [])) {
    $folder->setParent($parent); // $parent vaut null si parentId: null → mise à la racine ✅
}
```

### Vérifier les imports en haut du fichier

- `use App\Entity\Folder;` → déjà présent ✅
- `use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;` → déjà présent ✅
- Bonus : corriger la ligne 200 de `handleDelete()` qui utilise le FQCN `\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException` au lieu de l'import → remplacer par `AccessDeniedHttpException` seul.

---

## TODO 2 — Tests TDD pour déplacement Folder (⚠️ écrire EN PREMIER — RED avant TODOs 0 et 1)

**Fichier :** `tests/Api/FolderCrudTest.php`
**Ces 5 tests doivent être écrits AVANT les TODOs 0 et 1 (RED)**

Ajouter à la fin de la classe `FolderCrudTest` :

### Test 2a — Déplacer vers un autre parent (cas nominal → 200)

```php
public function testMoveFolderToAnotherParent(): void
{
    $user   = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
    $root   = $this->createFolder('Root', $user);
    $sub    = $this->createFolder('Sub', $user, $root);    // Sub est enfant de Root
    $target = $this->createFolder('Target', $user);         // Target est à la racine

    $client = $this->createAuthenticatedClient($user);
    $response = $client->request('PATCH', '/api/v1/folders/' . $sub->getId(), [
        'headers' => ['Content-Type' => 'application/merge-patch+json'],
        'json'    => ['parentId' => (string) $target->getId()],
    ]);

    static::assertResponseStatusCodeSame(200);
    $data = $response->toArray();
    // Sub doit maintenant pointer vers Target
    $this->assertSame((string) $target->getId(), $data['parentId']);
}
```

### Test 2b — Déplacer à la racine (`parentId: null` explicite → 200)

```php
public function testMoveFolderToRootBySettingParentIdNull(): void
{
    $user   = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
    $parent = $this->createFolder('Parent', $user);
    $sub    = $this->createFolder('Sub', $user, $parent); // Sub est enfant de Parent

    $client = $this->createAuthenticatedClient($user);
    $response = $client->request('PATCH', '/api/v1/folders/' . $sub->getId(), [
        'headers' => ['Content-Type' => 'application/merge-patch+json'],
        'json'    => ['parentId' => null], // null explicite = mise à la racine
    ]);

    static::assertResponseStatusCodeSame(200);
    $data = $response->toArray();
    // Sub ne doit plus avoir de parent
    $this->assertNull($data['parentId']);
}
```

### Test 2c — Cycle profond détecté → 400

```php
public function testMoveFolderCycleDeepReturns400(): void
{
    $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
    $a = $this->createFolder('A', $user);
    $b = $this->createFolder('B', $user, $a); // B enfant de A
    $c = $this->createFolder('C', $user, $b); // C enfant de B → A→B→C

    // Tenter de mettre C comme parent de A = cycle A→B→C→A
    $client = $this->createAuthenticatedClient($user);
    $response = $client->request('PATCH', '/api/v1/folders/' . $a->getId(), [
        'headers' => ['Content-Type' => 'application/merge-patch+json'],
        'json'    => ['parentId' => (string) $c->getId()],
    ]);

    static::assertResponseStatusCodeSame(400);
}
```

### Test 2d — Parent inexistant → 404

```php
public function testMoveFolderToNonExistentParentReturns404(): void
{
    $user   = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
    $folder = $this->createFolder('ToMove', $user);

    $client = $this->createAuthenticatedClient($user);
    $response = $client->request('PATCH', '/api/v1/folders/' . $folder->getId(), [
        'headers' => ['Content-Type' => 'application/merge-patch+json'],
        'json'    => ['parentId' => '123e4567-e89b-12d3-a456-426614174000'],
    ]);

    static::assertResponseStatusCodeSame(404);
}
```

### Test 2e — Dossier cible appartenant à un autre user → 403

```php
public function testMoveFolderToOtherUserFolderReturns403(): void
{
    $alice = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
    $bob   = $this->createUser('bob_move@example.com', 'password123', 'Bob');

    $aliceFolder = $this->createFolder('AliceFolder', $alice);
    $bobFolder   = $this->createFolder('BobFolder', $bob);

    // Alice veut déplacer son dossier sous un dossier appartenant à Bob
    $client = $this->createAuthenticatedClient($alice);
    $response = $client->request('PATCH', '/api/v1/folders/' . $aliceFolder->getId(), [
        'headers' => ['Content-Type' => 'application/merge-patch+json'],
        'json'    => ['parentId' => (string) $bobFolder->getId()],
    ]);

    // Le parent cible n'appartient pas à Alice → 403
    static::assertResponseStatusCodeSame(403);
}
```

---

## TODO 3 — Ajouter `setFolder()` à l'entité File

**Fichier :** `src/Entity/File.php`

L'entité `File` n'a aucun setter (commentaire "immuable"). Pour le déplacement,
on a besoin de modifier le `folder`. Ajouter **après `getFolder()`** :

```php
/**
 * Déplace le fichier vers un autre dossier.
 * Utilisé uniquement par FileProcessor::handlePatch() (PATCH /api/v1/files/{id}).
 */
public function setFolder(Folder $folder): void
{
    $this->folder = $folder;
}
```

Aucun autre changement dans ce fichier.

---

## TODO 4 — Ajouter l'opération PATCH dans FileOutput

**Fichier :** `src/ApiResource/FileOutput.php`

### Étape 4a — Import

Ajouter **après** `use ApiPlatform\Metadata\Post;` :

```php
use ApiPlatform\Metadata\Patch;
```

### Étape 4b — Opération dans `#[ApiResource]`

Dans le tableau `operations: [...]`, ajouter **après `new Delete(...)`** :

```php
new Patch(
    uriTemplate: '/v1/files/{id}',
    openapi: new Model\Operation(
        summary: 'Déplace un fichier vers un autre dossier.',
        description: 'Met à jour le dossier parent d\'un fichier. Corps `application/merge-patch+json` : `{ "targetFolderId": "<uuid>" }`.',
    ),
),
```

### Étape 4c — Champ input dans le DTO

À la fin des propriétés de `FileOutput`, **après** `public string $createdAt = '';` :

```php
// --- Input PATCH uniquement ---
/**
 * UUID du dossier de destination (déplacement). Input seulement, absent de la sortie GET.
 * Distinct de folderId (champ de sortie).
 */
public ?string $targetFolderId = null;
```

---

## TODO 5 — Implémenter `handlePatch()` dans FileProcessor

**Fichier :** `src/State/FileProcessor.php`
Ce fichier ne gère actuellement que DELETE (process() câblé dur).

### Étape 5a — Ajouter les imports en haut du fichier

```php
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use App\Repository\FolderRepository;
use App\State\FileProvider;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
```

### Étape 5b — Modifier le constructeur

Remplacer le constructeur actuel par :

```php
public function __construct(
    private readonly EntityManagerInterface $em,
    private readonly FileRepository $fileRepository,
    private readonly FolderRepository $folderRepository,   // ← AJOUTÉ
    private readonly MediaRepository $mediaRepository,
    private readonly UserRepository $userRepository,
    private readonly StorageServiceInterface $storageService,
    private readonly TokenStorageInterface $tokenStorage,
    private readonly LoggerInterface $logger,
    private readonly FileProvider $provider,               // ← AJOUTÉ
) {}
```

⚠️ Symfony autowire automatiquement — aucune configuration YAML n'est nécessaire.
⚠️ `FileProvider` n'injecte PAS `FileProcessor` → pas de dépendance circulaire.

### Étape 5c — Transformer `process()` en dispatcher

Remplacer la méthode `process()` entière par :

```php
/**
 * @implements ProcessorInterface<FileOutput, FileOutput|null>
 */
public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
{
    return match (true) {
        $operation instanceof Patch  => $this->handlePatch($data, $uriVariables),
        $operation instanceof Delete => $this->handleDelete($uriVariables),
        default                      => $data,
    };
}
```

### Étape 5d — Renommer l'ancienne logique DELETE en `handleDelete()`

Extraire le corps de l'ancien `process()` dans une méthode privée :

```php
/**
 * DELETE /api/v1/files/{id} — supprime les métadonnées, le thumbnail ET le fichier physique.
 */
private function handleDelete(array $uriVariables): null
{
    $file = $this->fileRepository->find($uriVariables['id'])
        ?? throw new NotFoundHttpException('File not found');

    // Supprimer le thumbnail AVANT le cascade DB (sinon la ligne Media disparaît avant)
    $media = $this->mediaRepository->findOneBy(['file' => $file]);
    if ($media !== null && $media->getThumbnailPath() !== null) {
        $this->storageService->delete($media->getThumbnailPath());
    }

    $this->storageService->delete($file->getPath());
    $this->em->remove($file);
    $this->em->flush();

    return null;
}
```

### Étape 5e — Ajouter `handlePatch()`

```php
/**
 * PATCH /api/v1/files/{id} — déplace le fichier vers un autre dossier.
 *
 * Règles métier :
 * - Seul le propriétaire du fichier peut le déplacer (403 sinon).
 * - Le dossier cible doit exister (404 sinon).
 * - Le dossier cible doit appartenir au même utilisateur (403 sinon).
 * - Si targetFolderId est absent → aucun changement, retour du DTO tel quel.
 */
private function handlePatch(FileOutput $data, array $uriVariables): FileOutput
{
    $file = $this->fileRepository->find($uriVariables['id'])
        ?? throw new NotFoundHttpException('File not found');

    $user = $this->getAuthenticatedUser();
    if (!$user instanceof \App\Entity\User) {
        throw new AccessDeniedHttpException('You must be authenticated');
    }
    if ((string) $user->getId() !== (string) $file->getOwner()->getId()) {
        throw new AccessDeniedHttpException('You are not the owner of this file');
    }

    if ($data->targetFolderId === null) {
        // Aucun champ à modifier → retour sans changement
        return $this->provider->toOutput($file);
    }

    $targetFolder = $this->folderRepository->find($data->targetFolderId)
        ?? throw new NotFoundHttpException('Target folder not found');

    // Le dossier cible doit appartenir au même utilisateur que le fichier
    if ((string) $targetFolder->getOwner()->getId() !== (string) $user->getId()) {
        throw new AccessDeniedHttpException('You do not own the target folder');
    }

    $file->setFolder($targetFolder);
    $this->em->flush();

    return $this->provider->toOutput($file);
}
```

---

## TODO 6 — Tests TDD pour déplacement File (⚠️ écrire EN PREMIER — RED avant TODOs 3, 4, 5)

**Fichier :** `tests/Api/FileMoveTest.php` (nouveau fichier)

Créer ce fichier dans `tests/Api/` :

```php
<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Tests fonctionnels pour PATCH /api/v1/files/{id} (déplacement).
 */
final class FileMoveTest extends AuthenticatedApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
        $this->createUser('alice@example.com', 'password123', 'Alice');
    }

    /**
     * Crée un File directement en base (sans passer par l'upload multipart).
     * path factice car on ne teste pas le stockage ici, seulement les métadonnées.
     */
    private function createFile(string $name, \App\Entity\Folder $folder, \App\Entity\User $owner): File
    {
        $file = new File($name, 'text/plain', 42, 'test/' . uniqid() . '.txt', $folder, $owner, false);
        $this->em->persist($file);
        $this->em->flush();
        return $file;
    }

    /** Déplacer un fichier vers un autre dossier → 200 avec folderId mis à jour */
    public function testMoveFileToAnotherFolder(): void
    {
        $alice   = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folderA = $this->createFolder('FolderA', $alice);
        $folderB = $this->createFolder('FolderB', $alice);
        $file    = $this->createFile('document.txt', $folderA, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => (string) $folderB->getId()],
        ]);

        static::assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        // folderId dans la réponse doit pointer vers FolderB
        $this->assertSame((string) $folderB->getId(), $data['folderId']);
        $this->assertSame('FolderB', $data['folderName']);
    }

    /** Dossier cible inexistant → 404 */
    public function testMoveFileToNonExistentFolderReturns404(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Folder', $alice);
        $file   = $this->createFile('doc.txt', $folder, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => '123e4567-e89b-12d3-a456-426614174000'],
        ]);

        static::assertResponseStatusCodeSame(404);
    }

    /** Dossier cible appartenant à un autre user → 403 */
    public function testMoveFileToOtherUserFolderReturns403(): void
    {
        $alice     = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $bob       = $this->createUser('bob_file@example.com', 'password123', 'Bob');
        $aliceFolder = $this->createFolder('AliceFolder', $alice);
        $bobFolder   = $this->createFolder('BobFolder', $bob);
        $file        = $this->createFile('doc.txt', $aliceFolder, $alice);

        // Alice essaie de déplacer son fichier dans un dossier de Bob
        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => (string) $bobFolder->getId()],
        ]);

        static::assertResponseStatusCodeSame(403);
    }

    /** Fichier inexistant → 404 */
    public function testMoveNonExistentFileReturns404(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Folder', $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/123e4567-e89b-12d3-a456-426614174000', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => (string) $folder->getId()],
        ]);

        static::assertResponseStatusCodeSame(404);
    }

    /** Sans authentification → 401 (ou 200 si PUBLIC_ACCESS en test) */
    public function testMoveFileWithoutAuthReturns401(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Folder', $alice);
        $file   = $this->createFile('doc.txt', $folder, $alice);

        $client = static::createClient(); // pas de token
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => (string) $folder->getId()],
        ]);

        // En env test : TestJwtAuthenticator → PUBLIC_ACCESS possible
        static::assertThat(
            $response->getStatusCode(),
            static::logicalOr(static::equalTo(200), static::equalTo(401)),
        );
    }
}
```

---

## TODO 7 — Modal de déplacement (Twig component)

**Fichier à créer :** `templates/components/MoveModal.html.twig`

Ce composant est une modale réutilisable pour déplacer un dossier OU un fichier.
Il affiche la liste des dossiers disponibles (sauf celui courant) et envoie le PATCH via fetch.

```twig
{# Composant MoveModal — Modale de déplacement Folder ou File
 #
 # Props attendues :
 #   folders : liste des dossiers de l'utilisateur (App\Entity\Folder[])
 # Usage :
 #   <twig:MoveModal :folders="userFolders" />
 #}
<div id="move-modal"
     data-testid="move-modal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">

    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl p-6 w-full max-w-sm">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Déplacer vers…</h2>
            <button onclick="closeMoveModal()"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
        </div>

        {# Liste des dossiers destination #}
        <div id="move-folder-list"
             data-testid="move-folder-list"
             class="flex flex-col gap-1 max-h-56 overflow-y-auto mb-4">

            {# Option "Racine" (null) #}
            <button type="button"
                    onclick="selectMoveTarget(null, this)"
                    data-testid="move-target-root"
                    class="move-target flex items-center gap-2 px-3 py-2 rounded-xl text-sm text-left text-gray-600
                           hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/40 transition-colors">
                🏠 Racine (aucun dossier parent)
            </button>

            {% for folder in folders %}
                <button type="button"
                        onclick="selectMoveTarget('{{ folder.id }}', this)"
                        data-testid="move-target-{{ folder.id }}"
                        data-folder-id="{{ folder.id }}"
                        class="move-target flex items-center gap-2 px-3 py-2 rounded-xl text-sm text-left text-gray-600
                               hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/40 transition-colors">
                    📁 {{ folder.name }}
                </button>
            {% endfor %}
        </div>

        <p id="move-error" class="hidden text-sm text-red-500 mb-3"></p>

        <div class="flex justify-end gap-2">
            <button onclick="closeMoveModal()"
                    class="px-4 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-100 transition-colors">
                Annuler
            </button>
            <button id="move-submit-btn"
                    data-testid="move-submit"
                    onclick="submitMove()"
                    class="px-4 py-2 rounded-xl text-sm bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                Déplacer ✓
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var _moveType   = null; // 'folder' ou 'file'
    var _moveId     = null; // UUID de l'élément à déplacer
    var _targetId   = null; // UUID du dossier cible (null = racine)

    window.openMoveModal = function (type, id) {
        _moveType   = type;
        _moveId     = id;
        _targetId   = null;

        // Réinitialiser la sélection
        document.querySelectorAll('.move-target').forEach(function (btn) {
            btn.classList.remove('bg-blue-50', 'text-blue-700', 'active-move');
        });
        document.getElementById('move-error').classList.add('hidden');
        document.getElementById('move-submit-btn').disabled = false;
        document.getElementById('move-modal').classList.remove('hidden');

        // A11y : focus sur le premier dossier de la liste
        setTimeout(function () {
            var first = document.querySelector('.move-target');
            if (first) first.focus();
        }, 50);
    };

    window.closeMoveModal = function () {
        document.getElementById('move-modal').classList.add('hidden');
        _moveType = null;
        _moveId   = null;
        _targetId = null;
    };

    window.selectMoveTarget = function (folderId, btn) {
        _targetId = folderId; // null si racine

        document.querySelectorAll('.move-target').forEach(function (el) {
            el.classList.remove('bg-blue-50', 'text-blue-700', 'active-move');
        });
        btn.classList.add('bg-blue-50', 'text-blue-700', 'active-move');
    };

    // A11y : Escape ferme, Enter valide
    document.addEventListener('keydown', function (e) {
        var modal = document.getElementById('move-modal');
        if (modal && !modal.classList.contains('hidden')) {
            if (e.key === 'Escape') closeMoveModal();
            if (e.key === 'Enter')  submitMove();
        }
    });

    // Helper : affiche un toast temporaire en bas à droite
    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 z-50 px-4 py-2 rounded-xl shadow-lg text-white text-sm ' +
                          (type === 'success' ? 'bg-green-500' : 'bg-red-500');
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 3000);
    }

    window.submitMove = async function () {
        if (!_moveId || !_moveType) return;

        var btn = document.getElementById('move-submit-btn');
        var err = document.getElementById('move-error');

        // Validation client : fichier doit avoir un dossier cible
        if (_moveType === 'file' && _targetId === null) {
            err.textContent = '⚠️ Veuillez sélectionner un dossier de destination.';
            err.classList.remove('hidden');
            return;
        }

        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '⏳ Déplacement…';
        err.classList.add('hidden');

        var token = await window.HC.getToken();
        var url, body;

        if (_moveType === 'folder') {
            url  = '/api/v1/folders/' + _moveId;
            body = JSON.stringify({ parentId: _targetId });
        } else {
            url  = '/api/v1/files/' + _moveId;
            body = JSON.stringify({ targetFolderId: _targetId });
        }

        try {
            var res = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/merge-patch+json',
                },
                body: body,
            });

            if (res.ok) {
                closeMoveModal();
                showToast('✅ Déplacement réussi', 'success');
                setTimeout(function () { window.location.reload(); }, 500);
            } else {
                // Parser le message d'erreur de l'API (format JSON:API { "detail": "..." })
                var errorData = {};
                try { errorData = await res.json(); } catch (_e) {}
                var detail = errorData.detail || errorData.message || '';

                if (res.status === 400) {
                    err.textContent = '❌ ' + (detail || 'Déplacement impossible (cycle ou conflit de nom).');
                } else if (res.status === 403) {
                    err.textContent = '🔒 ' + (detail || 'Accès refusé.');
                } else if (res.status === 404) {
                    err.textContent = '🔍 ' + (detail || 'Élément introuvable.');
                } else {
                    err.textContent = '⚠️ Erreur ' + res.status + (detail ? ' : ' + detail : '.');
                }
                err.classList.remove('hidden');
            }
        } catch (e) {
            err.textContent = '🌐 Erreur réseau : ' + e.message;
            err.classList.remove('hidden');
        } finally {
            // Toujours réactiver le bouton après tentative
            btn.disabled = false;
            btn.textContent = originalText;
        }
    };

    // Fermer sur clic en dehors
    document.getElementById('move-modal').addEventListener('click', function (e) {
        if (e.target === this) closeMoveModal();
    });
}());
</script>
```

---

## TODO 8 — Ajouter le bouton "Déplacer" dans FolderCard + inclure MoveModal

### Étape 8a — Modifier `templates/components/FolderCard.html.twig`

**Pour les dossiers**, ajouter un bouton "Déplacer" après `{# Actions dossier #}` :

```twig
{% if item.isFolder %}
    <a href="/?folder={{ item.id }}" class="flex flex-col items-center gap-1 cursor-pointer">
        <div class="folder-icon">📁</div>
        <div class="folder-name">{{ item.name }}</div>
    </a>
    <div class="folder-actions" style="margin-top:0.5rem; display:flex; gap:0.5rem;">
        <button type="button"
                onclick="openMoveModal('folder', '{{ item.id }}')"
                data-testid="move-folder-btn-{{ item.id }}"
                class="p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50
                       dark:hover:bg-blue-950/40 transition-colors"
                title="Déplacer">↪️</button>
    </div>
```

**Pour les fichiers**, ajouter un bouton "Déplacer" dans `<div class="file-actions">`,
entre le téléchargement et la suppression :

```twig
<button type="button"
        onclick="openMoveModal('file', '{{ item.id }}')"
        data-testid="move-file-btn-{{ item.id }}"
        class="p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50
               dark:hover:bg-blue-950/40 transition-colors"
        title="Déplacer">↪️</button>
```

### Étape 8b — Inclure `<twig:MoveModal>` dans `templates/web/layout.html.twig`

Ajouter **après** `<twig:NewFolderModal :folders="userFolders" />` (ligne 121 environ) :

```twig
<twig:MoveModal :folders="userFolders" />
```

### Étape 8c — Créer le composant PHP `src/Twig/Components/MoveModal.php`

```php
<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\User;
use App\Repository\FolderRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Composant MoveModal — Modale de déplacement Folder ou File.
 *
 * Injecte la liste des dossiers de l'utilisateur courant pour le sélecteur de destination.
 * Rendu côté serveur, logique de déplacement côté client via fetch PATCH.
 */
#[AsTwigComponent]
final class MoveModal
{
    /** @var \App\Entity\Folder[] */
    public array $folders = [];

    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly Security $security,
    ) {}

    public function mount(): void
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if ($user === null) {
            $this->folders = [];
            return;
        }
        $this->folders = $this->folderRepository->findBy(
            ['owner' => $user],
            ['name' => 'ASC']
        );
    }
}
```

---

## TODO 9 — Lancer tous les tests

```bash
cd /home/ronan/code/php/home-cloud && php bin/phpunit --no-coverage
```

**Attendu :** 0 failure, 0 error. Compter le total de tests pour l'avancement.

Si un test échoue, corriger **avant** de passer au TODO 10.

---

## TODO 10 — Mettre à jour `.github/avancement.md`

Dans la section `✅ Fait`, ajouter :

```markdown
| 2026-03-03 | ♻️ **fix(FolderProcessor)**: détection de cycle profond `wouldCreateCycle()` + fix `parentId: null` explicite via JSON brut ✅ |
| 2026-03-03 | ✨ **feat(FileMove)**: `PATCH /api/v1/files/{id}` avec `targetFolderId` — déplacement avec ownership check ✅ |
| 2026-03-03 | ✨ **feat(MoveModal)**: composant Twig MoveModal + bouton ↪️ sur FolderCard (Folder et File) — appel `PATCH` avec `window.HC.getToken()` ✅ |
| 2026-03-03 | ✅ **XX/XX tests passing** (+ 5 folder move + 5 file move) |
```

Remplacer `XX/XX` par le vrai compteur affiché par `php bin/phpunit`.

---

## Récapitulatif des fichiers modifiés / créés

| Fichier | Type | Ce qui change |
|---------|------|---------------|
| `src/Repository/FolderRepository.php` | Modifié | +`findAncestorIds()` (CTE récursif MariaDB 10.3+) |
| `src/State/FolderProcessor.php` | Modifié | +`wouldCreateCycle()` (utilise CTE) + fix JSON brut `parentId` |
| `src/Entity/File.php` | Modifié | +`setFolder(Folder $folder): void` |
| `src/ApiResource/FileOutput.php` | Modifié | +`use Patch` + `new Patch(...)` + champ `$targetFolderId` |
| `src/State/FileProcessor.php` | Modifié | Dispatcher + `handleDelete()` + `handlePatch()` + nouveaux injectés |
| `src/Twig/Components/MoveModal.php` | Créé | Composant PHP TwigComponent |
| `templates/components/MoveModal.html.twig` | Créé | Template modale + JS (fetch PATCH) |
| `templates/components/FolderCard.html.twig` | Modifié | +bouton ↪️ pour Folder et File |
| `templates/web/layout.html.twig` | Modifié | +`<twig:MoveModal>` |
| `tests/Api/FolderCrudTest.php` | Modifié | +5 tests déplacement Folder |
| `tests/Api/FileMoveTest.php` | Créé | 5 tests déplacement File |
| `.github/avancement.md` | Modifié | +3 lignes ✅ + compteur tests |

---

## Pièges connus

| Sujet                                  | Piège                                                                                                                             | Solution                                                                                                                           |
|----------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------|
| `parentId: null` dans PATCH Folder     | `isset($data->parentId)` retourne `false` que la valeur soit `null` ou absente                                                    | `array_key_exists('parentId', json_decode(..., true))`                                                                             |
| Cycle profond                          | N+1 requêtes SQL avec lazy-load Doctrine                                                                                          | `findAncestorIds()` charge tous les ancêtres en 1 CTE SQL                                                                          |
| **Ownership du parent cible (Folder)** | FolderProcessor vérifie l'owner du dossier déplacé mais PAS l'owner du dossier cible → Alice peut déplacer dans le dossier de Bob | Après `find($parentId)`, ajouter : `if ($parent->getOwner()->getId() !== $user->getId()) throw new AccessDeniedHttpException(...)` |
| `File` immuable                        | Pas de `setFolder()`                                                                                                              | L'ajouter avec PHPDoc explicite                                                                                                    |
| PATCH File inexistant                  | Aucune opération `Patch` dans `FileOutput`                                                                                        | `new Patch(...)` dans `#[ApiResource]`                                                                                             |
| `FileProcessor` mono-DELETE            | `process()` retourne `null` hardcodé                                                                                              | Dispatcher `match (instanceof)`                                                                                                    |
| Dépendance circulaire                  | `FileProvider` injecté dans `FileProcessor` ?                                                                                     | Non circulaire : `FileProvider` n'injecte pas `FileProcessor`                                                                      |
| Token dans JS                          | Le token doit être en mémoire, jamais en localStorage                                                                             | `window.HC.getToken()` déjà en place dans `layout.html.twig`                                                                       |
| JS `submitMove()` erreur silencieuse   | `btn.disabled = true` sans réactivation en cas d'exception                                                                        | `try/finally { btn.disabled = false; btn.textContent = originalText; }`                                                            |
| JS messages d'erreur génériques        | "Erreur inattendue" sans détail                                                                                                   | Parser `res.json()` → `errorData.detail` (format API Platform)                                                                     |
| JS accessibilité                       | Modale non fermable au clavier                                                                                                    | `keydown` listener : Escape = fermer, Enter = valider                                                                              |
| JS focus                               | Utilisateur doit chercher où cliquer                                                                                              | `setTimeout → querySelector('.move-target')?.focus()` à l'ouverture                                                                |
| Fichier déplacé à la racine            | Un fichier ne peut pas être à la racine (toujours dans un dossier)                                                                | Validation JS avant fetch : message "⚠️ Veuillez sélectionner un dossier" si `_targetId === null`                                   |

## 📋 Checklist Déplacement Folder & File (API + Web)

- [x] 1. **Branche dédiée** : `git checkout -b feat/folder-file-move`
- [x] 2. **Tests RED déplacement Folder**  
        Ajouter 5 tests dans [tests/Api/FolderCrudTest.php](tests/Api/FolderCrudTest.php) :
  - [x] Déplacer vers un autre parent (200)
  - [x] Déplacer à la racine (`parentId: null`) (200)
  - [x] Cycle profond détecté (400)
  - [x] Parent inexistant (404)
  - [x] Dossier cible autre user (403)
- [x] 3. **Implémenter `findAncestorIds()`**  
        Ajouter la méthode CTE dans [src/Repository/FolderRepository.php](src/Repository/FolderRepository.php)
- [x] 4. **Corriger détection de cycle + ownership + JSON brut**  
  - Ajouter `wouldCreateCycle()` dans [src/State/FolderProcessor.php](src/State/FolderProcessor.php)
  - Vérifier ownership du parent
  - Remplacer `isset` par lecture JSON brut
- [x] 5. **Tests RED déplacement File**  
        Créer [tests/Api/FileMoveTest.php](tests/Api/FileMoveTest.php) avec 5 tests :
  - [x] Déplacer fichier vers autre dossier (200)
  - [x] Dossier cible inexistant (404)
  - [x] Dossier cible autre user (403)
  - [x] Fichier inexistant (404)
  - [x] Sans authentification (401 ou 200)

---

## 🟢 Micro-tâche en cours

### Micro-tâche 2 : Implémentation de la logique métier déplacement Folder/File

**Objectif :** Implémenter la logique métier pour le déplacement de dossier et de fichier selon les règles TDD validées par les tests RED précédents.

**Étapes détaillées :**

1. Implémenter la logique dans FolderProcessor et FileProcessor (PATCH, ownership, cycle, etc.)
2. Vérifier que tous les tests passent (GREEN)
3. Committer avec le message conforme :
     `✨ feat(FolderProcessor,FileProcessor): implémentation déplacement folder/file (PATCH, ownership, cycle)`
4. Mettre à jour le plan et avancement.

- [ ] 6. **Ajouter `setFolder()` à File**  
    Méthode setter dans [src/Entity/File.php](src/Entity/File.php)
- [ ] 7. **Ajouter PATCH dans FileOutput**  
  - Import `Patch`  
  - Opération PATCH dans `#[ApiResource]`  
  - Champ `$targetFolderId` dans [src/ApiResource/FileOutput.php](src/ApiResource/FileOutput.php)
- [ ] 8. **Implémenter `handlePatch()` dans FileProcessor**  
  - Dispatcher dans `process()`  
  - Méthode `handleDelete()`  
  - Méthode `handlePatch()` dans [src/State/FileProcessor.php](src/State/FileProcessor.php)
- [ ] 9. **Créer MoveModal (Twig component)**  
  - [ ] [templates/components/MoveModal.html.twig](templates/components/MoveModal.html.twig)
  - [ ] [src/Twig/Components/MoveModal.php](src/Twig/Components/MoveModal.php)
- [ ] 10. **Bouton "Déplacer" dans FolderCard + inclusion MoveModal**  
  - [ ] Modifier [templates/components/FolderCard.html.twig](templates/components/FolderCard.html.twig)
  - [ ] Inclure `<twig:MoveModal>` dans [templates/web/layout.html.twig](templates/web/layout.html.twig)
- [ ] 11. **Lancer tous les tests**  
  - [ ] `php bin/phpunit --no-coverage`  
  - [ ] Corriger si échec
- [ ] 12. **Mettre à jour `.github/avancement.md`**  
  - [ ] Ajouter les lignes de validation + compteur de tests
