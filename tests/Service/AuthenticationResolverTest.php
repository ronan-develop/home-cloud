<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthenticationResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class AuthenticationResolverTest extends KernelTestCase
{
    private AuthenticationResolver $resolver;
    private TokenStorageInterface $tokenStorage;
    private UserRepository $userRepository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resolver = new AuthenticationResolver(
            $this->tokenStorage,
            $this->userRepository,
            self::getContainer()->get('logger'),
        );
    }

    public function testGetAuthenticatedUserReturnsUserWhenTokenIsUserInstance(): void
    {
        // Setup: Create test user
        $user = new User('test@example.com', 'Test User');
        $this->em->persist($user);
        $this->em->flush();

        // Setup: Create token with User instance
        $token = new UsernamePasswordToken($user, 'main', []);
        $this->tokenStorage->setToken($token);

        // Act
        $result = $this->resolver->getAuthenticatedUser();

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->getId(), $result->getId());
    }

    public function testGetAuthenticatedUserReturnsNullWhenNoToken(): void
    {
        // Setup: No token in storage
        $this->tokenStorage->setToken(null);

        // Act
        $result = $this->resolver->getAuthenticatedUser();

        // Assert
        $this->assertNull($result);
    }

    public function testRequireUserThrowsWhenNotAuthenticated(): void
    {
        // Setup
        $this->tokenStorage->setToken(null);

        // Assert
        $this->expectException(UnauthorizedHttpException::class);

        // Act
        $this->resolver->requireUser();
    }

    public function testRequireUserReturnsUserWhenAuthenticated(): void
    {
        // Setup
        $user = new User('test2@example.com', 'Test User 2');
        $this->em->persist($user);
        $this->em->flush();

        $token = new UsernamePasswordToken($user, 'main', []);
        $this->tokenStorage->setToken($token);

        // Act
        $result = $this->resolver->requireUser();

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->getId(), $result->getId());
    }
}
