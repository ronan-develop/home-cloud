<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

/**
 * Lit l'état du transport Messenger "failed" de l'instance courante (#377).
 *
 * Réutilise le même mécanisme que `messenger:failed:show`
 * (Symfony\Component\Messenger\Command\FailedMessagesShowCommand) plutôt que
 * du SQL brut sur messenger_messages, pour rester robuste au schéma interne
 * du composant.
 */
final class CronHealthReporter
{
    private const MAX_RECENT_FAILURES = 20;

    public function __construct(
        #[Autowire(service: 'messenger.transport.failed')]
        private readonly ListableReceiverInterface $failedReceiver,
    ) {}

    public function getReport(): CronHealthReport
    {
        $failedCount = 0;
        $recentFailures = [];

        foreach ($this->failedReceiver->all() as $envelope) {
            ++$failedCount;

            if (\count($recentFailures) >= self::MAX_RECENT_FAILURES) {
                continue;
            }

            $errorStamp = $envelope->last(ErrorDetailsStamp::class);
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);

            $recentFailures[] = new CronFailureItem(
                $envelope->getMessage()::class,
                $errorStamp?->getExceptionMessage() ?? 'Erreur inconnue',
                $redeliveryStamp?->getRedeliveredAt(),
            );
        }

        return new CronHealthReport($failedCount, $recentFailures);
    }
}
