<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add participant invitation state for voyage invitation emails.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE participation ADD invitation_status VARCHAR(20) DEFAULT 'accepted' NOT NULL, ADD invitation_token VARCHAR(64) DEFAULT NULL, ADD invited_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD accepted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PARTICIPATION_INVITATION_TOKEN ON participation (invitation_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_PARTICIPATION_INVITATION_TOKEN ON participation');
        $this->addSql('ALTER TABLE participation DROP invitation_status, DROP invitation_token, DROP invited_at, DROP accepted_at');
    }
}