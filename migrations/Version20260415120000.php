<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image_name and updated_at columns to hebergement for VichUploader integration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE hebergement ADD COLUMN IF NOT EXISTS image_name VARCHAR(255) DEFAULT NULL, ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hebergement DROP COLUMN IF EXISTS image_name, DROP COLUMN IF EXISTS updated_at');
    }
}
