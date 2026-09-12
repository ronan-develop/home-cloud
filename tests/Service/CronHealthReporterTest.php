<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Message\MediaProcessMessage;
use App\Service\CronHealthReporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * TDD RED → GREEN : lecture de l'état du transport Messenger "failed" pour
 * l'écran admin de santé cron (#377) — réutilise le même mécanisme que
 * `messenger:failed:show` (ErrorDetailsStamp), pas de SQL brut sur
 * messenger_messages pour rester robuste au schéma interne du composant.
 */
final class CronHealthReporterTest extends KernelTestCase
{
    private CronHealthReporter $reporter;
    private SenderInterface $failedSender;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->reporter = $container->get(CronHealthReporter::class);
        $this->failedSender = $container->get('messenger.transport.failed');

        // Vide le transport failed avant chaque test pour partir d'un état propre.
        if ($this->failedSender instanceof \Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface) {
            foreach ($this->failedSender->all() as $envelope) {
                $this->failedSender->reject($envelope);
            }
        }
    }

    public function testReportsNoFailuresOnEmptyTransport(): void
    {
        $report = $this->reporter->getReport();

        self::assertSame(0, $report->failedCount);
        self::assertSame([], $report->recentFailures);
    }

    public function testReportsFailedMessageWithErrorDetails(): void
    {
        $exception = new RecoverableMessageHandlingException('Le fichier source a disparu');

        $envelope = new Envelope(new MediaProcessMessage('some-file-id'), [
            new ErrorDetailsStamp(
                RecoverableMessageHandlingException::class,
                0,
                'Le fichier source a disparu',
                FlattenException::createFromThrowable($exception),
            ),
            new RedeliveryStamp(3),
        ]);

        $this->failedSender->send($envelope);

        $report = $this->reporter->getReport();

        self::assertSame(1, $report->failedCount);
        self::assertCount(1, $report->recentFailures);
        self::assertSame(MediaProcessMessage::class, $report->recentFailures[0]->messageClass);
        self::assertSame('Le fichier source a disparu', $report->recentFailures[0]->errorMessage);
    }
}
