<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatché une fois tous les ZIP extraits (#520, dernier
 * TakeoutImportExtractMessage traité sans ZIP restant) — déclenche le
 * parsing de l'arborescence extraite et l'import des médias. Reste en un
 * seul message : cette phase parcourt déjà les fichiers un par un avec flush
 * par tranches (TakeoutImportHandler::FLUSH_BATCH_SIZE), robuste face au
 * volume contrairement à l'extraction qui traitait tous les ZIP d'un coup.
 */
final readonly class TakeoutImportProcessMessage
{
    public function __construct(
        public string $takeoutImportId,
    ) {}
}
