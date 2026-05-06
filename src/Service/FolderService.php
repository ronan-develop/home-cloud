<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Folder;
use App\Entity\User;
use App\Enum\FolderMediaType;
use App\Interface\AuthenticationResolverInterface;
use App\Interface\DefaultFolderServiceInterface;
use App\Interface\FilenameValidatorInterface;
use App\Interface\FolderRepositoryInterface;
use App\Interface\OwnershipCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Logique métier des opérations sur les dossiers.
 *
 * Extrait de FolderProcessor pour respecter le Single Responsibility Principle :
 * ce service est responsable des règles métier uniquement (validation, ownership,
 * cycle detection, persistance). FolderProcessor délègue ici et reste un pur
 * dispatcher HTTP → domaine.
 *
 * Responsabilités :
 * - createFolder : validation nom, unicité, persist
 * - updateFolder : ownership, rename, mediaType, déplacement avec détection de cycle
 * - deleteFolder : ownership, suppression avec ou sans migration des fichiers
 */
final class FolderService
{
    public function __construct(
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly FilenameValidatorInterface $filenameValidator,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly AuthenticationResolverInterface $authResolver,
        private readonly DefaultFolderServiceInterface $defaultFolderService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly Stopwatch $stopwatch,
    ) {}

    /**
     * Crée un dossier après validation du nom et vérification de l'unicité dans le parent.
     */
    public function createFolder(User $owner, string $name, ?Folder $parent, FolderMediaType $mediaType): Folder
    {
        $event = $this->stopwatch->start('folder.create');

        $this->filenameValidator->validate($name);

        $criteria = ['name' => $name, 'owner' => $owner, 'parent' => $parent];
        if ($this->folderRepository->findOneBy($criteria) !== null) {
            throw new BadRequestHttpException('A folder with this name already exists in the parent');
        }

        $folder = new Folder($name, $owner, $parent);
        $folder->setMediaType($mediaType);
        $this->em->persist($folder);
        $this->em->flush();

        $event->stop();
        $this->logger->info('Folder created', [
            'folder_id' => (string) $folder->getId(),
            'name'      => $name,
            'duration'  => $event->getDuration() . 'ms',
        ]);

        return $folder;
    }

    /**
     * Met à jour un dossier (rename, mediaType, déplacement).
     *
     * @param string           $newName       Nouveau nom — vide = pas de changement
     * @param FolderMediaType|null $newMediaType  null = pas de changement
     * @param bool             $parentChanged true si parentId était présent dans le body JSON
     * @param Folder|null      $newParent     null = déplacement à la racine (si $parentChanged=true)
     */
    public function updateFolder(
        Folder $folder,
        string $newName,
        ?FolderMediaType $newMediaType,
        bool $parentChanged,
        ?Folder $newParent,
    ): void {
        $event = $this->stopwatch->start('folder.update');

        $this->logger->info('Folder PATCH operation initiated', [
            'folder_id' => (string) $folder->getId(),
        ]);

        $this->ownershipChecker->denyUnlessOwner($folder);

        if ($newName !== '') {
            $this->filenameValidator->validate($newName);
        }

        // Unicité vérifiée contre le parent effectif (nouveau si déplacement, sinon actuel)
        $effectiveName   = $newName !== '' ? $newName : $folder->getName();
        $effectiveParent = $parentChanged ? $newParent : $folder->getParent();

        if ($newName !== '' || $parentChanged) {
            $existing = $this->folderRepository->findOneBy([
                'name'   => $effectiveName,
                'owner'  => $folder->getOwner(),
                'parent' => $effectiveParent,
            ]);
            if ($existing !== null && !$existing->getId()->equals($folder->getId())) {
                throw new BadRequestHttpException('A folder with this name already exists in the parent');
            }
        }

        if ($newName !== '') {
            $folder->setName($newName);
        }

        if ($newMediaType !== null) {
            $folder->setMediaType($newMediaType);
        }

        if ($parentChanged) {
            if ($newParent !== null) {
                if ($newParent->getId()->equals($folder->getId())) {
                    throw new BadRequestHttpException('A folder cannot be its own parent');
                }
                if (!$this->ownershipChecker->isOwner($newParent)) {
                    throw new AccessDeniedHttpException('You do not own the target parent folder');
                }
                if ($this->wouldCreateCycle($folder, $newParent)) {
                    throw new BadRequestHttpException('Moving this folder would create a cycle');
                }
            }
            $folder->setParent($newParent);
        }

        $this->em->flush();

        $event->stop();
        $this->logger->info('Folder updated', [
            'folder_id' => (string) $folder->getId(),
            'duration'  => $event->getDuration() . 'ms',
        ]);
    }

    /**
     * Supprime un dossier.
     *
     * Si $deleteContents=false : déplace tous les fichiers des sous-dossiers vers
     * le dossier Uploads avant de supprimer l'arborescence.
     * Si $deleteContents=true : supprime directement (cascade Doctrine).
     */
    public function deleteFolder(Folder $folder, bool $deleteContents): void
    {
        $event = $this->stopwatch->start('folder.delete');

        $this->ownershipChecker->denyUnlessOwner($folder);

        if (!$deleteContents) {
            $user          = $this->authResolver->getAuthenticatedUser();
            $uploadsFolder = $this->defaultFolderService->resolve(null, null, $user);

            $descendantIds = $this->folderRepository->findDescendantIds($folder);

            // Déplace les fichiers du dossier principal (entité déjà chargée)
            foreach ($folder->getFiles() as $file) {
                $file->setFolder($uploadsFolder);
            }

            // Déplace les fichiers des sous-dossiers
            foreach ($descendantIds as $descId) {
                $f = $this->folderRepository->find($descId);
                if ($f === null) {
                    continue;
                }
                foreach ($f->getFiles() as $file) {
                    $file->setFolder($uploadsFolder);
                }
            }

            // Flush les déplacements de fichiers (crée Uploads si besoin)
            $this->em->flush();

            // Supprime sous-dossiers puis dossier lui-même.
            // refresh() recharge depuis la DB : collection files vide après déplacement.
            foreach ($descendantIds as $descId) {
                $desc = $this->folderRepository->find($descId);
                if ($desc !== null) {
                    $this->em->refresh($desc);
                    $this->em->remove($desc);
                }
            }
            $this->em->refresh($folder);
            $this->em->remove($folder);
            $this->em->flush();
        } else {
            $this->em->remove($folder);
            $this->em->flush();
        }

        $event->stop();
        $this->logger->info('Folder deleted', [
            'folder_id'      => (string) $folder->getId(),
            'deleteContents' => $deleteContents,
            'duration'       => $event->getDuration() . 'ms',
        ]);
    }

    /**
     * Vérifie si définir $newParent comme parent de $folder créerait un cycle.
     * Utilise une CTE récursive (1 requête SQL) pour remonter tous les ancêtres
     * de $newParent. Si $folder est parmi eux → cycle.
     */
    private function wouldCreateCycle(Folder $folder, Folder $newParent): bool
    {
        $ancestorIds = $this->folderRepository->findAncestorIds($newParent);
        $folderId    = strtolower(str_replace('-', '', (string) $folder->getId()));
        foreach ($ancestorIds as $ancestorId) {
            if (strtolower(str_replace('-', '', $ancestorId)) === $folderId) {
                return true;
            }
        }
        return false;
    }
}
