# 🧞‍♂️ Exemple de test fonctionnel API Platform (entité PrivateSpace)

Ce fichier donne un exemple minimaliste de test fonctionnel pour la ressource `PrivateSpace` de ce projet.

```php
namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

final class PrivateSpaceTest extends ApiTestCase
{
    public function testPrivateSpaceDoesNotExist(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/private_spaces/99999');
        $this->assertResponseStatusCodeSame(404);
        $this->assertJsonContains([
            'detail' => 'Not Found',
        ]);
    }

    public function testGetPrivateSpaceCollection(): void
    {
        $response = static::createClient()->request('GET', '/api/private_spaces');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertIsArray($data['hydra:member'] ?? []);
        // Vérifie la présence d'une entité fixture
        $names = array_column($data['hydra:member'], 'name');
        $this->assertContains('Espace Démo', $names);
        $this->assertContains('Espace Test', $names);
    }
}
```

**À retenir** :

- Utiliser `ApiTestCase` pour tous les tests fonctionnels API Platform
- Toujours vérifier le code HTTP et le contenu JSON
- Utiliser les assertions fournies par API Platform pour valider le schéma et la structure
- Adapter les chemins et entités à votre projet
