<?php

declare(strict_types=1);

namespace App\Interface\Notification;

use App\Dto\NotificationItem;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface NotificationNormalizerInterface
{
    /**
     * @return list<NotificationItem>
     */
    public function normalize(User $user): array;
}
