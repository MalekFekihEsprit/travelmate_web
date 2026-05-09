<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase hebergement image_name length to store Unsplash URLs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hebergement CHANGE image_name image_name VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hebergement CHANGE image_name image_name VARCHAR(255) DEFAULT NULL');
    }
}
