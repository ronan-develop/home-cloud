<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

/**
 * Transforme la table pivot album_media (clé composite album_id+media_id)
 * en entité de jointure à part entière (id, position), pour supporter le
 * réordonnancement manuel des médias dans un album.
 *
 * Backfill : les lignes existantes reçoivent un id généré et une position
 * basée sur leur ordre d'insertion naturel (rowid), avant que les colonnes
 * ne deviennent NOT NULL.
 */
final class Version20260713082311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'album_media : ajoute id (uuid) et position pour le réordonnancement des médias dans un album';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE album_media ADD id BINARY(16) DEFAULT NULL, ADD position INT DEFAULT NULL');

        $rows = $this->connection->fetchAllAssociative(
            'SELECT album_id, media_id FROM album_media ORDER BY album_id, media_id'
        );

        $positionByAlbum = [];
        foreach ($rows as $row) {
            $albumId = $row['album_id'];
            $positionByAlbum[$albumId] ??= 0;

            $this->addSql(
                'UPDATE album_media SET id = :id, position = :position WHERE album_id = :albumId AND media_id = :mediaId',
                [
                    'id'       => Uuid::v7()->toBinary(),
                    'position' => $positionByAlbum[$albumId],
                    'albumId'  => $albumId,
                    'mediaId'  => $row['media_id'],
                ],
                [
                    'id'       => \Doctrine\DBAL\ParameterType::BINARY,
                    'position' => \Doctrine\DBAL\ParameterType::INTEGER,
                    'albumId'  => \Doctrine\DBAL\ParameterType::BINARY,
                    'mediaId'  => \Doctrine\DBAL\ParameterType::BINARY,
                ]
            );

            $positionByAlbum[$albumId]++;
        }

        $this->addSql('ALTER TABLE album_media MODIFY id BINARY(16) NOT NULL, MODIFY position INT NOT NULL');
        $this->addSql('ALTER TABLE album_media DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_album_media ON album_media (album_id, media_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_album_media ON album_media');
        $this->addSql('ALTER TABLE album_media DROP id, DROP position, DROP PRIMARY KEY, ADD PRIMARY KEY (album_id, media_id)');
    }
}
