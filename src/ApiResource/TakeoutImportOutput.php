<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Controller\Api\TakeoutImportCreateController;
use App\Controller\Api\TakeoutImportFileUploadController;
use App\Controller\Api\TakeoutImportStartController;
use App\State\TakeoutImportProvider;

/**
 * DTO de sortie pour le suivi d'un import Google Photos Takeout (#327).
 *
 * Upload séquentiel en 3 étapes (#466) : un gros POST multipart avec tous les
 * ZIP dépassait la limite de taille de requête du serveur mutualisé (413) dès
 * qu'un utilisateur sélectionnait plusieurs fichiers Takeout volumineux d'un
 * coup. Chaque ZIP est désormais envoyé dans sa propre requête, largement
 * sous la limite, sans changer l'UX (l'utilisateur sélectionne tout en une
 * fois, le front enchaîne les requêtes) :
 *   1. POST /v1/takeout-imports          → crée l'import (pending), pas de fichier
 *   2. POST /v1/takeout-imports/{id}/files → un seul ZIP par appel, répété
 *   3. POST /v1/takeout-imports/{id}/start → dispatch le traitement asynchrone
 */
#[ApiResource(
    shortName: 'TakeoutImport',
    operations: [
        new Get(
            uriTemplate: '/v1/takeout-imports/{id}',
            openapi: new Model\Operation(
                summary: 'Consulte le statut d\'un import Google Photos Takeout.',
                description: 'Retourne le statut (pending, extracting, processing, completed, failed) et les compteurs une fois le traitement terminé.',
            ),
        ),
        new Post(
            uriTemplate: '/v1/takeout-imports',
            controller: TakeoutImportCreateController::class,
            deserialize: false,
            status: 201,
            openapi: new Model\Operation(
                summary: 'Crée un import Google Photos Takeout (sans fichier).',
                description: 'Retourne 201 avec l\'id de l\'import (statut pending). Envoyer ensuite chaque ZIP via POST /v1/takeout-imports/{id}/files, puis démarrer via POST /v1/takeout-imports/{id}/start.',
            ),
        ),
        new Post(
            uriTemplate: '/v1/takeout-imports/{id}/files',
            controller: TakeoutImportFileUploadController::class,
            deserialize: false,
            status: 204,
            output: false,
            openapi: new Model\Operation(
                summary: 'Ajoute un fichier ZIP à un import Google Photos Takeout en attente (multipart/form-data).',
                description: 'Corps `multipart/form-data` : un seul champ `file`. À répéter une fois par ZIP. Retourne 204 sans contenu.',
            ),
        ),
        new Post(
            uriTemplate: '/v1/takeout-imports/{id}/start',
            controller: TakeoutImportStartController::class,
            deserialize: false,
            status: 202,
            openapi: new Model\Operation(
                summary: 'Démarre le traitement asynchrone d\'un import Google Photos Takeout.',
                description: 'À appeler une fois tous les ZIP envoyés via POST /v1/takeout-imports/{id}/files. Retourne 202 ; suivre l\'avancement via GET /v1/takeout-imports/{id}.',
            ),
        ),
    ],
    provider: TakeoutImportProvider::class,
)]
final class TakeoutImportOutput
{
    public string $id = '';
    public string $status = '';
    public ?int $mediaImportedCount = null;
    public ?int $duplicatesSkippedCount = null;
    public ?int $unrecognizedFilesCount = null;
    /** Progress bar (#327) : total de médias détectés, connu dès le statut "processing". */
    public ?int $totalMediaCount = null;
    /** Progress bar (#327) : médias déjà traités (importés ou doublons confondus). */
    public int $processedCount = 0;
    public string $createdAt = '';
    public ?string $completedAt = null;
    public ?string $errorMessage = null;
}
