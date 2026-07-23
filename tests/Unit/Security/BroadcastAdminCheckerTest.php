<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\BroadcastAdminChecker;
use PHPUnit\Framework\TestCase;

/**
 * TDD RED → GREEN : garde applicative de l'écran /admin/broadcast (#283).
 * Pas de nouveau ROLE_ADMIN Symfony (aucun rôle différencié n'existe dans le
 * projet, getRoles() renvoie toujours ROLE_USER) — juste une whitelist par
 * email, explicite et testable, qui couvre le cas où un guest serait un jour
 * ajouté sur l'instance ronan.lenouvel.me elle-même.
 */
final class BroadcastAdminCheckerTest extends TestCase
{
    public function testIsAdminReturnsTrueForConfiguredAdminEmail(): void
    {
        $checker = new BroadcastAdminChecker('ronan@example.com');
        $user = new User('ronan@example.com', 'Ronan');

        $this->assertTrue($checker->isAdmin($user));
    }

    public function testIsAdminReturnsFalseForOtherUsers(): void
    {
        $checker = new BroadcastAdminChecker('ronan@example.com');
        $user = new User('guest@example.com', 'Guest');

        $this->assertFalse($checker->isAdmin($user));
    }
}
