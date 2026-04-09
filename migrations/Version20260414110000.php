<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add note_destination table and default score_destination to 0';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE note_destination (id_note_destination INT AUTO_INCREMENT NOT NULL, id_destination INT NOT NULL, id_user INT NOT NULL, note DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_23606E9E26D4F35D (id_destination), INDEX IDX_23606E9EA76ED395 (id_user), UNIQUE INDEX uniq_note_destination_user (id_destination, id_user), PRIMARY KEY(id_note_destination)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE note_destination ADD CONSTRAINT FK_23606E9E26D4F35D FOREIGN KEY (id_destination) REFERENCES destination (id_destination) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note_destination ADD CONSTRAINT FK_23606E9EA76ED395 FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql('UPDATE destination SET score_destination = 0 WHERE score_destination IS NULL');
        $this->addSql('ALTER TABLE destination CHANGE score_destination score_destination DOUBLE PRECISION NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE note_destination DROP FOREIGN KEY FK_23606E9E26D4F35D');
        $this->addSql('ALTER TABLE note_destination DROP FOREIGN KEY FK_23606E9EA76ED395');
        $this->addSql('DROP TABLE note_destination');

        $this->addSql('ALTER TABLE destination CHANGE score_destination score_destination DOUBLE PRECISION DEFAULT NULL');
    }
}
