<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['file:read']],
    denormalizationContext: ['groups' => ['file:write']]
)]
#[Assert\Callback('validateReservedNames')]
#[Assert\Callback('validateExtension')]
#[Assert\Callback('validatePathSecurity')]
class File
{
    public const MAX_FILE_SIZE = 104857600; // 100 Mo
    public const RESERVED_NAMES = [
        'nul',
        'con',
        'prn',
        'aux',
        'com1',
        'com2',
        'com3',
        'com4',
        'com5',
        'com6',
        'com7',
        'com8',
        'com9',
        'lpt1',
        'lpt2',
        'lpt3',
        'lpt4',
        'lpt5',
        'lpt6',
        'lpt7',
        'lpt8',
        'lpt9'
    ];
    public const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'zip',
        'rar',
        '7z',
        'txt',
        'csv',
        'html',
        'mp3',
        'wav',
        'ogg',
        'mp4',
        'avi',
        'mkv'
    ];

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
        pattern: '/^(?!.*\\.\\.)(?!\.)[a-zA-Z0-9_\-\(\),]+\\.([a-zA-Z0-9]+)$/',
        message: 'Le nom du fichier doit contenir une extension (ex: .pdf), ne doit pas commencer par un point, ne doit pas contenir de points consécutifs, et ne doit contenir que des lettres, chiffres, espaces, tirets, underscores, parenthèses, virgules et un seul point pour l\'extension.'
    )]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['file:read', 'file:write'])]
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
    #[Assert\Range(min: 1, max: 104857600, notInRangeMessage: 'La taille du fichier doit être comprise entre 1 octet et 100 Mo.')]
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
    public function validateReservedNames(ExecutionContextInterface $context, $payload): void
    {
        $basename = pathinfo($this->name, PATHINFO_FILENAME);
        $extension = pathinfo($this->name, PATHINFO_EXTENSION);
        // On ne vérifie les noms réservés que si une extension est présente
        if ($extension === '' || $extension === null) {
            return;
        }
        if ($basename !== null && in_array(strtolower($basename), self::RESERVED_NAMES, true)) {
            $context->buildViolation('Ce nom de fichier est réservé et ne peut pas être utilisé.')
                ->atPath('name')
                ->addViolation();
        }
    }

    /**
     * Validation personnalisée pour la sécurité du chemin (interdit .., /, \\ et variantes encodées)
     */
    public function validatePathSecurity(ExecutionContextInterface $context, $payload): void
    {
        $path = $this->path ?? '';
        $decodedPath = urldecode($path);
        if (
            strpos($decodedPath, '..') !== false ||
            strpos($decodedPath, '/') !== false ||
            strpos($decodedPath, '\\') !== false ||
            preg_match('/%2e|%2f|%5c/i', $path)
        ) {
            $context->buildViolation('Le chemin ne doit contenir aucune séquence ../, /, \\ ou variante encodée pour des raisons de sécurité.')
                ->atPath('path')
                ->addViolation();
        }
    }

    public function validateExtension(ExecutionContextInterface $context, $payload): void
    {
        $extension = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
        if (!in_array($extension, array_map('strtolower', self::ALLOWED_EXTENSIONS), true)) {
            $context->buildViolation('L\'extension de fichier n\'est pas autorisée.')
                ->atPath('name')
                ->addViolation();
        }
    }
}
