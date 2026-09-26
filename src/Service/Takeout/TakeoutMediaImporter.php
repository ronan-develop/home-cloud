<?php

declare(strict_types=1);

namespace App\Service\Takeout;

use App\Entity\ContentFingerprint;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Interface\File\StorageServiceInterface;
use App\Interface\Media\MediaProcessorInterface;
use App\Interface\MediaDateResolverInterface;
use App\Repository\ContentFingerprintRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Importe UN média (+ son .json optionnel) d'un export Google Takeout
 * extrait sur disque — SRP forcé dès l'écriture (#327) : ne connaît ni les
 * tranches de flush ni la boucle sur l'ensemble du lot, ça reste au
 * TakeoutImportHandler qui orchestre.
 *
 * Le hash de $existingHashes est déjà résolu par lot par l'appelant
 * (ContentFingerprintRepository::findExistingHashes) — cette méthode ne fait
 * qu'une comparaison en mémoire, jamais de requête DB par fichier.
 */
class TakeoutMediaImporter
{
    public function __construct(
        private readonly StorageServiceInterface $storageService,
        private readonly MediaProcessorInterface $mediaProcessor,
        private readonly MediaDateResolverInterface $dateResolver,
        private readonly ContentFingerprintRepository $fingerprintRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @param string[] $existingHashes Hashs déjà connus pour $owner (résolus par lot)
     */
    public function import(
        TakeoutMediaEntry $entry,
        ?TakeoutMetadata $metadata,
        Folder $destinationFolder,
        User $owner,
        array $existingHashes,
    ): TakeoutImportOutcome {
        $contentHash = hash_file('sha256', $entry->mediaPath);

        if (in_array($contentHash, $existingHashes, true)) {
            return TakeoutImportOutcome::duplicate();
        }

        $originalName = basename($entry->mediaPath);
        $uploadedFile = new UploadedFile($entry->mediaPath, $originalName, null, null, true);

        $storeResult = $this->storageService->store($uploadedFile);

        $file = new File(
            originalName: $originalName,
            mimeType: $uploadedFile->getMimeType() ?? 'application/octet-stream',
            size: filesize($entry->mediaPath) ?: 0,
            path: $storeResult['path'],
            folder: $destinationFolder,
            owner: $owner,
            neutralized: $storeResult['neutralized'],
        );
        $this->em->persist($file);

        $this->em->persist(new ContentFingerprint($owner, $contentHash));

        $media = $this->mediaProcessor->process($file);

        if ($media !== null) {
            $resolvedDate = $this->dateResolver->resolve($media->getTakenAt(), $metadata?->takenAt);
            $media->setTakenAt($resolvedDate);
        }

        return TakeoutImportOutcome::imported($media);
    }
}
