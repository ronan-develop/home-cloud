<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\File;
use App\Entity\Media;

interface MediaProcessorInterface
{
    /**
     * Crée (ou retourne, si déjà traité) le Media associé à un File.
     * Retourne null si le type MIME du fichier n'est pas un média supporté.
     */
    public function process(File $file): ?Media;

    /**
     * Un fichier mérite-t-il un Media, sans toucher au disque ? Permet aux
     * appelants (contrôleurs d'upload) de décider s'il faut dispatcher/traiter
     * sans dupliquer la logique de reconnaissance (mimeType + extensions RAW).
     */
    public function supports(string $mimeType, string $originalName): bool;
}
