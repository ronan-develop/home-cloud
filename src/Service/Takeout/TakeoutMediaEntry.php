<?php

declare(strict_types=1);

namespace App\Service\Takeout;

/**
 * Un média trouvé dans l'arborescence extraite d'un export Google Takeout,
 * avec son fichier de métadonnées associé s'il existe — cf. TakeoutStructureParser.
 */
final readonly class TakeoutMediaEntry
{
    public function __construct(
        public string $mediaPath,
        public ?string $metadataPath,
    ) {}
}
