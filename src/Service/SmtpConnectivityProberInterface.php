<?php

declare(strict_types=1);

namespace App\Service;

interface SmtpConnectivityProberInterface
{
    /**
     * Effectue un handshake SMTP réel (connexion, EHLO, TLS, AUTH) sans
     * envoyer de message, pour vérifier que le DSN est utilisable. Lève une
     * exception en cas d'échec.
     */
    public function probe(string $dsn, float $timeoutSeconds): void;
}
