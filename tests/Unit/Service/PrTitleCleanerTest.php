<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PrTitleCleaner;
use PHPUnit\Framework\TestCase;

/**
 * Extrait de GitHubChangelogFetcher (SRP) : le nettoyage de titre de PR est une
 * responsabilité indépendante, pas celle du fetcher. Corrige au passage un bug
 * où l'emoji ne disparaissait pas sur certains titres réels de l'historique du
 * dépôt.
 */
final class PrTitleCleanerTest extends TestCase
{
    public function testStripsEmojiAndConventionalPrefix(): void
    {
        $result = (new PrTitleCleaner())->clean(
            "✨ feat(FolderZipArchiver): téléchargement d'un dossier entier en zip",
        );

        $this->assertSame("Téléchargement d'un dossier entier en zip", $result);
    }

    /**
     * Bug réel : un emoji suivi d'un variation selector (U+FE0F, ex: le
     * bouclier "🛡️" = U+1F6E1 + U+FE0F) n'était pas reconnu par l'ancienne
     * regex, qui n'autorisait qu'un seul point de code \p{So} avant l'espace —
     * le "️" bloquait le match entier et l'emoji restait affiché.
     */
    public function testStripsEmojiFollowedByVariationSelector(): void
    {
        $result = (new PrTitleCleaner())->clean(
            "🛡️ fix(security): signature FileVoter::voteOnAttribute conforme à l'API",
        );

        $this->assertSame("Signature FileVoter::voteOnAttribute conforme à l'API", $result);
    }

    /**
     * Bug réel : un titre avec emoji mais sans préfixe conventionnel
     * "type(scope): " (pas de deux-points) laissait l'emoji intact, car
     * l'ancienne regex ne matchait l'emoji QUE si tout le motif complet
     * (type + scope + deux-points) suivait aussi.
     */
    public function testStripsEmojiEvenWithoutConventionalPrefix(): void
    {
        $result = (new PrTitleCleaner())->clean('✅ Feature Move folder/File');

        $this->assertSame('Feature Move folder/File', $result);
    }

    /**
     * Bug réel : un titre en prose avec deux-points plus loin dans la phrase
     * (pas un préfixe "type:") ne devait pas être tronqué au premier mot, mais
     * l'emoji en tête devait quand même disparaître.
     */
    public function testStripsEmojiFromProseTitleWithUnrelatedColon(): void
    {
        $result = (new PrTitleCleaner())->clean('✨ Fonctionnalité principale : Gestion des photos');

        $this->assertSame('Fonctionnalité principale : Gestion des photos', $result);
    }

    public function testLeavesTitleWithoutEmojiOrPrefixUnchanged(): void
    {
        $result = (new PrTitleCleaner())->clean(
            'Servir la preview JPEG des RAW en plein écran (lightbox, diaporama, partages)',
        );

        $this->assertSame(
            'Servir la preview JPEG des RAW en plein écran (lightbox, diaporama, partages)',
            $result,
        );
    }

    public function testStripsConventionalPrefixWithoutEmoji(): void
    {
        $result = (new PrTitleCleaner())->clean('fix(dashboard): palette, layout et redirection post-login');

        $this->assertSame('Palette, layout et redirection post-login', $result);
    }

    /**
     * Bug réel (retour utilisateur sur le rendu du changelog) : un "#" de titre
     * markdown collé par erreur en tête du titre de PR (ex: "# 🏷️ PR : ...")
     * bloquait la détection de l'emoji qui suivait, puisque le "#" n'est ni un
     * symbole \p{So} ni un caractère blanc.
     */
    /**
     * "PR :" est ici traité comme un préfixe conventionnel générique
     * ("type:"), au même titre que "fix:"/"feat:" — résultat plus propre
     * pour l'utilisateur final qu'un "PR :" redondant conservé tel quel.
     */
    public function testStripsLeadingMarkdownHeadingMarkerBeforeEmoji(): void
    {
        $result = (new PrTitleCleaner())->clean('# 🏷️ PR : Move Folder/File UI — Sidebar + Modal');

        $this->assertSame('Move Folder/File UI — Sidebar + Modal', $result);
    }

    public function testStripsLeadingMarkdownHeadingMarkerBeforeEmojiAndProseColon(): void
    {
        $result = (new PrTitleCleaner())->clean('# ✨ Fonctionnalité principale : Gestion des photos');

        $this->assertSame('Fonctionnalité principale : Gestion des photos', $result);
    }
}
