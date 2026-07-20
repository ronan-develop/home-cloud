<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * #241 — la plupart des navigateurs affichent un PDF nativement si le
 * serveur répond en Content-Disposition: inline. app_file_download force
 * "attachment" en dur (téléchargement systématique) : impossible de
 * visualiser un PDF sans le télécharger d'abord.
 *
 * Nouvelle route dédiée à la visualisation, sans dupliquer la logique
 * d'autorisation de app_file_download.
 */
final class FileViewInlineTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private string $storageDir;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->storageDir = static::getContainer()->getParameter('app.storage_dir');
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email = 'test@example.com', string $password = 'pwd12345'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, 'Test');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function login(string $email = 'test@example.com', string $password = 'pwd12345'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    private function createPdfFile(User $owner): File
    {
        $folder = new Folder('Docs', $owner);
        $this->em->persist($folder);

        $rel = 'view-test/doc-' . uniqid() . '.pdf';
        @mkdir($this->storageDir . '/view-test', 0777, true);
        $content = '%PDF-1.4 fake content';
        file_put_contents($this->storageDir . '/' . $rel, $content);

        $file = new File('doc.pdf', 'application/pdf', strlen($content), $rel, $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    /**
     * Fichier neutralisé (#278) : stocké en .bin sur disque, contenu HTML réel —
     * simule ce que StorageService produit pour un upload HTML/SVG dangereux.
     */
    private function createNeutralizedHtmlFile(User $owner): File
    {
        $folder = new Folder('Docs', $owner);
        $this->em->persist($folder);

        $rel = 'view-test/evil-' . uniqid() . '.bin';
        @mkdir($this->storageDir . '/view-test', 0777, true);
        $content = '<html><body><script>alert(document.cookie)</script></body></html>';
        file_put_contents($this->storageDir . '/' . $rel, $content);

        $file = new File('evil.html', 'text/html', strlen($content), $rel, $folder, $owner, neutralized: true);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    public function testViewRouteRespondsWithInlineDisposition(): void
    {
        $user = $this->createUser();
        $file = $this->createPdfFile($user);
        $fileId = $file->getId();
        $this->em->clear();

        $this->login();
        $this->client->request('GET', '/files/' . $fileId . '/view');

        $this->assertResponseIsSuccessful();
        $disposition = (string) $this->client->getResponse()->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline', $disposition, 'Le PDF doit s\'afficher dans le navigateur, pas forcer un téléchargement');
    }

    public function testDownloadRouteStillForcesAttachment(): void
    {
        // Non-régression : la route de téléchargement existante ne doit pas changer.
        $user = $this->createUser();
        $file = $this->createPdfFile($user);
        $fileId = $file->getId();
        $this->em->clear();

        $this->login();
        $this->client->request('GET', '/files/' . $fileId . '/download');

        $this->assertResponseIsSuccessful();
        $disposition = (string) $this->client->getResponse()->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment', $disposition);
    }

    public function testViewRouteForcesOctetStreamForNeutralizedFile(): void
    {
        $user = $this->createUser();
        $file = $this->createNeutralizedHtmlFile($user);
        $fileId = $file->getId();
        $this->em->clear();

        $this->login();
        $this->client->request('GET', '/files/' . $fileId . '/view');

        $this->assertResponseIsSuccessful();
        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertSame('application/octet-stream', $contentType, 'Un fichier neutralisé ne doit jamais être rendu comme HTML/SVG en inline');
    }

    public function testDownloadRouteForcesOctetStreamForNeutralizedFile(): void
    {
        $user = $this->createUser();
        $file = $this->createNeutralizedHtmlFile($user);
        $fileId = $file->getId();
        $this->em->clear();

        $this->login();
        $this->client->request('GET', '/files/' . $fileId . '/download');

        $this->assertResponseIsSuccessful();
        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertSame('application/octet-stream', $contentType);
    }

    /**
     * PDF valide mais dont l'en-tête `%PDF-` n'est pas à l'octet 0 (ex: un
     * fichier téléchargé depuis un site tiers ayant laissé fuiter du texte de
     * debug avant le flux réel). La norme PDF (ISO 32000-1 §7.5.2) tolère cet
     * en-tête décalé dans les 1024 premiers octets — les vrais lecteurs PDF
     * l'ouvrent sans problème — mais `finfo` (utilisé par BinaryFileResponse)
     * est plus strict et détecte `application/octet-stream`. Combiné à
     * X-Content-Type-Options: nosniff, le navigateur ne peut pas se
     * rattraper : il propose le téléchargement au lieu du rendu inline.
     */
    private function createPdfWithShiftedHeader(User $owner): File
    {
        $folder = new Folder('Docs', $owner);
        $this->em->persist($folder);

        $rel = 'view-test/shifted-' . uniqid() . '.pdf';
        @mkdir($this->storageDir . '/view-test', 0777, true);
        $content = "SELECT * FROM `manuals` WHERE `id` = '375015'%PDF-1.5\r\n%âãÏÓ\r\nfake content";
        file_put_contents($this->storageDir . '/' . $rel, $content);

        $file = new File('manual.pdf', 'application/pdf', strlen($content), $rel, $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    public function testViewRouteForcesPdfContentTypeWhenHeaderIsShiftedButWithinSpecTolerance(): void
    {
        $user = $this->createUser();
        $file = $this->createPdfWithShiftedHeader($user);
        $fileId = $file->getId();
        $this->em->clear();

        $this->login();
        $this->client->request('GET', '/files/' . $fileId . '/view');

        $this->assertResponseIsSuccessful();
        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertSame(
            'application/pdf',
            $contentType,
            'Un PDF valide dont l\'en-tête est décalé dans les 1024 premiers octets (toléré par la norme) doit rester servi comme application/pdf.'
        );
    }

    public function testViewRouteDeniesAccessToNonOwner(): void
    {
        $owner = $this->createUser('owner@example.com', 'pwd12345');
        $file = $this->createPdfFile($owner);
        $fileId = $file->getId();
        $this->createUser('intruder@example.com', 'pwd12345');
        $this->em->clear();

        $this->login('intruder@example.com', 'pwd12345');
        $this->client->request('GET', '/files/' . $fileId . '/view');

        $this->assertResponseStatusCodeSame(403);
    }
}
