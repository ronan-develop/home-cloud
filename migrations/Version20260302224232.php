<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration de consolidation complète du schéma HomeCloud.
 *
 * Idempotente : si les tables existent déjà (serveur avec déploiement antérieur),
 * la migration est sautée silencieusement pour éviter les erreurs "Table exists".
 */
final class Version20260302224232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma complet HomeCloud (migration de consolidation)';
    }

    public function up(Schema $schema): void
    {
        // Idempotence : si users existe déjà, le schéma est déjà en place
        if ($this->connection->createSchemaManager()->tablesExist(['users'])) {
            $this->write('  <info>Schéma déjà présent — migration ignorée.</info>');
            return;
        }

        $this->addSql('CREATE TABLE users (id BINARY(16) NOT NULL, email VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, display_name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, password VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE folders (id BINARY(16) NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME NOT NULL, parent_id BINARY(16) DEFAULT NULL, owner_id BINARY(16) NOT NULL, media_type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'general\' NOT NULL COLLATE `utf8mb4_general_ci`, INDEX IDX_FE37D30F7E3C61F9 (owner_id), INDEX IDX_FE37D30F727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE folders ADD CONSTRAINT `FK_FE37D30F727ACA70` FOREIGN KEY (parent_id) REFERENCES folders (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE folders ADD CONSTRAINT `FK_FE37D30F7E3C61F9` FOREIGN KEY (owner_id) REFERENCES users (id)');

        $this->addSql('CREATE TABLE files (id BINARY(16) NOT NULL, original_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, mime_type VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, size INT NOT NULL, path VARCHAR(1024) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME NOT NULL, folder_id BINARY(16) NOT NULL, owner_id BINARY(16) NOT NULL, neutralized TINYINT DEFAULT 0 NOT NULL, INDEX IDX_6354059162CB942 (folder_id), INDEX IDX_63540597E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE files ADD CONSTRAINT `FK_6354059162CB942` FOREIGN KEY (folder_id) REFERENCES folders (id)');
        $this->addSql('ALTER TABLE files ADD CONSTRAINT `FK_63540597E3C61F9` FOREIGN KEY (owner_id) REFERENCES users (id)');

        $this->addSql('CREATE TABLE medias (id BINARY(16) NOT NULL, media_type VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, width INT DEFAULT NULL, height INT DEFAULT NULL, taken_at DATETIME DEFAULT NULL, gps_lat NUMERIC(10, 7) DEFAULT NULL, gps_lon NUMERIC(10, 7) DEFAULT NULL, camera_model VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, thumbnail_path VARCHAR(1024) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME NOT NULL, file_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_12D2AF8193CB796C (file_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE medias ADD CONSTRAINT `FK_12D2AF8193CB796C` FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE albums (id BINARY(16) NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME NOT NULL, owner_id BINARY(16) NOT NULL, INDEX IDX_F4E2474F7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE albums ADD CONSTRAINT `FK_F4E2474F7E3C61F9` FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE album_media (album_id BINARY(16) NOT NULL, media_id BINARY(16) NOT NULL, INDEX IDX_1C94EB2A1137ABCF (album_id), INDEX IDX_1C94EB2AEA9FDD75 (media_id), PRIMARY KEY (album_id, media_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE album_media ADD CONSTRAINT `FK_1C94EB2A1137ABCF` FOREIGN KEY (album_id) REFERENCES albums (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE album_media ADD CONSTRAINT `FK_1C94EB2AEA9FDD75` FOREIGN KEY (media_id) REFERENCES medias (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE shares (id BINARY(16) NOT NULL, resource_type VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, resource_id BINARY(16) NOT NULL, permission VARCHAR(5) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, owner_id BINARY(16) NOT NULL, guest_id BINARY(16) NOT NULL, INDEX IDX_905F717C9A4AA658 (guest_id), INDEX IDX_905F717C7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shares ADD CONSTRAINT `FK_905F717C7E3C61F9` FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shares ADD CONSTRAINT `FK_905F717C9A4AA658` FOREIGN KEY (guest_id) REFERENCES users (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE refresh_tokens (id BINARY(16) NOT NULL, token VARCHAR(128) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, user_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_9BACE7E15F37A13B (token), INDEX IDX_9BACE7E1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT `FK_9BACE7E1A76ED395` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, hashed_token VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id BINARY(16) NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT `FK_7CE748AA76ED395` FOREIGN KEY (user_id) REFERENCES users (id)');

        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, headers LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, queue_name VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `album_media`');
        $this->addSql('DROP TABLE IF EXISTS `albums`');
        $this->addSql('DROP TABLE IF EXISTS `medias`');
        $this->addSql('DROP TABLE IF EXISTS `files`');
        $this->addSql('DROP TABLE IF EXISTS `shares`');
        $this->addSql('DROP TABLE IF EXISTS `refresh_tokens`');
        $this->addSql('DROP TABLE IF EXISTS `reset_password_request`');
        $this->addSql('DROP TABLE IF EXISTS `messenger_messages`');
        $this->addSql('DROP TABLE IF EXISTS `folders`');
        $this->addSql('DROP TABLE IF EXISTS `users`');
    }
}
