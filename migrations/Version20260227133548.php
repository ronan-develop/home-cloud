<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227133548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create medias table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE medias (id BINARY(16) NOT NULL, file_id BINARY(16) NOT NULL, media_type VARCHAR(20) NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, taken_at DATETIME DEFAULT NULL, gps_lat NUMERIC(10, 7) DEFAULT NULL, gps_lon NUMERIC(10, 7) DEFAULT NULL, camera_model VARCHAR(255) DEFAULT NULL, thumbnail_path VARCHAR(1024) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_12D2AF8193CB796C (file_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE medias ADD CONSTRAINT FK_12D2AF8193CB796C FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medias DROP FOREIGN KEY FK_12D2AF8193CB796C');
        $this->addSql('DROP TABLE medias');
    }
}
