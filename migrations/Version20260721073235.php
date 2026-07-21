<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721073235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute revoked_at sur shares : révocation manuelle réversible d'un partage par compte (#305)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shares ADD revoked_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shares DROP revoked_at');
    }
}
