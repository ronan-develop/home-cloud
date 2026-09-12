<?php

declare(strict_types=1);

namespace App\Interface;

interface DeployNotificationMailerInterface
{
    /**
     * @param list<array{instance: string, status: 'ok'|'failed'|'skipped', step: ?string, sha: ?string}> $results
     */
    public function sendDeployReport(array $results): void;
}
