<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class PasswordResetServiceTest extends TestCase
{
    public function testResetPasswordSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('setPassword')
            ->with($this->callback(fn($hash) => password_verify('newPass', $hash)));

        $helper = $this->createMock(ResetPasswordHelperInterface::class);
        $helper->expects($this->once())
            ->method('validateTokenAndFetchUser')
            ->with('token')
            ->willReturn($user);
        $helper->expects($this->once())
            ->method('removeResetRequest')
            ->with('token');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('flush');

        $service = new PasswordResetService($helper, $em);
        $service->resetPassword('token', 'newPass');
    }

    public function testResetPasswordThrowsOnInvalidToken(): void
    {
        $helper = $this->createMock(ResetPasswordHelperInterface::class);
        $helper->expects($this->once())
            ->method('validateTokenAndFetchUser')
            ->willThrowException($this->createMock(ResetPasswordExceptionInterface::class));

        $em = $this->createMock(EntityManagerInterface::class);
        $service = new PasswordResetService($helper, $em);

        $this->expectException(ResetPasswordExceptionInterface::class);
        $service->resetPassword('badtoken', 'irrelevant');
    }
}
