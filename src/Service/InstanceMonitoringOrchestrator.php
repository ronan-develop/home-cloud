<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\BroadcastTargetProviderInterface;
use App\Interface\InstanceMonitoringReporterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Orchestration multi-instances du monitoring admin (#376), sur le modèle de
 * BroadcastOrchestrator (#283). Chaque instance a sa propre DB isolée :
 * l'instance courante est traitée en local (pas d'aller-retour réseau vers
 * elle-même), les autres sont appelées sur leur endpoint interne
 * /internal/monitoring, authentifié par un secret partagé. Une instance
 * injoignable ne doit jamais bloquer l'affichage des autres.
 */
final readonly class InstanceMonitoringOrchestrator
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private BroadcastTargetProviderInterface $targetProvider,
        private InstanceMonitoringReporterInterface $localReporter,
        private LoggerInterface $logger,
        private string $currentInstance,
        private string $sharedToken,
    ) {}

    /**
     * @return array<string, InstanceMonitoringSnapshot>
     */
    public function collectAll(): array
    {
        $snapshots = [];

        foreach ($this->targetProvider->getAllTargets() as $instance => $url) {
            $snapshots[$instance] = $instance === $this->currentInstance
                ? $this->localReporter->getLocalSnapshot()
                : $this->collectRemote($url);
        }

        return $snapshots;
    }

    private function collectRemote(string $url): InstanceMonitoringSnapshot
    {
        try {
            $response = $this->httpClient->request('GET', $url . '/internal/monitoring', [
                'headers' => ['X-Broadcast-Token' => $this->sharedToken],
                'timeout' => 3,
            ]);
            $data = $response->toArray();

            return new InstanceMonitoringSnapshot(
                reachable: true,
                userCount: (int) $data['userCount'],
                totalStorageBytes: (int) $data['totalStorageBytes'],
                gitRevision: (string) $data['gitRevision'],
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Monitoring : instance injoignable', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return new InstanceMonitoringSnapshot(reachable: false);
        }
    }
}
