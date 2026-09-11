<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260911185136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table login_attempts (#386) — tracking des tentatives de connexion échouées.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE login_attempts (id BINARY(16) NOT NULL, email_hash VARCHAR(64) NOT NULL, ip VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_login_attempts_email_hash (email_hash), INDEX idx_login_attempts_ip (ip), INDEX idx_login_attempts_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE login_attempts');
    }
}
