<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\ShareLinkTokenGenerator;
use PHPUnit\Framework\TestCase;

final class ShareLinkTokenGeneratorTest extends TestCase
{
    public function testGeneratedTokenIs64HexCharacters(): void
    {
        $generator = new ShareLinkTokenGenerator();

        $generated = $generator->generate();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $generated->token);
    }

    public function testGeneratedSelectorIs32HexCharacters(): void
    {
        $generator = new ShareLinkTokenGenerator();

        $generated = $generator->generate();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $generated->selector);
    }

    public function testTwoCallsProduceDifferentTokensAndSelectors(): void
    {
        $generator = new ShareLinkTokenGenerator();

        $first = $generator->generate();
        $second = $generator->generate();

        $this->assertNotSame($first->token, $second->token);
        $this->assertNotSame($first->selector, $second->selector);
    }

    public function testHashedTokenIsNotThePlainToken(): void
    {
        $generator = new ShareLinkTokenGenerator();

        $generated = $generator->generate();

        $this->assertNotSame($generated->token, $generated->hashedToken);
    }

    public function testHashedTokenMatchesHashOfPlainToken(): void
    {
        $generator = new ShareLinkTokenGenerator();

        $generated = $generator->generate();

        $this->assertSame(hash('sha256', $generated->token), $generated->hashedToken);
    }
}
