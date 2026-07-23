<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723114759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#246 — Media::$file devient nullable (détachement) ; ajout de Media::$owner pour ne plus dépendre de File::$owner comme source d\'autorité.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medias DROP FOREIGN KEY `FK_12D2AF8193CB796C`');
        $this->addSql('ALTER TABLE medias ADD owner_id BINARY(16) DEFAULT NULL, CHANGE file_id file_id BINARY(16) DEFAULT NULL');
        // Backfill : owner_id hérite du propriétaire du File tant qu'il existe encore (aucun Media n'est détaché à ce stade).
        $this->addSql('UPDATE medias m INNER JOIN files f ON m.file_id = f.id SET m.owner_id = f.owner_id');
        $this->addSql('ALTER TABLE medias CHANGE owner_id owner_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE medias ADD CONSTRAINT FK_12D2AF8193CB796C FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE medias ADD CONSTRAINT FK_12D2AF817E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_12D2AF817E3C61F9 ON medias (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // Irréversible pour les Media détachés (file_id NULL) : ils perdent
        // leur seule ancre vers un File au moment de repasser en NOT NULL.
        $this->addSql('ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF8193CB796C');
        $this->addSql('ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF817E3C61F9');
        $this->addSql('DROP INDEX IDX_12D2AF817E3C61F9 ON medias');
        $this->addSql('ALTER TABLE medias DROP owner_id, CHANGE file_id file_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE medias ADD CONSTRAINT `FK_12D2AF8193CB796C` FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE');
    }
}
