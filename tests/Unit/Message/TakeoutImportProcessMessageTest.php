<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\TakeoutImportProcessMessage;
use PHPUnit\Framework\TestCase;

/**
 * #520 : dispatché une fois TOUS les ZIP extraits (dernier
 * TakeoutImportExtractMessage traité sans ZIP restant) — déclenche la phase
 * parsing/import, qui reste dans un seul message (elle n'a pas le même
 * problème de volume que l'extraction : le hash/import se fait fichier par
 * fichier avec flush par tranches, déjà robuste face à un gros volume).
 */
final class TakeoutImportProcessMessageTest extends TestCase
{
    public function testCarriesImportId(): void
    {
        $message = new TakeoutImportProcessMessage('import-1');

        $this->assertSame('import-1', $message->takeoutImportId);
    }
}
