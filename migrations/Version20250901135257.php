<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250901135257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_log (id INT AUTO_INCREMENT NOT NULL, share_id INT NOT NULL, accessed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ip_address VARCHAR(45) NOT NULL, action VARCHAR(32) NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_EF7F35102AE63FDB (share_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE file (id INT AUTO_INCREMENT NOT NULL, private_space_id INT NOT NULL, filename VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, size INT NOT NULL, mime_type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8C9F361084C18CED (private_space_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE private_space (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_53B9DC5A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE share (id INT AUTO_INCREMENT NOT NULL, file_id INT DEFAULT NULL, private_space_id INT DEFAULT NULL, token VARCHAR(64) NOT NULL, email VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_external TINYINT(1) NOT NULL, access_level VARCHAR(32) NOT NULL, expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', password VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_EF069D5A5F37A13B (token), INDEX IDX_EF069D5A93CB796C (file_id), INDEX IDX_EF069D5A84C18CED (private_space_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE access_log ADD CONSTRAINT FK_EF7F35102AE63FDB FOREIGN KEY (share_id) REFERENCES share (id)');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F361084C18CED FOREIGN KEY (private_space_id) REFERENCES private_space (id)');
        $this->addSql('ALTER TABLE private_space ADD CONSTRAINT FK_53B9DC5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE share ADD CONSTRAINT FK_EF069D5A93CB796C FOREIGN KEY (file_id) REFERENCES file (id)');
        $this->addSql('ALTER TABLE share ADD CONSTRAINT FK_EF069D5A84C18CED FOREIGN KEY (private_space_id) REFERENCES private_space (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_log DROP FOREIGN KEY FK_EF7F35102AE63FDB');
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F361084C18CED');
        $this->addSql('ALTER TABLE private_space DROP FOREIGN KEY FK_53B9DC5A76ED395');
        $this->addSql('ALTER TABLE share DROP FOREIGN KEY FK_EF069D5A93CB796C');
        $this->addSql('ALTER TABLE share DROP FOREIGN KEY FK_EF069D5A84C18CED');
        $this->addSql('DROP TABLE access_log');
        $this->addSql('DROP TABLE file');
        $this->addSql('DROP TABLE private_space');
        $this->addSql('DROP TABLE share');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
