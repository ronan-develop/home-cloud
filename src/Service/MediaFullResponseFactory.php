<?php

declare(strict_types=1);

namespace App\Service;

use RonanLenouvel\RawPreviewExtractor\Exception\RawPreviewExtractorException;
use RonanLenouvel\RawPreviewExtractor\ExtractedPreview;
use RonanLenouvel\RawPreviewExtractor\Orientation;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Construit la réponse d'affichage plein écran d'un média (lightbox, diaporama,
 * partage public).
 *
 * Rôle : un navigateur ne sait pas décoder un RAW. Servir le fichier tel quel
 * faisait télécharger plusieurs dizaines de Mo pour n'afficher qu'une image
 * cassée — et le diaporama répétait l'opération à chaque photo. On sert donc la
 * preview JPEG que l'appareil embarque déjà dans le RAW.
 *
 * Partagé entre la galerie authentifiée et les partages publics, qui avaient la
 * même logique dupliquée.
 *
 * Choix :
 * - Image classique : streamée depuis le disque (BinaryFileResponse), jamais
 *   chargée en mémoire.
 * - RAW : la preview est extraite à la volée (~23 ms), sans cache disque. Le
 *   cache navigateur évite qu'un diaporama en boucle ne retape le serveur.
 * - Dégradation gracieuse : un RAW sans preview exploitable est servi tel quel
 *   plutôt que de faire échouer la requête.
 */
final readonly class MediaFullResponseFactory
{
    /**
     * Durée de cache navigateur. Le contenu d'un média ne change jamais : seule
     * sa suppression le rend obsolète, auquel cas la route répond 404.
     */
    private const CACHE_MAX_AGE = 3600;

    /**
     * Qualité du réencodage, appliqué seulement aux previews à redresser. Assez
     * haute pour rester invisible à l'œil sur une photo affichée en grand.
     */
    private const PREVIEW_QUALITY = 90;

    public function __construct(
        private RawPreviewExtractorInterface $rawPreviewExtractor,
    ) {}

    /**
     * @param string $absolutePath Chemin absolu du fichier média
     * @param string $mimeType     mimeType déclaré du fichier d'origine
     */
    public function create(string $absolutePath, string $mimeType): Response
    {
        if ($this->rawPreviewExtractor->supports($absolutePath)) {
            $previewResponse = $this->createPreviewResponse($absolutePath);

            if ($previewResponse !== null) {
                return $previewResponse;
            }
        }

        return $this->createFileResponse($absolutePath, $mimeType);
    }

    /**
     * Réponse portant la preview JPEG extraite du RAW, ou null si elle est
     * introuvable — à charge de l'appelant de retomber sur le fichier d'origine.
     */
    private function createPreviewResponse(string $absolutePath): ?Response
    {
        try {
            $preview = $this->rawPreviewExtractor->extract($absolutePath);
        } catch (RawPreviewExtractorException) {
            return null;
        }

        $response = new Response($this->uprightJpeg($preview));
        $response->headers->set('Content-Type', 'image/jpeg');

        return $this->applyCommonHeaders($response);
    }

    /**
     * Redresse la preview si l'appareil était tenu de travers.
     *
     * Une preview est stockée telle que le capteur l'a vue : l'appareil
     * enregistre la rotation à appliquer plutôt que de l'appliquer lui-même. Le
     * package s'interdit de le faire (il évite GD par design), donc c'est ici ou
     * nulle part — sans quoi la lightbox affiche la photo couchée.
     */
    private function uprightJpeg(ExtractedPreview $preview): string
    {
        if ($preview->orientation->isUpright()) {
            // Cas courant : on rend les octets d'origine, sans réencodage JPEG
            // qui dégraderait l'image pour rien.
            return $preview->jpegData;
        }

        $image = @imagecreatefromstring($preview->jpegData);
        if ($image === false) {
            return $preview->jpegData;
        }

        $image = $this->applyOrientation($image, $preview->orientation);

        ob_start();
        imagejpeg($image, null, self::PREVIEW_QUALITY);
        $rotated = (string) ob_get_clean();
        imagedestroy($image);

        return $rotated;
    }

    /**
     * imagerotate() tourne dans le sens antihoraire là où l'EXIF compte en
     * horaire, d'où la négation de l'angle.
     */
    private function applyOrientation(\GdImage $image, Orientation $orientation): \GdImage
    {
        if ($orientation->isMirrored()) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $degrees = $orientation->degrees();
        if ($degrees === 0) {
            return $image;
        }

        $rotated = @imagerotate($image, -$degrees, 0);
        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    private function createFileResponse(string $absolutePath, string $mimeType): BinaryFileResponse
    {
        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', $mimeType);

        /** @var BinaryFileResponse $response */
        $response = $this->applyCommonHeaders($response);

        return $response;
    }

    private function applyCommonHeaders(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // makeDisposition plutôt que setContentDisposition() : cette dernière
        // n'existe que sur BinaryFileResponse, or une preview extraite est une
        // Response ordinaire.
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, ''),
        );
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE);

        return $response;
    }
}
