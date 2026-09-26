<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260926183234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute takeout_imports (#327 — suivi d\'un import Google Photos Takeout)';
    }

    public function up(Schema $schema): void
    {
        // Note : make:migration détecte ici aussi content_fingerprints (déjà
        // migré sur main via #449) et le même drift preexistant sans rapport
        // (broadcast_messages / last_broadcast_seen_at) — non repris, cf.
        // Version20260926172610.
        $this->addSql('CREATE TABLE takeout_imports (id BINARY(16) NOT NULL, status VARCHAR(16) NOT NULL, media_imported_count INT DEFAULT NULL, duplicates_skipped_count INT DEFAULT NULL, unrecognized_files_count INT DEFAULT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, owner_id BINARY(16) NOT NULL, INDEX IDX_416C1617E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE takeout_imports ADD CONSTRAINT FK_416C1617E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE takeout_imports DROP FOREIGN KEY FK_416C1617E3C61F9');
        $this->addSql('DROP TABLE takeout_imports');
    }
}
