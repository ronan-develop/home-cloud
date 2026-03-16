<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Security\AuthorizationChecker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthorizationCheckerTest extends KernelTestCase
{
    private AuthorizationChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new AuthorizationChecker();
    }

    public function testAssertOwnsThrowsWhenUserDoesNotOwnFolder(): void
    {
        // Setup
        $owner1 = new User('owner1@example.com', 'Owner 1');
        $owner2 = new User('owner2@example.com', 'Owner 2');
        $folder = new Folder('Test Folder', $owner1);

        // Assert
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('do not own');

        // Act
        $this->checker->assertOwns($folder, $owner2);
    }

    public function testAssertOwnsPassesWhenUserOwnsFolder(): void
    {
        // Setup
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Test Folder', $owner);

        // Act & Assert (no exception)
        $this->checker->assertOwns($folder, $owner);
        $this->assertTrue(true); // If we reach here, test passes
    }

    public function testAssertOwnsThrowsWhenUserDoesNotOwnFile(): void
    {
        // Setup
        $owner1 = new User('owner1@example.com', 'Owner 1');
        $owner2 = new User('owner2@example.com', 'Owner 2');
        $folder = new Folder('Root', $owner1);
        $file = new File('test.txt', 'text/plain', 100, '/path/to/test.txt', $folder, $owner1);

        // Assert
        $this->expectException(AccessDeniedHttpException::class);

        // Act
        $this->checker->assertOwns($file, $owner2);
    }

    public function testWouldCreateCycleReturnsFalseWhenNoParent(): void
    {
        // Setup
        $owner = new User('owner@example.com', 'Owner');
        $source = new Folder('Source', $owner);
        $target = new Folder('Target', $owner); // No parent, so no cycle

        // Act
        $result = $this->checker->wouldCreateCycle($source, $target);

        // Assert
        $this->assertFalse($result);
    }

    public function testWouldCreateCycleReturnsTrueWhenSourceIsTargetAncestor(): void
    {
        // Setup: Create hierarchy A > B > C
        $owner = new User('owner@example.com', 'Owner');
        $a = new Folder('A', $owner);
        $b = new Folder('B', $owner, parent: $a);
        $c = new Folder('C', $owner, parent: $b);

        // Try to move A under C (which would create: A > B > C > A)
        // Act
        $result = $this->checker->wouldCreateCycle($a, $c);

        // Assert
        $this->assertTrue($result);
    }

    public function testWouldCreateCycleReturnsFalseWhenSourceIsNotAncestor(): void
    {
        // Setup: Create two separate hierarchies
        $owner = new User('owner@example.com', 'Owner');
        $a = new Folder('A', $owner);
        $b = new Folder('B', $owner, parent: $a);
        $c = new Folder('C', $owner); // Separate tree
        $d = new Folder('D', $owner, parent: $c);

        // Try to move A under D (no cycle, separate hierarchies)
        // Act
        $result = $this->checker->wouldCreateCycle($a, $d);

        // Assert
        $this->assertFalse($result);
    }
}
