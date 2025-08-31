<?php

namespace App\Entity;

use App\Repository\AccessLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccessLogRepository::class)]
class AccessLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Share::class, inversedBy: 'accessLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Share $share = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $accessedAt = null;

    #[ORM\Column(length: 45)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 32)]
    private ?string $action = null;

    #[ORM\Column(nullable: true)]
    private ?int $userId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShare(): ?Share
    {
        return $this->share;
    }

    public function setShare(?Share $share): static
    {
        $this->share = $share;
        return $this;
    }

    public function getAccessedAt(): ?\DateTimeImmutable
    {
        return $this->accessedAt;
    }

    public function setAccessedAt(\DateTimeImmutable $accessedAt): static
    {
        $this->accessedAt = $accessedAt;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;
        return $this;
    }
}
