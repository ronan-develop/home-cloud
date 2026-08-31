<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831125502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute last_changelog_viewed_at sur users : marque la dernière visite du changelog pour le badge de notification (#293)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD last_changelog_viewed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP last_changelog_viewed_at');
    }
}
