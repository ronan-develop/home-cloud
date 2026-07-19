<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\UploadBatch;

/**
 * Contrat de notification de fin de traitement d'un lot d'upload (DIP :
 * mockable en test, canal swappable — email aujourd'hui, autre demain).
 */
interface BatchCompletionNotifierInterface
{
    /**
     * Notifie le propriétaire que le lot est prêt et pose notifiedAt.
     */
    public function notify(UploadBatch $batch): void;
}
