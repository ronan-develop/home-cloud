<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\BroadcastMailerInterface;
use App\Interface\BroadcastOrchestratorInterface;
use App\Interface\BroadcastTargetProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Orchestration multi-instances du broadcast admin (#283). Chaque instance a
 * sa propre DB isolée : l'instance courante traite son cas en local (pas
 * d'aller-retour réseau vers elle-même), les autres sont appelées sur leur
 * endpoint interne /internal/broadcast, authentifié par un secret partagé.
 * En dry-run, aucun appel HTTP sortant n'est fait — sinon on spammerait
 * quand même les autres instances depuis un simple essai.
 */
final readonly class BroadcastOrchestrator implements BroadcastOrchestratorInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private BroadcastTargetProviderInterface $targetProvider,
        private BroadcastMailerInterface $localMailer,
        private LoggerInterface $logger,
        private string $currentInstance,
        private string $sharedToken,
    ) {}

    public function dispatch(string $subject, string $body, ?string $targetInstance, bool $dryRun): array
    {
        $targets = $targetInstance !== null
            ? [$targetInstance => $this->targetProvider->getTarget($targetInstance)]
            : $this->targetProvider->getAllTargets();

        $results = [];

        foreach ($targets as $instance => $url) {
            if ($instance === $this->currentInstance) {
                $this->localMailer->sendToAllUsers($subject, $body, $dryRun);
                $results[$instance] = true;
                continue;
            }

            if ($dryRun) {
                $results[$instance] = true;
                continue;
            }

            $results[$instance] = $this->dispatchToRemote($url, $subject, $body);
        }

        return $results;
    }

    private function dispatchToRemote(string $url, string $subject, string $body): bool
    {
        try {
            $this->httpClient->request('POST', $url . '/internal/broadcast', [
                'headers' => ['X-Broadcast-Token' => $this->sharedToken],
                'json'    => ['subject' => $subject, 'body' => $body, 'dryRun' => false],
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Broadcast : échec vers une instance distante', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
