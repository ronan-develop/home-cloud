<?php

declare(strict_types=1);

namespace App\Service\Help;

/**
 * Un sujet de la page d'aide utilisateur (#529) — contenu statique, pas
 * d'entité Doctrine : aucun besoin de persistance/édition pour cette
 * itération (YAGNI, cf. ticket).
 */
final readonly class HelpTopic
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $summary,
        public string $contentHtml,
    ) {}
}
