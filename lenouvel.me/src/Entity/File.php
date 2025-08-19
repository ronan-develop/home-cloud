<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['file:read']],
    denormalizationContext: ['groups' => ['file:write']]
)]
class File
{
    public const MAX_FILE_SIZE = 104857600; // 100 Mo

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['file:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['file:read', 'file:write'])]
    #[Assert\NotBlank(message: 'Le nom du fichier ne doit pas être vide.')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Le nom du fichier doit comporter au moins {{ limit }} caractère.',
        maxMessage: 'Le nom du fichier ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_\-\.]+$/',
        message: 'Le nom du fichier ne doit contenir que des lettres, chiffres, tirets, underscores et points.'
    )]
    #[Assert\Callback('validateReservedNames')]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['file:read', 'file:write'])]
    #[Assert\Regex(
        pattern: '/^(?!.*\.\.)(?!\/)(?!\\\\)[a-zA-Z0-9_\-\/\.]+$/',
        message: 'Le chemin doit être relatif, ne pas commencer par / ou \\, et ne pas contenir de séquence ../ pour des raisons de sécurité.'
    )]
    private string $path;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['file:read', 'file:write'])]
    #[Assert\Choice(
        choices: [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'text/plain',
            'text/csv',
            'text/html',
            'audio/mpeg',
            'audio/wav',
            'audio/ogg',
            'video/mp4',
            'video/x-msvideo',
            'video/x-matroska'
        ],
        message: 'Le type MIME fourni n\'est pas autorisé.'
    )]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    #[Groups(['file:read', 'file:write'])]
    #[Assert\Range(min: 1, max: self::MAX_FILE_SIZE, notInRangeMessage: 'La taille du fichier doit être comprise entre 1 octet et 100 Mo.')]
    private int $size;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['file:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['file:read'])]
    private User $owner;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * Validation personnalisée pour les noms réservés (ex: nul, con, prn, etc.)
     */
    public function validateReservedNames(\Symfony\Component\Validator\Context\ExecutionContextInterface $context, $payload): void
    {
        $reserved = ['nul', 'con', 'prn', 'aux', 'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9'];
        if (in_array(strtolower($this->name), $reserved, true)) {
            $context->buildViolation('Ce nom de fichier est réservé et ne peut pas être utilisé.')
                ->addViolation();
        }
    }
}
