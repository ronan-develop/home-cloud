<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Vérifie l'état du mailer pour l'espace admin (#385, suite à #378 où un
 * mot de passe SMTP expiré était resté silencieusement invisible pendant
 * des semaines). Ne jamais exposer $mailerDsn brut (contient un mot de
 * passe en clair) — seul errorMessage (issu de l'exception Symfony) est
 * sûr à afficher.
 */
final readonly class MailerConnectivityChecker
{
    private const CONNECTIVITY_TIMEOUT_SECONDS = 3.0;

    public function __construct(
        private string $mailerDsn,
        private SmtpConnectivityProberInterface $prober,
    ) {}

    public function check(): MailerConnectivityResult
    {
        if (str_starts_with($this->mailerDsn, 'null://')) {
            return new MailerConnectivityResult(isConfigured: false, isReachable: null, errorMessage: null);
        }

        try {
            $this->prober->probe($this->mailerDsn, self::CONNECTIVITY_TIMEOUT_SECONDS);
        } catch (\Throwable $e) {
            return new MailerConnectivityResult(isConfigured: true, isReachable: false, errorMessage: $e->getMessage());
        }

        return new MailerConnectivityResult(isConfigured: true, isReachable: true, errorMessage: null);
    }
}
