<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShareLinkReactivateWebTest extends WebTestCase
{
    use WebFixturesTrait;

    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM share_links');
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createOwner(string $email = 'reactivate-owner@example.com'): User
    {
        return $this->createWebUser($email, 'secret123', 'Owner');
    }

    private function createRevokedLink(User $owner, ?\DateTimeImmutable $revokedAt = null): ShareLink
    {
        $folder = new Folder('Docs', $owner);
        $folder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($folder);
        $file = new File('photo.jpg', 'image/jpeg', 1024, 'test/photo.jpg', $folder, $owner);
        $file->setVisibility(File::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($file);
        $this->em->flush();

        $link = new ShareLink(
            $owner,
            Share::RESOURCE_FILE,
            $file->getId(),
            bin2hex(random_bytes(16)),
            hash('sha256', 'valid-plain-token'),
            new \DateTimeImmutable('+7 days'),
        );
        $link->revoke();

        if ($revokedAt !== null) {
            $ref = new \ReflectionProperty(ShareLink::class, 'revokedAt');
            $ref->setValue($link, $revokedAt);
        }

        $this->em->persist($link);
        $this->em->flush();

        return $link;
    }

    private function reactivateToken(): string
    {
        $crawler = $this->client->request('GET', '/partages');

        return $crawler->filter('form[action*="/share-link-reactivate"] input[name="_token"]')->first()->attr('value');
    }

    public function testOwnerCanReactivateTheirRevokedLink(): void
    {
        $owner = $this->createOwner();
        $link = $this->createRevokedLink($owner);
        $this->loginAs('reactivate-owner@example.com');

        $token = $this->reactivateToken();

        $this->client->request('POST', '/share-link-reactivate', [
            '_token' => $token,
            'linkId' => $link->getId()->toRfc4122(),
        ]);

        $this->assertResponseRedirects('/partages');

        $this->em->clear();
        $reloaded = $this->em->getRepository(ShareLink::class)->find($link->getId());
        $this->assertTrue($reloaded->isActive());
    }

    public function testNonOwnerCannotReactivateLink(): void
    {
        $owner = $this->createOwner();
        $link = $this->createRevokedLink($owner);
        $attacker = $this->createWebUser('reactivate-attacker@example.com', 'secret123', 'Attacker');
        $this->createRevokedLink($attacker);

        $this->loginAs('reactivate-attacker@example.com');
        $token = $this->reactivateToken();

        $this->client->request('POST', '/share-link-reactivate', [
            '_token' => $token,
            'linkId' => $link->getId()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSharesPageShowsReactivateButtonAndPurgeCountdownForRevokedLink(): void
    {
        $owner = $this->createOwner();
        // Révoqué il y a 5 jours : il reste 25 jours avant purge (fenêtre 30j).
        $this->createRevokedLink($owner, new \DateTimeImmutable('-5 days'));
        $this->loginAs('reactivate-owner@example.com');

        $crawler = $this->client->request('GET', '/partages');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[action*="/share-link-reactivate"] button[type="submit"]');
        $row = $crawler->filter('[data-testid="share-link-row"]')->text();
        $this->assertStringContainsString('supprimé', $row);
        $this->assertStringContainsString('25 j', $row);
    }
}
