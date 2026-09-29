<?php

declare(strict_types=1);

namespace App\Service\Help;

/**
 * Sujets de la page d'aide utilisateur (#529), en dur — contenu statique
 * mainteneur unique (pas de multi-langue/versioning pour cette itération,
 * cf. ticket). $contentHtml passe par Twig comme du HTML brut (pas de saisie
 * utilisateur, contenu écrit par l'équipe HomeCloud — pas de risque XSS).
 */
final class HelpTopicRepository
{
    /**
     * @return list<HelpTopic>
     */
    public function findAll(): array
    {
        return [
            new HelpTopic(
                slug: 'upload',
                title: 'Importer des fichiers',
                summary: 'Glisser-déposer ou sélectionner des fichiers et des dossiers à importer.',
                contentHtml: <<<'HTML'
                    <p>Pour importer des fichiers dans HomeCloud, deux méthodes :</p>
                    <ul>
                        <li>Glissez-déposez un ou plusieurs fichiers, ou un dossier entier, directement dans l'explorateur.</li>
                        <li>Cliquez sur le bouton « + Nouveau » puis « Importer des fichiers » ou « Importer un dossier ».</li>
                    </ul>
                    <p>La sélection multiple est possible : sélectionnez plusieurs fichiers à la fois avant de les glisser.</p>
                    HTML,
            ),
            new HelpTopic(
                slug: 'albums',
                title: 'Organiser en dossiers et albums',
                summary: 'Ranger vos fichiers dans des dossiers, et vos photos dans des albums.',
                contentHtml: <<<'HTML'
                    <p>Les <strong>dossiers</strong> organisent tous vos fichiers, comme sur un ordinateur classique.</p>
                    <p>Les <strong>albums</strong> sont spécifiques aux photos et vidéos : un même média peut appartenir à plusieurs albums sans être dupliqué. Créez un album depuis la Galerie en sélectionnant des photos, puis « Ajouter à un album ».</p>
                    HTML,
            ),
            new HelpTopic(
                slug: 'partage',
                title: 'Partager des fichiers',
                summary: 'Créer un lien de partage pour un fichier ou un dossier, avec ou sans invités.',
                contentHtml: <<<'HTML'
                    <p>Pour partager un fichier ou un dossier, cliquez sur son menu d'actions puis « Partager ». Un lien est généré, que vous pouvez copier et envoyer à qui vous voulez.</p>
                    <p>Vous pouvez aussi inviter des personnes précises (onglet « Invités ») pour leur donner un accès permanent, sans dépendre d'un lien.</p>
                    HTML,
            ),
            new HelpTopic(
                slug: 'recherche',
                title: 'Rechercher un fichier',
                summary: 'Retrouver rapidement un fichier ou un dossier par son nom.',
                contentHtml: <<<'HTML'
                    <p>La barre de recherche en haut de l'écran cherche parmi tous vos fichiers et dossiers, par nom, où qu'ils soient rangés.</p>
                    HTML,
            ),
            new HelpTopic(
                slug: 'takeout',
                title: 'Importer un export Google Photos',
                summary: 'Récupérer toutes vos photos et albums Google Photos dans HomeCloud.',
                contentHtml: <<<'HTML'
                    <p>Depuis la page <a href="/import/takeout">Import Google Photos</a>, sélectionnez le ou les fichiers ZIP obtenus via <a href="https://takeout.google.com" target="_blank" rel="noopener">Google Takeout</a>.</p>
                    <p>Vos photos sont rangées dans un dossier dédié, les albums Google Photos sont recréés automatiquement, et les métadonnées (date, position) sont conservées. Les doublons déjà importés sont ignorés.</p>
                    <p>Pour un gros export, le traitement peut être différé de quelques minutes selon la charge du serveur — vous serez informé sur la page, et pouvez fermer l'onglet sans risque une fois l'envoi terminé.</p>
                    HTML,
            ),
        ];
    }

    public function find(string $slug): ?HelpTopic
    {
        foreach ($this->findAll() as $topic) {
            if ($topic->slug === $slug) {
                return $topic;
            }
        }

        return null;
    }
}
