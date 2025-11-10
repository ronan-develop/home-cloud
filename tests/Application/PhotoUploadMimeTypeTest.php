<?php

namespace App\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\Integration\DatabaseResetTrait;

class PhotoUploadMimeTypeTest extends WebTestCase
{
    use DatabaseResetTrait;

    protected function setUp(): void
    {
        parent::setUp();
        self::resetDatabaseAndFixtures();
    }

    public function testUploadRefusedForInvalidMimeType(): void
    {
        $client = static::createClient();
        // Connexion utilisateur (adapter selon ton système d'auth)
        $client->request('GET', '/login');
        $client->submitForm('Sign in', [
            '_username' => 'user1@homecloud.test',
            '_password' => 'root',
        ]);
        $client->followRedirect();

        $crawler = $client->request('GET', '/photos/upload');
        $formNodes = $crawler->filter('form[name="photo_upload"]');
        if ($formNodes->count() === 0) {
            file_put_contents(__DIR__ . '/../last-upload-form.html', $crawler->html());
            $this->fail('Formulaire photo_upload introuvable. Voir last-upload-form.html pour debug.');
        }
        $form = $formNodes->form([
            'photo_upload[file]' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                __DIR__ . '/../fixtures/test.pptx',
                'test.pptx',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                null,
                true
            ),
            'photo_upload[title]' => 'Test PPTX',
        ]);
        $client->submit($form);

        // Dump du HTML pour analyse si le message n'est pas trouvé
        file_put_contents(__DIR__ . '/../last-upload-form.html', $client->getResponse()->getContent());

        $this->assertSelectorExists('.flash-error', 'Le message d\'erreur doit être affiché');
        $this->assertSelectorTextContains('.flash-error', 'Type MIME refusé', 'Le message doit mentionner le refus du type MIME');
    }
}
