<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\IriExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class IriExtractorTest extends TestCase
{
    private IriExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new IriExtractor(
            logger: $this->createMock(LoggerInterface::class)
        );
    }

    public function testExtractsValidUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $iri = "/api/folders/{$uuid}";

        $result = $this->extractor->extractUuid($iri);

        $this->assertSame($uuid, $result);
    }

    public function testAcceptsRawUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $this->assertSame($uuid, $this->extractor->extractUuid($uuid));
    }

    public function testExtractsUuidFromNestedIri(): void
    {
        $uuid = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $iri = "/api/v1/files/{$uuid}";

        $this->assertSame($uuid, $this->extractor->extractUuid($iri));
    }

    #[DataProvider('invalidIrisProvider')]
    public function testRejectsInvalidIris(string $iri): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->extractor->extractUuid($iri);
    }

    public static function invalidIrisProvider(): array
    {
        return [
            'no uuid'       => ['/api/folders'],
            'invalid uuid'  => ['/api/folders/not-a-uuid'],
            'partial uuid'  => ['/api/folders/550e8400'],
            'empty string'  => [''],
        ];
    }

    public function testExtractsMultipleUuids(): void
    {
        $uuids = [
            '550e8400-e29b-41d4-a716-446655440000',
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
        ];
        $iris = array_map(fn($uuid) => "/api/folders/{$uuid}", $uuids);

        $result = $this->extractor->extractUuids($iris);

        $this->assertSame($uuids, $result);
    }

    public function testExtractsMultipleUuidsThrowsOnFirstInvalid(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->extractor->extractUuids(['/api/folders/invalid', '/api/folders/550e8400-e29b-41d4-a716-446655440000']);
    }
}
