<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\FilenameValidatorInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Validates file and folder names against filesystem-unsafe characters.
 *
 * Forbidden characters: \ / : * ? " < > |  (Windows + Unix filesystem)
 */
final class FilenameValidator implements FilenameValidatorInterface
{
    private const MAX_LENGTH = 255;
    private const FORBIDDEN_CHARS_PATTERN = '/[\\\\\/\:\*\?\"\<\>\|]/u';

    public function validate(string $name): void
    {
        if ($name === '') {
            throw new BadRequestHttpException('Name cannot be empty');
        }

        if (mb_strlen($name) > self::MAX_LENGTH) {
            throw new BadRequestHttpException(sprintf('Name too long (255 max, got %d)', mb_strlen($name)));
        }

        if (preg_match(self::FORBIDDEN_CHARS_PATTERN, $name)) {
            throw new BadRequestHttpException('Invalid characters in name: \\ / : * ? " < > | are not allowed');
        }
    }
}
