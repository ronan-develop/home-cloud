<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ApiTestHelper
{
    /**
     * Envoie une requête HTTP avec JWT injecté dans les headers
     * Compatible KernelBrowser (BrowserKit) et ApiPlatform Client
     */
    public static function requestWithJwt($client, string $method, string $url, array $options = [], ?string $email = null)
    {
        $jwtHeader = ['Authorization' => 'Bearer FAKE_JWT_TOKEN'];
        if ($email) {
            $jwtHeader['X-User-Email'] = $email;
        }
        // ApiPlatform Client
        if (method_exists($client, 'setDefaultOptions')) {
            if (!isset($options['headers'])) {
                $options['headers'] = [];
            }
            $options['headers'] = array_merge($options['headers'], $jwtHeader);
            return $client->request($method, $url, $options);
        }
        // KernelBrowser (BrowserKit)
        if (method_exists($client, 'setServerParameter')) {
            $client->setServerParameter('HTTP_Authorization', $jwtHeader['Authorization']);
            if (isset($jwtHeader['X-User-Email'])) {
                $client->setServerParameter('HTTP_X_USER_EMAIL', $jwtHeader['X-User-Email']);
            }
            return $client->request($method, $url, $options);
        }
        throw new \InvalidArgumentException('Client type non supporté pour le mock JWT');
    }

    /**
     * Ajoute un JWT factice dans l'en-tête Authorization pour simuler l'authentification
     * Compatible KernelBrowser (BrowserKit) et ApiPlatform Client
     */
    public static function withFakeJwt($client): void
    {
        // ApiPlatform Client
        if (method_exists($client, 'setDefaultOptions')) {
            $client->setDefaultOptions([
                'headers' => [
                    'Authorization' => 'Bearer FAKE_JWT_TOKEN',
                ],
            ]);
            return;
        }
        // KernelBrowser (BrowserKit)
        if (method_exists($client, 'setServerParameter')) {
            $client->setServerParameter('HTTP_Authorization', 'Bearer FAKE_JWT_TOKEN');
            return;
        }
        throw new \InvalidArgumentException('Client type non supporté pour le mock JWT');
    }

    /**
     * Supprime l'en-tête Authorization pour tester les cas non authentifiés
     */
    public static function withoutJwt($client): void
    {
        if (method_exists($client, 'setDefaultOptions')) {
            $client->setDefaultOptions([
                'headers' => [
                    'Authorization' => null,
                ],
            ]);
            return;
        }
        if (method_exists($client, 'setServerParameter')) {
            $client->setServerParameter('HTTP_Authorization', null);
            return;
        }
        throw new \InvalidArgumentException('Client type non supporté pour le mock JWT');
    }
}
