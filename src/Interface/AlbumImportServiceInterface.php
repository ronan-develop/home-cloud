<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Album;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface AlbumImportServiceInterface
{
    /**
     * Upload une sélection de fichiers depuis le disque et les ajoute
     * immédiatement à l'album (traitement média synchrone, contrairement
     * au flux d'upload normal qui traite en asynchrone).
     *
     * Les fichiers dont le type MIME n'est pas une image/vidéo sont uploadés
     * mais ignorés silencieusement pour l'ajout à l'album (pas de Media
     * possible pour eux).
     *
     * @param UploadedFile[] $files
     */
    public function import(Album $album, array $files, User $owner): void;
}
