<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AlbumMediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Entité de jointure explicite Album↔Media, portant l'ordre d'affichage.
 *
 * Remplace une simple relation ManyToMany : une table pivot "brute" ne peut
 * pas porter de colonne métier (position) sans devenir elle-même une entité.
 *
 * Choix :
 * - position : entier croissant, unique par album, réattribué en bloc à
 *   chaque réordonnancement (pas de gestion de trous/fractions).
 * - Pas de cascade remove : supprimer un Album supprime ses AlbumMedia
 *   (onDelete CASCADE au niveau DB), mais jamais les Media eux-mêmes.
 */
#[ORM\Entity(repositoryClass: AlbumMediaRepository::class)]
#[ORM\Table(name: 'album_media')]
#[ORM\UniqueConstraint(name: 'uniq_album_media', columns: ['album_id', 'media_id'])]
class AlbumMedia
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Album::class, inversedBy: 'albumMedias')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Album $album;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Media $media;

    #[ORM\Column]
    private int $position;

    public function __construct(Album $album, Media $media, int $position)
    {
        $this->id = Uuid::v7();
        $this->album = $album;
        $this->media = $media;
        $this->position = $position;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAlbum(): Album
    {
        return $this->album;
    }

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
