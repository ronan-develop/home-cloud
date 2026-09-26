<?php

declare(strict_types=1);

namespace App\Service\Takeout;

/**
 * Résultat du parcours d'une arborescence extraite d'un export Google
 * Takeout — cf. TakeoutStructureParser.
 */
final readonly class TakeoutStructureResult
{
    /**
     * @param TakeoutMediaEntry[] $mediaEntries
     */
    public function __construct(
        public array $mediaEntries,
        public int $ignoredCount,
    ) {}
}
