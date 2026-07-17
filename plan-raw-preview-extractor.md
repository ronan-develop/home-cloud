# Plan — Package `ronanlenouvel/raw-preview-extractor`

Extraction de la preview JPEG embarquée dans les fichiers RAW (CR2, CR3, NEF, ARW, DNG), en **PHP pur, sans binaire externe**. Destiné à être **publié sur Packagist** — respect strict des conventions officielles Symfony (sources en fin de document).

---

## 0. Décision d'architecture : librairie pure + bundle d'intégration

> **Point clé.** Le cœur (parsing TIFF/ISO-BMFF, extraction JPEG) n'utilise **aucune** feature Symfony. La doc officielle est explicite : un bundle sert à « partager du code entre applications Symfony », pas à héberger du code qui n'a pas besoin du framework. Rendre ce code bundle-only le rendrait inutilisable hors Symfony (Laravel, vanilla…) et réduirait la portée de la publication.

**Structure retenue** (pattern des composants Symfony eux-mêmes) : **un seul repo mono-package**

- une **librairie framework-agnostic** = le vrai cœur (`type: library`, dépend uniquement de `php`) ;
- un **bundle Symfony fin et optionnel** dans le même repo, qui se contente d'enregistrer le service et de l'auto-wirer.

Le bundle dépend de la librairie (même code, même repo) ; la librairie ne dépend jamais du bundle. `symfony/framework-bundle` est en `require-dev` uniquement.

---

## 1. Repo séparé de HomeCloud

**Oui, repo Git dédié** — obligatoire :

- publication Packagist = un repo public = un cycle de versions/CI/issues propre, découplé de HomeCloud ;
- réutilisabilité : un package portable ne doit rien savoir de HomeCloud.

**Développement local conjoint** : dans HomeCloud, ajouter un `repositories` de type `path` pointant vers le dossier local du package → Composer crée un symlink, les modifs sont visibles immédiatement sans republier. À retirer avant prod (on repassera sur la version Packagist).

Nom Composer : **`ronanlenouvel/raw-preview-extractor`** — namespace racine **`RonanLenouvel\RawPreviewExtractor\`**.

---

## 2. Contexte technique (inchangé — validé)

HomeCloud doit afficher une vignette pour les RAW. Pas de décodage RAW complet (nécessiterait libraw/Imagick, indisponible sur o2switch mutualisé sans root). À la place : **extraction de la preview JPEG déjà embarquée par l'appareil** — présente dans la quasi-totalité des RAW modernes.

- **CR2, NEF, ARW, DNG** = variantes du format **TIFF 6.0** (conteneur documenté : en-tête → chaîne d'IFD → tags typés). La preview JPEG est référencée par des tags standards (`JPEGInterchangeFormat`/`StripOffsets`) pointant vers un offset + une taille. Localisation + extraction par simple lecture + parsing d'IFD, sans décoder le RAW.
- **CR3** = conteneur **ISO-BMFF** (famille MP4/HEIF), structure de boîtes (`ftyp`, `moov`, `uuid`/`CMT1`, `PRVW`, `THMB`). Parsing distinct, PHP pur également.

exiftool / libraw servent de **référence de comportement** (open source), **jamais de source de code copié**.

---

## 3. Périmètre v1

- **CR2, NEF, ARW, DNG** → parseur TIFF commun.
- **CR3** → parseur ISO-BMFF dédié.
- RAF (Fuji), ORF (Olympus) : reportés v2 (structure TIFF proche, extension naturelle une fois le parseur TIFF validé).

---

## 4. Structure du repo (conventions Symfony)

Mono-package : `src/` = librairie, `src/Bridge/Symfony/` = bundle optionnel. Namespaces distincts.

```
raw-preview-extractor/
├── composer.json
├── README.md                       # obligatoire (description + exemples + lien docs)
├── LICENSE                         # obligatoire (MIT — SPDX valide)
├── phpunit.dist.xml                # obligatoire (convention bundle)
├── docs/
│   └── index.md                    # obligatoire (doc racine)
├── src/
│   ├── RawPreviewExtractorInterface.php
│   ├── RawPreviewExtractor.php      # façade, résout le bon parseur
│   ├── ExtractedPreview.php         # value object readonly
│   ├── Exception/
│   │   ├── RawPreviewExtractorException.php   # marker interface commune
│   │   ├── UnsupportedFormatException.php
│   │   ├── PreviewNotFoundException.php
│   │   └── CorruptedFileException.php
│   ├── Format/
│   │   ├── FormatDetectorInterface.php
│   │   ├── FormatDetector.php
│   │   └── Format.php               # enum: CR2, CR3, NEF, ARW, DNG
│   ├── Parser/
│   │   ├── PreviewParserInterface.php
│   │   ├── Tiff/
│   │   │   ├── TiffPreviewParser.php
│   │   │   ├── TiffReader.php
│   │   │   ├── IfdEntry.php          # value object readonly
│   │   │   └── TiffTag.php           # enum tags TIFF/EXIF pertinents
│   │   └── Cr3/
│   │       ├── Cr3PreviewParser.php
│   │       └── IsoBmffBoxReader.php
│   └── Bridge/
│       └── Symfony/                 # ── BUNDLE optionnel ──
│           ├── RawPreviewExtractorBundle.php    # extends AbstractBundle
│           └── Resources/
│               └── config/
│                   └── services.php  # définitions explicites (pas d'autowiring interne)
├── tests/
│   ├── Unit/
│   │   ├── Format/FormatDetectorTest.php
│   │   ├── Parser/Tiff/TiffReaderTest.php
│   │   └── Parser/Cr3/IsoBmffBoxReaderTest.php
│   ├── Integration/
│   │   ├── TiffPreviewParserTest.php
│   │   ├── Cr3PreviewParserTest.php
│   │   └── Bridge/Symfony/BundleIntegrationTest.php   # bundle boote + service dispo
│   └── Fixtures/
│       ├── sample.cr2 / .cr3 / .nef / .arw / .dng
│       └── corrupted.cr2
└── .github/workflows/tests.yml
```

**Conventions bundle appliquées** :
- Classe bundle `RawPreviewExtractorBundle` extends **`AbstractBundle`** (Symfony 7+, `getPath()` auto).
- Config services : **définitions explicites**, préfixées par l'alias `raw_preview_extractor.*`, **pas d'autowiring/autoconfiguration internes** (règle bundle). Services non destinés à l'app = **privés** ; exposition via **alias** de l'interface publique.
- Alias bundle : `raw_preview_extractor`.
- Chemins de ressources en **physique** (`__DIR__ . '/Resources/config/services.php'`), pas de notation `@Bundle`.
- Le bundle **n'embarque aucune lib tierce** et se limite au câblage DI.

---

## 5. SOLID (inchangé, validé)

- **SRP** : `FormatDetector` détecte ; `TiffReader`/`IsoBmffBoxReader` lisent le bas niveau ; `TiffPreviewParser`/`Cr3PreviewParser` orchestrent l'extraction ; `RawPreviewExtractor` = façade.
- **OCP** : nouveau format = entrée d'enum `Format` + nouveau `PreviewParser`, sans toucher l'existant. Façade résout via une map `Format → PreviewParserInterface` injectée (pas de `switch` géant).
- **LSP** : tous les parseurs interchangeables via `PreviewParserInterface::extract(string $path): ExtractedPreview`.
- **ISP** : `FormatDetectorInterface` / `PreviewParserInterface` = interfaces étroites (une méthode publique).
- **DIP** : la façade dépend d'abstractions injectées au constructeur, jamais de `new` en dur.

---

## 6. API publique cible

```php
namespace RonanLenouvel\RawPreviewExtractor;

interface RawPreviewExtractorInterface
{
    /**
     * @throws Exception\UnsupportedFormatException  extension/signature non-RAW supportée
     * @throws Exception\PreviewNotFoundException     aucune preview JPEG trouvée
     * @throws Exception\CorruptedFileException       fichier illisible / structurellement invalide
     */
    public function extract(string $path): ExtractedPreview;

    public function supports(string $path): bool;
}

final readonly class ExtractedPreview
{
    public function __construct(
        public string $jpegData,      // JPEG binaire brut, prêt à écrire
        public int $width,
        public int $height,
        public Format\Format $sourceFormat,
    ) {}
}
```

Toutes les exceptions du package implémentent le marker `RawPreviewExtractorException` → l'appelant peut tout attraper d'un seul `catch` pour la dégradation gracieuse.

Usage côté HomeCloud (après publication) :

```php
try {
    $preview = $extractor->extract($absolutePath);
    file_put_contents($thumbnailPath, $preview->jpegData);
} catch (RawPreviewExtractorException) {
    // ThumbnailService retombe déjà proprement — thumbnailPath reste null
}
```

---

## 7. Découpage TDD (RED → GREEN → REFACTOR)

> **Fixtures** : petits RAW réels par format (échantillons libres « RAW test samples », ou export minimal). Tronquer après l'IFD/preview utile pour alléger le repo, à condition de garder la preview JPEG intacte. `corrupted.cr2` = fichier tronqué avant la preview pour tester la gestion d'erreur.

### Étape 1 — Détection de format par signature
- **RED** : `FormatDetectorTest` — `testDetectsCr2/Cr3/Nef/Arw/Dng`, `testReturnsNullForNonRaw` (JPEG normal).
- Magic bytes + tags TIFF discriminants (Make/Model en IFD0, `DNGVersion` pour DNG) ; CR3 via `ftyp` + brand `crx `.

### Étape 2 — Lecture bas niveau TIFF
- **RED** : `TiffReaderTest` — `testReadsLittleEndianHeader`, `testReadsBigEndianHeader`, `testParsesIfdEntries`, `testFollowsIfdChain`, `testThrowsOnTruncatedFile`.
- En-tête (`II`/`MM`, offset 1er IFD), parcours de la chaîne d'IFD, liste de `IfdEntry`.

### Étape 3 — Extraction preview JPEG (TIFF)
- **RED** : `TiffPreviewParserTest` — `testExtractsPreviewFromCr2/Nef/Arw/Dng`, `testThrowsPreviewNotFoundWhenNoJpegTag`, `testExtractedJpegHasValidDimensions`.
- Parcourt les sous-IFD, lit `JPEGInterchangeFormat(Length)` ou `StripOffsets/ByteCounts`, extrait le bloc, **valide le magic `FFD8`** avant de retourner, choisit la **plus grande** preview parmi les IFD candidats.

### Étape 4 — Lecture bas niveau ISO-BMFF (CR3)
- **RED** : `IsoBmffBoxReaderTest` — `testParsesTopLevelBoxes`, `testFindsNestedBox`, `testThrowsOnTruncatedFile`.
- Boîtes (taille 4o + type 4o + contenu, récursif pour `moov`), recherche par chemin.

### Étape 5 — Extraction preview JPEG (CR3)
- **RED** : `Cr3PreviewParserTest` — `testExtractsPreviewFromCr3`, `testThrowsPreviewNotFoundWhenNoPreviewBox`.
- Localise `PRVW`/`THMB`, extrait le JPEG.

### Étape 6 — Façade + exceptions
- **RED** : `RawPreviewExtractorTest` — `testExtractDelegatesToCorrectParser`, `testSupportsReturnsTrueForKnownFormats`, `testThrowsUnsupportedFormatForNonRaw`.
- Façade reçoit `FormatDetectorInterface` + `iterable<PreviewParserInterface>` indexés par `Format`, résout et délègue.

### Étape 7 — Intégration bout-en-bout
- Sur chaque fixture : extraire, vérifier JPEG valide via `getimagesizefromstring()` (validation croisée, sans dépendre de GD pour l'extraction).

### Étape 8 — Bridge Symfony
- **RED** : `BundleIntegrationTest` — boot d'un micro-kernel de test enregistrant `RawPreviewExtractorBundle`, assert que `RawPreviewExtractorInterface` est résolu depuis le container et fonctionnel.
- `services.php` : définitions explicites, alias public de l'interface, parseurs privés injectés dans la façade.

**Couverture attendue : ≥ 95 %** (exigence bundle officielle).

---

## 8. composer.json

```json
{
    "name": "ronanlenouvel/raw-preview-extractor",
    "description": "Extract embedded JPEG previews from camera RAW files (CR2, CR3, NEF, ARW, DNG) in pure PHP, no external binaries.",
    "type": "library",
    "license": "MIT",
    "keywords": ["raw", "jpeg", "preview", "thumbnail", "cr2", "cr3", "nef", "arw", "dng", "exif", "tiff"],
    "authors": [
        { "name": "Ronan Lenouvel", "email": "ronan.develop@gmail.com" }
    ],
    "require": {
        "php": ">=8.2"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "symfony/framework-bundle": "^6.4|^7.0",
        "symfony/http-kernel": "^6.4|^7.0"
    },
    "autoload": {
        "psr-4": { "RonanLenouvel\\RawPreviewExtractor\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "RonanLenouvel\\RawPreviewExtractor\\Tests\\": "tests/" }
    },
    "suggest": {
        "symfony/framework-bundle": "To auto-register the extractor as a service via the bundled RawPreviewExtractorBundle"
    },
    "config": {
        "sort-packages": true
    }
}
```

> `type: library` (pas `symfony-bundle`) car le package **est** d'abord une librairie ; le bundle interne est une commodité optionnelle (`suggest`). Les deps Symfony restent en `require-dev`.

---

## 9. CI (matrice conventions Symfony)

`.github/workflows/tests.yml` — matrice minimale recommandée :

| PHP | Symfony | Flags |
|-----|---------|-------|
| 8.2 | 6.4 | `--prefer-lowest` |
| 8.3 | 7.* | |
| 8.4 | 7.* | |

- tester le plancher de deps (`composer update --prefer-lowest`) ;
- `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` ;
- cacher `$HOME/.composer/cache/files`, jamais `vendor/`.

---

## 10. Publication Packagist

1. Repo GitHub public, `README.md` + `docs/index.md` + `LICENSE` (MIT) + PHPDoc complet sur classes/méthodes publiques.
2. Compte Packagist → soumettre le repo → activer le webhook auto-update.
3. Semver (`1.0.0` au premier release stable après l'étape 8).
4. (Optionnel) recipe Symfony Flex plus tard si une config par défaut devient utile.

---

## 11. Intégration future dans HomeCloud (hors scope du nouveau repo, pour mémoire)

1. Dev : `repositories` type `path` vers le package local ; prod : `composer require ronanlenouvel/raw-preview-extractor`.
2. Le bundle s'auto-enregistre (Flex) ou ajout manuel dans `config/bundles.php` ; `RawPreviewExtractorInterface` devient auto-wirable.
3. `ThumbnailService::generate()` : avant l'appel GD, `if ($rawExtractor->supports($path))` → extraire la preview, l'utiliser comme source du redimensionnement GD existant (pipeline inchangé, seule la source change) ; sur exception → comportement actuel (pas de vignette).
4. `MediaProcessHandler::resolveMediaType()` : élargir la détection `image/*` aux mimeTypes/extensions RAW pour déclencher `MediaProcessMessage`.
5. `ExifService` : EXIF du RAW plus riches que ceux de la preview — envisager de lire les EXIF via le `TiffReader` du package.

---

## 12. Risques et points ouverts

1. **Variabilité par génération d'appareil** (CR2 5D Mark II vs IV) : organisation d'IFD légèrement différente. Tester plusieurs générations ou documenter le sous-ensemble validé.
2. **CR3 moins documenté** que TIFF : itérer par rétro-ingénierie sur fichiers réels (référence exiftool/libraw, sans copier). Marge de temps sur étapes 4-5.
3. **Taille des fixtures** : RAW complets = 20-80 Mo. Chercher des échantillons réduits ou tronquer après la preview.
4. **Preview parfois basse résolution** : choisir la plus grande preview parmi les IFD (`JPEGInterchangeFormatLength` comparé).

---

## Sources (conventions officielles)

- [Best Practices for Reusable Bundles — symfony.com](https://symfony.com/doc/current/bundles/best_practices.html)
- [The Bundle System — symfony.com](https://symfony.com/doc/current/bundles.html)
- [Symfony Components — symfony.com](https://symfony.com/doc/current/components/index.html)
- [Creating a Reusable Bundle — SymfonyCasts](https://symfonycasts.com/screencast/symfony-bundle/extracting-bundle)
