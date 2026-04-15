<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419161919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE destination ADD image_name VARCHAR(255) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, CHANGE score_destination score_destination DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('DROP INDEX uniq_note_destination_user ON note_destination');
        $this->addSql('ALTER TABLE note_destination DROP FOREIGN KEY `FK_23606E9E26D4F35D`');
        $this->addSql('ALTER TABLE note_destination DROP FOREIGN KEY `FK_23606E9EA76ED395`');
        $this->addSql('ALTER TABLE note_destination CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_23606e9e26d4f35d ON note_destination');
        $this->addSql('CREATE INDEX IDX_187FA92F26D4F35D ON note_destination (id_destination)');
        $this->addSql('DROP INDEX idx_23606e9ea76ed395 ON note_destination');
        $this->addSql('CREATE INDEX IDX_187FA92F6B3CA4B ON note_destination (id_user)');
        $this->addSql('ALTER TABLE note_destination ADD CONSTRAINT `FK_23606E9E26D4F35D` FOREIGN KEY (id_destination) REFERENCES destination (id_destination) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note_destination ADD CONSTRAINT `FK_23606E9EA76ED395` FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE destination DROP image_name, DROP updated_at, CHANGE score_destination score_destination DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE note_destination DROP FOREIGN KEY FK_187FA92F26D4F35D');
        $this->addSql('ALTER TABLE note_destination DROP FOREIGN KEY FK_187FA92F6B3CA4B');
        $this->addSql('ALTER TABLE note_destination CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_note_destination_user ON note_destination (id_destination, id_user)');
        $this->addSql('DROP INDEX idx_187fa92f6b3ca4b ON note_destination');
        $this->addSql('CREATE INDEX IDX_23606E9EA76ED395 ON note_destination (id_user)');
        $this->addSql('DROP INDEX idx_187fa92f26d4f35d ON note_destination');
        $this->addSql('CREATE INDEX IDX_23606E9E26D4F35D ON note_destination (id_destination)');
        $this->addSql('ALTER TABLE note_destination ADD CONSTRAINT FK_187FA92F26D4F35D FOREIGN KEY (id_destination) REFERENCES destination (id_destination) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note_destination ADD CONSTRAINT FK_187FA92F6B3CA4B FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE');
    }
}
