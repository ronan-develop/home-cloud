<?php

namespace App\Tests\Unit;

use App\Uploader\DefaultFileNameGenerator;
use PHPUnit\Framework\TestCase;

class DefaultFileNameGeneratorTest extends TestCase
{
    public function testGenerateCleansFileName(): void
    {
        $generator = new DefaultFileNameGenerator();
        $original = "éx@mp l e/../tést 2025!.jpg";
        $result = $generator->generate($original);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+_test-2025.jpg$/i', $result);
    }

    public function testGenerateFallbackOnEmpty(): void
    {
        $generator = new DefaultFileNameGenerator();
        $original = "###";
        $result = $generator->generate($original);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+_file$/i', $result);
    }
}
