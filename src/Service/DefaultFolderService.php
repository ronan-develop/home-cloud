<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Folder;
use App\Entity\User;
use App\Interface\DefaultFolderServiceInterface;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Résout le dossier de destination d'un fichier uploadé.
 *
 * Rôle : centraliser la logique de résolution du dossier cible selon les
 * 3 cas possibles : folder existant, nouveau folder, ou dossier "Uploads" par défaut.
 *
 * Choix :
 * - Le dossier "Uploads" est un Folder normal en base, créé à la demande (lazy).
 *   Il n'existe pas de flag "système" — il est identifié par son nom réservé.
 * - La résolution est déléguée à ce service pour garder FileProcessor simple
 *   et testable indépendamment.
 */
final class DefaultFolderService implements DefaultFolderServiceInterface
{
    public const DEFAULT_FOLDER_NAME = 'Uploads';

    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Résout le Folder de destination selon la priorité :
     *   folderId fourni → vérifié en base
     *   newFolderName fourni → crée un nouveau Folder
     *   aucun → retourne (ou crée) le folder "Uploads"
     *
     * @throws \InvalidArgumentException si folderId est fourni mais introuvable
     */
    public function resolve(?string $folderId, ?string $newFolderName, User $owner): Folder
    {
        if ($folderId !== null) {
            $folder = $this->folderRepository->find($folderId);
            if ($folder === null) {
                throw new \InvalidArgumentException(sprintf('Folder "%s" not found', $folderId));
            }
            // Vérifier que le folder appartient bien à l'owner — empêche l'accès cross-user
            if ((string) $folder->getOwner()->getId() !== (string) $owner->getId()) {
                throw new \InvalidArgumentException(sprintf('Folder "%s" not found', $folderId));
            }

            return $folder;
        }

        if ($newFolderName !== null && $newFolderName !== '') {
            $trimmed = trim($newFolderName);
            if ($trimmed === '') {
                throw new \InvalidArgumentException('newFolderName cannot be blank');
            }
            if (mb_strlen($trimmed) > 255) {
                throw new \InvalidArgumentException('newFolderName must not exceed 255 characters');
            }
            $folder = new Folder($trimmed, $owner);
            $this->em->persist($folder);

            return $folder;
        }

        return $this->getOrCreateUploadsFolder($owner);
    }

    /**
     * Retourne le folder "Uploads" s'il existe déjà, sinon le crée.
     */
    private function getOrCreateUploadsFolder(User $owner): Folder
    {
        $folder = $this->folderRepository->findOneBy(['name' => self::DEFAULT_FOLDER_NAME]);

        if ($folder === null) {
            $folder = new Folder(self::DEFAULT_FOLDER_NAME, $owner);
            $this->em->persist($folder);
        }

        return $folder;
    }
}
