<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\PrTitleCleanerInterface;

/**
 * Nettoie un titre de PR GitHub pour un affichage lisible côté utilisateur
 * final (ex: "✨ feat(FolderZipArchiver): télécharger un dossier en zip" →
 * "Télécharger un dossier en zip").
 *
 * Deux étapes indépendantes, appliquées séparément :
 * - retrait de l'emoji en tête (avec son éventuel variation selector U+FE0F,
 *   ex: "🛡️" = U+1F6E1 + U+FE0F — sans lui, l'emoji reste accolé au texte)
 * - retrait du préfixe conventionnel "type(scope): " s'il est présent
 *
 * Les traiter indépendamment évite qu'un titre sans préfixe conventionnel
 * (ex: "✅ Feature Move folder/File", sans deux-points) ne conserve son emoji
 * simplement parce que le motif complet ne correspondait pas.
 */
final class PrTitleCleaner implements PrTitleCleanerInterface
{
    public function clean(string $title): string
    {
        $withoutEmoji = $this->stripLeadingEmoji(trim($title));
        $withoutPrefix = $this->stripConventionalPrefix($withoutEmoji);

        $cleaned = trim($withoutPrefix);
        if ($cleaned === '') {
            return trim($title);
        }

        return mb_strtoupper(mb_substr($cleaned, 0, 1)) . mb_substr($cleaned, 1);
    }

    private function stripLeadingEmoji(string $title): string
    {
        return preg_replace('/^[\p{So}\x{FE0F}\x{200D}]+\s*/u', '', $title) ?? $title;
    }

    private function stripConventionalPrefix(string $title): string
    {
        return preg_replace(
            '/^[a-zA-Zàâäéèêëïîôöùûüç]+(\([^)]*\))?\s*:\s*/u',
            '',
            $title,
        ) ?? $title;
    }
}
