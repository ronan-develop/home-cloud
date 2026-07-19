<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\UploadBatch;
use App\Interface\MediaProcessorInterface;

/**
 * Décide, côté serveur, si un lot d'upload est traité immédiatement (après la
 * réponse HTTP, sur kernel.terminate) ou déporté au worker Messenger.
 *
 * Le worker ne doit servir qu'aux lots lourds : un petit lot de photos se traite
 * en quelques secondes sans mobiliser la file. Deux critères déclenchent le
 * déport :
 * - taille cumulée strictement supérieure au seuil configuré ;
 * - présence d'au moins un RAW (décodage preview de loin le plus coûteux, un seul
 *   RAW pouvant peser plus que dix JPEG).
 *
 * La reconnaissance RAW délègue à MediaProcessor::isRaw — seule source de vérité,
 * jamais dupliquée ici.
 */
final class UploadRoutingDecider
{
    public function __construct(
        private readonly MediaProcessorInterface $mediaProcessor,
        private readonly int $deferredThresholdBytes,
    ) {}

    /**
     * @param list<string> $filenames noms des fichiers du lot (extension suffit)
     *
     * @return self::MODE_* ('immediate' | 'deferred')
     */
    public function decide(int $totalSize, array $filenames): string
    {
        if ($totalSize > $this->deferredThresholdBytes) {
            return UploadBatch::MODE_DEFERRED;
        }

        foreach ($filenames as $filename) {
            if ($this->mediaProcessor->isRaw($filename)) {
                return UploadBatch::MODE_DEFERRED;
            }
        }

        return UploadBatch::MODE_IMMEDIATE;
    }
}
