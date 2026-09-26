<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Progress bar (#327) : ajoute le suivi de progression sur takeout_imports —
 * total_media_count (connu après le parsing) et processed_count (incrémenté
 * au fil du Handler), exposés via GET /v1/takeout-imports/{id} pour que le
 * front puisse afficher une barre d'avancement pendant le traitement async.
 */
final class Version20260926215944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute total_media_count et processed_count sur takeout_imports (progress bar #327)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE takeout_imports ADD total_media_count INT DEFAULT NULL, ADD processed_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE takeout_imports DROP total_media_count, DROP processed_count');
    }
}
