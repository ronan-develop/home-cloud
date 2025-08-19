<?php

namespace App\Tests\Entity;

use App\Entity\File;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FileTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    private function getValidFile(): File
    {
        $user = $this->createMock(User::class);
        $file = new File();
        $file->setName('document.pdf');
        $file->setPath('dossier');
        $file->setMimeType('application/pdf');
        $file->setSize(1024);
        $file->setOwner($user);
        return $file;
    }

    public function testValidFile(): void
    {
        $file = $this->getValidFile();
        $errors = $this->validator->validate($file);
        $this->assertCount(0, $errors);
    }

    public function testReservedNamesAreRejected(): void
    {
        $reserved = \App\Entity\File::RESERVED_NAMES;
        foreach ($reserved as $name) {
            $file = $this->getValidFile();
            $file->setName($name . '.pdf');
            $errors = $this->validator->validate($file);
            $this->assertGreaterThan(0, count($errors), "Le nom réservé '$name' doit être refusé");
            $this->assertStringContainsString('réservé', (string) $errors);
        }
    }

    public function testInvalidExtensionsAreRejected(): void
    {
        $invalid = ['exe', 'php', 'bat', 'sh', 'js', 'bin'];
        foreach ($invalid as $ext) {
            $file = $this->getValidFile();
            $file->setName('test.' . $ext);
            $errors = $this->validator->validate($file);
            $this->assertGreaterThan(0, count($errors), "L'extension '$ext' doit être refusée");
            $this->assertStringContainsString('n\'est pas autorisée', (string) $errors);
        }
    }

    public function testPathSecurityRejectsDotDotSlash(): void
    {
        $file = $this->getValidFile();
        $file->setPath('..');
        $errors = $this->validator->validate($file);
        $this->assertGreaterThan(0, count($errors));
        $this->assertStringContainsString('sécurité', (string) $errors);
    }

    public function testPathSecurityRejectsSlash(): void
    {
        $file = $this->getValidFile();
        $file->setPath('mon/dossier');
        $errors = $this->validator->validate($file);
        $this->assertGreaterThan(0, count($errors));
        $this->assertStringContainsString('sécurité', (string) $errors);
    }

    public function testPathSecurityRejectsBackslash(): void
    {
        $file = $this->getValidFile();
        $file->setPath('mon\\dossier');
        $errors = $this->validator->validate($file);
        $this->assertGreaterThan(0, count($errors));
        $this->assertStringContainsString('sécurité', (string) $errors);
    }

    public function testFileNameRegexAllowsSpecialChars(): void
    {
        $file = $this->getValidFile();
        $file->setName('Mon fichier (v2), test; ok.pdf');
        $errors = $this->validator->validate($file);
        $this->assertCount(0, $errors);
    }

    public function testFileNameRegexRequiresExtension(): void
    {
        $file = $this->getValidFile();
        $file->setName('sans_extension');
        $errors = $this->validator->validate($file);
        $this->assertGreaterThan(0, count($errors));
        $this->assertStringContainsString('extension', (string) $errors);
    }

    public function testFileSizeLimit(): void
    {
        $file = $this->getValidFile();
        $file->setSize(File::MAX_FILE_SIZE + 1);
        $errors = $this->validator->validate($file);
        $this->assertGreaterThan(0, count($errors));
        $this->assertStringContainsString('100 Mo', (string) $errors);
    }

    public function testMimeTypeNotAllowed(): void
    {
        $file = $this->getValidFile();
        $file->setMimeType('application/x-msdownload');
        $errors = $this->validator->validate($file);
        $this->assertGreaterThan(0, count($errors));
        $this->assertStringContainsString('n\'est pas autorisé', (string) $errors);
    }
}
