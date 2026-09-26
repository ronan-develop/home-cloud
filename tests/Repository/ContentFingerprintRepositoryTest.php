<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\ContentFingerprint;
use App\Entity\User;
use App\Repository\ContentFingerprintRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContentFingerprintRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ContentFingerprintRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(ContentFingerprintRepository::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM content_fingerprints');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email): User
    {
        $user = new User($email, 'Test');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testSavePersistsFingerprint(): void
    {
        $owner = $this->createUser('owner@example.com');
        $hash = hash('sha256', 'contenu');
        $fingerprint = new ContentFingerprint($owner, $hash);

        $this->repository->save($fingerprint);

        $found = $this->repository->find($fingerprint->getId());
        $this->assertNotNull($found);
        $this->assertSame($hash, $found->getContentHash());
    }

    public function testExistsForOwnerReturnsTrueWhenFingerprintExists(): void
    {
        $owner = $this->createUser('owner@example.com');
        $hash = hash('sha256', 'contenu');
        $this->repository->save(new ContentFingerprint($owner, $hash));

        $this->assertTrue($this->repository->existsForOwner($owner, $hash));
    }

    public function testExistsForOwnerReturnsFalseWhenNoMatch(): void
    {
        $owner = $this->createUser('owner@example.com');

        $this->assertFalse($this->repository->existsForOwner($owner, hash('sha256', 'inconnu')));
    }

    public function testExistsForOwnerIsScopedPerOwner(): void
    {
        $owner = $this->createUser('owner@example.com');
        $other = $this->createUser('other@example.com');
        $hash = hash('sha256', 'contenu-partage');

        $this->repository->save(new ContentFingerprint($owner, $hash));

        $this->assertTrue($this->repository->existsForOwner($owner, $hash));
        $this->assertFalse(
            $this->repository->existsForOwner($other, $hash),
            'Le même contenu uploadé par un autre utilisateur ne doit pas être un doublon'
        );
    }

    public function testFingerprintSurvivesFileDeletion(): void
    {
        // Le fingerprint est volontairement sans relation vers File : on
        // vérifie ici qu'il reste interrogeable même après suppression du
        // File/Media qui l'a créé (simulée en ne créant jamais de File —
        // le fingerprint n'a par construction aucune dépendance vers lui).
        $owner = $this->createUser('owner@example.com');
        $hash = hash('sha256', 'photo-supprimee-puis-reimportee');
        $this->repository->save(new ContentFingerprint($owner, $hash));

        $this->em->clear();

        $this->assertTrue(
            $this->repository->existsForOwner($owner, $hash),
            'Le fingerprint doit rester détectable même si le File original a été supprimé'
        );
    }
}
