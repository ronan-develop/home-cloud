<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251106162218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file ADD owner_id INT NOT NULL, ADD hash VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F36107E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_8C9F36107E3C61F9 ON file (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F36107E3C61F9');
        $this->addSql('DROP INDEX IDX_8C9F36107E3C61F9 ON file');
        $this->addSql('ALTER TABLE file DROP owner_id, DROP hash');
    }
}
