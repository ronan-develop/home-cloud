<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002091651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table takeout_dispatch_log : historique des cycles de dispatch Takeout (#528)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE takeout_dispatch_log (id BINARY(16) NOT NULL, outcome VARCHAR(16) NOT NULL, load1 DOUBLE PRECISION DEFAULT NULL, load5 DOUBLE PRECISION DEFAULT NULL, load15 DOUBLE PRECISION DEFAULT NULL, threshold DOUBLE PRECISION NOT NULL, scheduled_count INT NOT NULL, dispatched_count INT NOT NULL, created_at DATETIME NOT NULL, INDEX idx_takeout_dispatch_log_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE takeout_dispatch_log');
    }
}
