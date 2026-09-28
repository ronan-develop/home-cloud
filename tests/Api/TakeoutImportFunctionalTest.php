<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Command\TakeoutImportNightlyDispatchCommand;
use App\Entity\File;
use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Handler\TakeoutImportExtractHandler;
use App\Handler\TakeoutImportHandler;
use App\Message\TakeoutImportExtractMessage;
use App\Repository\AlbumRepository;
use App\Repository\FolderRepository;
use App\Tests\AuthenticatedApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Test fonctionnel de bout en bout (#327, étape 9/9 du plan) : upload d'un
 * petit ZIP Google Takeout réel → import complet → fichier dans le bon
 * dossier. Les Handlers sont invoqués manuellement (transport in-memory en
 * test, cf. config/packages/messenger.yaml) plutôt que d'attendre un
 * worker, pour un test déterministe.
 *
 * #520 : le traitement se fait en 2 étapes (un ZIP par message
 * TakeoutImportExtractMessage, redispatché en boucle jusqu'à dispatcher
 * TakeoutImportProcessMessage) — runFullImportPipeline() enchaîne
 * manuellement les deux handlers jusqu'au message terminal, pour ne pas
 * dupliquer cette boucle dans chaque test.
 *
 * #522 : /start ne dispatche plus immédiatement (marque "scheduled") —
 * runFullImportPipeline() invoque d'abord TakeoutImportNightlyDispatchCommand
 * (simule le cron nocturne) avant de traiter la queue, pour ne pas dupliquer
 * cette étape dans chaque test qui veut un import traité de bout en bout.
 */
final class TakeoutImportFunctionalTest extends AuthenticatedApiTestCase
{
    // JPEG 1x1 minimal valide — nécessaire pour que MediaProcessor reconnaisse
    // un vrai mimeType image/jpeg (contrairement à un contenu texte factice,
    // détecté text/plain par finfo, qui produit un File sans Media associé
    // et empêche silencieusement la création d'album, #478).
    private const MINIMAL_JPEG = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

    private ?string $zipPath = null;

    protected function tearDown(): void
    {
        if ($this->zipPath !== null && is_file($this->zipPath)) {
            unlink($this->zipPath);
        }
        parent::tearDown();
    }

    private function makeTakeoutZip(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'hc_takeout_zip_') . '.zip';
        $this->zipPath = $zipPath;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('Google Photos/Vacances/photo.jpg', base64_decode(self::MINIMAL_JPEG));
        $zip->addFromString(
            'Google Photos/Vacances/photo.jpg.supplemental-metadata.json',
            json_encode(['photoTakenTime' => ['timestamp' => (string) (new \DateTimeImmutable('2020-06-15'))->getTimestamp()]]),
        );
        $zip->addFromString('Google Photos/metadata.json', '{"ignored": true}');
        $zip->close();

        return $zipPath;
    }

    /**
     * @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: string} client + importId
     */
    private function createImportAndUploadFiles(User $user, array $uploadedFiles): array
    {
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        foreach ($uploadedFiles as $uploadedFile) {
            $client->request('POST', "/api/v1/takeout-imports/{$importId}/files", [
                'extra' => ['files' => ['file' => $uploadedFile]],
            ]);
            $this->assertResponseStatusCodeSame(204, (string) $client->getResponse()->getContent());
        }

        return [$client, $importId];
    }

    /**
     * Consomme la file "async" en boucle (TakeoutImportExtractMessage puis
     * TakeoutImportProcessMessage) jusqu'à épuisement — reproduit le
     * comportement réel d'un worker Messenger qui traiterait chaque message
     * redispatché l'un après l'autre (#520).
     */
    private function runFullImportPipeline(): void
    {
        $nightlyDispatchCommand = static::getContainer()->get(TakeoutImportNightlyDispatchCommand::class);
        $nightlyDispatchCommand->run(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        );

        $extractHandler = static::getContainer()->get(TakeoutImportExtractHandler::class);
        $processHandler = static::getContainer()->get(TakeoutImportHandler::class);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');

        while (($envelopes = $transport->get()) !== []) {
            $envelope = $envelopes[0];
            $message = $envelope->getMessage();
            $transport->ack($envelope);

            if ($message instanceof TakeoutImportExtractMessage) {
                $extractHandler($message);
            } else {
                $processHandler($message);
            }
        }
    }

    public function testCreateImportReturnsPendingWithoutDispatchingMessage(): void
    {
        $user = $this->createUser('takeout-create@example.com');

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', '/api/v1/takeout-imports');

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(TakeoutImport::STATUS_PENDING, $data['status']);
        $this->assertNotEmpty($data['id']);

        $transport = static::getContainer()->get('messenger.transport.async');
        $this->assertCount(0, $transport->get(), 'La création seule ne doit dispatcher aucun message');
    }

    // #522 : traiter les imports Takeout la nuit pour limiter la contention
    // sur le mutualisé o2switch — /start ne dispatche plus immédiatement,
    // marque seulement l'import "scheduled" ; le dispatch réel est fait par
    // TakeoutImportNightlyDispatchCommand (cron nocturne dédié).
    public function testStartMarksImportScheduledWithoutDispatchingMessage(): void
    {
        $user = $this->createUser('takeout-upload@example.com');
        $zipPath = $this->makeTakeoutZip();
        $uploadedFile = new UploadedFile($zipPath, 'takeout-20260101-001.zip', 'application/zip', null, true);

        [$client, $importId] = $this->createImportAndUploadFiles($user, [$uploadedFile]);

        $client->request('POST', "/api/v1/takeout-imports/{$importId}/start");
        $this->assertResponseStatusCodeSame(202);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(TakeoutImport::STATUS_SCHEDULED, $data['status']);
        $this->assertSame($importId, $data['id']);

        $transport = static::getContainer()->get('messenger.transport.async');
        $this->assertCount(0, $transport->get(), 'Le dispatch réel se fait par le cron nocturne, pas immédiatement');
    }

    public function testFullImportCreatesFileInDedicatedFolder(): void
    {
        $user = $this->createUser('takeout-full@example.com');
        $zipPath = $this->makeTakeoutZip();
        $uploadedFile = new UploadedFile($zipPath, 'takeout-20260101-001.zip', 'application/zip', null, true);

        [$client, $importId] = $this->createImportAndUploadFiles($user, [$uploadedFile]);
        $client->request('POST', "/api/v1/takeout-imports/{$importId}/start");
        $this->assertResponseStatusCodeSame(202);

        $this->runFullImportPipeline();

        $this->em->clear();

        $import = $this->em->getRepository(TakeoutImport::class)->find($importId);
        $this->assertSame(TakeoutImport::STATUS_COMPLETED, $import->getStatus(), (string) $import->getErrorMessage());
        $this->assertSame(1, $import->getMediaImportedCount());
        $this->assertSame(0, $import->getDuplicatesSkippedCount());

        $folderRepository = static::getContainer()->get(FolderRepository::class);
        $expectedFolderName = sprintf('Import Google Photos %s', $import->getCreatedAt()->format('Y-m-d'));
        $folder = $folderRepository->findOneBy(['name' => $expectedFolderName, 'owner' => $user]);
        $this->assertNotNull($folder, 'Le dossier "Import Google Photos <date>" doit exister');

        $file = $this->em->getRepository(File::class)->findOneBy(['folder' => $folder]);
        $this->assertNotNull($file);
        $this->assertSame('photo.jpg', $file->getOriginalName());
        $this->assertSame((string) $user->getId(), (string) $file->getOwner()->getId());

        // #478 : "Google Photos/Vacances/photo.jpg" — "Vacances" est un vrai
        // album Google Photos (pas "Photos from <année>"), doit être recréé
        // comme Album HomeCloud, immédiatement visible sur /albums.
        $album = static::getContainer()->get(AlbumRepository::class)
            ->findOneBy(['name' => 'Vacances', 'owner' => $user]);
        $this->assertNotNull($album, 'L\'album "Vacances" doit avoir été créé');
        $this->assertCount(1, $album->getMedias());
    }

    public function testReimportingSameZipSkipsAsDuplicate(): void
    {
        $user = $this->createUser('takeout-dup@example.com');

        // Premier import
        $zipPath1 = $this->makeTakeoutZip();
        $uploadedFile1 = new UploadedFile($zipPath1, 'takeout-20260101-001.zip', 'application/zip', null, true);
        [$client, $importId1] = $this->createImportAndUploadFiles($user, [$uploadedFile1]);
        $client->request('POST', "/api/v1/takeout-imports/{$importId1}/start");
        $this->assertResponseStatusCodeSame(202, (string) $client->getResponse()->getContent());

        $this->runFullImportPipeline();

        // Second import du même contenu
        $zipPath2 = $this->makeTakeoutZip();
        $uploadedFile2 = new UploadedFile($zipPath2, 'takeout-20260101-001.zip', 'application/zip', null, true);
        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
        $importId2 = json_decode($client->getResponse()->getContent(), true)['id'];
        $client->request('POST', "/api/v1/takeout-imports/{$importId2}/files", [
            'extra' => ['files' => ['file' => $uploadedFile2]],
        ]);
        $this->assertResponseStatusCodeSame(204, (string) $client->getResponse()->getContent());
        $client->request('POST', "/api/v1/takeout-imports/{$importId2}/start");
        $this->assertResponseStatusCodeSame(202, (string) $client->getResponse()->getContent());

        $this->runFullImportPipeline();
        unlink($zipPath2);

        $import2 = $this->em->getRepository(TakeoutImport::class)->find($importId2);
        $this->assertSame(TakeoutImport::STATUS_COMPLETED, $import2->getStatus());
        $this->assertSame(0, $import2->getMediaImportedCount(), 'Le média déjà importé doit être détecté comme doublon');
        $this->assertSame(1, $import2->getDuplicatesSkippedCount());
    }

    /**
     * Progress bar (#327) : GET /v1/takeout-imports/{id} expose
     * totalMediaCount/processedCount pour que le front calcule
     * processedCount/totalMediaCount pendant le traitement asynchrone.
     */
    public function testGetExposesProgressAfterCompletion(): void
    {
        $user = $this->createUser('takeout-progress@example.com');
        $zipPath = $this->makeTakeoutZip();
        $uploadedFile = new UploadedFile($zipPath, 'takeout-20260101-001.zip', 'application/zip', null, true);

        [$client, $importId] = $this->createImportAndUploadFiles($user, [$uploadedFile]);
        $client->request('POST', "/api/v1/takeout-imports/{$importId}/start");
        $this->assertResponseStatusCodeSame(202);

        $this->runFullImportPipeline();

        $client->request('GET', '/api/v1/takeout-imports/' . $importId);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['totalMediaCount']);
        $this->assertSame(1, $data['processedCount']);
        $this->assertSame(TakeoutImport::STATUS_COMPLETED, $data['status']);
    }

    public function testStartWithoutFilesReturns400(): void
    {
        $user = $this->createUser('takeout-empty@example.com');

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('POST', "/api/v1/takeout-imports/{$importId}/start");

        $this->assertResponseStatusCodeSame(400);
    }
}
