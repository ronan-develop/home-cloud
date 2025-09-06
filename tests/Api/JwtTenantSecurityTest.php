<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\Yaml\Yaml;

class JwtTenantSecurityTest extends ApiTestCase
{
    private static function generateJwt(array $payload, string $privateKeyPath, string $passphrase = null): string
    {
        // Génère un JWT signé avec la clé privée de dev (RSA SHA256)
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $segments = [
            rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '='),
            rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=')
        ];
        $data = implode('.', $segments);
        $privateKey = file_get_contents($privateKeyPath);
        $key = openssl_pkey_get_private($privateKey, $passphrase ?? '');
        openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        return implode('.', $segments);
    }

    public function testJwtTenantHappyPath(): void
    {
        // Simule un sous-domaine "ronan.lenouvel.me" et un JWT avec tenant "ronan"
        $payload = [
            'username' => 'ronan',
            'tenant' => 'ronan',
            'exp' => time() + 3600,
        ];
        $jwt = self::generateJwt($payload, __DIR__ . '/../../config/jwt/private.pem');
        $response = static::createClient()->request('GET', '/api/private_spaces', [
            'headers' => [
                'Authorization' => 'Bearer ' . $jwt,
                'Host' => 'ronan.lenouvel.me',
            ],
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function testJwtTenantMismatch(): void
    {
        // Simule un sous-domaine "ronan.lenouvel.me" et un JWT avec tenant "alice"
        $payload = [
            'username' => 'alice',
            'tenant' => 'alice',
            'exp' => time() + 3600,
        ];
        $jwt = self::generateJwt($payload, __DIR__ . '/../../config/jwt/private.pem');
        $response = static::createClient()->request('GET', '/api/private_spaces', [
            'headers' => [
                'Authorization' => 'Bearer ' . $jwt,
                'Host' => 'ronan.lenouvel.me',
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }
}
