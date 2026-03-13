<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\FilenameValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class FilenameValidatorTest extends TestCase
{
    private FilenameValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new FilenameValidator();
    }

    // ── Noms valides ────────────────────────────────────────────────────────────

    public function testSimpleAlphanumericNameIsValid(): void
    {
        $this->validator->validate('my-folder');
        $this->expectNotToPerformAssertions();
    }

    public function testNameWithSpacesIsValid(): void
    {
        $this->validator->validate('Mon dossier photos');
        $this->expectNotToPerformAssertions();
    }

    public function testNameWithDotsAndDashesIsValid(): void
    {
        $this->validator->validate('rapport-2024.01.pdf');
        $this->expectNotToPerformAssertions();
    }

    public function testNameWithUnicodeIsValid(): void
    {
        $this->validator->validate('Été 2024 — vacances');
        $this->expectNotToPerformAssertions();
    }

    public function testNameWithUnderscoresIsValid(): void
    {
        $this->validator->validate('my_folder_name');
        $this->expectNotToPerformAssertions();
    }

    public function testNameExactly255CharsIsValid(): void
    {
        $this->validator->validate(str_repeat('a', 255));
        $this->expectNotToPerformAssertions();
    }

    // ── Longueur ────────────────────────────────────────────────────────────────

    public function testEmptyNameThrows(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->validator->validate('');
    }

    public function testNameOver255CharsThrows(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->validator->validate(str_repeat('a', 256));
    }

    // ── Caractères interdits (Windows/filesystem) ───────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenCharsProvider')]
    public function testForbiddenCharThrows(string $name): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->validator->validate($name);
    }

    /** @return array<string, array{string}> */
    public static function forbiddenCharsProvider(): array
    {
        return [
            'backslash' => ['fold\\er'],
            'slash'     => ['fold/er'],
            'colon'     => ['fold:er'],
            'asterisk'  => ['fold*er'],
            'question'  => ['fold?er'],
            'quote'     => ['fold"er'],
            'lt'        => ['fold<er'],
            'gt'        => ['fold>er'],
            'pipe'      => ['fold|er'],
        ];
    }

    // ── Message d'erreur ────────────────────────────────────────────────────────

    public function testInvalidCharExceptionMessageMentionsCharacters(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/characters/i');
        $this->validator->validate('bad/name');
    }

    public function testTooLongExceptionMessageMentionsLength(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/255/');
        $this->validator->validate(str_repeat('a', 256));
    }
}
