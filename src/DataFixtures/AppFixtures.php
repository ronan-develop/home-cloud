<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use App\Service\ExifService;
use App\Service\ThumbnailService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Fixtures de démo — utilisateur, dossiers, fichiers/médias et albums.
 *
 * Les photos proviennent de fixtures/demo-photos/ (JPEG libres de droit,
 * Pixabay — voir le nom de fichier pour l'attribution auteur). Le même
 * traitement que MediaProcessHandler (EXIF + thumbnail) est appliqué ici en
 * synchrone, pour que la démo reflète fidèlement le pipeline de production.
 *
 * Usage : php bin/console doctrine:fixtures:load
 * Chargées uniquement en dev/test (voir config/bundles.php).
 */
class AppFixtures extends Fixture
{
    private const DEMO_EMAIL = 'demo@homecloud.local';
    private const DEMO_PASSWORD = 'demo12345';

    /** @var array<int, array{file: string, name: string}> */
    private const DEMO_PHOTOS = [
        ['file' => 'himmelstraeume-bachalpsee-7572681_1920.jpg', 'name' => 'Bachalpsee.jpg'],
        ['file' => 'kanenori-sunset-7133867_1920.jpg',           'name' => 'Coucher de soleil.jpg'],
        ['file' => 'tieubaotruong-field-9295186_1920.jpg',       'name' => 'Champ.jpg'],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ExifService $exifService,
        private readonly ThumbnailService $thumbnailService,
        private readonly string $storageDir,
        private readonly string $projectDir,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $user = new User(self::DEMO_EMAIL, 'Utilisateur Démo');
        $user->setPassword($this->passwordHasher->hashPassword($user, self::DEMO_PASSWORD));
        $manager->persist($user);

        $photosFolder = new Folder('Photos', $user);
        $manager->persist($photosFolder);

        $docsFolder = new Folder('Documents', $user);
        $manager->persist($docsFolder);

        $medias = [];
        foreach (self::DEMO_PHOTOS as $spec) {
            $relativePath = $this->copyDemoPhoto($spec['file']);
            $absolutePath = $this->storageDir . '/' . $relativePath;

            $file = new File(
                originalName: $spec['name'],
                mimeType: 'image/jpeg',
                size: filesize($absolutePath) ?: 0,
                path: $relativePath,
                folder: $photosFolder,
                owner: $user,
            );
            $manager->persist($file);

            // Même traitement que MediaProcessHandler (production, asynchrone) :
            // extraction EXIF + génération du thumbnail. thumbnailPath reste null
            // si GD est absent de l'environnement (dégradation gracieuse déjà
            // prévue par ThumbnailService) — le template retombe alors sur son
            // icône de repli.
            $exif = $this->exifService->extract($absolutePath);
            $media = new Media($file, 'photo');
            $media->setWidth($exif['width']);
            $media->setHeight($exif['height']);
            $media->setTakenAt($exif['takenAt']);
            $media->setCameraModel($exif['cameraModel']);
            $media->setGpsLat($exif['gpsLat']);
            $media->setGpsLon($exif['gpsLon']);
            $media->setThumbnailPath($this->thumbnailService->generate($absolutePath));
            $manager->persist($media);

            $medias[] = $media;
        }

        $album = new Album('Paysages', $user);
        foreach ($medias as $media) {
            $album->addMedia($media);
        }
        $manager->persist($album);

        $manager->flush();
    }

    /**
     * Copie une photo de démo depuis fixtures/demo-photos/ vers var/storage/demo/,
     * en réutilisant les conventions de StorageService (chemin relatif au storageDir).
     *
     * @return string Chemin relatif à storageDir (ex: "demo/photo.jpg")
     */
    private function copyDemoPhoto(string $filename): string
    {
        $source = $this->projectDir . '/fixtures/demo-photos/' . $filename;
        if (!is_file($source)) {
            throw new \RuntimeException(sprintf('Photo de démo introuvable : %s', $source));
        }

        $dir = $this->storageDir . '/demo';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $relativePath = 'demo/' . $filename;
        copy($source, $this->storageDir . '/' . $relativePath);

        return $relativePath;
    }
}
