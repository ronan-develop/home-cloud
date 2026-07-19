<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Réglages de prise de vue (EXIF) sur medias : ouverture, vitesse, ISO, focale,
 * objectif — pack photographe pour JPEG et RAW (#268).
 */
final class Version20260719194000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute aperture, shutter_speed, iso, focal_length, lens sur medias (#268)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medias ADD aperture VARCHAR(16) DEFAULT NULL, ADD shutter_speed VARCHAR(16) DEFAULT NULL, ADD iso INT DEFAULT NULL, ADD focal_length VARCHAR(16) DEFAULT NULL, ADD lens VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medias DROP aperture, DROP shutter_speed, DROP iso, DROP focal_length, DROP lens');
    }
}
