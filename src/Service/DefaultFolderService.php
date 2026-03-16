<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Folder;
use App\Entity\User;
use App\Interface\DefaultFolderServiceInterface;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
     * @throws BadRequestHttpException si folderId est fourni mais introuvable
     */
    public function resolve(?string $folderId, ?string $newFolderName, User $owner): Folder
    {
        if ($folderId !== null && $folderId !== '') {
            $folder = $this->folderRepository->find($folderId);
            if ($folder === null) {
                throw new BadRequestHttpException(sprintf('Folder "%s" not found', $folderId));
            }
            // Vérifier que le folder appartient bien à l'owner — empêche l'accès cross-user
            if ((string) $folder->getOwner()->getId() !== (string) $owner->getId()) {
                throw new BadRequestHttpException(sprintf('Folder "%s" not found', $folderId));
            }

            return $folder;
        }

        if ($newFolderName !== null && $newFolderName !== '') {
            $trimmed = trim($newFolderName);
            if ($trimmed === '') {
                throw new BadRequestHttpException('newFolderName cannot be blank');
            }
            if (mb_strlen($trimmed) > 255) {
                throw new BadRequestHttpException('newFolderName must not exceed 255 characters');
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
        $folder = $this->folderRepository->findOneBy(['name' => self::DEFAULT_FOLDER_NAME, 'owner' => $owner]);

        if ($folder === null) {
            $folder = new Folder(self::DEFAULT_FOLDER_NAME, $owner);
            $this->em->persist($folder);
        }

        return $folder;
    }

    /**
     * Ensure a nested subfolder path exists under the given parent.
     *
     * - Accepts relative paths using "/" or "\\" as separator
     * - Collapses multiple separators and trims segments
     * - Persists newly created folders and performs a single flush at the end
     */
    public function ensureSubfolderPath(Folder $parent, string $relativePath, User $owner): Folder
    {
        $segments = $this->parseRelativePath($relativePath);

        if (count($segments) === 0) {
            return $parent;
        }

        $current = $parent;
        foreach ($segments as $segment) {
            // Try to find existing child with this name, parent and owner
            $existing = $this->folderRepository->findOneBy([
                'name' => $segment,
                'parent' => $current,
                'owner' => $owner,
            ]);

            if ($existing instanceof Folder) {
                $current = $existing;
                continue;
            }

            $new = new Folder($segment, $owner, $current);
            $this->em->persist($new);
            $current = $new;
        }

        // Single flush as recommended by plan
        $this->em->flush();

        return $current;
    }

    /**
     * Parse a relative path into segments.
     * Normalizes separators, trims segments and filters out empties.
     * Validates each segment length (<=255) and that it is not empty after trim.
     *
     * @return string[]
     */
    private function parseRelativePath(string $path): array
    {
        $normalized = str_replace('\\', '/', $path);
        // collapse multiple slashes
        $normalized = preg_replace('#/+#', '/', $normalized);
        $normalized = trim($normalized, " \/");

        if ($normalized === '') {
            return [];
        }

        $parts = explode('/', $normalized);
        $segments = [];
        foreach ($parts as $part) {
            $seg = trim($part);
            if ($seg === '') {
                continue;
            }
            if (mb_strlen($seg) > 255) {
                throw new BadRequestHttpException('Folder name segment too long');
            }
            // Basic sanitization: disallow NUL bytes
            if (strpos($seg, "\0") !== false) {
                throw new BadRequestHttpException('Invalid characters in folder name');
            }
            $segments[] = $seg;
        }

        return $segments;
    }
}
