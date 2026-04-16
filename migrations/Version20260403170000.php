<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add participant role storage on the participation join table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE participation ADD role_participation VARCHAR(50) DEFAULT 'Participant' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation DROP role_participation');
    }
}