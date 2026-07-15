<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\EmailBranding;
use PHPUnit\Framework\TestCase;

/**
 * Couleur accent centralisée pour tous les emails HTML — un seul point de
 * changement aujourd'hui, prêt à devenir personnalisable par utilisateur
 * plus tard (voir avancement.md) sans toucher aux templates.
 */
final class EmailBrandingTest extends TestCase
{
    public function testAccentColorMatchesLightThemeDesignSystem(): void
    {
        $this->assertSame('#A34B4B', EmailBranding::ACCENT_COLOR);
    }
}
