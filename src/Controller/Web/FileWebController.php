<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\File;
use App\Interface\StorageServiceInterface;
use App\Repository\FileRepository;
use App\Service\DefaultFolderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gère l'upload et la suppression de fichiers via l'interface web (session auth).
 * Réutilise StorageService et DefaultFolderService de la couche API.
 */
#[IsGranted('ROLE_USER')]
final class FileWebController extends AbstractController
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'exe', 'msi', 'com', 'bat', 'cmd', 'ps1', 'psm1', 'psd1', 'scr', 'pif',
        'vbs', 'vbe', 'wsf', 'wsh', 'gadget', 'msc', 'msp', 'mst',
        'run', 'elf', 'appimage', 'deb', 'rpm',
        'dmg', 'pkg', 'app',
        'jar', 'jnlp',
        'asp', 'aspx', 'jsp', 'cfm',
    ];

    public function __construct(
        private readonly StorageServiceInterface $storage,
        private readonly DefaultFolderService $folderService,
        private readonly FileRepository $fileRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/files/{id}/download', name: 'app_file_download', methods: ['GET'])]
    public function download(string $id): Response
    {
        $file = $this->fileRepository->find(\Symfony\Component\Uid\Uuid::fromString($id));

        if ($file === null) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$file->getOwner()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $absolutePath = $this->storage->getAbsolutePath($file->getPath());
        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getOriginalName()
        );

        return $response;
    }

    #[Route('/files/upload', name: 'app_file_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        $uploadedFile = $request->files->get('file');

        if ($uploadedFile === null) {
            throw new BadRequestHttpException('No file provided.');
        }

        $ext = strtolower($uploadedFile->getClientOriginalExtension() ?? '');
        if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            throw new BadRequestHttpException(
                sprintf('File type ".%s" is not allowed.', $ext)
            );
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $folderId = $request->request->get('folder_id');
        $folder = $this->folderService->resolve($folderId, null, $user);

        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $uploadedFile->getClientOriginalName());
        $mimeType = $uploadedFile->getClientMimeType();
        $size = $uploadedFile->getSize();  // Avant store() qui déplace le fichier

        ['path' => $path, 'neutralized' => $neutralized] = $this->storage->store($uploadedFile);

        $file = new File(
            $originalName,
            $mimeType,
            $size,
            $path,
            $folder,
            $user,
            $neutralized,
        );

        $this->em->persist($file);
        $this->em->flush();

        $this->addFlash('success', "Fichier « {$originalName} » uploadé avec succès.");

        $redirectUrl = '/';
        if ($folderId) {
            $redirectUrl = '/?folder=' . $folderId;
        }

        return $this->redirect($redirectUrl);
    }

    #[Route('/files/{id}/delete', name: 'app_file_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        $file = $this->fileRepository->find(\Symfony\Component\Uid\Uuid::fromString($id));

        if ($file === null) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$file->getOwner()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce fichier.');
        }

        $this->storage->delete($file->getPath());
        $this->em->remove($file);
        $this->em->flush();

        $this->addFlash('success', "Fichier « {$file->getOriginalName()} » supprimé.");

        $folderId = $request->request->get('folder_id');

        return $this->redirect($folderId ? '/?folder=' . $folderId : '/');
    }
}
