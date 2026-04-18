<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute latitude et longitude (optionnelles) sur evenement pour carte back-office et fiche publique.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD latitude DOUBLE DEFAULT NULL, ADD longitude DOUBLE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP COLUMN latitude');
        $this->addSql('ALTER TABLE evenement DROP COLUMN longitude');
    }
}
