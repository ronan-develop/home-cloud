<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719170154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table upload_batches (lots d\'upload) + files.batch_id nullable pour le routage immediate/deferred du traitement média (#259)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE upload_batches (id BINARY(16) NOT NULL, expected_count INT NOT NULL, total_size BIGINT NOT NULL, mode VARCHAR(16) NOT NULL, status VARCHAR(16) DEFAULT \'pending\' NOT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, notified_at DATETIME DEFAULT NULL, owner_id BINARY(16) NOT NULL, INDEX IDX_35597FBD7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE upload_batches ADD CONSTRAINT FK_35597FBD7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE files ADD batch_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE files ADD CONSTRAINT FK_6354059F39EBE7A FOREIGN KEY (batch_id) REFERENCES upload_batches (id)');
        $this->addSql('CREATE INDEX IDX_6354059F39EBE7A ON files (batch_id)');
    }

    public function down(Schema $schema): void
    {
        // Retirer d'abord la FK + colonne côté files (qui référence upload_batches)
        // AVANT de supprimer la table cible, sinon la contrainte bloque le DROP.
        $this->addSql('ALTER TABLE files DROP FOREIGN KEY FK_6354059F39EBE7A');
        $this->addSql('DROP INDEX IDX_6354059F39EBE7A ON files');
        $this->addSql('ALTER TABLE files DROP batch_id');
        $this->addSql('ALTER TABLE upload_batches DROP FOREIGN KEY FK_35597FBD7E3C61F9');
        $this->addSql('DROP TABLE upload_batches');
    }
}
