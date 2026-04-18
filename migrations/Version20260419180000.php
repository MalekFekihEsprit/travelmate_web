<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create favorite_destination table for destination favorites.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE favorite_destination (id_favorite_destination INT AUTO_INCREMENT NOT NULL, id_destination INT NOT NULL, id_user INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_7C89C7B3F9D89552 (id_destination), INDEX IDX_7C89C7B32E3BE198 (id_user), UNIQUE INDEX uniq_favorite_destination_user (id_destination, id_user), PRIMARY KEY(id_favorite_destination)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE favorite_destination ADD CONSTRAINT FK_7C89C7B3F9D89552 FOREIGN KEY (id_destination) REFERENCES destination (id_destination) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite_destination ADD CONSTRAINT FK_7C89C7B32E3BE198 FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE favorite_destination');
    }
}