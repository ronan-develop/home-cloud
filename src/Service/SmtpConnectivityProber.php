<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

final class SmtpConnectivityProber implements SmtpConnectivityProberInterface
{
    public function probe(string $dsn, float $timeoutSeconds): void
    {
        $transport = Transport::fromDsn($dsn);

        if (!$transport instanceof SmtpTransport) {
            return;
        }

        $transport->getStream()->setTimeout($timeoutSeconds);
        $transport->start();
        $transport->stop();
    }
}
