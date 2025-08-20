<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250820132645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE file (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8C9F36107E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F36107E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64984C18CED');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D64984C18CED FOREIGN KEY (private_space_id) REFERENCES private_space (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F36107E3C61F9');
        $this->addSql('DROP TABLE file');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D64984C18CED');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D64984C18CED FOREIGN KEY (private_space_id) REFERENCES private_space (id)');
    }
}
