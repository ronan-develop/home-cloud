<?php

declare(strict_types=1);

namespace App\Tests\Web\Component;

use App\Entity\Folder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;

class NewFolderModalTest extends KernelTestCase
{
    use InteractsWithTwigComponents;

    private function crawl(array $props = []): \Symfony\Component\DomCrawler\Crawler
    {
        return $this->renderTwigComponent('NewFolderModal', $props)->crawler();
    }

    public function testModalIsHiddenByDefault(): void
    {
        $node = $this->crawl()->filter('[data-testid="new-folder-modal"]');
        $this->assertCount(1, $node);
        $this->assertStringContainsString('display:none', $node->attr('style') ?? '');
    }

    public function testHasNameInput(): void
    {
        $this->assertCount(1, $this->crawl()->filter('[data-testid="new-folder-name"]'));
    }

    public function testHasMediaTypeRadios(): void
    {
        $crawler = $this->crawl();
        foreach (['general', 'photo', 'video', 'document', 'music', 'other'] as $type) {
            $this->assertCount(
                1,
                $crawler->filter('[data-testid="media-type-'.$type.'"]'),
                "Missing radio for mediaType: $type"
            );
        }
    }

    public function testHasParentFolderSelector(): void
    {
        $this->assertCount(1, $this->crawl()->filter('[data-testid="parent-folder-select"]'));
    }

    public function testRootOptionIsPresent(): void
    {
        $node = $this->crawl()->filter('[data-testid="parent-folder-select"]');
        $this->assertStringContainsString('root', $node->attr('data-root-label') ?? $node->html());
    }

    public function testFolderListRendersProps(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $em->clear();

        $user = new User('test@example.com', 'Test', 'test', 'hash');
        $em->persist($user);
        $f1 = new Folder('Photos', $user);
        $f2 = new Folder('Vidéos', $user);
        $em->persist($f1);
        $em->persist($f2);
        $em->flush();

        $folders = [$f1, $f2];
        $html    = (string) $this->renderTwigComponent('NewFolderModal', ['folders' => $folders]);

        $this->assertStringContainsString('Photos', $html);
        $this->assertStringContainsString('Vidéos', $html);

        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $em->clear();
    }

    public function testHasSubmitButton(): void
    {
        $this->assertCount(1, $this->crawl()->filter('[data-testid="new-folder-submit"]'));
    }

    public function testHasInlineScript(): void
    {
        $html = (string) $this->renderTwigComponent('NewFolderModal');
        $this->assertStringContainsString('<script>', $html);
    }
}
