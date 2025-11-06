<?php

namespace App\Security;

use App\Entity\File;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Psr\Log\LoggerInterface;

class FileAccessManager
{
    private AuthorizationCheckerInterface $authChecker;
    private LoggerInterface $logger;

    public function __construct(AuthorizationCheckerInterface $authChecker, LoggerInterface $logger)
    {
        $this->authChecker = $authChecker;
        $this->logger = $logger;
    }

    public function assertDownloadAccess(File $file, ?User $user): void
    {
        if (!$this->authChecker->isGranted('FILE_DOWNLOAD', $file)) {
            $this->logAccessDenied('téléchargement', $file, $user);
            throw new AccessDeniedException('Accès refusé ou fichier inexistant.');
        }
    }

    public function assertDeleteAccess(File $file, ?User $user): void
    {
        if (!$this->authChecker->isGranted('FILE_DELETE', $file)) {
            $this->logAccessDenied('suppression', $file, $user);
            throw new AccessDeniedException('Accès refusé ou fichier inexistant.');
        }
    }

    public function logAccessDenied(string $action, File $file, ?User $user): void
    {
        $username = $user ? $user->getUserIdentifier() : 'anonyme';
        $this->logger->warning(sprintf(
            'Tentative d’accès refusé à la %s du fichier ID %d par %s',
            $action,
            $file->getId(),
            $username
        ));
    }
}
