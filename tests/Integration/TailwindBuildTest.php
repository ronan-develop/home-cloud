<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TailwindBuildTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testTailwindCssIsBuiltAndContainsUtilityClasses(): void
    {
        $builtCssPath = self::getContainer()->getParameter('kernel.project_dir') . '/var/tailwind/app.built.css';

        self::assertFileExists($builtCssPath, 'Tailwind CSS built file should exist at ' . $builtCssPath);

        $cssContent = file_get_contents($builtCssPath);
        self::assertNotEmpty($cssContent, 'Tailwind CSS built file should not be empty');

        // Verify it contains actual Tailwind utility classes (not just the placeholder)
        self::assertStringContainsString('.flex', $cssContent, 'CSS should contain .flex utility class');
        self::assertStringContainsString('.gap-', $cssContent, 'CSS should contain gap utility classes');
        self::assertStringContainsString('.grid', $cssContent, 'CSS should contain .grid utility class');

        // Verify it's not the placeholder (which was only 4 lines)
        $lineCount = substr_count($cssContent, "\n");
        self::assertGreaterThan(1000, $lineCount, 'Tailwind CSS should have significant content (>1000 lines), not a placeholder');
    }

    public function testTailwindPlaceholderFileDoesNotExist(): void
    {
        $placeholderPath = self::getContainer()->getParameter('kernel.project_dir') . '/assets/styles/tailwindcss';
        self::assertFileDoesNotExist($placeholderPath, 'Placeholder file should be removed from repo');
    }
}
