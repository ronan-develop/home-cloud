<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227121101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE folders (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, parent_id BINARY(16) DEFAULT NULL, owner_id BINARY(16) NOT NULL, INDEX IDX_FE37D30F727ACA70 (parent_id), INDEX IDX_FE37D30F7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE folders ADD CONSTRAINT FK_FE37D30F727ACA70 FOREIGN KEY (parent_id) REFERENCES folders (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE folders ADD CONSTRAINT FK_FE37D30F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE folders DROP FOREIGN KEY FK_FE37D30F727ACA70');
        $this->addSql('ALTER TABLE folders DROP FOREIGN KEY FK_FE37D30F7E3C61F9');
        $this->addSql('DROP TABLE folders');
    }
}
