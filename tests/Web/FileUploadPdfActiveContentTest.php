<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * #286 : le format PDF peut embarquer du JavaScript exécutable
 * (/OpenAction, /AA, /JS) exécuté par le lecteur PDF natif du navigateur.
 * Un PDF malveillant est neutralisé comme les autres types dangereux
 * (HTML/SVG/JS) plutôt que stocké tel quel — même mécanisme, même garanties
 * que StorageService::NEUTRALIZED_MIME_TYPES.
 */
final class FileUploadPdfActiveContentTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
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

    private function uploadPdf(string $content): void
    {
        $this->createUser();
        $this->em->clear();
        $this->login();

        $crawler = $this->client->request('GET', '/explorer');
        $token = $crawler->filter('#main-upload-form input[name="_token"]')->attr('value');

        $tmp = tempnam(sys_get_temp_dir(), 'pdf_upload');
        file_put_contents($tmp, $content);
        $upload = new UploadedFile($tmp, 'document.pdf', 'application/pdf', null, true);

        $this->client->request('POST', '/files/upload', ['_token' => $token], ['file' => $upload]);
        @unlink($tmp);
    }

    /**
     * PDF minimal avec une action d'ouverture exécutant du JavaScript
     * (/OpenAction /S /JavaScript /JS) — pattern typique d'un PDF malveillant.
     */
    public function testPdfWithOpenActionJavaScriptIsNeutralized(): void
    {
        $maliciousPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /OpenAction 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Action /S /JavaScript /JS (app.alert\\('pwned'\\);) >>\nendobj\n"
            . "%%EOF";

        $this->uploadPdf($maliciousPdf);

        $stored = $this->em->getRepository(File::class)->findOneBy([]);

        $this->assertNotNull($stored);
        $this->assertTrue(
            $stored->isNeutralized(),
            'Un PDF avec /OpenAction /JavaScript doit être neutralisé, pas stocké tel quel.'
        );
    }

    /**
     * Un PDF ordinaire, sans contenu actif, ne doit subir aucune régression.
     */
    public function testOrdinaryPdfIsNotNeutralized(): void
    {
        $ordinaryPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "%%EOF";

        $this->uploadPdf($ordinaryPdf);

        $stored = $this->em->getRepository(File::class)->findOneBy([]);

        $this->assertNotNull($stored);
        $this->assertFalse(
            $stored->isNeutralized(),
            'Un PDF ordinaire sans contenu actif ne doit pas être neutralisé.'
        );
    }

    /**
     * Faux positif à éviter : un PDF légitime (ex. un cours sur le JavaScript
     * ou le JSON) mentionne ces mots dans son contenu texte, sans qu'il
     * s'agisse d'une véritable action PDF embarquée. Un marqueur `/JS` en
     * simple sous-chaîne matcherait à tort `/JSON` (ex: un chemin de fichier
     * "config/JSON" cité dans le texte) — le détecteur doit distinguer un
     * vrai token PDF (`/JS` isolé) d'une sous-chaîne fortuite.
     */
    public function testPdfMentioningJsonInTextIsNotNeutralized(): void
    {
        $courseAboutJsonPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length 60 >>\nstream\n"
            . "BT (Voir le fichier de configuration config/JSON pour la suite) Tj ET\n"
            . "endstream\nendobj\n"
            . "%%EOF";

        $this->uploadPdf($courseAboutJsonPdf);

        $stored = $this->em->getRepository(File::class)->findOneBy([]);

        $this->assertNotNull($stored);
        $this->assertFalse(
            $stored->isNeutralized(),
            'Une mention textuelle de "/JSON" ne doit pas être confondue avec le token PDF "/JS".'
        );
    }
}
