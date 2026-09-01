<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table direct_messages : messagerie directe admin → utilisateur ciblé
 * (#373). Isolée manuellement du diff auto-généré, qui incluait des
 * changements sans rapport (résidu broadcast_messages/last_broadcast_seen_at
 * absent du code source actuel — dérive de la DB de dev locale).
 */
final class Version20260901092024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#373 : table direct_messages (messagerie directe admin)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE direct_messages (
              id BINARY(16) NOT NULL,
              subject VARCHAR(255) NOT NULL,
              body LONGTEXT NOT NULL,
              created_at DATETIME NOT NULL,
              read_at DATETIME DEFAULT NULL,
              sender_id BINARY(16) NOT NULL,
              recipient_id BINARY(16) NOT NULL,
              INDEX IDX_721C1B5AF624B39D (sender_id),
              INDEX IDX_721C1B5AE92F8F78 (recipient_id),
              INDEX idx_direct_message_recipient_unread (recipient_id, read_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE direct_messages
            ADD CONSTRAINT FK_721C1B5AF624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE direct_messages
            ADD CONSTRAINT FK_721C1B5AE92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE direct_messages DROP FOREIGN KEY FK_721C1B5AF624B39D');
        $this->addSql('ALTER TABLE direct_messages DROP FOREIGN KEY FK_721C1B5AE92F8F78');
        $this->addSql('DROP TABLE direct_messages');
    }
}
