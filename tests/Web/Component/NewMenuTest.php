<?php

namespace App\Tests\Web\Component;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;

class NewMenuTest extends KernelTestCase
{
    use InteractsWithTwigComponents;

    public function testRendersNewButton(): void
    {
        $rendered = $this->renderTwigComponent('NewMenu');

        $rendered->assertSuccessful();
        $rendered->assertHasElement('[data-testid="new-btn"]');
    }

    public function testRendersDropdownMenu(): void
    {
        $rendered = $this->renderTwigComponent('NewMenu');

        $rendered->assertHasElement('[data-testid="new-menu"]');
    }

    public function testMenuHasNewFolderItem(): void
    {
        $rendered = $this->renderTwigComponent('NewMenu');

        $rendered->assertHasElement('[data-testid="new-menu-new-folder"]');
    }

    public function testMenuHasImportFileItem(): void
    {
        $rendered = $this->renderTwigComponent('NewMenu');

        $rendered->assertHasElement('[data-testid="new-menu-import-file"]');
    }

    public function testMenuHasImportFolderItem(): void
    {
        $rendered = $this->renderTwigComponent('NewMenu');

        $rendered->assertHasElement('[data-testid="new-menu-import-folder"]');
    }

    public function testMenuIsHiddenByDefault(): void
    {
        $rendered = $this->renderTwigComponent('NewMenu');

        $rendered->assertHasElement('[data-testid="new-menu"].hidden');
    }

    public function testHasStimulusController(): void
    {
        $rendered = $this->renderTwigComponent('NewMenu');

        $rendered->assertHasElement('[data-controller="new-menu"]');
    }
}
