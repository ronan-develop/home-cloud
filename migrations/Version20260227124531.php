<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227124531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE files (id BINARY(16) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, path VARCHAR(1024) NOT NULL, created_at DATETIME NOT NULL, folder_id BINARY(16) NOT NULL, owner_id BINARY(16) NOT NULL, INDEX IDX_6354059162CB942 (folder_id), INDEX IDX_63540597E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE folders (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, parent_id BINARY(16) DEFAULT NULL, owner_id BINARY(16) NOT NULL, INDEX IDX_FE37D30F727ACA70 (parent_id), INDEX IDX_FE37D30F7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE files ADD CONSTRAINT FK_6354059162CB942 FOREIGN KEY (folder_id) REFERENCES folders (id)');
        $this->addSql('ALTER TABLE files ADD CONSTRAINT FK_63540597E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE folders ADD CONSTRAINT FK_FE37D30F727ACA70 FOREIGN KEY (parent_id) REFERENCES folders (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE folders ADD CONSTRAINT FK_FE37D30F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE files DROP FOREIGN KEY FK_6354059162CB942');
        $this->addSql('ALTER TABLE files DROP FOREIGN KEY FK_63540597E3C61F9');
        $this->addSql('ALTER TABLE folders DROP FOREIGN KEY FK_FE37D30F727ACA70');
        $this->addSql('ALTER TABLE folders DROP FOREIGN KEY FK_FE37D30F7E3C61F9');
        $this->addSql('DROP TABLE files');
        $this->addSql('DROP TABLE folders');
    }
}
