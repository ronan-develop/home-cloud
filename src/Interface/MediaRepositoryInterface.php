<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Media;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;

interface MediaRepositoryInterface
{
    /**
     * @param array<string, string> $orderBy Champ => direction ('asc'|'desc'). Champs
     *                                        inconnus ignorés silencieusement.
     * @return Media[]
     */
    public function findByOwner(User $user, ?string $type = null, array $orderBy = []): array;

    public function findById(Uuid $id): ?Media;
}
