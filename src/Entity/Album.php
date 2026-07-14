<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Entité représentant un album de médias.
 *
 * Rôle : regrouper des médias sous un nom sans imposer de structure de dossier,
 * dans un ordre choisi par l'utilisateur (réordonnancement manuel).
 *
 * Choix :
 * - AlbumMedia (jointure explicite avec position) plutôt qu'une ManyToMany
 *   brute : porter l'ordre d'affichage nécessite une colonne métier sur la
 *   table pivot, donc une entité à part entière.
 * - Pas de suppression en cascade des médias : supprimer un album ne supprime
 *   pas les fichiers/médias, seulement les associations (AlbumMedia).
 * - owner ManyToOne User : un album appartient à un seul utilisateur.
 */
#[ORM\Entity(repositoryClass: AlbumRepository::class)]
#[ORM\Table(name: 'albums')]
class Album
{
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_LINK_ALLOWED = 'link_allowed';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /** @var Collection<int, AlbumMedia> */
    #[ORM\OneToMany(targetEntity: AlbumMedia::class, mappedBy: 'album', cascade: ['persist'], orphanRemoval: true)]
    private Collection $albumMedias;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * private par défaut : le serveur refuse de créer un lien public tant que
     * l'owner n'a pas explicitement basculé la ressource en link_allowed.
     */
    #[ORM\Column(type: 'string', length: 12, options: ['default' => self::VISIBILITY_PRIVATE])]
    private string $visibility = self::VISIBILITY_PRIVATE;

    public function __construct(string $name, User $owner)
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Le nom de l\'album ne peut pas être vide.');
        }

        $this->id = Uuid::v7();
        $this->name = $trimmed;
        $this->owner = $owner;
        $this->albumMedias = new ArrayCollection();
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
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Le nom de l\'album ne peut pas être vide.');
        }
        $this->name = $trimmed;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    /** Vérifie si cet album appartient à l'utilisateur donné. */
    public function isOwnedBy(User $user): bool
    {
        return $this->owner->getId()->equals($user->getId());
    }

    /**
     * @return Collection<int, Media> Médias triés par position croissante.
     */
    public function getMedias(): Collection
    {
        $criteria = Criteria::create()->orderBy(['position' => Order::Ascending]);
        $sorted   = $this->albumMedias->matching($criteria)->map(
            fn (AlbumMedia $am) => $am->getMedia()
        );

        return new ArrayCollection(array_values($sorted->toArray()));
    }

    public function addMedia(Media $media): void
    {
        if ($this->findAlbumMedia($media) !== null) {
            return;
        }

        $nextPosition = $this->albumMedias->count();
        $this->albumMedias->add(new AlbumMedia($this, $media, $nextPosition));
    }

    public function removeMedia(Media $media): void
    {
        $albumMedia = $this->findAlbumMedia($media);
        if ($albumMedia !== null) {
            $this->albumMedias->removeElement($albumMedia);
        }
    }

    /**
     * Réordonne les médias selon la liste d'IDs fournie (nouvel ordre complet).
     * Les IDs inconnus ou ne correspondant à aucun média de l'album sont
     * ignorés silencieusement ; les médias non mentionnés conservent leur
     * position relative à la fin.
     *
     * @param Uuid[] $mediaIds
     */
    public function reorder(array $mediaIds): void
    {
        $byMediaId = [];
        foreach ($this->albumMedias as $albumMedia) {
            $byMediaId[$albumMedia->getMedia()->getId()->toRfc4122()] = $albumMedia;
        }

        $position = 0;
        foreach ($mediaIds as $mediaId) {
            $albumMedia = $byMediaId[$mediaId->toRfc4122()] ?? null;
            if ($albumMedia === null) {
                continue;
            }
            $albumMedia->setPosition($position);
            unset($byMediaId[$mediaId->toRfc4122()]);
            $position++;
        }

        foreach ($byMediaId as $remaining) {
            $remaining->setPosition($position);
            $position++;
        }
    }

    private function findAlbumMedia(Media $media): ?AlbumMedia
    {
        foreach ($this->albumMedias as $albumMedia) {
            if ($albumMedia->getMedia() === $media) {
                return $albumMedia;
            }
        }

        return null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): void
    {
        $this->visibility = $visibility;
    }
}
