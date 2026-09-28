<?php

declare(strict_types=1);

namespace App\Service\Takeout;

/**
 * Décide si le serveur mutualisé o2switch est assez calme pour démarrer un
 * traitement lourd (extraction Takeout) — #524. Seuil calibré empiriquement
 * (3.0 par défaut) : un kill LVE a été observé à charge ~10 sur 56 cœurs
 * visibles côté conteneur (2026-09-28), bien au-delà ; aucune valeur
 * officielle o2switch n'existe pour un seuil "exact" (cf. investigation
 * #522), donc marge large plutôt qu'une formule précise.
 *
 * $loadAverageProvider injecté (pas sys_getloadavg() en dur) pour rester
 * testable sans dépendre de l'état réel de la machine qui exécute les tests.
 */
final class ServerLoadChecker
{
    /**
     * @param callable(): (array{0: float, 1: float, 2: float}|false) $loadAverageProvider
     */
    public function __construct(
        private readonly float $threshold = 3.0,
        private $loadAverageProvider = 'sys_getloadavg',
    ) {}

    public function isServerCalmEnough(): bool
    {
        $loadAverage = ($this->loadAverageProvider)();
        if ($loadAverage === false) {
            return false;
        }

        // Charge à 1 minute (index 0) : reflète l'état instantané, pas une
        // moyenne sur 5/15 min qui inclurait un pic déjà retombé.
        return $loadAverage[0] < $this->threshold;
    }
}
