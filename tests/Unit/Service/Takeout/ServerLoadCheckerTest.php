<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Takeout;

use App\Service\Takeout\ServerLoadChecker;
use PHPUnit\Framework\TestCase;

/**
 * #524 : décide si le serveur mutualisé o2switch est assez calme pour
 * démarrer immédiatement un traitement lourd (extraction Takeout), au lieu
 * de toujours différer à la nuit (#522). Seuil calibré empiriquement (3.0) —
 * un kill LVE a été observé à charge ~10 sur 56 cœurs visibles côté
 * conteneur (2026-09-28), bien au-delà de ce seuil conservateur ; aucune
 * valeur officielle o2switch n'existe pour calculer un seuil "exact" (cf.
 * investigation #522), donc marge large plutôt qu'une formule précise.
 */
final class ServerLoadCheckerTest extends TestCase
{
    public function testConsideredCalmWhenLoadAverageBelowThreshold(): void
    {
        $checker = new ServerLoadChecker(threshold: 3.0, loadAverageProvider: fn () => [1.5, 1.2, 1.0]);

        $this->assertTrue($checker->isServerCalmEnough());
    }

    public function testNotConsideredCalmWhenLoadAverageAboveThreshold(): void
    {
        $checker = new ServerLoadChecker(threshold: 3.0, loadAverageProvider: fn () => [9.86, 10.45, 10.48]);

        $this->assertFalse($checker->isServerCalmEnough());
    }

    public function testUsesOneMinuteLoadAverageNotFiveOrFifteen(): void
    {
        // Charge instantanée (1 min) basse mais tendance récente (5/15 min)
        // haute : on démarre sur l'état actuel, pas une moyenne qui inclurait
        // un pic déjà retombé.
        $checker = new ServerLoadChecker(threshold: 3.0, loadAverageProvider: fn () => [1.0, 9.0, 9.0]);

        $this->assertTrue($checker->isServerCalmEnough());
    }

    // Défaut prudent : si sys_getloadavg() échoue (indisponible sur certains
    // environnements), ne jamais supposer le serveur calme — laisser
    // scheduled pour la nuit plutôt que risquer un nouveau kill.
    public function testNotConsideredCalmWhenLoadAverageUnavailable(): void
    {
        $checker = new ServerLoadChecker(threshold: 3.0, loadAverageProvider: fn () => false);

        $this->assertFalse($checker->isServerCalmEnough());
    }

    // Recalibrage #543 : le seuil de 3.0 était fixé sans tenir compte des 56
    // cœurs visibles côté conteneur — charge mesurée en usage normal (hors
    // pic) à ~5-6, largement sous le seuil de kill LVE observé (~10), mais
    // au-dessus de 3.0. Conséquence en prod : 173/173 tentatives de dispatch
    // échouées sur ~43h (0% de succès), l'import restait scheduled en
    // permanence. Nouveau défaut à 8.0 : marge de sécurité réelle sous 10,
    // tout en laissant les imports démarrer en usage normal du serveur.
    public function testDefaultThresholdAllowsNormalServerLoad(): void
    {
        $checker = new ServerLoadChecker(loadAverageProvider: fn () => [6.2, 6.0, 6.08]);

        $this->assertTrue($checker->isServerCalmEnough());
    }

    public function testDefaultThresholdStillRejectsLoadNearKillLevel(): void
    {
        $checker = new ServerLoadChecker(loadAverageProvider: fn () => [9.86, 10.45, 10.48]);

        $this->assertFalse($checker->isServerCalmEnough());
    }
}
