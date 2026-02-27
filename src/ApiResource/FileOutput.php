<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Controller\FileUploadController;
use App\State\FileProcessor;
use App\State\FileProvider;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * DTO partagé lecture/écriture pour la ressource File.
 *
 * Rôle : contrat d'API pour les métadonnées d'un fichier uploadé.
 * Le binaire est reçu via multipart/form-data et stocké par StorageService.
 *
 * Choix :
 * - Pas de PATCH : un fichier est immuable après upload (remplacer = DELETE + POST).
 * - folderId / folderName sont null si non renseignés côté client ;
 *   le Processor résout le folder via DefaultFolderService (priorité : folderId > newFolderName > "Uploads").
 * - normalizationContext SKIP_NULL_VALUES => false : folderId toujours présent en réponse.
 */
#[ApiResource(
    shortName: 'File',
    operations: [
        new Get(
            uriTemplate: '/v1/files/{id}',
            openapi: new Model\Operation(
                summary: 'Récupère les métadonnées d\'un fichier.',
                description: 'Retourne les métadonnées d\'un fichier (id, originalName, mimeType, size, folderId, ownerId, createdAt). Pour télécharger le binaire, utiliser `GET /api/v1/files/{id}/download`.',
            ),
        ),
        new GetCollection(
            uriTemplate: '/v1/files',
            openapi: new Model\Operation(
                summary: 'Liste les fichiers (paginé, filtrable par dossier).',
                description: 'Retourne la liste paginée des fichiers. Paramètre optionnel : `?folderId=<uuid>` pour filtrer par dossier.',
            ),
        ),
        new Post(
            uriTemplate: '/v1/files',
            controller: FileUploadController::class,
            deserialize: false,
            openapi: new Model\Operation(
                summary: 'Upload un fichier (multipart/form-data).',
                description: 'Téléverse un fichier binaire. Corps `multipart/form-data` : champ `file` (binaire obligatoire), `ownerId` (UUID), `folderId` (UUID, optionnel), `newFolderName` (créé à la volée si absent). Les exécutables (.php, .exe, .sh…) sont bloqués.',
            ),
        ),
        new Delete(
            uriTemplate: '/v1/files/{id}',
            openapi: new Model\Operation(
                summary: 'Supprime un fichier.',
                description: 'Supprime les métadonnées en base ET le fichier physique sur disque (ainsi que son thumbnail si c\'est un média).',
            ),
        ),
    ],
    provider: FileProvider::class,
    processor: FileProcessor::class,
    normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => false],
)]
final class FileOutput
{
    // --- Champs de sortie (GET) ---
    public string $id = '';
    public string $originalName = '';
    public string $mimeType = '';
    public int $size = 0;
    public string $path = '';
    public ?string $folderId = null;
    public string $folderName = '';
    public ?string $ownerId = null;
    public string $createdAt = '';

    // --- Champs d'entrée supplémentaires (POST via JSON ou multipart) ---
    /** UUID d'un folder existant. Prioritaire sur newFolderName. */
    public ?string $newFolderName = null;
}
