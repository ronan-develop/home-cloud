<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContentFingerprintRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Fingerprint persistant du contenu d'un fichier uploadé (#327).
 *
 * Volontairement indépendant du cycle de vie de File/Media : aucune relation
 * vers File, jamais purgé à la suppression. Un utilisateur qui supprime un
 * média puis réimporte un export Google Photos qui le contient à nouveau
 * doit retrouver le doublon détecté — sinon le hash serait perdu avec le
 * File et le doublon reviendrait à chaque réimport.
 *
 * Scope par owner (contrainte unique owner+hash) : deux utilisateurs peuvent
 * uploader le même contenu sans se gêner, cohérent avec l'isolation des
 * données déjà en place partout ailleurs (ownership check systématique).
 */
#[ORM\Entity(repositoryClass: ContentFingerprintRepository::class)]
#[ORM\Table(name: 'content_fingerprints')]
#[ORM\UniqueConstraint(name: 'uniq_owner_content_hash', columns: ['owner_id', 'content_hash'])]
#[ORM\Index(columns: ['content_hash'], name: 'idx_content_fingerprints_hash')]
class ContentFingerprint
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(length: 64)]
    private string $contentHash;

    #[ORM\Column]
    private \DateTimeImmutable $firstSeenAt;

    public function __construct(User $owner, string $contentHash)
    {
        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->contentHash = $contentHash;
        $this->firstSeenAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function getFirstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }
}
