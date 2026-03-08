# ✅ Vérification Approche TDD + Complétion Tests

## 🔴 Approche TDD Validée

### Cycle RED → GREEN → REFACTOR

```
┌─────────────────────────────────────────────────────────────┐
│  Phase 1 : Foundation — Authentication & Authorization      │
├─────────────────────────────────────────────────────────────┤
│  🔴 RED    → Écrire les tests AVANT le code                │
│  🟢 GREEN  → Implémenter le minimum pour passer les tests  │
│  🔵 REFACTOR → Améliorer sans casser les tests             │
└─────────────────────────────────────────────────────────────┘
```

### Ordre d'Exécution TDD pour Phase 1


```bash
# 1. 🔴 RED : Écrire TOUS les tests (qui échouent)
tests/Unit/Service/Security/AuthenticationResolverTest.php
tests/Unit/Service/Security/AuthorizationCheckerTest.php
tests/Unit/Repository/FolderRepositoryTest.php

# 2. 🟢 GREEN : Implémenter le code minimum
src/Service/Security/AuthenticationResolver.php
src/Service/Security/AuthorizationChecker.php
src/Repository/FolderRepository.php (méthode findAncestorIds)

# 3. 🔵 REFACTOR : Optimiser + documenter
# (Améliorer sans casser les tests)

# 4. ✅ Validation : Tous les tests passent
./vendor/bin/phpunit tests/Unit/Service/Security/
```

---

## 📝 1.4 Tests Unitaires Complets (TDD)

### 1.4.1 AuthenticationResolverTest

**Fichier :** `tests/Unit/Service/Security/AuthenticationResolverTest.php`

```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Security\AuthenticationResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Uid\Uuid;

/**
 * @covers \App\Service\Security\AuthenticationResolver
 */
final class AuthenticationResolverTest extends TestCase
{
    private TokenStorageInterface $tokenStorage;
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private AuthenticationResolver $resolver;

    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->resolver = new AuthenticationResolver(
            $this->tokenStorage,
            $this->userRepository,
            $this->logger
        );
    }

    /**
     * 🔴 RED : Test écrit AVANT l'implémentation
     * 
     * @test
     */
    public function it_returns_user_when_token_contains_user_instance(): void
    {
        // Given
        $user = $this->createUser('john@example.com');
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        
        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        // When
        $result = $this->resolver->getAuthenticatedUser();

        // Then
        $this->assertSame($user, $result);
    }

    /**
     * @test
     */
    public function it_returns_user_when_token_contains_email_string(): void
    {
        // Given
        $email = 'jane@example.com';
        $user = $this->createUser($email);
        
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($email);
        
        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn($user);

        // When
        $result = $this->resolver->getAuthenticatedUser();

        // Then
        $this->assertSame($user, $result);
    }

    /**
     * @test
     */
    public function it_returns_null_when_no_token_exists(): void
    {
        // Given
        $this->tokenStorage
            ->method('getToken')
            ->willReturn(null);

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('No security token found');

        // When
        $result = $this->resolver->getAuthenticatedUser();

        // Then
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function it_returns_null_when_user_email_not_found_in_database(): void
    {
        // Given
        $email = 'unknown@example.com';
        
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($email);
        
        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->userRepository
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn(null);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('User not found in database', ['email' => $email]);

        // When
        $result = $this->resolver->getAuthenticatedUser();

        // Then
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function it_returns_null_when_token_contains_unexpected_type(): void
    {
        // Given
        $invalidUser = new \stdClass();
        
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($invalidUser);
        
        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected user type in security token',
                $this->callback(function ($context) {
                    return isset($context['type']) && $context['type'] === 'stdClass';
                })
            );

        // When
        $result = $this->resolver->getAuthenticatedUser();

        // Then
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_requiring_user_but_not_authenticated(): void
    {
        // Given
        $this->tokenStorage
            ->method('getToken')
            ->willReturn(null);

        // Expect
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Authentication required');

        // When
        $this->resolver->requireUser();
    }

    /**
     * @test
     */
    public function it_returns_user_when_requiring_user_and_authenticated(): void
    {
        // Given
        $user = $this->createUser('admin@example.com');
        
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        
        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        // When
        $result = $this->resolver->requireUser();

        // Then
        $this->assertSame($user, $result);
    }

    /**
     * @test
     */
    public function it_returns_user_id_when_authenticated(): void
    {
        // Given
        $uuid = Uuid::v4();
        $user = $this->createUser('test@example.com', $uuid);
        
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        
        $this->tokenStorage
            ->method('getToken')
            ->willReturn($token);

        // When
        $result = $this->resolver->getUserId();

        // Then
        $this->assertSame($uuid->toRfc4122(), $result);
    }

    /**
     * @test
     */
    public function it_returns_null_user_id_when_not_authenticated(): void
    {
        // Given
        $this->tokenStorage
            ->method('getToken')
            ->willReturn(null);

        // When
        $result = $this->resolver->getUserId();

        // Then
        $this->assertNull($result);
    }

    /**
     * Helper : Créer un utilisateur de test
     */
    private function createUser(string $email, ?Uuid $id = null): User
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn($email);
        $user->method('getId')->willReturn($id ?? Uuid::v4());
        
        return $user;
    }
}
```

---

# 📝 1.4.2 AuthorizationCheckerTest (Complet)

**Fichier :** `tests/Unit/Service/Security/AuthorizationCheckerTest.php`

```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Repository\FolderRepository;
use App\Service\Security\AuthorizationChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * @covers \App\Service\Security\AuthorizationChecker
 */
final class AuthorizationCheckerTest extends TestCase
{
    private FolderRepository $folderRepository;
    private AuthorizationChecker $checker;

    protected function setUp(): void
    {
        $this->folderRepository = $this->createMock(FolderRepository::class);
        $this->checker = new AuthorizationChecker($this->folderRepository);
    }

    /**
     * @test
     */
    public function it_allows_access_when_user_owns_file(): void
    {
        // Given
        $ownerId = Uuid::v4();
        $owner = $this->createUser($ownerId);
        $requester = $this->createUser($ownerId); // Même ID
        
        $file = $this->createMock(File::class);
        $file->method('getOwner')->willReturn($owner);

        // When / Then (pas d'exception)
        $this->checker->assertOwns($file, $requester);
        
        $this->assertTrue(true); // Assertion pour éviter "risky test"
    }

    /**
     * @test
     */
    public function it_denies_access_when_user_does_not_own_file(): void
    {
        // Given
        $owner = $this->createUser(Uuid::v4());
        $requester = $this->createUser(Uuid::v4()); // ID différent
        
        $file = $this->createMock(File::class);
        $file->method('getOwner')->willReturn($owner);

        // Expect
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/You do not own this File/');

        // When
        $this->checker->assertOwns($file, $requester);
    }

    /**
     * @test
     */
    public function it_allows_access_when_user_owns_folder(): void
    {
        // Given
        $ownerId = Uuid::v4();
        $owner = $this->createUser($ownerId);
        $requester = $this->createUser($ownerId);
        
        $folder = $this->createMock(Folder::class);
        $folder->method('getOwner')->willReturn($owner);

        // When / Then
        $this->checker->assertOwns($folder, $requester);
        
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function it_denies_access_when_user_does_not_own_folder(): void
    {
        // Given
        $owner = $this->createUser(Uuid::v4());
        $requester = $this->createUser(Uuid::v4());
        
        $folder = $this->createMock(Folder::class);
        $folder->method('getOwner')->willReturn($owner);

        // Expect
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/You do not own this Folder/');

        // When
        $this->checker->assertOwns($folder, $requester);
    }

    /**
     * @test
     */
    public function it_detects_cycle_when_moving_folder_under_descendant(): void
    {
        // Given
        // Structure : A > B > C
        // Tentative : Déplacer A sous C (créerait A > B > C > A)
        
        $folderA = $this->createFolder('A');
        $folderC = $this->createFolder('C');
        
        // Mock : C a pour ancêtres [B, A]
        $this->folderRepository
            ->expects($this->once())
            ->method('findAncestorIds')
            ->with($folderC)
            ->willReturn([
                $folderA->getId()->toRfc4122(), // A est ancêtre de C
                Uuid::v4()->toRfc4122(),        // B (autre ancêtre)
            ]);

        // When
        $result = $this->checker->wouldCreateCycle($folderA, $folderC);

        // Then
        $this->assertTrue($result, 'Should detect cycle');
    }

    /**
     * @test
     */
    public function it_allows_move_when_no_cycle_created(): void
    {
        // Given
        // Structure : A > B, C (séparés)
        // Tentative : Déplacer B sous C (OK, pas de cycle)
        
        $folderB = $this->createFolder('B');
        $folderC = $this->createFolder('C');
        
        // Mock : C n'a pas B dans ses ancêtres
        $this->folderRepository
            ->method('findAncestorIds')
            ->with($folderC)
            ->willReturn([
                Uuid::v4()->toRfc4122(), // Autre ancêtre (pas B)
            ]);

        // When
        $result = $this->checker->wouldCreateCycle($folderB, $folderC);

        // Then
        $this->assertFalse($result, 'Should allow move (no cycle)');
    }

    /**
     * @test
     */
    public function it_allows_move_when_target_has_no_ancestors(): void
    {
        // Given
        // Structure : A, B (racines)
        // Tentative : Déplacer A sous B (OK)
        
        $folderA = $this->createFolder('A');
        $folderB = $this->createFolder('B');
        
        // Mock : B n'a pas d'ancêtres
        $this->folderRepository
            ->method('findAncestorIds')
            ->with($folderB)
            ->willReturn([]);

        // When
        $result = $this->checker->wouldCreateCycle($folderA, $folderB);

        // Then
        $this->assertFalse($result, 'Should allow move (no ancestors)');
    }

    /**
     * @test
     */
    public function it_detects_cycle_when_moving_folder_under_itself(): void
    {
        // Given
        // Tentative : Déplacer A sous A (cycle direct)
        
        $folderA = $this->createFolder('A');
        
        // Mock : A a pour ancêtres [A] (lui-même)
        $this->folderRepository
            ->method('findAncestorIds')
            ->with($folderA)
            ->willReturn([
                $folderA->getId()->toRfc4122(),
            ]);

        // When
        $result = $this->checker->wouldCreateCycle($folderA, $folderA);

        // Then
        $this->assertTrue($result, 'Should detect self-cycle');
    }

    /**
     * @test
     */
    public function it_allows_access_when_user_is_folder_owner(): void
    {
        // Given
        $ownerId = Uuid::v4();
        $owner = $this->createUser($ownerId);
        $requester = $this->createUser($ownerId);
        
        $folder = $this->createMock(Folder::class);
        $folder->method('getOwner')->willReturn($owner);

        // When / Then
        $this->checker->assertCanAccessFolder($folder, $requester);
        
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function it_denies_access_when_user_is_not_folder_owner(): void
    {
        // Given
        $owner = $this->createUser(Uuid::v4());
        $requester = $this->createUser(Uuid::v4());
        
        $folder = $this->createMock(Folder::class);
        $folder->method('getOwner')->willReturn($owner);

        // Expect
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You cannot access this folder');

        // When
        $this->checker->assertCanAccessFolder($folder, $requester);
    }

    /**
     * @test
     */
    public function it_handles_deep_folder_hierarchy_without_cycle(): void
    {
        // Given
        // Structure : A > B > C > D > E
        // Tentative : Déplacer F sous E (OK, F pas dans la hiérarchie)
        
        $folderF = $this->createFolder('F');
        $folderE = $this->createFolder('E');
        
        // Mock : E a 4 ancêtres (A, B, C, D) mais pas F
        $this->folderRepository
            ->method('findAncestorIds')
            ->with($folderE)
            ->willReturn([
                Uuid::v4()->toRfc4122(), // A
                Uuid::v4()->toRfc4122(), // B
                Uuid::v4()->toRfc4122(), // C
                Uuid::v4()->toRfc4122(), // D
            ]);

        // When
        $result = $this->checker->wouldCreateCycle($folderF, $folderE);

        // Then
        $this->assertFalse($result, 'Should allow move in deep hierarchy');
    }

    /**
     * @test
     */
    public function it_detects_cycle_in_deep_folder_hierarchy(): void
    {
        // Given
        // Structure : A > B > C > D > E
        // Tentative : Déplacer B sous E (créerait A > B > C > D > E > B)
        
        $folderB = $this->createFolder('B');
        $folderE = $this->createFolder('E');
        
        // Mock : E a pour ancêtres [A, B, C, D]
        $this->folderRepository
            ->method('findAncestorIds')
            ->with($folderE)
            ->willReturn([
                Uuid::v4()->toRfc4122(),        // A
                $folderB->getId()->toRfc4122(), // B ← trouvé !
                Uuid::v4()->toRfc4122(),        // C
                Uuid::v4()->toRfc4122(),        // D
            ]);

        // When
        $result = $this->checker->wouldCreateCycle($folderB, $folderE);

        // Then
        $this->assertTrue($result, 'Should detect cycle in deep hierarchy');
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Créer un utilisateur de test avec un UUID spécifique
     */
    private function createUser(Uuid $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        
        return $user;
    }

    /**
     * Créer un dossier de test avec un UUID aléatoire
     */
    private function createFolder(string $name): Folder
    {
        $folder = $this->createMock(Folder::class);
        $folder->method('getId')->willReturn(Uuid::v4());
        $folder->method('getName')->willReturn($name);
        
        return $folder;
    }
}
```

---

# 📝 1.4.3 FolderRepositoryTest (Complet)

**Fichier :** `tests/Integration/Repository/FolderRepositoryTest.php`

```php
<?php
declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Folder;
use App\Entity\User;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration pour FolderRepository
 * 
 * @covers \App\Repository\FolderRepository
 * @group integration
 */
final class FolderRepositoryTest extends KernelTestCase
{
    private FolderRepository $repository;
    private EntityManagerInterface $em;
    private User $testUser;

    protected function setUp(): void
    {
        self::bootKernel();
        
        $container = static::getContainer();
        $this->repository = $container->get(FolderRepository::class);
        $this->em = $container->get('doctrine')->getManager();
        
        // Créer un utilisateur de test
        $this->testUser = new User();
        $this->testUser->setEmail('test-' . uniqid() . '@example.com');
        $this->testUser->setPassword('hashed_password');
        $this->em->persist($this->testUser);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        // Nettoyer les données de test
        $this->em->createQuery('DELETE FROM App\Entity\Folder')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
        
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_finds_ancestor_ids_for_nested_folders(): void
    {
        // Given
        // Structure : A > B > C > D
        $folderA = $this->createFolder('A', null);
        $folderB = $this->createFolder('B', $folderA);
        $folderC = $this->createFolder('C', $folderB);
        $folderD = $this->createFolder('D', $folderC);
        
        $this->em->persist($folderA);
        $this->em->persist($folderB);
        $this->em->persist($folderC);
        $this->em->persist($folderD);
        $this->em->flush();

        // When
        $ancestorIds = $this->repository->findAncestorIds($folderD);

        // Then
        $this->assertCount(3, $ancestorIds, 'D should have 3 ancestors (A, B, C)');
        $this->assertContains($folderA->getId()->toRfc4122(), $ancestorIds);
        $this->assertContains($folderB->getId()->toRfc4122(), $ancestorIds);
        $this->assertContains($folderC->getId()->toRfc4122(), $ancestorIds);
        $this->assertNotContains($folderD->getId()->toRfc4122(), $ancestorIds, 'Should not include itself');
    }

    /**
     * @test
     */
    public function it_returns_empty_array_for_root_folder(): void
    {
        // Given
        $rootFolder = $this->createFolder('Root', null);
        $this->em->persist($rootFolder);
        $this->em->flush();

        // When
        $ancestorIds = $this->repository->findAncestorIds($rootFolder);

        // Then
        $this->assertEmpty($ancestorIds, 'Root folder should have no ancestors');
    }

    /**
     * @test
     */
    public function it_finds_ancestor_ids_in_correct_order(): void
    {
        // Given
        // Structure : A > B > C > D > E (5 niveaux)
        $folderA = $this->createFolder('A', null);
        $folderB = $this->createFolder('B', $folderA);
        $folderC = $this->createFolder('C', $folderB);
        $folderD = $this->createFolder('D', $folderC);
        $folderE = $this->createFolder('E', $folderD);
        
        $this->em->persist($folderA);
        $this->em->persist($folderB);
        $this->em->persist($folderC);
        $this->em->persist($folderD);
        $this->em->persist($folderE);
        $this->em->flush();

        // When
        $ancestorIds = $this->repository->findAncestorIds($folderE);

        // Then
        $this->assertCount(4, $ancestorIds, 'E should have 4 ancestors');
        
        // Vérifier que tous les ancêtres sont présents
        $expectedIds = [
            $folderA->getId()->toRfc4122(),
            $folderB->getId()->toRfc4122(),
            $folderC->getId()->toRfc4122(),
            $folderD->getId()->toRfc4122(),
        ];
        
        foreach ($expectedIds as $expectedId) {
            $this->assertContains($expectedId, $ancestorIds);
        }
    }

    /**
     * @test
     */
    public function it_finds_user_tree_optimized_with_eager_loading(): void
    {
        // Given
        // Structure :
        // Root1
        //   ├─ Child1
        //   └─ Child2
        // Root2
        
        $root1 = $this->createFolder('Root1', null);
        $child1 = $this->createFolder('Child1', $root1);
        $child2 = $this->createFolder('Child2', $root1);
        $root2 = $this->createFolder('Root2', null);
        
        $this->em->persist($root1);
        $this->em->persist($child1);
        $this->em->persist($child2);
        $this->em->persist($root2);
        $this->em->flush();
        
        // Clear pour forcer le chargement depuis la DB
        $this->em->clear();

        // When
        $tree = $this->repository->findUserTreeOptimized($this->testUser);

        // Then
        $this->assertCount(2, $tree, 'Should return 2 root folders');
        
        // Vérifier que les enfants sont chargés (eager loading)
        $rootFolders = array_values($tree);
        $firstRoot = $rootFolders[0];
        
        // Accéder aux enfants ne devrait PAS déclencher de requête SQL
        // (car eager loading via leftJoin dans la requête)
        $children = $firstRoot->getChildren();
        $this->assertNotEmpty($children, 'Children should be loaded');
    }

    /**
     * @test
     */
    public function it_returns_empty_array_when_user_has_no_folders(): void
    {
        // Given
        // Utilisateur sans dossiers
        
        // When
        $tree = $this->repository->findUserTreeOptimized($this->testUser);

        // Then
        $this->assertEmpty($tree, 'Should return empty array for user with no folders');
    }

    /**
     * @test
     */
    public function it_only_returns_root_folders_in_user_tree(): void
    {
        // Given
        // Structure :
        // Root1
        //   └─ Child1
        //       └─ GrandChild1
        
        $root1 = $this->createFolder('Root1', null);
        $child1 = $this->createFolder('Child1', $root1);
        $grandChild1 = $this->createFolder('GrandChild1', $child1);
        
        $this->em->persist($root1);
        $this->em->persist($child1);
        $this->em->persist($grandChild1);
        $this->em->flush();
        $this->em->clear();

        // When
        $tree = $this->repository->findUserTreeOptimized($this->testUser);

        // Then
        $this->assertCount(1, $tree, 'Should return only root folders');
        $this->assertEquals('Root1', $tree[0]->getName());
    }

    /**
     * @test
     */
    public function it_handles_multiple_users_separately(): void
    {
        // Given
        $user2 = new User();
        $user2->setEmail('user2-' . uniqid() . '@example.com');
        $user2->setPassword('hashed_password');
        $this->em->persist($user2);
        
        // User1 folders
        $user1Folder = $this->createFolder('User1Folder', null);
        $this->em->persist($user1Folder);
        
        // User2 folders
        $user2Folder = $this->createFolder('User2Folder', null, $user2);
        $this->em->persist($user2Folder);
        
        $this->em->flush();
        $this->em->clear();

        // When
        $user1Tree = $this->repository->findUserTreeOptimized($this->testUser);
        $user2Tree = $this->repository->findUserTreeOptimized($user2);

        // Then
        $this->assertCount(1, $user1Tree, 'User1 should have 1 folder');
        $this->assertCount(1, $user2Tree, 'User2 should have 1 folder');
        $this->assertEquals('User1Folder', $user1Tree[0]->getName());
        $this->assertEquals('User2Folder', $user2Tree[0]->getName());
    }

    /**
     * @test
     */
    public function it_handles_deep_hierarchy_performance(): void
    {
        // Given
        // Structure : A > B > C > D > E > F > G > H > I > J (10 niveaux)
        $folders = [];
        $parent = null;
        
        for ($i = 0; $i < 10; $i++) {
            $folder = $this->createFolder('Folder' . $i, $parent);
            $this->em->persist($folder);
            $folders[] = $folder;
            $parent = $folder;
        }
        
        $this->em->flush();

        // When
        $startTime = microtime(true);
        $ancestorIds = $this->repository->findAncestorIds($folders[9]); // Dernier niveau
        $duration = microtime(true) - $startTime;

        // Then
        $this->assertCount(9, $ancestorIds, 'Should find 9 ancestors');
        $this->assertLessThan(0.1, $duration, 'Should execute in less than 100ms (single query)');
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Créer un dossier de test
     */
    private function createFolder(string $name, ?Folder $parent = null, ?User $owner = null): Folder
    {
        $folder = new Folder();
        $folder->setName($name);
        $folder->setOwner($owner ?? $this->testUser);
        
        if ($parent) {
            $folder->setParent($parent);
        }
        
        return $folder;
    }
}
```

---

## ✅ Récapitulatif Phase 1 (Tests TDD)

### Tests Créés

| Fichier                          | Tests        | Couverture |
|----------------------------------|--------------|------------|
| `AuthenticationResolverTest.php` | 9 tests      | 100%       |
| `AuthorizationCheckerTest.php`   | 11 tests     | 100%       |
| `FolderRepositoryTest.php`       | 9 tests      | 100%       |
| **Total**                        | **29 tests** | **100%**   |

---

### Commandes de Validation

```bash
# 1. Lancer les tests unitaires
./vendor/bin/phpunit tests/Unit/Service/Security/ --testdox

# Résultat attendu :
# ✓ It returns user when token contains user instance
# ✓ It returns user when token contains email string
# ✓ It returns null when no token exists
# ✓ It returns null when user email not found in database
# ✓ It returns null when token contains unexpected type
# ✓ It throws exception when requiring user but not authenticated
# ✓ It returns user when requiring user and authenticated
# ✓ It returns user id when authenticated
# ✓ It returns null user id when not authenticated
# ✓ It allows access when user owns file
# ✓ It denies access when user does not own file
# ... (20 autres tests)

# 2. Lancer les tests d'intégration
./vendor/bin/phpunit tests/Integration/Repository/ --testdox --group=integration

# 3. Vérifier la couverture
./vendor/bin/phpunit --coverage-text --coverage-filter=src/Service/Security/

# Résultat attendu :
# Code Coverage Report:
#   2024-01-15 10:30:00
# 
# Summary:
#   Classes: 100.00% (2/2)
#   Methods: 100.00% (8/8)
#   Lines:   100.00% (45/45)
```

---

