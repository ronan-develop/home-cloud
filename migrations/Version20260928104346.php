<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Progress bar pendant l'extraction des ZIP Takeout (#515).
 */
final class Version20260928104346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute total_zip_count et extracted_zip_count sur takeout_imports (#515)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE takeout_imports ADD total_zip_count INT DEFAULT NULL, ADD extracted_zip_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE takeout_imports DROP total_zip_count, DROP extracted_zip_count');
    }
}
