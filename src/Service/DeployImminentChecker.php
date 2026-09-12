<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lit le signal de préavis écrit par bin/deploy-nightly.sh (#422 étape 3/3)
 * — fichier plat contenant le timestamp Unix du début du préavis, sur le
 * même modèle qu'ActivityTracker. Sert de base à l'endpoint de polling
 * consulté par la popup front.
 */
final readonly class DeployImminentChecker
{
    public function __construct(
        #[Autowire('%app.deploy_imminent.file_path%')]
        private string $filePath,
        #[Autowire('%app.deploy_imminent.warning_seconds%')]
        private int $warningSeconds,
    ) {}

    public function getDeploymentEta(): ?\DateTimeImmutable
    {
        if (!is_file($this->filePath)) {
            return null;
        }

        $raw = trim((string) @file_get_contents($this->filePath));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (new \DateTimeImmutable())
            ->setTimestamp((int) $raw)
            ->modify("+{$this->warningSeconds} seconds");
    }
}
