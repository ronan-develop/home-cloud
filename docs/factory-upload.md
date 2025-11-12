# 🏗️ Pattern Factory pour l’upload – Guide pédagogique

## Objectif

Mettre en place un **pattern Factory** pour la gestion des uploads (photos, fichiers, etc.).

---

## 1. Pourquoi utiliser le pattern Factory ?

- Centraliser la logique de sélection de l’uploader adapté (Photo, Fichier, etc.)
- Faciliter l’ajout de nouveaux types d’upload sans modifier le code existant
- Respecter les principes SOLID (Open/Closed, Single Responsibility)
- Améliorer la testabilité et la maintenabilité
- Offrir une architecture professionnelle, évolutive et claire

---

## 2. Étapes de mise en place

### a. Définir une interface commune

Créer une interface `UploaderInterface` (ou réutiliser une interface existante) qui définit la méthode d’upload attendue.

```php
interface UploaderInterface {
    public function upload(UploadedFile $file, ...$context): mixed;
}
```

### b. Implémenter les uploaders spécialisés

- `PhotoUploader` pour les photos (avec validation, EXIF, etc.)
- `FileUploader` pour les fichiers génériques

Chaque uploader implémente l’interface commune.

### c. Créer la Factory

- La Factory reçoit tous les uploaders en dépendance (injection via le constructeur ou le container)
- Elle expose une méthode `getUploader($type, $context)` qui retourne l’uploader adapté selon le contexte (ex : mime type, extension, usage métier…)

```php
class UploaderFactory {
    public function __construct(
        private PhotoUploader $photoUploader,
        private FileUploader $fileUploader
    ) {}

    public function getUploader(string $type): UploaderInterface {
        return match($type) {
            'photo' => $this->photoUploader,
            'file' => $this->fileUploader,
            default => throw new \InvalidArgumentException('Type d’upload inconnu'),
        };
    }
}
```

### d. Utilisation dans le code métier

- Le contrôleur ou le service métier demande à la Factory l’uploader adapté selon le contexte
- Il délègue l’upload à l’uploader retourné

```php
$uploader = $uploaderFactory->getUploader($type);
$uploader->upload($file, ...);
```

---

## 3. Avantages pédagogiques et techniques

- **Extensible** : ajout facile de nouveaux uploaders
- **Centralisé** : logique de sélection unique
- **Testable** : chaque uploader et la factory sont testables indépendamment
- **Lisible** : séparation claire des responsabilités
- **Évolutif** : prêt pour de nouveaux besoins (vidéo, audio, etc.)

---

## 4. Inconvénients / Points de vigilance

- **Complexité initiale** : nécessite plus de fichiers/classes
- **Sur-ingénierie** si le besoin reste très simple et figé
- **Bien documenter** la logique de sélection pour éviter la « magie »

---

## 5. Bonnes pratiques

- Documenter la Factory et chaque uploader
- Utiliser l’injection de dépendances (pas de new dans la Factory)
- Prévoir des exceptions explicites pour les cas non gérés
- Tester chaque uploader et la Factory séparément

---

## 6. Exemple d’évolution

- Ajout d’un `VideoUploader` : il suffit d’implémenter l’interface et d’ajouter un cas dans la Factory

---

## 7. Conclusion

Le pattern Factory pour l’upload est un excellent exercice pour structurer un projet Symfony de façon professionnelle, anticiper les évolutions et garantir la maintenabilité.

---
