<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute latitude et longitude (optionnelles) sur activites pour filtre carte / alentours.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activites ADD latitude DOUBLE DEFAULT NULL, ADD longitude DOUBLE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activites DROP COLUMN latitude');
        $this->addSql('ALTER TABLE activites DROP COLUMN longitude');
    }
}
