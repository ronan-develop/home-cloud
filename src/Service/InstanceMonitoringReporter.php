<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\InstanceMonitoringReporterInterface;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * Construit le snapshot de santé de l'instance courante pour le monitoring
 * multi-instances (#376). Traité en local : pas d'aller-retour réseau vers
 * soi-même, même logique que BroadcastOrchestrator::dispatch.
 */
final readonly class InstanceMonitoringReporter implements InstanceMonitoringReporterInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private FileRepository $fileRepository,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function getLocalSnapshot(): InstanceMonitoringSnapshot
    {
        return new InstanceMonitoringSnapshot(
            reachable: true,
            userCount: $this->userRepository->count([]),
            totalStorageBytes: $this->fileRepository->sumTotalSize(),
            gitRevision: $this->getGitRevision(),
        );
    }

    private function getGitRevision(): string
    {
        $process = new Process(['git', 'rev-parse', '--short', 'HEAD'], $this->projectDir);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : 'inconnue';
    }
}
