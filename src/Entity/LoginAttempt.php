<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Trace une tentative de connexion échouée (#386), persistée en plus du log
 * applicatif existant (#392) pour permettre une vue admin filtrable/agrégeable.
 *
 * Une ligne = un échec (le listener n'écoute que LoginFailureEvent) : pas de
 * champ `success`. Email stocké en hash SHA-256 complet (pas tronqué comme
 * dans le log) pour un regroupement fiable par compte visé sans donnée
 * personnelle exploitable.
 */
#[ORM\Entity(repositoryClass: LoginAttemptRepository::class)]
#[ORM\Table(name: 'login_attempts')]
#[ORM\Index(columns: ['email_hash'], name: 'idx_login_attempts_email_hash')]
#[ORM\Index(columns: ['ip'], name: 'idx_login_attempts_ip')]
#[ORM\Index(columns: ['created_at'], name: 'idx_login_attempts_created_at')]
class LoginAttempt
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 64)]
    private string $emailHash;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $emailHash, ?string $ip, ?string $userAgent)
    {
        $this->id = Uuid::v7();
        $this->emailHash = $emailHash;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmailHash(): string
    {
        return $this->emailHash;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
