<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\TakeoutImportExtractMessage;
use PHPUnit\Framework\TestCase;

/**
 * #520 : un ZIP à la fois par message (au lieu de tous les ZIP dans un seul
 * message Messenger) — sur un import volumineux (plusieurs dizaines de Go),
 * traiter tous les ZIP dans un seul cycle de worker dépassait la contention
 * LVE du mutualisé o2switch (process tué au milieu de l'extraction, constaté
 * en conditions réelles le 2026-09-28). Chaque ZIP restant est redispatché
 * comme un nouveau message, laissant le worker s'arrêter proprement entre
 * deux ZIP plutôt qu'être tué en plein milieu d'un seul.
 */
final class TakeoutImportExtractMessageTest extends TestCase
{
    public function testCarriesImportIdCurrentZipPathAndRemainingZipPaths(): void
    {
        $message = new TakeoutImportExtractMessage(
            'import-1',
            '/tmp/a.zip',
            ['/tmp/b.zip', '/tmp/c.zip'],
        );

        $this->assertSame('import-1', $message->takeoutImportId);
        $this->assertSame('/tmp/a.zip', $message->zipPath);
        $this->assertSame(['/tmp/b.zip', '/tmp/c.zip'], $message->remainingZipPaths);
    }
}
