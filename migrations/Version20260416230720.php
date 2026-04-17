<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416230720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evenement ADD telegram_group_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservations CHANGE montant_total montant_total NUMERIC(10, 2) NOT NULL, CHANGE acompte acompte NUMERIC(10, 2) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evenement DROP telegram_group_id');
        $this->addSql('ALTER TABLE reservations CHANGE montant_total montant_total DOUBLE PRECISION NOT NULL, CHANGE acompte acompte DOUBLE PRECISION NOT NULL');
    }
}
