<?php

declare(strict_types=1);

namespace App\Exception\Video;

/**
 * Contrat racine des échecs d'extraction de frame vidéo.
 *
 * Toute exception levée par un extracteur vidéo l'implémente : un `catch`
 * unique suffit à dégrader gracieusement, sans énumérer les cas — même
 * principe que RawPreviewExtractorException dans le package RAW.
 */
interface VideoThumbnailExtractionException extends \Throwable
{
}
