<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\DeployNotificationMailerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Email récapitulatif du déploiement nocturne (#421) — un seul envoi par
 * nuit, jamais si toutes les instances sont "skipped" (aucun merge depuis
 * la veille), sinon un email quotidien inutile finirait par être ignoré et
 * un vrai échec passerait inaperçu.
 */
final readonly class DeployNotificationMailer implements DeployNotificationMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private string $reportEmail,
        private LoggerInterface $logger,
    ) {}

    public function sendDeployReport(array $results): void
    {
        $relevant = array_filter($results, static fn (array $r) => $r['status'] !== 'skipped');
        if ($relevant === []) {
            return;
        }

        if ($this->reportEmail === '') {
            $this->logger->warning('DeployNotificationMailer : DEPLOY_REPORT_EMAIL absent, rapport non envoyé');
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@lenouvel.me'))
            ->to($this->reportEmail)
            ->subject('Déploiement nocturne — HomeCloud')
            ->htmlTemplate('emails/deploy_report.html.twig')
            ->context([
                'results'     => array_values($results),
                'accentColor' => EmailBranding::ACCENT_COLOR,
            ]);

        $this->mailer->send($email);
    }
}
