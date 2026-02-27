<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Entité représentant un album de médias.
 *
 * Rôle : regrouper des médias sous un nom sans imposer de structure de dossier.
 *
 * Choix :
 * - ManyToMany avec Media (table pivot album_media) : un média peut appartenir
 *   à plusieurs albums, un album contient plusieurs médias.
 * - Pas de suppression en cascade des médias : supprimer un album ne supprime
 *   pas les fichiers/médias, seulement les associations.
 * - owner ManyToOne User : un album appartient à un seul utilisateur.
 */
#[ORM\Entity(repositoryClass: AlbumRepository::class)]
#[ORM\Table(name: 'albums')]
class Album
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /** @var Collection<int, Media> */
    #[ORM\ManyToMany(targetEntity: Media::class)]
    #[ORM\JoinTable(name: 'album_media')]
    private Collection $medias;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, User $owner)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->owner = $owner;
        $this->medias = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(Media $media): void
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
        }
    }

    public function removeMedia(Media $media): void
    {
        $this->medias->removeElement($media);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
