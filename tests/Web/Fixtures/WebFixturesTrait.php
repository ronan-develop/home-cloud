<?php

declare(strict_types=1);

namespace App\Tests\Web\Fixtures;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Trait DRY pour les fixtures communes aux tests web fonctionnels.
 * Mutualise : createWebUser(), loginAs(), createMediaFile().
 *
 * Prérequis : la classe utilisatrice doit avoir $this->em (EntityManagerInterface)
 * et $this->client (KernelBrowser) initialisés avant d'utiliser ces méthodes.
 */
trait WebFixturesTrait
{
    private function createWebUser(
        string $email = 'web@example.com',
        string $password = 'secret123',
        string $displayName = 'Web User',
    ): User {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, $displayName);
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function loginAs(string $email = 'web@example.com', string $password = 'secret123'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    private function createMediaFile(
        User $user,
        string $name = 'photo.jpg',
        string $mediaType = 'photo',
        int $size = 1024,
        ?\DateTimeImmutable $createdAt = null,
    ): Media {
        $folder = new Folder('Photos', $user);
        $this->em->persist($folder);

        $file = new File($name, 'image/jpeg', $size, "test/{$name}", $folder, $user);

        if ($createdAt !== null) {
            // File::$createdAt n'a pas de setter (immuable en production) — écriture
            // directe réservée aux tests pour pouvoir varier la date dans les fixtures.
            $prop = new \ReflectionProperty(File::class, 'createdAt');
            $prop->setAccessible(true);
            $prop->setValue($file, $createdAt);
        }

        $this->em->persist($file);

        $media = new Media($file, $mediaType);
        $media->setWidth(1920);
        $media->setHeight(1080);
        $media->setThumbnailPath("thumbs/{$name}.thumb.jpg");
        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }
}
