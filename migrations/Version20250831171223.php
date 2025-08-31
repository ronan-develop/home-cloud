<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250831171223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables share (partages) et access_log (logs d’accès), modification de file.private_space_id (NOT NULL).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_log (id INT AUTO_INCREMENT NOT NULL, share_id INT NOT NULL, accessed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ip VARCHAR(45) NOT NULL, action VARCHAR(32) NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_EF7F35102AE63FDB (share_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE share (id INT AUTO_INCREMENT NOT NULL, file_id INT DEFAULT NULL, private_space_id INT DEFAULT NULL, token VARCHAR(64) NOT NULL, email VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_external TINYINT(1) NOT NULL, access_level VARCHAR(32) NOT NULL, expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', password VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_EF069D5A5F37A13B (token), INDEX IDX_EF069D5A93CB796C (file_id), INDEX IDX_EF069D5A84C18CED (private_space_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE access_log ADD CONSTRAINT FK_EF7F35102AE63FDB FOREIGN KEY (share_id) REFERENCES share (id)');
        $this->addSql('ALTER TABLE share ADD CONSTRAINT FK_EF069D5A93CB796C FOREIGN KEY (file_id) REFERENCES file (id)');
        $this->addSql('ALTER TABLE share ADD CONSTRAINT FK_EF069D5A84C18CED FOREIGN KEY (private_space_id) REFERENCES private_space (id)');
        $this->addSql('ALTER TABLE file CHANGE private_space_id private_space_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_log DROP FOREIGN KEY FK_EF7F35102AE63FDB');
        $this->addSql('ALTER TABLE share DROP FOREIGN KEY FK_EF069D5A93CB796C');
        $this->addSql('ALTER TABLE share DROP FOREIGN KEY FK_EF069D5A84C18CED');
        $this->addSql('DROP TABLE access_log');
        $this->addSql('DROP TABLE share');
        $this->addSql('ALTER TABLE file CHANGE private_space_id private_space_id INT DEFAULT NULL');
    }
}
