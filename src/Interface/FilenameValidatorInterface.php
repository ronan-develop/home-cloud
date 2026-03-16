<?php

declare(strict_types=1);

namespace App\Interface;

/**
 * Contrat de validation des noms de fichiers et dossiers.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface FilenameValidatorInterface
{
    /**
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException si le nom est invalide
     */
    public function validate(string $name): void;
}
