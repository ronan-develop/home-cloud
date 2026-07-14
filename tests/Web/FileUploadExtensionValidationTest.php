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
 * Sécurité — F8 de l'audit : la validation d'extension à l'upload se basait
 * uniquement sur l'extension fournie par le client (manipulable), et les
 * listes BLOCKED_EXTENSIONS (FileWebController) / NEUTRALIZED_EXTENSIONS
 * (StorageService) n'étaient pas synchronisées. Un fichier PHP renommé en
 * .jpg contournait donc le blocage.
 */
final class FileUploadExtensionValidationTest extends WebTestCase
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

    /**
     * Un fichier PHP renommé en .jpg (extension client mensongère) doit être
     * détecté via son contenu réel (finfo) et neutralisé, pas stocké tel quel.
     */
    public function testPhpContentDisguisedAsJpgIsNeutralized(): void
    {
        $this->createUser();
        $this->em->clear();
        $this->login();

        $crawler = $this->client->request('GET', '/explorer');
        $token = $crawler->filter('#main-upload-form input[name="_token"]')->attr('value');

        $tmp = tempnam(sys_get_temp_dir(), 'php_disguised');
        file_put_contents($tmp, "<?php system(\$_GET['cmd']); ?>");
        $upload = new UploadedFile($tmp, 'photo.jpg', 'image/jpeg', null, true);

        $this->client->request('POST', '/files/upload', ['_token' => $token], ['file' => $upload]);
        @unlink($tmp);

        $stored = $this->em->getRepository(File::class)->findOneBy([]);

        $this->assertNotNull($stored);
        $this->assertTrue(
            $stored->isNeutralized(),
            'Un fichier PHP déguisé en .jpg doit être neutralisé (contenu réel détecté via finfo), pas stocké exécutable.'
        );
    }

    /**
     * Les extensions bloquées explicitement (BLOCKED_EXTENSIONS) doivent
     * rester refusées même quand le contenu réel ne les confirme pas —
     * pas de régression sur le comportement existant.
     */
    public function testExplicitlyBlockedExtensionIsRejected(): void
    {
        $this->createUser();
        $this->em->clear();
        $this->login();

        $crawler = $this->client->request('GET', '/explorer');
        $token = $crawler->filter('#main-upload-form input[name="_token"]')->attr('value');

        $tmp = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($tmp, 'MZ fake binary content');
        $upload = new UploadedFile($tmp, 'setup.exe', 'application/octet-stream', null, true);

        $this->client->request('POST', '/files/upload', ['_token' => $token], ['file' => $upload]);
        @unlink($tmp);

        $this->assertResponseStatusCodeSame(400);
    }
}
