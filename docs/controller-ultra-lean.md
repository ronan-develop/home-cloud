# Pattern contrôleur ultra-lean – Home Cloud

## Objectif

Centraliser toute la gestion d’erreur métier dans les services, via des exceptions métier, pour obtenir des contrôleurs :

- ultra-lean (aucun if métier, flux nominal uniquement)
- testables et SRP
- fail-fast (erreur détectée et propagée immédiatement)
- compatibles avec une gestion d’erreur centralisée (listener d’exception)

## Exemple appliqué

Dans `FileController.php`, toutes les méthodes critiques (downloadZip, bulkDelete, download, delete, downloadSelectedZip) ne contiennent plus aucun if métier.  
La validation et la gestion d’erreur sont déportées dans les services :

```php
// Contrôleur
public function downloadZip(EntityManagerInterface $em, ZipArchiveService $zipArchiveService): BinaryFileResponse
{
    $user = $this->getUser();
    $userId = ...;
    $files = $em->getRepository(File::class)->findBy(['owner' => $user]);
    return $zipArchiveService->createZipResponse($files, 'mes-fichiers-homecloud.zip', (string)$userId);
}
```

```php
// Service
public function createZipResponse(array $files, string $zipName, string $userId): BinaryFileResponse
{
    if (!$files || count($files) === 0) {
        throw new NoFilesFoundException();
    }
    // ...création ZIP...
}
```

## Listener d’exception

Un listener Symfony intercepte les exceptions métier et utilise FileErrorRedirectorService pour rediriger et flasher le message.

## Avantages

- Contrôleur ultra-lean, lisible et maintenable
- Testabilité accrue (mock des exceptions)
- Centralisation de la gestion d’erreur
- Respect des principes SOLID et fail-fast

## À appliquer

Ce pattern doit être généralisé à tous les contrôleurs métier du projet Home Cloud.

---
