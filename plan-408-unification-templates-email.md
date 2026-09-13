# Plan — #408 : Unifier les templates email (layout partagé, identité visuelle)

## 1. Contexte vérifié

6 templates HTML existent aujourd'hui (5 cités dans le ticket + `deploy_report.html.twig` ajouté hors ticket lors de #421) :

| Template | Lignes | Envoyé par | Contexte Twig |
|---|---|---|---|
| `emails/share_notification.html.twig` | 73 | `ShareNotificationMailer` | `ownerName`, `resourceName`, `accessUrl`, `accentColor` |
| `emails/batch_ready.html.twig` | 76 | `BatchCompletionNotifier` | `ownerName`, `count`, `galleryUrl`, `accentColor` |
| `emails/broadcast_message.html.twig` | 57 | `BroadcastMailer` | `subject`, `body` (raw), `accentColor` |
| `reset_password/guest_invitation_email.html.twig` | 73 | `GuestAccountCreator` | `ownerName` (nullable), `activationUrl`, `accentColor` |
| `reset_password/reset_request_email.html.twig` | **13** | `PasswordResetInitiator` | `resetUrl` — **PAS d'`accentColor`** |
| `emails/deploy_report.html.twig` | 129 | `DeployNotificationMailer` | `results`, `accentColor` |

**Constat** : les 5 templates hors `reset_request_email` partagent déjà ~90% du même HTML — squelette table/table imbriqué, header "HomeCloud" en texte coloré, footer identique mot pour mot ("HomeCloud — votre cloud personnel / Solution entièrement développée par Ronan Lenouvel"). Seul `reset_request_email.html.twig` fait exception : il `extends 'base.html.twig'` (le layout **web**, pas un layout email dédié), ce qui explique ses 13 lignes — il n'a jamais reçu d'habillage email et **s'affiche donc sans aucun style inline dans une boîte mail**.

**Couleur d'accent** : déjà centralisée dans `App\Service\EmailBranding::ACCENT_COLOR` (`#A34B4B`), avec un commentaire explicite « deviendra personnalisable par utilisateur plus tard » — recoupe le ticket #409 (couleur choisie par l'utilisateur). Aucune modification requise ici, juste à réutiliser.

**Tests existants** : un seul test touche le rendu (`BatchCompletionNotifierTest`), et il ne vérifie que `getHtmlTemplate()` (le nom du fichier) + le destinataire — **aucun test ne vérifie le HTML rendu**. Refactorer la structure interne ne casse donc aucun test tant que les noms de fichiers et les clés de contexte restent stables.

## 2. Décision de périmètre (validée avec l'utilisateur)

**Scindé en deux, ce plan couvre uniquement la partie 1** :

1. **Ce ticket (#408)** : layout Twig partagé + bandeau/identité renforcés + mise à niveau de `reset_request_email` — **sans logo image**, uniquement typographie/couleur/structure.
2. **Itération future, hors périmètre** : ajout d'un vrai logo graphique (asset à produire au préalable — pas dans ce ticket).

`deploy_report.html.twig` (#421, rapport interne à l'auteur, pas un email "utilisateur final") **est inclus dans la migration vers le layout partagé** pour la cohérence technique (moins de duplication), mais son bandeau reste sobre — pas la cible principale de "l'identité de marque forte".

## 3. Architecture retenue

### 3.1 Un seul layout Twig, composé par blocs

Créer `templates/emails/_layout.html.twig` :

```twig
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{% block title %}HomeCloud{% endblock %}</title>
</head>
<body style="margin:0; padding:0; background-color:#FAFAFA; font-family:Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FAFAFA;">
<tr>
<td align="center" style="padding:32px 16px;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:{% block maxWidth %}480{% endblock %}px; background-color:#FFFFFF; border-radius:12px; border:1px solid #e5e5e5; overflow:hidden;">

{# Bandeau renforcé : fond plein accentColor au lieu du texte coloré seul #}
<tr>
<td align="center" style="padding:24px 32px; background-color:{{ accentColor }};">
<span style="font-size:22px; font-weight:bold; color:#FFFFFF; letter-spacing:0.02em;">☁ HomeCloud</span>
</td>
</tr>

{% block content %}{% endblock %}

<tr>
<td style="padding:0 32px 28px 32px; border-top:1px solid #e5e5e5; padding-top:20px;">
<span style="font-size:12px; color:#8a8f98; line-height:1.5;">
{% block footerNote %}{% endblock %}
</span>
</td>
</tr>

</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:{% block maxWidth2 %}480{% endblock %}px;">
<tr>
<td align="center" style="padding:16px 32px; color:#8a8f98; font-size:12px;">
HomeCloud — votre cloud personnel
<br>
Solution entièrement développée par Ronan Lenouvel
</td>
</tr>
</table>

</td>
</tr>
</table>
</body>
</html>
```

**Point d'attention (piège Twig connu)** : `maxWidth` doit être répété dans un second bloc (`maxWidth2`) car Twig n'autorise pas de réutiliser deux fois le même `{% block %}` dans un template parent sans un `{{ block('maxWidth') }}` explicite — préférer cette seconde syntaxe (`{{ block('maxWidth') }}`) plutôt que dupliquer le nom du bloc, pour rester DRY :

```twig
style="max-width:{{ block('maxWidth') }}px; ...">
...
style="max-width:{{ block('maxWidth') }}px;">
```

**Changement visuel du bandeau** : passage d'un simple texte coloré sur fond blanc à un vrai bandeau plein `accentColor` avec texte blanc — c'est le changement concret qui répond à "identité immédiatement reconnaissable dès l'ouverture" sans nécessiter de logo image. Un caractère Unicode `☁` en guise d'icône minimaliste (rendu fiable dans tous les clients mail, contrairement à une image externe souvent bloquée par défaut).

### 3.2 Chaque template hérite et ne fournit que son contenu propre

Exemple pour `share_notification.html.twig` après migration :

```twig
{% extends 'emails/_layout.html.twig' %}

{% block title %}Nouveau partage HomeCloud{% endblock %}

{% block content %}
<tr>
<td style="padding:24px 32px 8px 32px; color:#0b1220; font-size:15px; line-height:1.5;">
Bonjour,
</td>
</tr>
<tr>
<td style="padding:0 32px 24px 32px; color:#0b1220; font-size:15px; line-height:1.5;">
<strong>{{ ownerName }}</strong> a partagé une ressource avec vous sur HomeCloud&nbsp;:
<br>
<span style="font-size:17px; font-weight:bold;">{{ resourceName }}</span>
</td>
</tr>
<tr>
<td align="center" style="padding:0 32px 32px 32px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
<tr>
<td align="center" bgcolor="{{ accentColor }}" style="border-radius:8px;">
<a href="{{ accessUrl }}" class="hc-email-cta-button" style="display:inline-block; padding:12px 28px; font-size:15px; font-weight:bold; color:#FFFFFF; text-decoration:none; border-radius:8px;">
Accéder à « {{ resourceName }} »
</a>
</td>
</tr>
</table>
</td>
</tr>
{% endblock %}

{% block footerNote %}
Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur&nbsp;: {{ accessUrl }}
{% endblock %}
```

Réduction attendue : ~73 lignes → ~25 lignes par template migré (le squelette table/header/footer disparaît, ne reste que le contenu spécifique).

### 3.3 Cas particulier — `reset_request_email.html.twig`

Aujourd'hui `extends 'base.html.twig'` (layout **web** de l'application, pas un layout email — d'où l'absence totale de style inline et son aspect visuel probablement cassé/nu dans une vraie boîte mail, à vérifier en §6). À migrer vers `emails/_layout.html.twig` comme les autres :

```twig
{% extends 'emails/_layout.html.twig' %}

{% block title %}Réinitialisation de votre mot de passe{% endblock %}

{% block content %}
<tr>
<td style="padding:24px 32px 8px 32px; color:#0b1220; font-size:15px; line-height:1.5;">
Bonjour,
</td>
</tr>
<tr>
<td style="padding:0 32px 24px 32px; color:#0b1220; font-size:15px; line-height:1.5;">
Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.
</td>
</tr>
<tr>
<td align="center" style="padding:0 32px 32px 32px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
<tr>
<td align="center" bgcolor="{{ accentColor }}" style="border-radius:8px;">
<a href="{{ resetUrl }}" style="display:inline-block; padding:12px 28px; font-size:15px; font-weight:bold; color:#FFFFFF; text-decoration:none; border-radius:8px;">
Choisir un nouveau mot de passe
</a>
</td>
</tr>
</table>
</td>
</tr>
{% endblock %}

{% block footerNote %}
Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.
<br>
Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur&nbsp;: {{ resetUrl }}
{% endblock %}
```

**Changement backend requis** : `PasswordResetInitiator::sendResetLink()` (src/Service/PasswordResetInitiator.php:43) ne passe actuellement **aucun `accentColor`** dans son contexte — à ajouter :

```php
->context([
    'resetUrl' => $resetUrl,
    'accentColor' => EmailBranding::ACCENT_COLOR,
]);
```

C'est le seul changement PHP nécessaire dans tout ce ticket (les 4 autres mailers passent déjà `accentColor`).

### 3.4 `broadcast_message.html.twig` — cas du contenu `raw`

Ce template affiche `{{ body|raw }}` (contenu HTML libre saisi par l'admin) directement dans le bloc contenu — à conserver tel quel dans le nouveau `{% block content %}`, aucun changement de logique, seulement le déplacement dans le layout partagé.

### 3.5 `deploy_report.html.twig` — cas du bandeau conditionnel

Ce template (créé lors de #421) a déjà un bandeau d'alerte **conditionnel** propre à son usage (rouge, visible seulement s'il y a un échec) — à conserver intégralement comme contenu spécifique du `{% block content %}`, indépendant du bandeau d'en-tête générique du layout. Les deux bandeaux coexistent (en-tête HomeCloud + alerte conditionnelle en dessous), pas de conflit.

## 4. Ce qui NE change PAS

- Les noms de fichiers de template restent identiques (`emails/share_notification.html.twig`, etc.) — **aucun changement dans les appels `->htmlTemplate(...)` des mailers**, sauf l'ajout d'`accentColor` dans `PasswordResetInitiator`.
- Aucune nouvelle entité, aucune migration Doctrine.
- `EmailBranding::ACCENT_COLOR` reste la seule source de couleur — pas de nouveau service.
- Aucun logo image dans ce ticket (voir §2).

## 5. Étapes TDD

Comme constaté en §1, le seul test existant qui touche ces templates (`BatchCompletionNotifierTest`) ne vérifie que le nom du fichier — pas de RED à écrire pour la migration de structure elle-même (refactoring pur, comportement identique). En revanche, un vrai gap TDD à combler :

**Étape 1 — RED : test manquant sur `PasswordResetInitiator`**
Aucun test n'existe aujourd'hui pour `PasswordResetInitiator::sendResetLink()`. Écrire `tests/Service/PasswordResetInitiatorTest.php` avant la migration, vérifiant :
- Le contexte passé au `TemplatedEmail` contient bien `accentColor` (actuellement absent → test RED confirmé avant le fix)
- Le template utilisé reste `reset_password/reset_request_email.html.twig`

**Étape 2 — GREEN : ajouter `accentColor` au contexte** (§3.3), confirmer le test passe.

**Étape 3 — Migration des 6 templates** (pas de RED/GREEN unitaire possible sur du HTML pur sans introduire un test de rendu Twig dédié — voir option en §5.1). Ordre recommandé, du plus simple au plus risqué :
1. `emails/broadcast_message.html.twig` (le plus court, contenu `raw` simple)
2. `emails/share_notification.html.twig`
3. `emails/batch_ready.html.twig`
4. `reset_password/guest_invitation_email.html.twig`
5. `reset_password/reset_request_email.html.twig` (le seul avec changement PHP, §3.3)
6. `emails/deploy_report.html.twig` (le plus complexe, bandeau conditionnel à préserver)

### 5.1 Option — test de rendu Twig (recommandé, léger)

Pour sécuriser la migration sans dépendre uniquement d'une relecture visuelle manuelle, ajouter un test simple par template migré vérifiant que le rendu contient des marqueurs clés (pas un test snapshot complet, juste des assertions ciblées) :

```php
// tests/Unit/Templates/ShareNotificationEmailRenderTest.php
public function testRendersAccessUrlAndResourceName(): void
{
    $html = $this->twig->render('emails/share_notification.html.twig', [
        'ownerName' => 'Alice', 'resourceName' => 'Vacances.zip',
        'accessUrl' => 'https://x/y', 'accentColor' => '#A34B4B',
    ]);
    self::assertStringContainsString('Vacances.zip', $html);
    self::assertStringContainsString('https://x/y', $html);
    self::assertStringContainsString('#A34B4B', $html); // bandeau utilise bien accentColor
}
```

Un test de ce type par template migré (6 tests courts) donne un vrai filet RED→GREEN pour la migration : RED avant migration si le test est écrit contre le layout *cible* (le contenu ne change pas, donc en pratique GREEN avant et après si la migration ne casse rien — sert surtout de garde-fou de non-régression future). Optionnel mais recommandé vu l'absence totale de couverture actuelle sur le rendu HTML.

## 6. Vérification manuelle obligatoire (pas automatisable)

Le ticket le mentionne explicitement : le rendu doit rester compatible Gmail/Outlook/Apple Mail. Aucun test automatisé ne peut garantir ça (chaque client mail a son propre moteur de rendu HTML, souvent très restrictif). Après la migration :

1. Envoyer un exemplaire réel de chaque template migré (via `bin/console mailer:test` adapté, ou un envoi direct de test) vers une adresse Gmail réelle — vérifier que le bandeau plein `accentColor` s'affiche correctement (pas de `<style>` externe utilisé, tout est inline — déjà le cas dans l'existant, à ne pas régresser).
2. Vérifier spécifiquement `reset_request_email.html.twig` : c'est le seul qui change de nature complètement (base.html.twig → layout email) — comparer visuellement avant/après.
3. Vérifier `deploy_report.html.twig` dans les deux scénarios (jour calme / jour avec échec, déjà validés lors de #421) pour confirmer que le nouveau bandeau générique ne perturbe pas le bandeau d'alerte conditionnel existant.

## 7. Livrables (checklist PR)

- [ ] `templates/emails/_layout.html.twig` créé
- [ ] `tests/Service/PasswordResetInitiatorTest.php` créé (RED→GREEN sur `accentColor` manquant)
- [ ] `src/Service/PasswordResetInitiator.php` : ajout `accentColor` au contexte
- [ ] 6 templates migrés vers `{% extends 'emails/_layout.html.twig' %}` (liste §5, étape 3)
- [ ] (optionnel recommandé) 6 tests de rendu ciblés, §5.1
- [ ] Suite PHPUnit complète verte
- [ ] Vérification manuelle multi-clients mail (§6) — au moins Gmail, à documenter dans la description de la PR
- [ ] `.github/avancement.md` mis à jour
- [ ] PR avec label `frontend`, `Closes #408`

## 8. Hors périmètre (rappel)

- Logo image — asset graphique à produire, itération future
- Personnalisation de la couleur par utilisateur (#409) — `EmailBranding::ACCENT_COLOR` reste statique ici, l'architecture en bloc Twig le permettra facilement plus tard sans nouveau refactoring
