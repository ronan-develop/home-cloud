<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CreateUserCommand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final class CreateUserCommandTest extends TestCase
{
    private function makeHasherStub(): UserPasswordHasherInterface
    {
        $stub = $this->createMock(UserPasswordHasherInterface::class);
        $stub->method('hashPassword')->willReturnCallback(
            fn (User $user, string $plainPassword) => 'hashed-' . $plainPassword,
        );

        return $stub;
    }

    private function makeResetPasswordHelperStub(): ResetPasswordHelperInterface
    {
        $stub = $this->createMock(ResetPasswordHelperInterface::class);
        $stub->method('generateResetToken')
            ->willReturn(new ResetPasswordToken('fake-token', new \DateTimeImmutable('+1 hour'), time()));

        return $stub;
    }

    private function makeUrlGeneratorStub(): UrlGeneratorInterface
    {
        $stub = $this->createMock(UrlGeneratorInterface::class);
        $stub->method('generate')->willReturn('https://prenom.lenouvel.me/reset-password/fake-token');

        return $stub;
    }

    private function makeCommand(
        ?EntityManagerInterface $em = null,
        ?UserPasswordHasherInterface $hasher = null,
        ?ResetPasswordHelperInterface $resetPasswordHelper = null,
        ?UrlGeneratorInterface $urlGenerator = null,
    ): CreateUserCommand {
        $em ??= $this->createMock(EntityManagerInterface::class);
        $hasher ??= $this->makeHasherStub();
        $resetPasswordHelper ??= $this->makeResetPasswordHelperStub();
        $urlGenerator ??= $this->makeUrlGeneratorStub();

        return new CreateUserCommand($em, $hasher, $resetPasswordHelper, $urlGenerator);
    }

    public function testCreatesUserWithoutPasswordWhenArgumentOmittedAndPrintsResetUrl(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $persistedUser = null;
        $em->expects($this->once())->method('persist')->willReturnCallback(
            function (User $user) use (&$persistedUser) {
                $persistedUser = $user;
            }
        );
        $em->expects($this->once())->method('flush');

        $command = $this->makeCommand(em: $em);
        $tester = new CommandTester($command);
        $tester->execute([
            'email'       => 'admin@example.com',
            'displayName' => 'prenom',
        ]);

        $this->assertSame('', $persistedUser->getPassword());
        $this->assertStringContainsString(
            'https://prenom.lenouvel.me/reset-password/fake-token',
            $tester->getDisplay(),
        );
    }

    public function testCreatesUserWithHashedPasswordWhenArgumentProvided(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $persistedUser = null;
        $em->expects($this->once())->method('persist')->willReturnCallback(
            function (User $user) use (&$persistedUser) {
                $persistedUser = $user;
            }
        );
        $em->expects($this->once())->method('flush');

        $command = $this->makeCommand(em: $em);
        $tester = new CommandTester($command);
        $tester->execute([
            'email'       => 'admin@example.com',
            'password'    => 'S3cret!',
            'displayName' => 'prenom',
        ]);

        $this->assertSame('hashed-S3cret!', $persistedUser->getPassword());
        $this->assertStringNotContainsString('reset-password', $tester->getDisplay());
    }
}
