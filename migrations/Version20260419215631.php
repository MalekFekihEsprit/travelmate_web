<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419215631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favorite_destination (id_favorite_destination INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, id_destination INT NOT NULL, id_user INT NOT NULL, INDEX IDX_FDD47BE926D4F35D (id_destination), INDEX IDX_FDD47BE96B3CA4B (id_user), PRIMARY KEY (id_favorite_destination)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE note_destination (id_note_destination INT AUTO_INCREMENT NOT NULL, note DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, id_destination INT NOT NULL, id_user INT NOT NULL, INDEX IDX_187FA92F26D4F35D (id_destination), INDEX IDX_187FA92F6B3CA4B (id_user), PRIMARY KEY (id_note_destination)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE favorite_destination ADD CONSTRAINT FK_FDD47BE926D4F35D FOREIGN KEY (id_destination) REFERENCES destination (id_destination) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite_destination ADD CONSTRAINT FK_FDD47BE96B3CA4B FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note_destination ADD CONSTRAINT FK_187FA92F26D4F35D FOREIGN KEY (id_destination) REFERENCES destination (id_destination) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note_destination ADD CONSTRAINT FK_187FA92F6B3CA4B FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE destination ADD image_name VARCHAR(255) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, CHANGE score_destination score_destination DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY `FK_4852DD9C6D6036A7`');
        $this->addSql('ALTER TABLE hebergement ADD image_name VARCHAR(255) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT FK_4852DD9C6D6036A7 FOREIGN KEY (destination_hebergement) REFERENCES destination (id_destination) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY `FK_3F9D895526D4F35D`');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D895526D4F35D FOREIGN KEY (id_destination) REFERENCES destination (id_destination) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE favorite_destination DROP FOREIGN KEY FK_FDD47BE926D4F35D');
        $this->addSql('ALTER TABLE favorite_destination DROP FOREIGN KEY FK_FDD47BE96B3CA4B');
        $this->addSql('ALTER TABLE note_destination DROP FOREIGN KEY FK_187FA92F26D4F35D');
        $this->addSql('ALTER TABLE note_destination DROP FOREIGN KEY FK_187FA92F6B3CA4B');
        $this->addSql('DROP TABLE favorite_destination');
        $this->addSql('DROP TABLE note_destination');
        $this->addSql('ALTER TABLE destination DROP image_name, DROP updated_at, CHANGE score_destination score_destination DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY FK_4852DD9C6D6036A7');
        $this->addSql('ALTER TABLE hebergement DROP image_name, DROP updated_at');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT `FK_4852DD9C6D6036A7` FOREIGN KEY (destination_hebergement) REFERENCES destination (id_destination)');
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D895526D4F35D');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT `FK_3F9D895526D4F35D` FOREIGN KEY (id_destination) REFERENCES destination (id_destination)');
    }
}
