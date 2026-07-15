<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ShareLink;

/**
 * Résultat de ShareLinkFactory::create().
 * `plainToken` n'existe qu'ici : ShareLink ne stocke que son hash, donc c'est
 * la seule occasion de composer l'URL complète à afficher/copier à l'owner.
 */
final readonly class CreatedShareLink
{
    public function __construct(
        public ShareLink $link,
        public string $plainToken,
    ) {}
}
