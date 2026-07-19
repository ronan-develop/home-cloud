<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\UploadBatch;
use App\Tests\AuthenticatedApiTestCase;

/**
 * POST /api/v1/uploads/batch — déclaration d'un lot d'upload. Le serveur, seul
 * juge du routage, renvoie le mode (immediate/deferred) que le front joindra
 * ensuite à chaque upload. La décision ne dépend jamais du client.
 */
final class UploadBatchControllerTest extends AuthenticatedApiTestCase
{
    public function testSmallJpegBatchReturnsImmediateMode(): void
    {
        $alice = $this->createUser('alice-batch@example.com', 'password123', 'Alice');
        $browser = $this->createAuthenticatedKernelBrowser($alice);

        $browser->request(
            'POST',
            '/api/v1/uploads/batch',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['count' => 3, 'totalSize' => 6_000_000, 'filenames' => ['a.jpg', 'b.jpg', 'c.jpg']]),
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode((string) $browser->getResponse()->getContent(), true);
        $this->assertSame(UploadBatch::MODE_IMMEDIATE, $data['mode']);
        $this->assertNotEmpty($data['batchId']);
    }

    public function testBatchWithRawReturnsDeferredMode(): void
    {
        $alice = $this->createUser('alice-raw@example.com', 'password123', 'Alice');
        $browser = $this->createAuthenticatedKernelBrowser($alice);

        $browser->request(
            'POST',
            '/api/v1/uploads/batch',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['count' => 2, 'totalSize' => 2_000_000, 'filenames' => ['a.jpg', 'shot.nef']]),
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode((string) $browser->getResponse()->getContent(), true);
        $this->assertSame(UploadBatch::MODE_DEFERRED, $data['mode']);
    }

    public function testBatchIsOwnedByCurrentUser(): void
    {
        $alice = $this->createUser('alice-owner@example.com', 'password123', 'Alice');
        $browser = $this->createAuthenticatedKernelBrowser($alice);

        $browser->request(
            'POST',
            '/api/v1/uploads/batch',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['count' => 1, 'totalSize' => 1000, 'filenames' => ['a.jpg']]),
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode((string) $browser->getResponse()->getContent(), true);

        $batch = $this->em->getRepository(UploadBatch::class)
            ->find(\Symfony\Component\Uid\Uuid::fromString($data['batchId']));
        $this->assertNotNull($batch);
        $this->assertSame((string) $alice->getId(), (string) $batch->getOwner()->getId());
    }

    public function testUnauthenticatedRequestIsRejected(): void
    {
        $browser = static::createClient()->getKernelBrowser();

        $browser->request(
            'POST',
            '/api/v1/uploads/batch',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['count' => 1, 'totalSize' => 1000, 'filenames' => ['a.jpg']]),
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testStatusReturnsProgressForOwnBatch(): void
    {
        $alice = $this->createUser('alice-status@example.com', 'password123', 'Alice');
        $batch = new UploadBatch($alice, 3, 300_000_000, UploadBatch::MODE_DEFERRED);
        $this->em->persist($batch);
        $this->em->flush();
        $batchId = (string) $batch->getId();

        $browser = $this->createAuthenticatedKernelBrowser($alice);
        $browser->request('GET', '/api/v1/uploads/' . $batchId . '/status');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode((string) $browser->getResponse()->getContent(), true);
        $this->assertSame(UploadBatch::STATUS_PENDING, $data['status']);
        $this->assertSame(0, $data['processed']);
        $this->assertSame(3, $data['total']);
    }

    public function testStatusOfAnotherUsersBatchIsForbidden(): void
    {
        $alice = $this->createUser('alice-owner2@example.com', 'password123', 'Alice');
        $batch = new UploadBatch($alice, 1, 1000, UploadBatch::MODE_IMMEDIATE);
        $this->em->persist($batch);
        $this->em->flush();
        $batchId = (string) $batch->getId();

        $bob = $this->createUser('bob-intrus@example.com', 'password123', 'Bob');
        $browser = $this->createAuthenticatedKernelBrowser($bob);
        $browser->request('GET', '/api/v1/uploads/' . $batchId . '/status');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testStatusOfUnknownBatchReturns404(): void
    {
        $alice = $this->createUser('alice-404@example.com', 'password123', 'Alice');
        $browser = $this->createAuthenticatedKernelBrowser($alice);
        $browser->request('GET', '/api/v1/uploads/00000000-0000-0000-0000-000000000000/status');

        $this->assertResponseStatusCodeSame(404);
    }
}
