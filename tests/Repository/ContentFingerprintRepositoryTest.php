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

    // ── #327 (Takeout) — vérification par lot, pas une requête par fichier ──

    public function testFindExistingHashesReturnsOnlyKnownHashes(): void
    {
        $owner = $this->createUser('owner@example.com');
        $known1 = hash('sha256', 'connu-1');
        $known2 = hash('sha256', 'connu-2');
        $unknown = hash('sha256', 'inconnu');

        $this->repository->save(new ContentFingerprint($owner, $known1));
        $this->repository->save(new ContentFingerprint($owner, $known2));

        $result = $this->repository->findExistingHashes($owner, [$known1, $known2, $unknown]);

        $expected = [$known1, $known2];
        sort($expected);
        sort($result);
        $this->assertSame($expected, $result);
    }

    public function testFindExistingHashesReturnsEmptyArrayWhenNoneMatch(): void
    {
        $owner = $this->createUser('owner@example.com');

        $result = $this->repository->findExistingHashes($owner, [hash('sha256', 'a'), hash('sha256', 'b')]);

        $this->assertSame([], $result);
    }

    public function testFindExistingHashesReturnsEmptyArrayForEmptyInput(): void
    {
        $owner = $this->createUser('owner@example.com');

        $result = $this->repository->findExistingHashes($owner, []);

        $this->assertSame([], $result);
    }

    public function testFindExistingHashesIsScopedPerOwner(): void
    {
        $owner = $this->createUser('owner@example.com');
        $other = $this->createUser('other@example.com');
        $hash = hash('sha256', 'contenu-partage');

        $this->repository->save(new ContentFingerprint($other, $hash));

        $result = $this->repository->findExistingHashes($owner, [$hash]);

        $this->assertSame([], $result, "Un hash existant pour un autre owner ne doit pas remonter");
    }

    public function testFindExistingHashesHandlesLargeBatchInOneCall(): void
    {
        // Contrainte d'efficacité (#327) : un import Takeout de plusieurs
        // milliers de fichiers doit passer par un seul appel avec la liste
        // complète des hashs, jamais une boucle d'appels unitaires — vérifié
        // ici sur le résultat correct avec un lot de taille réaliste, la
        // garantie "une seule requête SQL" étant assurée par construction
        // (implémentation en clause IN, pas de boucle interne).
        $owner = $this->createUser('owner@example.com');
        $hashes = [];
        for ($i = 0; $i < 50; ++$i) {
            $hashes[] = hash('sha256', "fichier-{$i}");
        }
        foreach (array_slice($hashes, 0, 10) as $h) {
            $this->repository->save(new ContentFingerprint($owner, $h));
        }

        $result = $this->repository->findExistingHashes($owner, $hashes);
        sort($result);

        $expected = array_slice($hashes, 0, 10);
        sort($expected);
        $this->assertSame($expected, $result);
    }
}
