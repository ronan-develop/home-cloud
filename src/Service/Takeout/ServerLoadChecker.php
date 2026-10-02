<?php

declare(strict_types=1);

namespace App\Service\Takeout;

/**
 * Décide si le serveur mutualisé o2switch est assez calme pour démarrer un
 * traitement lourd (extraction Takeout) — #524. Seuil calibré empiriquement :
 * un kill LVE a été observé à charge ~10 sur 56 cœurs visibles côté
 * conteneur (2026-09-28). Premier seuil fixé à 3.0 sans tenir compte du
 * nombre de cœurs — en usage normal (hors pic), la charge mesurée tourne à
 * ~5-6, largement sous le seuil de kill mais au-dessus de 3.0 : conséquence
 * en prod, 173/173 tentatives de dispatch échouées sur ~43h (0% de succès,
 * #543). Seuil recalibré à 8.0 : marge de sécurité réelle sous 10, tout en
 * laissant les imports démarrer en usage normal du serveur.
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
        private readonly float $threshold = 8.0,
        private $loadAverageProvider = 'sys_getloadavg',
    ) {}

    /**
     * @return array{0: float, 1: float, 2: float}|null null si la mesure est indisponible
     */
    public function getLoadAverage(): ?array
    {
        $loadAverage = ($this->loadAverageProvider)();

        return $loadAverage === false ? null : $loadAverage;
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    public function isServerCalmEnough(): bool
    {
        return $this->isCalm($this->getLoadAverage());
    }

    /**
     * Décision sur une mesure déjà prise (#528) : permet à l'appelant de
     * mesurer une seule fois et de journaliser la mesure et la décision
     * sans risque d'incohérence entre deux lectures successives.
     *
     * @param array{0: float, 1: float, 2: float}|null $loadAverage
     */
    public function isCalm(?array $loadAverage): bool
    {
        if ($loadAverage === null) {
            return false;
        }

        // Charge à 1 minute (index 0) : reflète l'état instantané, pas une
        // moyenne sur 5/15 min qui inclurait un pic déjà retombé.
        return $loadAverage[0] < $this->threshold;
    }
}
