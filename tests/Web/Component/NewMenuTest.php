<?php

namespace App\Tests\Web\Component;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;

class NewMenuTest extends KernelTestCase
{
    use InteractsWithTwigComponents;

    private function crawl(): \Symfony\Component\DomCrawler\Crawler
    {
        return $this->renderTwigComponent('NewMenu')->crawler();
    }

    public function testRendersNewButton(): void
    {
        $this->assertCount(1, $this->crawl()->filter('[data-testid="new-btn"]'));
    }

    public function testRendersDropdownMenu(): void
    {
        $this->assertCount(1, $this->crawl()->filter('[data-testid="new-menu"]'));
    }

    public function testMenuHasNewFolderItem(): void
    {
        $this->assertCount(1, $this->crawl()->filter('[data-testid="new-menu-new-folder"]'));
    }

    public function testMenuHasImportFileItem(): void
    {
        $this->assertCount(1, $this->crawl()->filter('[data-testid="new-menu-import-file"]'));
    }

    public function testMenuHasImportFolderItem(): void
    {
        $this->assertCount(1, $this->crawl()->filter('[data-testid="new-menu-import-folder"]'));
    }

    public function testMenuIsHiddenByDefault(): void
    {
        $node = $this->crawl()->filter('[data-testid="new-menu"]');
        $this->assertStringContainsString('display:none', $node->attr('style') ?? '');
    }

    public function testHasInlineScript(): void
    {
        $html = (string) $this->renderTwigComponent('NewMenu');
        $this->assertStringContainsString('<script>', $html);
    }
}
