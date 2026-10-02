<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002092640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'takeout_imports.scheduled_at : date de passage en scheduled (#528)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE takeout_imports ADD scheduled_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE takeout_imports DROP scheduled_at');
    }
}
