<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227225533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shares table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shares (id BINARY(16) NOT NULL, resource_type VARCHAR(10) NOT NULL, resource_id BINARY(16) NOT NULL, permission VARCHAR(5) NOT NULL, expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', owner_id BINARY(16) NOT NULL, guest_id BINARY(16) NOT NULL, INDEX IDX_905F717C7E3C61F9 (owner_id), INDEX IDX_905F717C9A4AA658 (guest_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE shares ADD CONSTRAINT FK_905F717C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shares ADD CONSTRAINT FK_905F717C9A4AA658 FOREIGN KEY (guest_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shares DROP FOREIGN KEY FK_905F717C7E3C61F9');
        $this->addSql('ALTER TABLE shares DROP FOREIGN KEY FK_905F717C9A4AA658');
        $this->addSql('DROP TABLE shares');
    }
}
