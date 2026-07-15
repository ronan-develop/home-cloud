<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Bascule de visibilité d'une ressource — le "bouton d'arrêt d'urgence" :
 * repasser une ressource en private doit révoquer immédiatement tous ses
 * liens de partage actifs, pas seulement empêcher la création de nouveaux.
 */
final class ResourceVisibilityWebTest extends WebTestCase
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

    private function createOwner(): User
    {
        return $this->createWebUser('visibility-owner@example.com', 'secret123', 'Owner');
    }

    private function createShareableFileWithActiveLink(User $owner): array
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
        $this->em->persist($link);
        $this->em->flush();

        return [$file, $link];
    }

    public function testMakingResourcePrivateRevokesActiveShareLinks(): void
    {
        $owner = $this->createOwner();
        [$file, $link] = $this->createShareableFileWithActiveLink($owner);
        $this->loginAs('visibility-owner@example.com');

        $crawler = $this->client->request('GET', '/explorer');
        $csrfToken = $crawler->filter('form[action*="/resource-visibility-update"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/resource-visibility-update', [
            '_token'       => $csrfToken,
            'resourceType' => 'file',
            'resourceId'   => $file->getId()->toRfc4122(),
            'visibility'   => 'private',
        ]);

        $this->assertResponseRedirects();

        $this->em->clear();
        $reloadedLink = $this->em->getRepository(ShareLink::class)->find($link->getId());
        $this->assertNotNull($reloadedLink->getRevokedAt(), 'Le lien actif doit avoir été révoqué');

        $reloadedFile = $this->em->getRepository(File::class)->find($file->getId());
        $this->assertSame(File::VISIBILITY_PRIVATE, $reloadedFile->getVisibility());
    }

    public function testMakingResourceLinkAllowedEnablesPublicShareLinkCreation(): void
    {
        // Une ressource est private par défaut : basculer en link_allowed est
        // le préalable indispensable avant que l'onglet "Lien" de ShareModal
        // puisse créer un ShareLink (sinon 403 via VisibilityChecker).
        $owner = $this->createOwner();
        $folder = new Folder('Docs', $owner);
        $folder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($folder);
        $file = new File('photo.jpg', 'image/jpeg', 1024, 'test/photo.jpg', $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();
        $this->assertSame(File::VISIBILITY_PRIVATE, $file->getVisibility(), 'private par défaut');

        $this->loginAs('visibility-owner@example.com');

        $crawler = $this->client->request('GET', '/explorer');
        $csrfToken = $crawler->filter('form[action*="/resource-visibility-update"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/resource-visibility-update', [
            '_token'       => $csrfToken,
            'resourceType' => 'file',
            'resourceId'   => $file->getId()->toRfc4122(),
            'visibility'   => 'link_allowed',
        ]);

        $this->assertResponseRedirects();

        $this->em->clear();
        $reloaded = $this->em->getRepository(File::class)->find($file->getId());
        $this->assertSame(File::VISIBILITY_LINK_ALLOWED, $reloaded->getVisibility());
    }

    public function testAllowingLinkSharingOnAnAlbumRedirectsBackToTheAlbumPage(): void
    {
        // Referrer-Policy: no-referrer (posé pour protéger les tokens de
        // ShareLink) fait que le navigateur n'envoie jamais l'en-tête Referer :
        // le contrôleur ne peut donc PAS s'appuyer dessus pour revenir à la
        // bonne page, il doit dériver l'URL depuis resourceType/resourceId.
        $owner = $this->createOwner();
        $album = new Album('Vacances', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $this->loginAs('visibility-owner@example.com');

        $crawler = $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $csrfToken = $crawler->filter('form[action*="/resource-visibility-update"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/resource-visibility-update', [
            '_token'       => $csrfToken,
            'resourceType' => 'album',
            'resourceId'   => $album->getId()->toRfc4122(),
            'visibility'   => 'link_allowed',
        ]);

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
    }

    public function testNonOwnerCannotChangeVisibility(): void
    {
        $owner = $this->createOwner();
        [$file, ] = $this->createShareableFileWithActiveLink($owner);
        $attacker = $this->createWebUser('visibility-attacker@example.com', 'secret123', 'Attacker');
        // L'attaquant a son propre dossier partageable, pour obtenir un
        // token CSRF valide dans SA session (le token est lié à la session,
        // pas global) sans dépendre d'une ressource de la victime.
        $attackerFolder = new Folder('AttackerDocs', $attacker);
        $attackerFolder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($attackerFolder);
        $this->em->flush();

        $this->loginAs('visibility-attacker@example.com');
        $crawler = $this->client->request('GET', '/explorer');
        $csrfToken = $crawler->filter('form[action*="/resource-visibility-update"] input[name="_token"]')
            ->first()->attr('value');

        $this->client->request('POST', '/resource-visibility-update', [
            '_token'       => $csrfToken,
            'resourceType' => 'file',
            'resourceId'   => $file->getId()->toRfc4122(),
            'visibility'   => 'private',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
