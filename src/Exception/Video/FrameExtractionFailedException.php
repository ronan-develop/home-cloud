<?php

declare(strict_types=1);

namespace App\Exception\Video;

final class FrameExtractionFailedException extends \RuntimeException implements VideoThumbnailExtractionException
{
}
