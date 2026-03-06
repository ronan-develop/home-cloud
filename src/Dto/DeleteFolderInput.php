<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class DeleteFolderInput
{
    /**
     * Si true : suppression récursive complète
     * Si false : déplacer le contenu vers Uploads
     */
    #[Assert\Type('bool')]
    public bool $deleteContents = true;

    /**
     * IRI du dossier de destination (optionnel si deleteContents=false)
     */
    public ?string $targetFolder = null;

    /**
     * Stratégie de résolution des conflits de nom : 'suffix' | 'overwrite' | 'fail'
     */
    public string $conflictStrategy = 'suffix';
}
