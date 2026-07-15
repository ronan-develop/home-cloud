<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714201730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table share_links (partage par lien public, sans compte invité).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE share_links (id BINARY(16) NOT NULL, resource_type VARCHAR(10) NOT NULL, resource_id BINARY(16) NOT NULL, selector VARCHAR(32) NOT NULL, hashed_token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, owner_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_58EC4DF59692E25D (selector), INDEX IDX_58EC4DF57E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE share_links ADD CONSTRAINT FK_58EC4DF57E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE share_links DROP FOREIGN KEY FK_58EC4DF57E3C61F9');
        $this->addSql('DROP TABLE share_links');
    }
}
