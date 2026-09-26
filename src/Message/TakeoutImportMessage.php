<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message Messenger déclenché après l'upload du/des ZIP d'un export Google
 * Takeout (#327) — traitement asynchrone hors requête HTTP.
 *
 * Seuls les identifiants sont transportés (pas les entités, cf. contrainte
 * de sérialisation Doctrine déjà connue sur MediaProcessMessage). Les ZIP
 * sont déjà stockés sur disque à un emplacement temporaire par l'endpoint
 * d'upload avant dispatch — $zipPaths pointe vers ces fichiers.
 */
final readonly class TakeoutImportMessage
{
    /**
     * @param string[] $zipPaths Chemins absolus des ZIP déjà écrits sur disque
     */
    public function __construct(
        public string $takeoutImportId,
        public array $zipPaths,
    ) {}
}
