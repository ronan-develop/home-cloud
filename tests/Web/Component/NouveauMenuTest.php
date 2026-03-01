<?php

namespace App\Tests\Web\Component;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;

class NouveauMenuTest extends KernelTestCase
{
    use InteractsWithTwigComponents;

    public function testRendersNouveauButton(): void
    {
        $rendered = $this->renderTwigComponent('NouveauMenu');

        $rendered->assertSuccessful();
        $rendered->assertHasElement('[data-testid="nouveau-btn"]');
    }

    public function testRendersDropdownMenu(): void
    {
        $rendered = $this->renderTwigComponent('NouveauMenu');

        $rendered->assertHasElement('[data-testid="nouveau-menu"]');
    }

    public function testMenuHasNewFolderItem(): void
    {
        $rendered = $this->renderTwigComponent('NouveauMenu');

        $rendered->assertHasElement('[data-testid="nouveau-menu-new-folder"]');
    }

    public function testMenuHasImportFileItem(): void
    {
        $rendered = $this->renderTwigComponent('NouveauMenu');

        $rendered->assertHasElement('[data-testid="nouveau-menu-import-file"]');
    }

    public function testMenuHasImportFolderItem(): void
    {
        $rendered = $this->renderTwigComponent('NouveauMenu');

        $rendered->assertHasElement('[data-testid="nouveau-menu-import-folder"]');
    }

    public function testMenuIsHiddenByDefault(): void
    {
        $rendered = $this->renderTwigComponent('NouveauMenu');

        $rendered->assertHasElement('[data-testid="nouveau-menu"].hidden');
    }

    public function testHasStimulusController(): void
    {
        $rendered = $this->renderTwigComponent('NouveauMenu');

        $rendered->assertHasElement('[data-controller="nouveau-menu"]');
    }
}
