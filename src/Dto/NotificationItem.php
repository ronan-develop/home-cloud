<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * DTO commun de la pile de notifications (#373), produit par un normalizer
 * par source (DirectMessage, changelog) pour que NotificationFeedProvider
 * ne connaisse que ce type et n'ait pas à distinguer les sources.
 */
final readonly class NotificationItem
{
    public const TYPE_DIRECT_MESSAGE = 'direct_message';
    public const TYPE_CHANGELOG = 'changelog';

    public function __construct(
        public string $type,
        public string $title,
        public \DateTimeImmutable $date,
        public string $link,
        public bool $isRead,
        public ?string $id = null,
    ) {}
}
