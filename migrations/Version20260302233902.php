<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration corrective : ajoute les colonnes/tables manquantes sur les serveurs
 * déployés avant la migration de consolidation (Version20260302224232).
 *
 * Utilise IF NOT EXISTS / IF EXISTS pour être idempotente.
 */
final class Version20260302233902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Correctif schéma : colonnes et tables manquantes (media_type, neutralized, medias, albums, shares)';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        // ── folders.media_type ────────────────────────────────────────────────
        if ($sm->tablesExist(['folders'])) {
            $columns = array_map(fn($c) => $c->getName(), $sm->listTableColumns('folders'));
            if (!in_array('media_type', $columns, true)) {
                $this->addSql("ALTER TABLE folders ADD media_type VARCHAR(255) NOT NULL DEFAULT 'general' COLLATE `utf8mb4_general_ci`");
            }
        }

        // ── files.neutralized ─────────────────────────────────────────────────
        if ($sm->tablesExist(['files'])) {
            $columns = array_map(fn($c) => $c->getName(), $sm->listTableColumns('files'));
            if (!in_array('neutralized', $columns, true)) {
                $this->addSql('ALTER TABLE files ADD neutralized TINYINT DEFAULT 0 NOT NULL');
            }
        }

        // ── medias ────────────────────────────────────────────────────────────
        if (!$sm->tablesExist(['medias'])) {
            $this->addSql('CREATE TABLE medias (id BINARY(16) NOT NULL, media_type VARCHAR(20) NOT NULL COLLATE `utf8mb4_general_ci`, width INT DEFAULT NULL, height INT DEFAULT NULL, taken_at DATETIME DEFAULT NULL, gps_lat NUMERIC(10, 7) DEFAULT NULL, gps_lon NUMERIC(10, 7) DEFAULT NULL, camera_model VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_general_ci`, thumbnail_path VARCHAR(1024) DEFAULT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME NOT NULL, file_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_12D2AF8193CB796C (file_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE medias ADD CONSTRAINT FK_12D2AF8193CB796C FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE');
        }

        // ── albums ────────────────────────────────────────────────────────────
        if (!$sm->tablesExist(['albums'])) {
            $this->addSql('CREATE TABLE albums (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME NOT NULL, owner_id BINARY(16) NOT NULL, INDEX IDX_F4E2474F7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE albums ADD CONSTRAINT FK_F4E2474F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        }

        // ── album_media ───────────────────────────────────────────────────────
        if (!$sm->tablesExist(['album_media'])) {
            $this->addSql('CREATE TABLE album_media (album_id BINARY(16) NOT NULL, media_id BINARY(16) NOT NULL, INDEX IDX_1C94EB2A1137ABCF (album_id), INDEX IDX_1C94EB2AEA9FDD75 (media_id), PRIMARY KEY (album_id, media_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE album_media ADD CONSTRAINT FK_1C94EB2A1137ABCF FOREIGN KEY (album_id) REFERENCES albums (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE album_media ADD CONSTRAINT FK_1C94EB2AEA9FDD75 FOREIGN KEY (media_id) REFERENCES medias (id) ON DELETE CASCADE');
        }

        // ── shares ────────────────────────────────────────────────────────────
        if (!$sm->tablesExist(['shares'])) {
            $this->addSql('CREATE TABLE shares (id BINARY(16) NOT NULL, resource_type VARCHAR(10) NOT NULL COLLATE `utf8mb4_general_ci`, resource_id BINARY(16) NOT NULL, permission VARCHAR(5) NOT NULL COLLATE `utf8mb4_general_ci`, expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, owner_id BINARY(16) NOT NULL, guest_id BINARY(16) NOT NULL, INDEX IDX_905F717C9A4AA658 (guest_id), INDEX IDX_905F717C7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE shares ADD CONSTRAINT FK_905F717C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE shares ADD CONSTRAINT FK_905F717C9A4AA658 FOREIGN KEY (guest_id) REFERENCES users (id) ON DELETE CASCADE');
        }

        // ── refresh_tokens ────────────────────────────────────────────────────
        if (!$sm->tablesExist(['refresh_tokens'])) {
            $this->addSql('CREATE TABLE refresh_tokens (id BINARY(16) NOT NULL, token VARCHAR(128) NOT NULL COLLATE `utf8mb4_general_ci`, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, user_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_9BACE7E15F37A13B (token), INDEX IDX_9BACE7E1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_9BACE7E1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        // Pas de rollback destructif sur une migration corrective
    }
}
