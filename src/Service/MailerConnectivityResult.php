<?php

declare(strict_types=1);

namespace App\Service;

final readonly class MailerConnectivityResult
{
    public function __construct(
        public bool $isConfigured,
        public ?bool $isReachable,
        public ?string $errorMessage,
    ) {}
}
