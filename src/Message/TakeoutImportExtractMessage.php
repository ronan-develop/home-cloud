<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Un ZIP à la fois (#520) : l'extraction complète d'un import volumineux
 * (plusieurs dizaines de Go) dans un seul message Messenger dépassait la
 * contention LVE du mutualisé o2switch — le worker était tué en plein
 * milieu, sans exception catchable côté PHP (SIGKILL). Chaque handler
 * n'extrait qu'un seul ZIP, puis redispatche $remainingZipPaths comme un
 * nouveau message — laissant le worker s'arrêter proprement entre deux ZIP.
 */
final readonly class TakeoutImportExtractMessage
{
    /**
     * @param string[] $remainingZipPaths ZIP restants à extraire après celui-ci
     */
    public function __construct(
        public string $takeoutImportId,
        public string $zipPath,
        public array $remainingZipPaths,
    ) {}
}
