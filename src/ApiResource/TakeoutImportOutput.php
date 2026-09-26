<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Controller\Api\TakeoutImportUploadController;
use App\State\TakeoutImportProvider;

/**
 * DTO de sortie pour le suivi d'un import Google Photos Takeout (#327).
 *
 * Le POST accepte un ou plusieurs fichiers `takeout-*.zip` en
 * multipart/form-data et retourne immédiatement l'id de l'import (202) —
 * le traitement réel (extraction, parsing, création des médias) est
 * asynchrone (TakeoutImportHandler via Messenger).
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
            controller: TakeoutImportUploadController::class,
            deserialize: false,
            status: 202,
            openapi: new Model\Operation(
                summary: 'Démarre un import Google Photos Takeout (multipart/form-data).',
                description: 'Corps `multipart/form-data` : un ou plusieurs champs `files[]` (ZIP takeout-*.zip). Retourne 202 avec l\'id de l\'import à suivre via GET /api/v1/takeout-imports/{id}.',
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
