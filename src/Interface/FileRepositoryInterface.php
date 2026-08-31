<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\File;
use App\Entity\Folder;
use Symfony\Component\Uid\Uuid;

/**
 * Contrat pour l'accès aux données File.
 *
 * Dépendre de cette interface permet de mocker le repository en tests
 * et de swapper l'implémentation sans toucher aux consommateurs.
 */
interface FileRepositoryInterface
{
    /**
     * Find a file by ID.
     *
     * @return File|null
     */
    public function findById(Uuid $id): ?File;

    public function findOneByNameInFolder(string $name, Folder $folder): ?File;

    /**
     * Fichiers sans Media associé — candidats à un (re)traitement EXIF/vignette.
     *
     * Ne filtre pas par mimeType ici : MediaProcessor::process() sait déjà
     * décider en un accès mémoire (resolveMediaType()) si un fichier est
     * pertinent, sans toucher au disque pour ceux qu'il écarte (PDF, etc.).
     *
     * Retourne un itérable (toIterable()) plutôt qu'un tableau : un rattrapage
     * peut porter sur plusieurs centaines de fichiers, hydrater le résultat
     * entier d'un coup a fait tomber le worker en OOM (#365).
     *
     * @return iterable<File>
     */
    public function findWithoutMedia(): iterable;
}
