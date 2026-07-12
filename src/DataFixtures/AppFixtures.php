<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Fixtures de démo — utilisateur, dossiers, fichiers/médias (SVG placeholder
 * générés localement, aucune dépendance réseau) et albums.
 *
 * Usage : php bin/console doctrine:fixtures:load
 * Chargées uniquement en dev/test (voir config/bundles.php).
 */
class AppFixtures extends Fixture
{
    private const DEMO_EMAIL = 'demo@homecloud.local';
    private const DEMO_PASSWORD = 'demo12345';

    /** @var array<int, array{label: string, color: string}> */
    private const PHOTO_SPECS = [
        ['label' => 'Vacances plage',    'color' => '#4A90D9'],
        ['label' => 'Montagne',          'color' => '#5FA65F'],
        ['label' => 'Portrait',          'color' => '#D97A4A'],
        ['label' => 'Ville la nuit',     'color' => '#6B5FA8'],
        ['label' => 'Coucher de soleil', 'color' => '#D9A84A'],
        ['label' => 'Forêt',             'color' => '#3F8F6B'],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $storageDir,
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
        foreach (self::PHOTO_SPECS as $i => $spec) {
            $filename = sprintf('demo-photo-%d.svg', $i + 1);
            $relativePath = $this->writePlaceholderSvg($filename, $spec['label'], $spec['color']);
            $thumbnailRelativePath = $this->writePlaceholderSvg('thumb-' . $filename, $spec['label'], $spec['color'], thumbs: true);

            $file = new File(
                originalName: $spec['label'] . '.svg',
                mimeType: 'image/svg+xml',
                size: filesize($this->storageDir . '/' . $relativePath) ?: 0,
                path: $relativePath,
                folder: $photosFolder,
                owner: $user,
            );
            $manager->persist($file);

            $media = new Media($file, 'photo');
            $media->setWidth(800);
            $media->setHeight(600);
            $media->setThumbnailPath($thumbnailRelativePath);
            $manager->persist($media);

            $medias[] = $media;
        }

        $vacationAlbum = new Album('Vacances 2026', $user);
        foreach (array_slice($medias, 0, 3) as $media) {
            $vacationAlbum->addMedia($media);
        }
        $manager->persist($vacationAlbum);

        $natureAlbum = new Album('Nature', $user);
        foreach (array_slice($medias, 3) as $media) {
            $natureAlbum->addMedia($media);
        }
        $manager->persist($natureAlbum);

        $manager->flush();
    }

    /**
     * Génère un SVG placeholder simple (fond coloré + libellé) et l'écrit sur
     * disque dans var/storage/, en réutilisant les conventions de StorageService.
     * Aucune dépendance à GD ni au réseau — juste du balisage SVG texte.
     *
     * @return string Chemin relatif à storageDir (ex: "demo/demo-photo-1.svg")
     */
    private function writePlaceholderSvg(string $filename, string $label, string $color, bool $thumbs = false): string
    {
        $subDir = $thumbs ? 'thumbs' : 'demo';
        $width = $thumbs ? 300 : 800;
        $height = $thumbs ? 300 : 600;
        $fontSize = $thumbs ? 20 : 32;

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
              <rect width="100%" height="100%" fill="{$color}"/>
              <text x="50%" y="50%" font-family="sans-serif" font-size="{$fontSize}" fill="#ffffff" text-anchor="middle" dominant-baseline="middle">{$label}</text>
            </svg>
            SVG;

        $dir = $this->storageDir . '/' . $subDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $relativePath = $subDir . '/' . $filename;
        file_put_contents($this->storageDir . '/' . $relativePath, $svg);

        return $relativePath;
    }
}
