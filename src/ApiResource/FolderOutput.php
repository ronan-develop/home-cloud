<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\State\FolderProcessor;
use App\State\FolderProvider;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

#[ApiResource(
    shortName: 'Folder',
    operations: [
        new Get(
            uriTemplate: '/v1/folders/{id}',
            openapi: new Model\Operation(
                summary: 'Récupère un dossier par son UUID.',
                description: 'Retourne les métadonnées d\'un dossier (id, name, parentId, ownerId, createdAt).',
            ),
        ),
        new GetCollection(
            uriTemplate: '/v1/folders',
            openapi: new Model\Operation(
                summary: 'Liste tous les dossiers (paginé).',
                description: 'Retourne la liste paginée des dossiers de l\'utilisateur courant.',
            ),
        ),
        new Post(
            uriTemplate: '/v1/folders',
            openapi: new Model\Operation(
                summary: 'Crée un nouveau dossier.',
                description: 'Crée un dossier. `parentId` est optionnel ; si absent, le dossier est à la racine.',
            ),
        ),
        new Patch(
            uriTemplate: '/v1/folders/{id}',
            openapi: new Model\Operation(
                summary: 'Met à jour un dossier.',
                description: 'Permet de renommer le dossier ou de déplacer dans un autre parent (`parentId`).',
            ),
        ),
        new Delete(
            uriTemplate: '/v1/folders/{id}',
            openapi: new Model\Operation(
                summary: 'Supprime un dossier.',
                description: 'Supprime le dossier. Attention : les sous-dossiers et fichiers liés doivent être supprimés préalablement.',
            ),
        ),
    ],
    provider: FolderProvider::class,
    processor: FolderProcessor::class,
    normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => false],
)]
/**
 * DTO partagé lecture/écriture pour la ressource Folder.
 *
 * Rôle : sert à la fois de contrat de sortie (GET) et de contrat d'entrée
 * (POST, PATCH) — le désérialiseur Symfony y injecte les champs du corps JSON,
 * le sérialiseur en produit la réponse JSON.
 *
 * Choix : un seul DTO pour simplifier la stack (pas d'InputType séparé).
 * Les champs non renseignés lors d'un PATCH restent à leur valeur par défaut
 * (string vide / null) ; le Processor décide quels champs appliquer.
 *
 * normalizationContext SKIP_NULL_VALUES => false : garantit que parentId est
 * toujours présent dans la réponse, même lorsqu'il vaut null.
 */
final class FolderOutput
{
    public string $id = '';
    public string $name = '';
    public ?string $parentId = null;
    public ?string $ownerId = null;
    public string $mediaType = 'general';
    public string $createdAt = '';
}
