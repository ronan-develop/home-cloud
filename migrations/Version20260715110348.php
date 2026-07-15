<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715110348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute cover_media_id sur albums — couverture explicite choisie par l\'utilisateur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE albums ADD cover_media_id BINARY(16) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              albums
            ADD
              CONSTRAINT FK_F4E2474F329A1B2E FOREIGN KEY (cover_media_id) REFERENCES medias (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('CREATE INDEX IDX_F4E2474F329A1B2E ON albums (cover_media_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE albums DROP FOREIGN KEY FK_F4E2474F329A1B2E');
        $this->addSql('DROP INDEX IDX_F4E2474F329A1B2E ON albums');
        $this->addSql('ALTER TABLE albums DROP cover_media_id');
    }
}
