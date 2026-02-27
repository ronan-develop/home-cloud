<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message Messenger déclenché après l'upload d'un fichier potentiellement média.
 *
 * Rôle : transporter l'identifiant du File vers le handler asynchrone
 * qui se chargera d'extraire les EXIF et de générer le thumbnail.
 *
 * Choix :
 * - Seul l'UUID est transporté (pas l'entité) pour éviter les problèmes
 *   de sérialisation Doctrine dans la queue.
 * - Le handler ignorera silencieusement les Files non-média (PDF, ZIP…).
 */
final readonly class MediaProcessMessage
{
    public function __construct(public string $fileId) {}
}
