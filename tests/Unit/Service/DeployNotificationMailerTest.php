<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Monitoring\DeployNotificationMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

/**
 * TDD RED → GREEN : email récapitulatif du déploiement nocturne (#421).
 * Un seul email par nuit, jamais si toutes les instances sont "skipped",
 * jamais d'exception si le destinataire n'est pas configuré.
 */
final class DeployNotificationMailerTest extends TestCase
{
    private function makeService(
        MailerInterface $mailer,
        string $reportEmail = 'ronan@lenouvel.me',
        ?LoggerInterface $logger = null,
    ): DeployNotificationMailer {
        return new DeployNotificationMailer(
            $mailer,
            $reportEmail,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    public function testSendsReportToConfiguredRecipient(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $sentTo = null;
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            function (TemplatedEmail $message) use (&$sentTo) {
                $sentTo = $message->getTo()[0]->getAddress();
            }
        );

        $service = $this->makeService($mailer);
        $service->sendDeployReport([
            ['instance' => 'yannick', 'status' => 'ok', 'step' => null, 'sha' => 'abc1234'],
        ]);

        self::assertSame('ronan@lenouvel.me', $sentTo);
    }

    public function testFromAddressIsNoReplyLenouvelMe(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $sentFrom = null;
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            function (TemplatedEmail $message) use (&$sentFrom) {
                $sentFrom = $message->getFrom()[0]->getAddress();
            }
        );

        $service = $this->makeService($mailer);
        $service->sendDeployReport([
            ['instance' => 'yannick', 'status' => 'ok', 'step' => null, 'sha' => 'abc1234'],
        ]);

        self::assertSame('no-reply@lenouvel.me', $sentFrom);
    }

    public function testDistinguishesOkFailedAndSkippedStatuses(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $context = null;
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            function (TemplatedEmail $message) use (&$context) {
                $context = $message->getContext();
            }
        );

        $service = $this->makeService($mailer);
        $service->sendDeployReport([
            ['instance' => 'yannick', 'status' => 'ok', 'step' => null, 'sha' => 'abc1234'],
            ['instance' => 'coralie', 'status' => 'failed', 'step' => 'migrations', 'sha' => null],
            ['instance' => 'elea', 'status' => 'skipped', 'step' => null, 'sha' => null],
        ]);

        self::assertArrayHasKey('results', $context);
        self::assertCount(3, $context['results']);
        self::assertSame('failed', $context['results'][1]['status']);
        self::assertSame('migrations', $context['results'][1]['step']);
    }

    public function testSendsNoEmailWhenAllInstancesAreSkipped(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $service = $this->makeService($mailer);
        $service->sendDeployReport([
            ['instance' => 'yannick', 'status' => 'skipped', 'step' => null, 'sha' => null],
            ['instance' => 'coralie', 'status' => 'skipped', 'step' => null, 'sha' => null],
        ]);
    }

    public function testSendsEmailWhenAtLeastOneInstanceIsPostponed(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $context = null;
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            function (TemplatedEmail $message) use (&$context) {
                $context = $message->getContext();
            }
        );

        $service = $this->makeService($mailer);
        $service->sendDeployReport([
            ['instance' => 'yannick', 'status' => 'postponed', 'step' => null, 'sha' => null],
            ['instance' => 'coralie', 'status' => 'skipped', 'step' => null, 'sha' => null],
        ]);

        self::assertSame('postponed', $context['results'][0]['status']);
    }

    public function testSendsNoEmailWithEmptyResults(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $service = $this->makeService($mailer);
        $service->sendDeployReport([]);
    }

    // #421 étape suivante : un échec par commande introuvable (composer/php
    // absent du PATH cron) ne se résout jamais par un simple retry la nuit
    // suivante, contrairement à "failed" — le message doit le dire clairement
    // au lieu de rassurer à tort ("aucune action requise"), cause du silence
    // de 2 semaines constaté sur l'instance damien (2026-09-12 → 2026-09-27).
    public function testSendsEmailWhenAtLeastOneInstanceIsCritical(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $context = null;
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            function (TemplatedEmail $message) use (&$context) {
                $context = $message->getContext();
            }
        );

        $service = $this->makeService($mailer);
        $service->sendDeployReport([
            ['instance' => 'damien', 'status' => 'critical', 'step' => 'composer install', 'sha' => null],
            ['instance' => 'elea', 'status' => 'skipped', 'step' => null, 'sha' => null],
        ]);

        self::assertSame('critical', $context['results'][0]['status']);
        self::assertSame('composer install', $context['results'][0]['step']);
    }

    public function testDoesNotThrowWhenRecipientIsEmpty(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $service = $this->makeService($mailer, reportEmail: '', logger: $logger);
        $service->sendDeployReport([
            ['instance' => 'yannick', 'status' => 'ok', 'step' => null, 'sha' => 'abc1234'],
        ]);
    }
}
