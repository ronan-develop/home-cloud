# Plan d'implémentation — #422 (étape 1/3) Détection d'activité par instance

> Ticket : https://github.com/ronan-develop/home-cloud/issues/422
> Portée de ce plan : **uniquement la détection d'activité** (le mécanisme "renoncer plutôt que couper"). La popup temps réel et le cas `--now` sont des étapes suivantes, hors périmètre ici.
> Dépend de rien — s'implémente indépendamment de #421, dont le script nocturne n'existe pas encore.

---

## 1. Décisions tranchées (ne pas re-débattre)

| Sujet | Décision |
|-------|----------|
| Granularité | **Par instance**, pas par utilisateur — un seul timestamp, aucune table Doctrine, aucune migration |
| Seuil d'inactivité | **30 minutes** |
| Stockage | **Fichier plat** : `var/last-activity.txt`, hors webroot, jamais exposé publiquement |
| Lecteur | Un script bash (futur cron #421) lit le fichier directement (`stat`/`cat`), **sans lancer de process PHP** — coût nul sur le LVE partagé (cf. incident #395/#396) |
| Écriture | Amortie — pas à chaque requête, pour éviter une écriture disque par page vue |
| Portée de ce ticket | Le mécanisme de détection seul, exposé de façon testable. **Pas** de branchement dans un script de déploiement (n'existe pas encore) |

---

## 2. Pourquoi un fichier plat plutôt qu'une commande console

Un `bin/console app:instance:is-active` démarrerait le kernel Symfony complet (autoload, container DI) juste pour lire un booléen. Sur le LVE CloudLinux de o2switch (quota mémoire partagé entre 7 instances), c'est un coût inutile — exactement le type d'opération qui a déjà provoqué des `Killed` par accumulation (cf. `.claude/deploiement.md`, incident 2026-07-23).

Un fichier plat :
- coût de lecture nul (`stat -c %Y` ou `cat`) ;
- pas de dépendance croisée avec la DB — si Doctrine a un souci, la détection d'activité continue de fonctionner ;
- reste inspectable en SSH (`cat var/last-activity.txt`), dans l'esprit du reste du projet (pas de solution qui complique le diagnostic sur un hébergement mutualisé).

---

## 3. Architecture

```text
Requête HTTP authentifiée
        │
        ▼
ActivityTrackerSubscriber (kernel.request, priorité basse)
        │  utilisateur authentifié ?
        │       │
        │      non → rien à faire
        │       │
        │      oui
        │       ▼
        │  dernière écriture > 5 min ?  (amortissement, cf. §5)
        │       │
        │      non → rien à faire
        │       │
        │      oui
        │       ▼
        │  ActivityTracker::recordActivity()
        │       │
        │       ▼
        │  écriture atomique de var/last-activity.txt (timestamp Unix)
        ▼
Réponse HTTP (inchangée, aucun impact utilisateur)
```

Lecture (côté bash, futur #421, **pas dans ce ticket**) :

```bash
LAST=$(stat -c %Y var/last-activity.txt 2>/dev/null || echo 0)
NOW=$(date +%s)
if (( NOW - LAST < 1800 )); then
    echo "instance active, on reporte"
fi
```

---

## 4. Livrables

| # | Livrable | Nature |
|---|----------|--------|
| 1 | `src/Interface/ActivityTrackerInterface.php` | Contrat (DIP, cf. `.claude/architecture.md`) |
| 2 | `src/Service/ActivityTracker.php` | Écriture atomique du fichier, avec amortissement |
| 3 | `src/EventListener/ActivityTrackerSubscriber.php` | Écoute `kernel.request`, appelle le service si authentifié — **`EventListener/`, pas `EventSubscriber/`** : convention déjà en place dans le projet (cf. `tests/EventListener/TestAuthorizationListener.php`) |
| 4 | `services.yaml` | Chemin du fichier en paramètre (`%kernel.project_dir%/var/last-activity.txt`) |
| 5 | `.gitignore` | Ajouter `/var/last-activity.txt` (état local au serveur, jamais committé) |

**Aucune commande console, aucune route, aucune migration.**

---

## 5. Détail des livrables

### 5.1 `ActivityTrackerInterface`

```php
interface ActivityTrackerInterface
{
    public function recordActivity(): void;

    /** Pour les tests et un usage futur éventuel côté PHP (ex. admin, #422 étape 2/3) */
    public function getLastActivityAt(): ?\DateTimeImmutable;
}
```

### 5.2 `ActivityTracker`

```php
final readonly class ActivityTracker implements ActivityTrackerInterface
{
    private const AMORTIZATION_SECONDS = 300; // 5 min — cf. §5.4

    public function __construct(
        private string $filePath, // paramètre injecté, cf. services.yaml
    ) {}

    public function recordActivity(): void
    {
        $last = $this->getLastActivityAt();
        if ($last !== null && (time() - $last->getTimestamp()) < self::AMORTIZATION_SECONDS) {
            return;
        }

        $tmp = $this->filePath . '.tmp.' . getmypid();
        file_put_contents($tmp, (string) time());
        rename($tmp, $this->filePath); // écriture atomique
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        if (!is_file($this->filePath)) {
            return null;
        }
        $raw = trim(file_get_contents($this->filePath));
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }
        return (new \DateTimeImmutable())->setTimestamp((int) $raw);
    }
}
```

Points de vigilance :

- **`rename()` est atomique** sur un même système de fichiers — le fichier temporaire garantit qu'un lecteur concurrent (le futur cron bash) ne voit jamais un contenu tronqué.
- **`getmypid()` dans le nom du tmp** : évite une collision si deux requêtes PHP-FPM écrivent au même moment (l'amortissement de 5 min rend ça rare, mais pas impossible en cas de restart du fichier).
- **`ctype_digit`** avant le cast : un fichier corrompu/vide ne doit jamais faire planter la lecture, juste être traité comme "aucune activité connue".
- Le service ne lève **jamais d'exception** vers l'appelant — un problème d'écriture disque ne doit pas casser la requête HTTP en cours. Si `file_put_contents` échoue (droits, disque plein), logguer en warning et continuer silencieusement.

### 5.3 `ActivityTrackerSubscriber`

```php
final readonly class ActivityTrackerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityTrackerInterface $activityTracker,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', -100]]; // priorité basse, après le firewall
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null || !$token->getUser() instanceof User) {
            return;
        }

        $this->activityTracker->recordActivity();
    }
}
```

- **Priorité `-100`** : s'exécute après l'authentification du firewall JWT, pour que `getToken()` soit déjà renseigné.
- **`isMainRequest()`** : ignore les sous-requêtes internes (ESI, forward), qui ne représentent pas une action utilisateur.
- Vérifier `$token->getUser() instanceof User` plutôt que `!== null` seul : un token peut porter un `UserInterface` générique dans certains contextes (anonyme authentifié par exemple selon la config Symfony) — s'assurer que c'est bien *notre* entité `User`.

### 5.4 Pourquoi 5 minutes d'amortissement

Le seuil de décision (30 min, §1) tolère une imprécision largement supérieure à 5 min sans conséquence. Écrire au maximum une fois toutes les 5 min ramène le coût à une écriture disque par utilisateur actif toutes les 5 min, quel que soit son rythme de clics — négligeable même en usage intensif.

### 5.5 `services.yaml`

```yaml
parameters:
    app.activity_tracker.file_path: '%kernel.project_dir%/var/last-activity.txt'

services:
    App\Service\ActivityTracker:
        arguments:
            $filePath: '%app.activity_tracker.file_path%'
```

> **Rappel piège #381 déjà documenté** : ce service explicite doit être déclaré **après** le bloc `App\: resource: '../src/*'` dans `services.yaml`, sinon l'auto-découverte écrase silencieusement l'argument `$filePath`.

---

## 6. Ordre d'exécution TDD

Méthodologie du projet : **RED → GREEN → REFACTOR** (`.claude/tdd.md`).

### Étape 1 — `ActivityTracker`

1. 🔴 `tests/Service/ActivityTrackerTest.php` (utiliser un fichier temporaire réel, `sys_get_temp_dir()`, nettoyé en `tearDown`) :
   - `recordActivity()` sur un fichier absent → le fichier est créé, contient un timestamp Unix valide
   - `getLastActivityAt()` sur fichier absent → `null`
   - `getLastActivityAt()` après un `recordActivity()` → une `DateTimeImmutable` proche de `now()` (tolérance quelques secondes)
   - **Amortissement** : deux `recordActivity()` rapprochés (< 5 min) → le timestamp du fichier **ne change pas** entre les deux (contrôlable en pré-remplissant le fichier avec un timestamp connu avant le 2ᵉ appel)
   - fichier corrompu (contenu non numérique) → `getLastActivityAt()` retourne `null`, ne lève pas d'exception
   - écriture atomique : pas de fichier `.tmp.*` résiduel après un `recordActivity()` réussi
2. 🟢 `src/Interface/ActivityTrackerInterface.php` + `src/Service/ActivityTracker.php`
3. ✅ Commit : `✨ feat(deploy): service ActivityTracker — détection d'activité par instance (#422)`

### Étape 2 — `ActivityTrackerSubscriber`

1. 🔴 `tests/EventListener/ActivityTrackerSubscriberTest.php` (mock de `ActivityTrackerInterface` et `TokenStorageInterface`) :
   - requête avec un token portant un `User` → `recordActivity()` est appelé exactement une fois
   - aucun token (utilisateur anonyme) → `recordActivity()` n'est **jamais** appelé
   - token présent mais `getUser()` ne retourne pas un `User` (ex. `null`) → `recordActivity()` n'est pas appelé
   - sous-requête (`isMainRequest() === false`) → `recordActivity()` n'est pas appelé
   - `getSubscribedEvents()` retourne bien `KernelEvents::REQUEST` avec la priorité `-100`
2. 🟢 `src/EventSubscriber/ActivityTrackerSubscriber.php`
3. ✅ Commit : `✨ feat(deploy): ActivityTrackerSubscriber — trace l'activité sur chaque requête authentifiée (#422)`

### Étape 3 — Test d'intégration bout-en-bout

1. 🔴 `tests/Functional/ActivityTrackerIntegrationTest.php` (via `KernelBrowser`, cf. piège DI `public:true` déjà documenté pour ce type de test) :
   - une requête authentifiée réelle (login JWT puis appel à une route protégée) → `var/last-activity.txt` (chemin de test, isolé) est créé/mis à jour
   - une requête non authentifiée (route publique) → le fichier n'est pas créé
2. 🟢 Ajustements si nécessaire (config du chemin en environnement de test, cf. `services_test.yaml` ou équivalent)
3. ✅ Commit : `✅ test(deploy): couverture d'intégration de la détection d'activité (#422)`

### Étape 4 — Configuration et documentation

1. `services.yaml` : déclaration explicite du paramètre et de l'argument (§5.5, en respectant l'ordre post-autowiring)
2. `.gitignore` : ajouter `/var/last-activity.txt`
3. `.claude/deploiement.md` : courte section « Détection d'activité (#422) » — emplacement du fichier, seuil de 30 min, comment le lire en bash pour un futur script, comment vérifier manuellement (`cat var/last-activity.txt`, `date -d @$(cat var/last-activity.txt)`)
4. `.github/avancement.md` : suivi
5. ✅ Commit : `📝 docs(deploy): documentation de la détection d'activité (#422)`

---

## 7. Critères d'acceptation

- [ ] Une requête authentifiée met à jour `var/last-activity.txt` avec un timestamp Unix
- [ ] Une requête non authentifiée ne touche pas au fichier
- [ ] Deux requêtes authentifiées rapprochées (< 5 min) ne provoquent qu'une seule écriture
- [ ] Le fichier est lisible et interprétable par un simple `cat`/`stat` en bash, sans dépendance PHP
- [ ] Un fichier absent ou corrompu ne fait jamais planter l'application (dégradation silencieuse)
- [ ] `var/last-activity.txt` n'est jamais committé (`.gitignore`)
- [ ] Aucune route, commande console ou migration ajoutée
- [ ] Suite PHPUnit verte avant push (`./vendor/bin/phpunit`)

---

## 8. Pièges connus à ne pas retomber dedans

| Piège | Source | Conséquence si ignoré |
|-------|--------|------------------------|
| Service explicite déclaré **après** le bloc `App\: resource` dans `services.yaml` | #381, mémoire projet | Autowiring de `$filePath` écrasé silencieusement par l'auto-découverte |
| Ne jamais lancer de process PHP depuis un futur script bash pour lire l'activité | incident LVE #395/#396 | Coût mémoire inutile sur le compte mutualisé partagé |
| Écriture atomique (`rename()`), jamais une écriture directe | ce plan §5.2 | Le futur lecteur bash pourrait lire un fichier tronqué en cours d'écriture |
| Rebuild assets après modif `.twig` | mémoire projet | Non applicable ici (aucun template touché), mais rappel si le ticket évolue vers la popup |
| Jamais de commit direct sur `main` | `CLAUDE.md` | — |

---

## 9. Ce qui n'est PAS dans ce plan (étapes suivantes de #422)

- Le branchement dans `bin/deploy-nightly.sh` (dépend de #421, pas encore implémenté)
- La popup temps réel avec compte à rebours (dépend d'un endpoint exposant l'état + polling côté client)
- La couverture du cas `bash bin/deploy-all.sh --now`
- Toute notion de granularité par utilisateur (explicitement écartée, §1)

---

## 10. Branche et PR

```bash
git checkout main && git pull
git checkout -b feature/422-detection-activite-instance
```

- Commits atomiques, un par étape TDD (jamais `git add .`)
- Pas de ligne `Co-Authored-By`
- PR avec **`Closes #422`** dans la description **uniquement si ce plan couvre tout le ticket** — sinon utiliser une formulation du type « Contribue à #422 (détection d'activité — étape 1/3) » pour ne pas fermer le ticket prématurément
- **Label obligatoire** immédiatement après `gh pr create` (`feature`)
- Relancer la suite de tests **juste avant** le push
- CI verte avant merge, puis `--merge` (jamais `--squash`)
