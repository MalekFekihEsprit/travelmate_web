<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420104000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create destination_voyage_notification table for favorite-destination voyage alerts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE destination_voyage_notification (id_notification INT AUTO_INCREMENT NOT NULL, id_user INT NOT NULL, id_voyage INT NOT NULL, created_at DATETIME NOT NULL, is_dismissed TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_94E0F2D56B3CA4B (id_user), INDEX IDX_94E0F2D5D3E90A14 (id_voyage), UNIQUE INDEX uniq_destination_voyage_notification (id_user, id_voyage), PRIMARY KEY(id_notification)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE destination_voyage_notification ADD CONSTRAINT FK_94E0F2D56B3CA4B FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE destination_voyage_notification ADD CONSTRAINT FK_94E0F2D5D3E90A14 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE destination_voyage_notification DROP FOREIGN KEY FK_94E0F2D56B3CA4B');
        $this->addSql('ALTER TABLE destination_voyage_notification DROP FOREIGN KEY FK_94E0F2D5D3E90A14');
        $this->addSql('DROP TABLE destination_voyage_notification');
    }
}
