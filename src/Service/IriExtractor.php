<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Centralise l'extraction d'UUID depuis une IRI API Platform.
 * Élimine la duplication de basename()/strpos() dans FolderProcessor et FileProcessor.
 */
final readonly class IriExtractor
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function extractUuid(string $iri): string
    {
        // Cas 1 : IRI avec chemin (/api/folders/uuid) → extraire le segment final
        if (str_contains($iri, '/')) {
            if (!preg_match('#/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$#i', $iri, $matches)) {
                $this->logger->error('Invalid IRI format', [
                    'iri'     => $iri,
                    'context' => 'iri_extractor',
                ]);
                throw new BadRequestHttpException('Invalid IRI format');
            }
            return $matches[1];
        }

        // Cas 2 : UUID brut — valider le format
        if (!preg_match('#^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$#i', $iri)) {
            $this->logger->error('Invalid UUID format', [
                'value'   => $iri,
                'context' => 'iri_extractor',
            ]);
            throw new BadRequestHttpException('Invalid IRI format');
        }

        return $iri;
    }

    /**
     * Extrait plusieurs UUIDs depuis un tableau d'IRIs.
     *
     * @param string[] $iris
     * @return string[]
     */
    public function extractUuids(array $iris): array
    {
        return array_map(
            fn(string $iri) => $this->extractUuid($iri),
            $iris
        );
    }
}
