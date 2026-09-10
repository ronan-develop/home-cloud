<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901133449 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute is_active sur users : permet à l'admin de désactiver un compte (#375)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP is_active');
    }
}
