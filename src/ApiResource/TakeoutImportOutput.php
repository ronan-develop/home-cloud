<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Controller\Api\TakeoutImportAbandonController;
use App\Controller\Api\TakeoutImportCreateController;
use App\Controller\Api\TakeoutImportFileUploadController;
use App\Controller\Api\TakeoutImportFilesStatusController;
use App\Controller\Api\TakeoutImportFindPendingController;
use App\Controller\Api\TakeoutImportListPendingController;
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
            uriTemplate: '/v1/takeout-imports/pending',
            controller: TakeoutImportFindPendingController::class,
            read: false,
            openapi: new Model\Operation(
                summary: 'Retrouve l\'import Google Photos Takeout en attente le plus récent de l\'utilisateur courant.',
                description: 'À appeler avant de créer un nouvel import (#481) : si l\'utilisateur a fermé l\'onglet en cours d\'upload, cet import existant doit être repris. Retourne 404 si aucun import "pending" n\'existe.',
            ),
        ),
        new Get(
            uriTemplate: '/v1/takeout-imports/pending-list',
            controller: TakeoutImportListPendingController::class,
            read: false,
            openapi: new Model\Operation(
                summary: 'Liste tous les imports Google Photos Takeout en attente de l\'utilisateur courant.',
                description: 'Distinct de /pending (singulier, le plus récent) : plusieurs imports pending peuvent coexister (deux tentatives depuis deux appareils différents). Affichée sur la page avec reprise/abandon explicites par import (#481).',
            ),
        ),
        new Get(
            uriTemplate: '/v1/takeout-imports/{id}/files/status',
            controller: TakeoutImportFilesStatusController::class,
            read: false,
            output: false,
            openapi: new Model\Operation(
                summary: 'Liste les fichiers déjà partiellement uploadés pour un import en attente.',
                description: 'Retourne pour chaque fichier son dernier chunkIndex confirmé et le hash SHA-256 de son premier chunk déjà écrit, pour permettre au front de reprendre l\'upload au bon endroit sans risquer une corruption (#481).',
            ),
        ),
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
        new Delete(
            uriTemplate: '/v1/takeout-imports/{id}',
            controller: TakeoutImportAbandonController::class,
            read: false,
            output: false,
            openapi: new Model\Operation(
                summary: 'Abandonne un import Google Photos Takeout en attente.',
                description: 'Supprime immédiatement les fichiers déjà uploadés et la ligne en base, sans attendre la purge automatique à 7 jours. Uniquement pour un import "pending" — retourne 400 sinon (#481).',
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
    /** Progress bar pendant l'extraction (#515) : total de ZIP à extraire, connu dès le statut "extracting". */
    public ?int $totalZipCount = null;
    /** Progress bar pendant l'extraction (#515) : ZIP déjà extraits. */
    public int $extractedZipCount = 0;
    /** Liste des imports en attente (#481) : ZIP déjà (au moins partiellement) reçus pour cet import. */
    public int $filesUploadedCount = 0;
    public string $createdAt = '';
    public ?string $completedAt = null;
    public ?string $errorMessage = null;
}
