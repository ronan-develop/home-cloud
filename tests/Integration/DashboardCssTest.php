<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class DashboardCssTest extends TestCase
{
    public function testDashboardCssFileExists(): void
    {
        $cssPath = __DIR__ . '/../../assets/styles/dashboard.css';
        self::assertFileExists($cssPath, 'dashboard.css should exist');
    }

    public function testDashboardCssContainsStatGridClass(): void
    {
        $cssPath = __DIR__ . '/../../assets/styles/dashboard.css';
        $cssContent = file_get_contents($cssPath);
        self::assertStringContainsString('.hc-stat-grid', $cssContent);
    }

    public function testDashboardCssContainsStatCardClass(): void
    {
        $cssPath = __DIR__ . '/../../assets/styles/dashboard.css';
        $cssContent = file_get_contents($cssPath);
        self::assertStringContainsString('.hc-stat-card', $cssContent);
    }

    public function testDashboardCssContainsFileListClass(): void
    {
        $cssPath = __DIR__ . '/../../assets/styles/dashboard.css';
        $cssContent = file_get_contents($cssPath);
        self::assertStringContainsString('.hc-file-list', $cssContent);
    }

    public function testDashboardCssContainsFileRowClass(): void
    {
        $cssPath = __DIR__ . '/../../assets/styles/dashboard.css';
        $cssContent = file_get_contents($cssPath);
        self::assertStringContainsString('.hc-file-row', $cssContent);
    }

    public function testDashboardCssIsImportedInAppCss(): void
    {
        $appCssPath = __DIR__ . '/../../assets/styles/app.css';
        $appCssContent = file_get_contents($appCssPath);
        self::assertStringContainsString('dashboard.css', $appCssContent);
    }
}
