<?php

declare(strict_types=1);

namespace App\Service\Takeout;

/**
 * Un média trouvé dans l'arborescence extraite d'un export Google Takeout,
 * avec son fichier de métadonnées associé s'il existe — cf. TakeoutStructureParser.
 *
 * $albumName (#478) : nom du sous-dossier Google Photos contenant ce média,
 * s'il s'agit d'un véritable album nommé par l'utilisateur (null pour les
 * dossiers techniques "Photos from <année>" ou un média à la racine).
 */
final readonly class TakeoutMediaEntry
{
    public function __construct(
        public string $mediaPath,
        public ?string $metadataPath,
        public ?string $albumName = null,
    ) {}
}
