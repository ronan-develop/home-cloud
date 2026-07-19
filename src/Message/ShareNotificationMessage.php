<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message Messenger : notifier un invité d'un nouveau partage, hors requête HTTP.
 *
 * L'envoi SMTP synchrone bloquait la réponse de /share-create (× nombre
 * d'invités). On transporte l'UUID du Share et le nom de ressource déjà résolu ;
 * le handler recharge le Share et délègue au mailer. Routé vers le transport
 * async (cf. config/packages/messenger.yaml), consommé par le worker.
 *
 * Seul le nom de ressource est transporté (pas l'entité cible) : il est résolu
 * au moment du partage, où le contexte est disponible.
 */
final readonly class ShareNotificationMessage
{
    public function __construct(
        public string $shareId,
        public string $resourceName,
    ) {}
}
