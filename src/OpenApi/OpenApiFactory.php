<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Décorateur OpenApiFactory — complète la spec générée par API Platform.
 *
 * Responsabilités :
 * 1. Applique JWT Bearer globalement sur toutes les opérations
 * 2. Retire le JWT des routes publiques (login, token/refresh)
 * 3. Injecte les routes gérées par des controllers Symfony (non vues par AP) :
 *    - GET  /api/v1/files/{id}/download
 *    - GET  /api/v1/medias/{id}/thumbnail
 *    - POST /api/v1/auth/token/refresh
 * 4. Corrige le requestBody de POST /api/v1/files en multipart/form-data
 */
#[AsDecorator(decorates: 'api_platform.openapi.factory')]
final class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(private readonly OpenApiFactoryInterface $decorated) {}

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);

        $this->applyGlobalJwtSecurity($openApi);
        $this->removeJwtFromPublicRoutes($openApi);
        $this->addFileDownloadRoute($openApi);
        $this->addMediaThumbnailRoute($openApi);
        $this->addTokenRefreshRoute($openApi);
        $this->fixFileUploadRequestBody($openApi);

        return $openApi;
    }

    /** Ajoute security: [{JWT: []}] sur toutes les opérations existantes. */
    private function applyGlobalJwtSecurity(OpenApi $openApi): void
    {
        $jwtSecurity = [['JWT' => []]];

        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            foreach (['Get', 'Post', 'Put', 'Patch', 'Delete'] as $method) {
                $getter = 'get' . $method;
                $wither = 'with' . $method;
                /** @var Model\Operation|null $op */
                $op = $pathItem->$getter();
                if ($op === null) {
                    continue;
                }
                $openApi->getPaths()->addPath($path, $pathItem->$wither(
                    $op->withSecurity($jwtSecurity)
                ));
                // Re-fetch le pathItem mis à jour pour la prochaine itération
                $pathItem = $openApi->getPaths()->getPath($path);
            }
        }
    }

    /** Retire le JWT des endpoints publics (login, token/refresh). */
    private function removeJwtFromPublicRoutes(OpenApi $openApi): void
    {
        $publicPaths = [
            '/api/v1/auth/login' => 'Post',
            '/api/v1/auth/token/refresh' => 'Post',
        ];

        foreach ($publicPaths as $path => $method) {
            $pathItem = $openApi->getPaths()->getPath($path);
            if ($pathItem === null) {
                continue;
            }
            $getter = 'get' . $method;
            $wither = 'with' . $method;
            $op = $pathItem->$getter();
            if ($op === null) {
                continue;
            }
            $openApi->getPaths()->addPath($path, $pathItem->$wither(
                $op->withSecurity([])
            ));
        }
    }

    /** GET /api/v1/files/{id}/download — téléchargement du binaire déchiffré. */
    private function addFileDownloadRoute(OpenApi $openApi): void
    {
        $path = '/api/v1/files/{id}/download';
        $pathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'downloadFile',
                tags: ['File'],
                responses: [
                    '200' => new Model\Response(
                        description: 'Contenu binaire du fichier.',
                        content: new \ArrayObject(['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]]),
                    ),
                    '404' => new Model\Response(description: 'Fichier introuvable.'),
                ],
                summary: 'Télécharge le binaire d\'un fichier.',
                description: 'Retourne le contenu binaire déchiffré du fichier avec les headers `Content-Type` et `Content-Disposition`. Le fichier est streamé sans être chargé intégralement en RAM.',
                parameters: [
                    new Model\Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'UUID du fichier',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid'],
                    ),
                ],
                security: [['JWT' => []]],
            )
        );
        $openApi->getPaths()->addPath($path, $pathItem);
    }

    /** GET /api/v1/medias/{id}/thumbnail — image JPEG 320px. */
    private function addMediaThumbnailRoute(OpenApi $openApi): void
    {
        $path = '/api/v1/medias/{id}/thumbnail';
        $pathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getMediaThumbnail',
                tags: ['Media'],
                responses: [
                    '200' => new Model\Response(
                        description: 'Image JPEG du thumbnail.',
                        content: new \ArrayObject(['image/jpeg' => ['schema' => ['type' => 'string', 'format' => 'binary']]]),
                    ),
                    '404' => new Model\Response(description: 'Média ou thumbnail introuvable.'),
                ],
                summary: 'Retourne le thumbnail JPEG d\'un média.',
                description: 'Image JPEG 320px de large générée de façon asynchrone après l\'upload. Retourne 404 si le thumbnail n\'est pas encore disponible.',
                parameters: [
                    new Model\Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'UUID du média',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid'],
                    ),
                ],
                security: [['JWT' => []]],
            )
        );
        $openApi->getPaths()->addPath($path, $pathItem);
    }

    /** POST /api/v1/auth/token/refresh — échange un refresh token contre un nouvel access token. */
    private function addTokenRefreshRoute(OpenApi $openApi): void
    {
        $path = '/api/v1/auth/token/refresh';
        if ($openApi->getPaths()->getPath($path) !== null) {
            return; // déjà présent (ne devrait pas arriver)
        }

        $pathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'refreshToken',
                tags: ['Login Check'],
                responses: [
                    '200' => new Model\Response(
                        description: 'Nouveaux tokens.',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => ['type' => 'string', 'description' => 'Nouveau JWT access token'],
                                        'refreshToken' => ['type' => 'string', 'description' => 'Nouveau refresh token (rotation)'],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                    '401' => new Model\Response(description: 'Refresh token invalide ou expiré.'),
                ],
                summary: 'Rafraîchit le token JWT.',
                description: 'Échange un `refreshToken` valide contre un nouvel `accessToken` (et un nouveau `refreshToken` par rotation). TTL du refresh token : 7 jours.',
                requestBody: new Model\RequestBody(
                    description: 'Refresh token',
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['refreshToken'],
                                'properties' => [
                                    'refreshToken' => ['type' => 'string', 'example' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'],
                                ],
                            ],
                        ],
                    ]),
                ),
                security: [], // endpoint public
            )
        );
        $openApi->getPaths()->addPath($path, $pathItem);
    }

    /** Remplace le requestBody de POST /api/v1/files par multipart/form-data. */
    private function fixFileUploadRequestBody(OpenApi $openApi): void
    {
        $path = '/api/v1/files';
        $pathItem = $openApi->getPaths()->getPath($path);
        if ($pathItem === null || $pathItem->getPost() === null) {
            return;
        }

        $multipartRequestBody = new Model\RequestBody(
            description: 'Fichier à uploader avec ses métadonnées.',
            required: true,
            content: new \ArrayObject([
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['file', 'ownerId'],
                        'properties' => [
                            'file' => [
                                'type' => 'string',
                                'format' => 'binary',
                                'description' => 'Binaire du fichier. Exécutables bloqués (.php, .exe, .sh…).',
                            ],
                            'ownerId' => [
                                'type' => 'string',
                                'format' => 'uuid',
                                'description' => 'UUID de l\'utilisateur propriétaire.',
                            ],
                            'folderId' => [
                                'type' => 'string',
                                'format' => 'uuid',
                                'nullable' => true,
                                'description' => 'UUID du dossier destination. Prioritaire sur newFolderName.',
                            ],
                            'newFolderName' => [
                                'type' => 'string',
                                'nullable' => true,
                                'description' => 'Crée un nouveau dossier à la racine si folderId absent. Ignoré si folderId fourni.',
                            ],
                        ],
                    ],
                ],
            ]),
        );

        $openApi->getPaths()->addPath($path, $pathItem->withPost(
            $pathItem->getPost()->withRequestBody($multipartRequestBody)
        ));
    }
}
