<?php

namespace App\DataFixtures;

use App\Entity\Album;
use App\Entity\Photo;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PhotoAlbumFixture extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [UserFixture::class];
    }
    public function load(ObjectManager $manager): void
    {
        // Récupère l'utilisateur admin via référence
        /** @var User $user */
        $user = $this->getReference('admin', User::class);

        // Création de quelques albums

        $album1 = new Album();
        $album1->setName('Vacances été 2025');
        $album1->setDescription('Photos de vacances à la mer.');
        $album1->setOwner($user);
        $manager->persist($album1);


        $album2 = new Album();
        $album2->setName('Famille');
        $album2->setDescription('Moments en famille.');
        $album2->setOwner($user);
        $manager->persist($album2);

        // Création de photos associées aux albums
        foreach (
            [
                ['album' => $album1, 'title' => 'Plage', 'filename' => 'plage.jpg'],
                ['album' => $album1, 'title' => 'Coucher de soleil', 'filename' => 'sunset.jpg'],
                ['album' => $album2, 'title' => 'Anniversaire', 'filename' => 'anniversaire.jpg'],
            ] as $data
        ) {
            $photo = new Photo();
            $photo->setUser($user);
            $photo->setTitle($data['title']);
            $photo->setFilename($data['filename']);
            $photo->setOriginalName($data['filename']);
            $photo->setMimeType('image/jpeg');
            $photo->setSize(123456);
            $photo->setUploadedAt(new \DateTimeImmutable('-1 days'));
            $photo->setUpdatedAt(new \DateTimeImmutable());
            $photo->setDescription('Description de la photo ' . $data['title']);
            $photo->setIsFavorite(false);
            $photo->setHash(md5($data['filename']));
            $photo->setExifData([]);
            $manager->persist($photo);
        }

        $manager->flush();
    }
}
