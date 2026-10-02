<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260926172610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute content_fingerprints (#327 — détection de doublons par hash persistant, indépendant du cycle de vie File/Media)';
    }

    public function up(Schema $schema): void
    {
        // Note : make:migration détectait aussi des DROP sans rapport avec ce
        // ticket (broadcast_messages / last_broadcast_seen_at). Audit #571 : ils
        // viennent uniquement de la base de dev locale — aucune migration ni
        // entité ne les a jamais créés, et ils n'existent pas en prod. Non repris.
        $this->addSql('CREATE TABLE content_fingerprints (id BINARY(16) NOT NULL, content_hash VARCHAR(64) NOT NULL, first_seen_at DATETIME NOT NULL, owner_id BINARY(16) NOT NULL, INDEX IDX_8973604F7E3C61F9 (owner_id), INDEX idx_content_fingerprints_hash (content_hash), UNIQUE INDEX uniq_owner_content_hash (owner_id, content_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE content_fingerprints ADD CONSTRAINT FK_8973604F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_fingerprints DROP FOREIGN KEY FK_8973604F7E3C61F9');
        $this->addSql('DROP TABLE content_fingerprints');
    }
}
