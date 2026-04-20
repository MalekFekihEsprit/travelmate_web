<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420111500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align destination_voyage_notification index names with Doctrine naming strategy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_destination_voyage_notification ON destination_voyage_notification');
        $this->addSql('ALTER TABLE destination_voyage_notification DROP FOREIGN KEY `FK_94E0F2D56B3CA4B`');
        $this->addSql('ALTER TABLE destination_voyage_notification DROP FOREIGN KEY `FK_94E0F2D5D3E90A14`');
        $this->addSql('DROP INDEX idx_94e0f2d56b3ca4b ON destination_voyage_notification');
        $this->addSql('CREATE INDEX IDX_1E2C1B826B3CA4B ON destination_voyage_notification (id_user)');
        $this->addSql('DROP INDEX idx_94e0f2d5d3e90a14 ON destination_voyage_notification');
        $this->addSql('CREATE INDEX IDX_1E2C1B8219AA3CB8 ON destination_voyage_notification (id_voyage)');
        $this->addSql('ALTER TABLE destination_voyage_notification ADD CONSTRAINT `FK_94E0F2D56B3CA4B` FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE destination_voyage_notification ADD CONSTRAINT `FK_94E0F2D5D3E90A14` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE destination_voyage_notification DROP FOREIGN KEY `FK_94E0F2D56B3CA4B`');
        $this->addSql('ALTER TABLE destination_voyage_notification DROP FOREIGN KEY `FK_94E0F2D5D3E90A14`');
        $this->addSql('DROP INDEX IDX_1E2C1B826B3CA4B ON destination_voyage_notification');
        $this->addSql('CREATE INDEX IDX_94E0F2D56B3CA4B ON destination_voyage_notification (id_user)');
        $this->addSql('DROP INDEX IDX_1E2C1B8219AA3CB8 ON destination_voyage_notification');
        $this->addSql('CREATE INDEX IDX_94E0F2D5D3E90A14 ON destination_voyage_notification (id_voyage)');
        $this->addSql('ALTER TABLE destination_voyage_notification ADD CONSTRAINT `FK_94E0F2D56B3CA4B` FOREIGN KEY (id_user) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE destination_voyage_notification ADD CONSTRAINT `FK_94E0F2D5D3E90A14` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX uniq_destination_voyage_notification ON destination_voyage_notification (id_user, id_voyage)');
    }
}
