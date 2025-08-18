<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\InfoApiProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/info',
            extraProperties: [
                'summary' => 'Point d’entrée public de l’API',
                'description' => 'Message d’accueil, version, endpoint login, etc.',
                'responses' => [
                    '200' => [
                        'description' => 'Informations générales sur l’API',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string'],
                                        'version' => ['type' => 'string'],
                                        'login_endpoint' => ['type' => 'string'],
                                        'info' => ['type' => 'string'],
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            provider: InfoApiProvider::class
        )
    ],
    output: InfoApiOutput::class
)]
class InfoApiOutput
{
    public string $message = 'Bienvenue sur l’API Home Cloud.';
    public string $version = '1.0.0';
    public string $login_endpoint = '/api/login';
    public string $info = 'Authentifiez-vous via POST /api/login avec vos credentials (email/username + password).';
}
