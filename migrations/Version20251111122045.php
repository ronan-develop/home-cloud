<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251111122045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE album_photo (album_id INT NOT NULL, photo_id INT NOT NULL, INDEX IDX_620FCE3E1137ABCF (album_id), INDEX IDX_620FCE3E7E9E4C8C (photo_id), PRIMARY KEY(album_id, photo_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE album_photo ADD CONSTRAINT FK_620FCE3E1137ABCF FOREIGN KEY (album_id) REFERENCES album (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE album_photo ADD CONSTRAINT FK_620FCE3E7E9E4C8C FOREIGN KEY (photo_id) REFERENCES photo (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784181137ABCF');
        $this->addSql('DROP INDEX IDX_14B784181137ABCF ON photo');
        $this->addSql('ALTER TABLE photo DROP album_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE album_photo DROP FOREIGN KEY FK_620FCE3E1137ABCF');
        $this->addSql('ALTER TABLE album_photo DROP FOREIGN KEY FK_620FCE3E7E9E4C8C');
        $this->addSql('DROP TABLE album_photo');
        $this->addSql('ALTER TABLE photo ADD album_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784181137ABCF FOREIGN KEY (album_id) REFERENCES album (id)');
        $this->addSql('CREATE INDEX IDX_14B784181137ABCF ON photo (album_id)');
    }
}
