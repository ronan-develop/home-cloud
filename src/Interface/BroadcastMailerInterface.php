<?php

declare(strict_types=1);

namespace App\Interface;

interface BroadcastMailerInterface
{
    /**
     * Envoie un message admin à tous les comptes de l'instance courante
     * (propriétaires et invités). Retourne le nombre de destinataires
     * (envoyés réellement, ou qui l'auraient été en dry-run).
     */
    public function sendToAllUsers(string $subject, string $htmlBody, bool $dryRun): int;
}
