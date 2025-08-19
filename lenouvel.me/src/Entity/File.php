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
    /**
     * Types MIME autorisés pour l’upload de fichiers :
     * - Voir la constante ALLOWED_MIME_TYPES pour la liste exhaustive.
     *
     * Pour ajouter ou retirer un type, modifier uniquement la constante.
     */
    public const ALLOWED_MIME_TYPES = [
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

    /**
     * Caractères autorisés pour les noms de fichiers (hors extension) :
     * - Lettres (a-z, A-Z)
     * - Chiffres (0-9)
     * - Espace
     * - Tiret (-)
     * - Underscore (_)
     * - Parenthèses ( )
     * - Virgule (,)
     *
     * Extension obligatoire, un seul point, pas de points consécutifs, pas de point initial.
     * Le point-virgule (;) et tout autre caractère non listé sont strictement interdits.
     *
     * Voir la regex de validation pour la correspondance exacte.
     */
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
        pattern: '/^(?!.*\\.\\.)(?!\.)[a-zA-Z0-9 _\-\(\),]+\\.([a-zA-Z0-9]+)$/',
        message: 'Le nom du fichier doit contenir une extension (ex: .pdf), ne doit pas commencer par un point, ne doit pas contenir de points consécutifs, et ne doit contenir que des lettres, chiffres, espaces, tirets, underscores, parenthèses, virgules et un seul point pour l\'extension. Le point-virgule (;) est strictement interdit.'
    )]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['file:read', 'file:write'])]
    private string $path;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['file:read', 'file:write'])]
    #[Assert\Choice(
        choices: self::ALLOWED_MIME_TYPES,
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

    /** @var null|\Psr\Log\LoggerInterface */
    private static ?\Psr\Log\LoggerInterface $logger = null;

    /**
     * Permet d'injecter le logger statique depuis le service appelant (ex: EventListener).
     */
    public static function setLogger(?\Psr\Log\LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

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
     * Vérifie que le nom de fichier n'est pas un nom réservé Windows (nul, con, prn, lpt*, com*, etc.).
     * S'applique uniquement si une extension est présente.
     */
    public function validateReservedNames(ExecutionContextInterface $context, $payload): void
    {
        $basename = pathinfo($this->name, PATHINFO_FILENAME);
        $extension = pathinfo($this->name, PATHINFO_EXTENSION);
        if ($extension === '' || $extension === null) {
            return;
        }
        if ($basename !== null && in_array(strtolower($basename), self::RESERVED_NAMES, true)) {
            if (self::$logger) {
                self::$logger->warning('[SECURITY] Tentative d’upload d’un fichier avec nom réservé', [
                    'filename' => $this->name,
                    'user' => method_exists($this->owner ?? null, 'getId') ? $this->owner->getId() : null,
                ]);
            }
            $context->buildViolation('Ce nom de fichier est réservé et ne peut pas être utilisé.')
                ->atPath('name')
                ->addViolation();
        }
    }

    /**
     * Vérifie que le chemin ne contient aucune séquence ../, /, \ ou variante encodée pour des raisons de sécurité.
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
            if (self::$logger) {
                self::$logger->warning('[SECURITY] Tentative d’upload avec chemin dangereux', [
                    'path' => $this->path,
                    'filename' => $this->name,
                    'user' => method_exists($this->owner ?? null, 'getId') ? $this->owner->getId() : null,
                ]);
            }
            $context->buildViolation('Le chemin ne doit contenir aucune séquence ../, /, \\ ou variante encodée pour des raisons de sécurité.')
                ->atPath('path')
                ->addViolation();
        }
    }

    /**
     * Vérifie que l'extension du fichier est bien dans la liste blanche ALLOWED_EXTENSIONS.
     */
    public function validateExtension(ExecutionContextInterface $context, $payload): void
    {
        $extension = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
        if (!in_array($extension, array_map('strtolower', self::ALLOWED_EXTENSIONS), true)) {
            if (self::$logger) {
                self::$logger->warning('[SECURITY] Tentative d’upload d’un fichier avec extension interdite', [
                    'filename' => $this->name,
                    'extension' => $extension,
                    'user' => method_exists($this->owner ?? null, 'getId') ? $this->owner->getId() : null,
                ]);
            }
            $context->buildViolation('L\'extension de fichier n\'est pas autorisée.')
                ->atPath('name')
                ->addViolation();
        }
    }
}
