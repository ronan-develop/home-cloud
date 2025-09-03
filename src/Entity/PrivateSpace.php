<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\PrivateSpaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new \ApiPlatform\Metadata\Get(),
        new \ApiPlatform\Metadata\GetCollection(),
        new \ApiPlatform\Metadata\Post(),
        new \ApiPlatform\Metadata\Put(),
        new \ApiPlatform\Metadata\Delete()
    ]
)]
#[ORM\Entity(repositoryClass: PrivateSpaceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PrivateSpace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Regex(
        // Allow letters (including accents), numbers, spaces, hyphens and underscores. Length 3-63
        pattern: '/^[\p{L}0-9\s\-_]{3,63}$/u',
        message: 'Le nom doit contenir entre 3 et 63 caractères ; lettres, chiffres, espaces, tirets et underscores sont autorisés.'
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, File>
     */
    #[ORM\OneToMany(targetEntity: File::class, mappedBy: 'privateSpace')]
    private Collection $files;

    #[ORM\OneToOne(inversedBy: 'privateSpace', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->files = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        // store raw value; lifecycle callback will normalise before persist
        $this->name = $name;

        return $this;
    }

    // keep original display name; slug for URL is computed on demand in getPublicUrl()

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, File>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): static
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setPrivateSpace($this);
        }

        return $this;
    }

    public function removeFile(File $file): static
    {
        if ($this->files->removeElement($file)) {
            // set the owning side to null (unless already changed)
            if ($file->getPrivateSpace() === $this) {
                $file->setPrivateSpace(null);
            }
        }

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Retourne l'URL publique calculée du private space en fonction du domaine principal.
     * Exemple: si MAIN_DOMAIN=\"lenouvel.me\" et name=\"ronan\" -> https://ronan.lenouvel.me
     */
    public function getPublicUrl(string $mainDomain, bool $https = true): string
    {
        $scheme = $https ? 'https' : 'http';

        $name = $this->name ?? '';
        // produce a slug suitable for a subdomain: lowercase, replace spaces/accents by '-', remove invalid chars
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $slug = preg_replace('/[^a-zA-Z0-9\-]/', '-', strtolower($slug));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        $host = sprintf('%s.%s', $slug, $mainDomain);

        return $scheme . '://' . $host;
    }
}
