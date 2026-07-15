<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714200946 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le verrou visibility (private par défaut) sur albums, files, folders — partage par lien.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE albums ADD visibility VARCHAR(12) DEFAULT \'private\' NOT NULL');
        $this->addSql('ALTER TABLE files ADD visibility VARCHAR(12) DEFAULT \'private\' NOT NULL');
        $this->addSql('ALTER TABLE folders ADD visibility VARCHAR(12) DEFAULT \'private\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE albums DROP visibility');
        $this->addSql('ALTER TABLE files DROP visibility');
        $this->addSql('ALTER TABLE folders DROP visibility');
    }
}
