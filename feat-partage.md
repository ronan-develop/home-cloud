# Partage — état des lieux & plan « privé par défaut, public sur opt-in »

> Ce document remplace `plan-partage.md` (supprimé, jamais commité).
> Vérifié contre le code du **2026-07-14**, après les PR #204 → #215.

**En une phrase** : HomeCloud n'a aujourd'hui que du privé (tout accès exige un
compte). On ajoute l'accès public par lien — mais **verrouillé par ressource**, de
sorte qu'un fichier marqué privé ne puisse **jamais** être exposé par un simple
échange de lien, même en cas de bug d'interface.

---

## Partie A — Ce qui est FAIT (partage entre comptes)

Le chantier « partage collaboratif » est **terminé et mergé** (PR #204 → #215).
Résumé de ce qui existe réellement en prod, pour ne pas le redévelopper :

| Brique                   | Où                                                          | Comportement                                                                                                                                                                                                |
|--------------------------|-------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Entité `Share`           | `src/Entity/Share.php`                                      | Relation polymorphe `resourceType` (file/folder/album) + `resourceId`, **sans FK**. `permission` read/write. `expiresAt` nullable = permanent. Index `idx_share_lookup`, contrainte `uniq_share`.           |
| Source de vérité d'accès | `src/Security/ResourceAccessChecker.php`                    | `canRead()` / `canWrite()` = **owner OU partage actif**. Consommé par les 4 providers API, 2 contrôleurs de download, et `AlbumVoter`. **Aucun cache** : la révocation est effective à la requête suivante. |
| Résolution polymorphe    | `src/Security/ResourceLocator.php`                          | `(type, id)` → `File\|Folder\|Album`, 404 si absente.                                                                                                                                                       |
| Création d'un partage    | `ShareProcessor` (API) + `ShareWebController::create` (web) | Exige l'ownership réel de la ressource, rejette le self-share, 409 sur doublon, **rate-limité** (20 / 15 min / owner). Accepte `guestId` **ou** `guestEmail`.                                               |
| Nettoyage                | `ShareRepository::deleteByResource()`                       | Appelé aux 5 points de suppression réels (File API/web, Album, Folder récursif).                                                                                                                            |
| UI                       | `/partages`, `ShareModal`                                   | Page entrants/sortants (nom ressource, personne, droits, durée). Bouton « Partager » sur album et dossier.                                                                                                  |

**Étape non faite du chantier initial** : le bandeau « Partagé par X » sur une ressource
consultée en tant que guest (cosmétique, non bloquant).

### Le verrou actuel

```php
// src/Entity/Share.php
#[ORM\ManyToOne(targetEntity: User::class)]
#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
private User $guest;   // ← non-nullable : impose un compte existant
```

Partager, aujourd'hui, c'est **relier deux comptes**. L'invité s'authentifie
normalement (session/JWT), et à chaque requête `ShareAccessChecker` rejoue la
question « existe-t-il une ligne `shares` où `guest = moi` ? ». Il n'y a aucun
jeton, aucun lien : c'est ce qui rend la révocation instantanée et le modèle
simple. Le prix : **l'invité doit avoir un compte**.

---

## Partie B — Plan : privé par défaut, public sur opt-in explicite

### B.0 — La question préalable : veut-on de l'accès public ?

Avant de parler de tokens, il faut trancher ceci, sinon on construit sur du sable.

**Réponse : oui, mais jamais par défaut, et jamais sans un verrou explicite par
ressource.** Le privé reste le mode normal ; le public est une exception que
l'owner doit déclarer, ressource par ressource.

#### Pourquoi le public est irréductiblement différent

L'autorisation actuelle répond à « **qui es-tu ?** » (identité prouvée par mot de
passe). Un lien public répond à « **que détiens-tu ?** » (un secret dans une URL).
Ce n'est pas une variante, c'est un autre modèle, avec une propriété qu'on ne peut
pas corriger :

> **On ne peut pas empêcher la retransmission d'un lien.**
> Un lien transféré dans un mail, collé dans un Slack, capturé dans un historique
> de navigation ou un log de proxy → l'accès suit. Le lien ne distingue pas le
> destinataire prévu de n'importe qui d'autre.

D'où une asymétrie de révocation qu'il faut assumer :

| | Privé (`Share` + compte) | Public (`ShareLink`) |
|---|---|---|
| Qui accède | une personne identifiée | **quiconque détient l'URL** |
| Révocation | **réellement effective** — on coupe la ligne, l'accès s'arrête | ferme la porte, mais **ne défait pas ce qui a déjà été téléchargé** |
| Traçabilité | on sait qui | on ne sait pas combien de personnes ont eu le lien |

Un lien public est **réversible dans ses effets futurs, irréversible dans ses
conséquences**. C'est la raison pour laquelle il ne peut pas être le défaut.

#### Ce qui décide, ce n'est pas le type de fichier

Tentation à écarter : « les photos en public, les documents en privé ». Le même PDF
peut être un flyer de fête (fuite sans conséquence) ou un bulletin de salaire (fuite
grave). Le système **ne peut pas deviner** — c'est à l'owner de le déclarer au moment
du partage. Le rôle du code n'est pas de choisir à sa place, mais de rendre ce choix
**explicite, réversible et jamais implicite**.

#### Les trois lignes de défense (de la plus forte à la plus faible)

**1. Aucune URL latente.** Une ressource sans `ShareLink` est **inatteignable** — il
n'y a pas de « lien secret » à deviner, aucune route n'expose une ressource hors du
firewall. C'est déjà vrai aujourd'hui, et c'est la garantie la plus forte. À préserver.

**2. Le verrou `visibility` — la réponse à « comment garantir qu'un fichier ne peut
pas être exposé par un simple échange de lien ».** Voir B.1. En une phrase : le
serveur **refuse** de créer un lien sur une ressource marquée privée. Le refus vit
dans le domaine, pas dans un template — donc ni un bug d'UI, ni un clic malheureux,
ni une requête forgée ne peuvent produire un lien public sur un fichier verrouillé.
C'est la différence entre une politique (qu'on espère respectée) et une **invariante**
(que le système garantit).

**3. Les garde-fous d'usage** — expiration obligatoire, lecture seule, `noindex`,
rate-limit (voir B.2). Ils **limitent la casse, ils ne la préviennent pas**. Ils ne
remplacent jamais le point 2.

#### Les règles qui en découlent (non négociables)

1. **Le lien EST l'autorisation** — l'email qui le transporte ne protège rien. L'UI
   ne doit donc pas dire « partager *avec* Untel » mais « **créer un lien d'accès** ».
2. **Lien = lecture seule.** Un `write` porté par un lien signifierait que n'importe
   quel porteur peut renommer/supprimer. Exclu.
3. **Expiration obligatoire** (pas de `null`), défaut 7 j, max 30 j. Un lien qui
   n'expire jamais est une fuite qui n'expire jamais. C'est l'inverse du défaut du
   partage entre comptes (permanent) — et c'est délibéré.
4. **`visibility = private` par défaut** sur toute ressource, existante comme nouvelle.

Ces règles définissent ce qu'on livre. Si l'une est refusée, le plan change.

### B.1 — Modèle de données

#### a) Le verrou de visibilité (le cœur de la garantie)

Un champ `visibility` sur `File`, `Folder` et `Album` :

```php
public const VISIBILITY_PRIVATE      = 'private';       // défaut — aucun lien possible
public const VISIBILITY_LINK_ALLOWED = 'link_allowed';  // opt-in explicite de l'owner

#[ORM\Column(type: 'string', length: 12, options: ['default' => 'private'])]
private string $visibility = self::VISIBILITY_PRIVATE;
```

La migration met **`private` sur tout l'existant** : aucune ressource déjà en base ne
devient exposable par l'ajout de la fonctionnalité.

La règle est appliquée **côté serveur, dans le domaine** — pas dans un template :

```php
// ShareLinkFactory (ou le service de création)
if ($resource->getVisibility() !== VISIBILITY_LINK_ALLOWED) {
    throw new ResourceNotPubliclyShareableException();  // → 403
}
```

Conséquences concrètes, et c'est exactement ce qu'on cherchait :
- Un fichier `private` **ne peut pas** produire de lien, quoi qu'il arrive côté UI.
- **Un dossier `private` protège son contenu** : un dossier `Privé` (bulletins, papiers
  d'identité, contrats) est structurellement non-exposable par lien. C'est la réponse
  simple et robuste au besoin « certains fichiers ne doivent jamais sortir ».
- Repasser une ressource en `private` **révoque ses liens existants** (cascade — à
  tester explicitement, cf. B.4 étape 6).
- **Héritage** : un fichier hérite-t-il de la visibilité de son dossier ? Recommandé :
  la vérification remonte la chaîne des parents, et **le plus restrictif gagne** — un
  fichier `link_allowed` dans un dossier `private` reste non-partageable. Sinon
  déplacer un fichier dans le dossier « Privé » ne le protégerait pas, ce qui ruinerait
  la garantie. (À confirmer, cf. B.5.)

#### b) L'entité `ShareLink`

Deux options pour porter le lien. **Option 2 recommandée.**

**Option 1 — rendre `Share::$guest` nullable.** Un `Share` sans guest = un lien.
Séduisant (une seule table), mais toxique : chaque appelant de `getGuest()`
devient nullable, `uniq_share` casse (plusieurs liens sur la même ressource, tous
avec `guest = NULL`), et surtout `findActiveShare()` — le cœur de la sécurité,
appelé à chaque requête — mélangerait deux logiques d'autorisation
fondamentalement différentes. On dégraderait le code déjà solide.

**Option 2 (retenue) — nouvelle entité `ShareLink`, distincte.**

```php
#[ORM\Entity]
#[ORM\Table(name: 'share_links')]
#[ORM\Index(name: 'idx_sharelink_selector', columns: ['selector'])]
class ShareLink
{
    private Uuid $id;                    // Uuid::v7() dans le constructeur (règle projet)
    private User $owner;                 // qui a créé le lien
    private string $resourceType;        // file | folder | album
    private Uuid $resourceId;
    private string $selector;            // public, indexé, sert à retrouver la ligne
    private string $hashedToken;         // hash du secret — le secret n'est JAMAIS stocké
    private \DateTimeImmutable $expiresAt;   // NON nullable (cf. B.0)
    private ?string $invitedEmail;       // trace de livraison, sans valeur de sécurité
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $revokedAt = null;

    public function isActive(): bool
    {
        return $this->revokedAt === null && $this->expiresAt > new \DateTimeImmutable();
    }
}
```

`Share` reste **strictement inchangé**. Les deux mécanismes coexistent sans se
polluer : `ResourceAccessChecker` continue de répondre pour les comptes,
`ShareLinkAccessChecker` répond pour les liens.

**Les deux systèmes ne se recouvrent pas** — et c'est voulu :

| Besoin | Mécanisme | Le destinataire a-t-il un compte ? |
|---|---|---|
| Partager durablement avec un proche qui utilise HomeCloud | `Share` (existant) | oui |
| Envoyer une photo à quelqu'un qui n'aura jamais de compte | `ShareLink` | non |
| Ne jamais exposer un document sensible | `visibility = private` (défaut) | — aucun partage possible par lien |

**Pourquoi selector + hashedToken** (et non un token en clair en base) : c'est le
pattern déjà utilisé par le projet pour la réinitialisation de mot de passe
(`ResetPasswordRequest`, bundle SymfonyCasts). Si la base fuite, les liens ne sont
pas exploitables. Le secret n'existe qu'une fois, dans l'URL envoyée.

```
URL :   /p/{selector}/{token}
Base :  selector (clair, indexé)  +  hashedToken = hash('sha256', token)
Vérif : trouver par selector, puis hash_equals(hashedToken, hash(token_fourni))
        → comparaison à temps constant, pas de timing attack
```

### B.2 — Sécurité : la checklist non négociable

| Risque | Défense |
|---|---|
| Token devinable | `random_bytes(32)` → `bin2hex` (256 bits). Jamais `uniqid()`, `rand()`, ni un UUID. |
| Base compromise → liens rejouables | Seul le **hash** est stocké. |
| Timing attack sur la comparaison | `hash_equals()`, jamais `===`. |
| Token dans les logs serveur / Referer | Le token est dans le **path**, pas la query string (les query strings finissent dans les access logs et l'en-tête `Referer`). Ajouter `Referrer-Policy: no-referrer` — **déjà en place** (`SecurityHeadersListener`). |
| Indexation par un moteur de recherche | `X-Robots-Tag: noindex, nofollow` sur les réponses `/p/*`. |
| Brute-force du selector | Rate-limit sur `/p/*` (réutiliser le pattern `rate_limiter.yaml` de `share_creation`). |
| Énumération d'emails à la création | Déjà couvert : rate-limit sur la création. |
| Lien qui survit à la suppression de la ressource | Étendre `deleteByResource()` — **ou** un `ShareLinkRepository::deleteByResource()` appelé aux **mêmes 5 points** que `Share` (cf. Partie A). Ne pas oublier ce point : c'est exactement le trou qu'on a dû colmater à l'étape 6 du chantier précédent. |
| Élévation vers l'écriture | Impossible par construction : le lien n'accorde que `read` (B.0). |
| Le porteur du lien accède à **autre chose** | La ressource est liée au lien en base. Le porteur ne peut pas changer `resourceId` : il n'apparaît nulle part dans l'URL. |
| **Exposition accidentelle d'une ressource sensible** | **`visibility = private` par défaut** : le serveur refuse de créer un lien (B.1.a). Ni un bug d'UI, ni une requête forgée ne peuvent contourner ce refus — il est dans le domaine. |
| Contournement du verrou en déplaçant le fichier | La vérification **remonte la chaîne des parents** : le plus restrictif gagne (B.1.a). Un fichier dans un dossier `private` reste non-partageable. |

**Le piège du firewall.** `config/packages/security.yaml` : le firewall `web`
couvre `pattern: ^/`. Une route `/p/*` doit être explicitement `PUBLIC_ACCESS`.
⚠️ Le fichier prévient lui-même que `config/packages/test/security.yaml` redéfinit
tout l'`access_control` — **toute règle ajoutée doit être répercutée là-bas**,
sinon les tests ne valideront pas ce qu'on croit.

### B.3 — Envoi de l'email

`symfony/mailer` est installé, mais `.env` est sur `MAILER_DSN=null://null` :
**aucun email ne part aujourd'hui**. Il faut un vrai DSN (o2switch fournit du
SMTP) dans `.env.local` / secrets de déploiement.

Décision à prendre : bloquer la v1 sur la config SMTP, ou livrer d'abord le lien
**copiable dans l'UI** (« Copier le lien ») et brancher l'email juste après ?
La seconde option découple, permet de tester tout le mécanisme sans dépendre du
mail, et reste utile ensuite (on veut souvent copier un lien plutôt que
l'envoyer). **Recommandé : lien copiable d'abord, email en second temps.**

### B.4 — Découpage TDD

Conventions projet : une branche par étape depuis `main`, TDD RED → GREEN →
REFACTOR, PR + CI verte avant merge. Jamais de commit sur `main`.

**Étape 0 — Le verrou `visibility` (à faire EN PREMIER)**

Cette étape vient avant tout le reste, et ce n'est pas un détail d'ordonnancement :
tant qu'elle n'existe pas, toute route publique livrée ensuite est exposable sans
garde-fou. On pose la serrure avant de percer la porte.

- RED (unitaire) : une ressource neuve est `private` ; créer un lien sur une
  ressource `private` **lève une exception** ; sur une ressource `link_allowed`,
  ça passe ; un fichier `link_allowed` **dans un dossier `private`** est refusé
  (héritage, le plus restrictif gagne) ; repasser en `private` **révoque les liens
  existants**.
- GREEN : constantes + champ `visibility` sur `File`/`Folder`/`Album`,
  `VisibilityChecker` (SRP — répond « cette ressource est-elle exposable ? » en
  remontant les parents), exception dédiée → 403.
- Migration : `visibility = 'private'` sur **tout l'existant** (aucune ressource
  déjà en base ne doit devenir exposable par le déploiement).

**Étape 1 — Entité + génération du token (socle, aucune route exposée)**
- RED (unitaire) : le token généré fait 64 caractères hex ; deux appels donnent
  deux tokens différents ; le token en clair n'est pas stocké ; `hash_equals`
  valide le bon token et rejette un token modifié d'un caractère ; `isActive()`
  est faux si expiré, faux si révoqué, vrai sinon.
- GREEN : `ShareLink` (+ `Uuid::v7()` dans le constructeur, règle projet),
  `ShareLinkRepository`, `ShareLinkGenerator` (SRP : génère, ne persiste pas).
- Migration via `make:migration`.

**Étape 2 — `ShareLinkAccessChecker` (SRP, symétrique de `ShareAccessChecker`)**
- RED (unitaire) : selector inconnu → null ; token invalide → null ; lien expiré
  → null ; lien révoqué → null ; lien valide → retourne le `ShareLink`.
- GREEN : le service + son interface (DIP, comme le reste de `src/Security/`).

**Étape 3 — Route publique de consultation `/p/{selector}/{token}`**
- RED (fonctionnel) : lien valide → 200 et le contenu s'affiche **sans être
  connecté** ; token faux → 404 (**pas 403** : ne pas confirmer l'existence du
  selector) ; expiré → 404 ; révoqué → 404 ; en-tête `X-Robots-Tag: noindex`
  présent ; la page n'expose **aucune** action d'écriture.
- GREEN : `PublicShareController`, template dédié (ne pas réutiliser le layout
  authentifié : pas de sidebar, pas de menu — le visiteur n'a pas de compte),
  `access_control` PUBLIC_ACCESS **dans les deux fichiers de sécurité** (B.2).

⚠️ **Fait à l'issue de l'étape 3** : le verrou `VisibilityChecker` (étape 0)
n'est **pas encore branché à la création d'un `ShareLink`**. Rien n'empêche
aujourd'hui de créer un lien sur une ressource `private` — les tests de
l'étape 3 positionnent `visibility` manuellement sur les fixtures pour
simuler un lien déjà créé, ils ne passent par aucun point de création réel.
Le verrou doit être appelé dans le service de création du lien (étape 5,
`ShareLinkFactory` ou équivalent) via `denyUnlessPubliclyShareable()`, avec
un test RED dédié qui vérifie le refus. Ne pas livrer l'étape 5 sans ce test.

**Étape 4 — Téléchargement public**
- RED : le porteur d'un lien valide télécharge le fichier partagé (ou un fichier
  du dossier partagé) ; sans lien → 403 ; **un fichier hors du périmètre du lien
  → 403** (c'est LE test qui compte : vérifier qu'on ne peut pas pivoter vers
  une autre ressource de l'owner).
- GREEN : le contrôle passe par `ShareLinkAccessChecker`, jamais par
  `ResourceAccessChecker` (qui suppose un `User`).

**Étape 5 — Création du lien depuis `ShareModal` + « Copier le lien »**
- RED (fonctionnel) : POST crée le lien et l'affiche ; CSRF invalide → 403 ;
  non-owner de la ressource → 403 ; **ressource `private` → 403** (le verrou de
  l'étape 0 doit tenir jusqu'au bout de la chaîne HTTP, pas seulement en unitaire) ;
  `expiresAt` absent → défaut 7 jours ; au-delà de 30 jours → ramené à 30 (ou 400,
  à trancher).
- GREEN : étendre `ShareModal` avec un onglet/bascule « Compte » ↔ « Lien »
  (le composant est déjà paramétrable par ressource — ne pas le dupliquer),
  route web, affichage du lien + bouton copier.
- UI : sur une ressource `private`, l'onglet « Lien » **affiche pourquoi c'est
  bloqué** et propose l'opt-in explicite (« Autoriser le partage par lien pour
  cette ressource »), plutôt que de masquer le bouton sans explication.

**Étape 6 — Révocation + page `/partages`**
- RED : l'owner révoque → le lien renvoie 404 à la requête suivante ; les liens
  apparaissent dans `/partages` avec leur date d'expiration et un bouton
  « Révoquer » ; **supprimer la ressource supprime ses liens** (cf. B.2) ;
  **repasser la ressource en `private` révoque tous ses liens actifs** — c'est le
  bouton d'arrêt d'urgence, il doit être testé.
- GREEN : `revokedAt`, `deleteByResource()` branché aux **5 mêmes points** que
  `Share`, section « Liens publics » dans `shares.html.twig`.

**Étape 7 — Envoi de l'email** (dépend de la config SMTP, cf. B.3)
- RED : la création avec un email déclenche l'envoi (mailer mocké en test) ;
  le mail contient le lien complet ; l'échec d'envoi ne casse pas la création
  du lien (le lien reste copiable — dégradation gracieuse).

### B.5 — Points à trancher avant de coder

1. **Le verrou `visibility`, `private` par défaut** — c'est LA décision structurante
   (cf. B.0 et B.1.a). Tout le reste en découle. À confirmer en premier.
2. **Héritage de la visibilité : le plus restrictif gagne** — un fichier
   `link_allowed` dans un dossier `private` reste non-partageable. Recommandé :
   **oui**, sinon un simple déplacement de fichier contournerait le verrou et la
   garantie ne vaudrait plus rien (cf. B.1.a).
3. **`expiresAt` obligatoire, défaut 7 j, max 30 j** — confirmer (cf. B.0).
4. **Lecture seule uniquement** — confirmer (cf. B.0).
5. **Lien copiable d'abord, email ensuite** — confirmer (cf. B.3).
6. **Un lien par ressource, ou plusieurs ?** Plusieurs liens permettent de révoquer
   un destinataire sans casser les autres. Recommandé : plusieurs, pas de contrainte
   d'unicité (contrairement à `Share`).
7. **Mot de passe optionnel sur le lien ?** C'est la seule chose qui casse
   l'équation « lien = accès » : le porteur du lien ne suffit plus. Hors v1, mais le
   modèle le permettra (colonne nullable) — à garder en tête si des documents
   sensibles doivent un jour transiter par lien.

---

## Partie C — Ce qu'il reste du chantier précédent

- **Étape 10 (non faite)** : bandeau « Partagé par {owner} · lecture seule » sur
  une ressource ouverte en tant que guest, avec masquage des actions d'écriture
  côté UI si `permission = read`. La sécurité serveur est déjà en place ; ce n'est
  que le reflet visuel. Petit chantier, indépendant du partage par lien.
