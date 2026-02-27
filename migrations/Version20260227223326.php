<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227223326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE albums (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, owner_id BINARY(16) NOT NULL, INDEX IDX_F4E2474F7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE album_media (album_id BINARY(16) NOT NULL, media_id BINARY(16) NOT NULL, INDEX IDX_1C94EB2A1137ABCF (album_id), INDEX IDX_1C94EB2AEA9FDD75 (media_id), PRIMARY KEY (album_id, media_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE albums ADD CONSTRAINT FK_F4E2474F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE album_media ADD CONSTRAINT FK_1C94EB2A1137ABCF FOREIGN KEY (album_id) REFERENCES albums (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE album_media ADD CONSTRAINT FK_1C94EB2AEA9FDD75 FOREIGN KEY (media_id) REFERENCES medias (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE albums DROP FOREIGN KEY FK_F4E2474F7E3C61F9');
        $this->addSql('ALTER TABLE album_media DROP FOREIGN KEY FK_1C94EB2A1137ABCF');
        $this->addSql('ALTER TABLE album_media DROP FOREIGN KEY FK_1C94EB2AEA9FDD75');
        $this->addSql('DROP TABLE albums');
        $this->addSql('DROP TABLE album_media');
    }
}
