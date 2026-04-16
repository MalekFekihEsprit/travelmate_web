<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416105501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY `FK_73F2F77B19AA3CB8`');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77B19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY `FK_3405975755C54296`');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_3405975755C54296 FOREIGN KEY (id_budget) REFERENCES budget (id_budget) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY `FK_285F75DDEDF61AC6`');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT FK_285F75DDEDF61AC6 FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE itineraire DROP FOREIGN KEY `FK_487C9A1119AA3CB8`');
        $this->addSql('ALTER TABLE itineraire ADD CONSTRAINT FK_487C9A1119AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY `FK_B1DC7A1E19AA3CB8`');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `FK_AB55E24F19AA3CB8`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `FK_AB55E24FBF396750`');
        $this->addSql('ALTER TABLE participation ADD role_participation VARCHAR(50) DEFAULT \'Participant\' NOT NULL');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FBF396750 FOREIGN KEY (id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservations CHANGE montant_total montant_total DOUBLE PRECISION NOT NULL, CHANGE acompte acompte DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE user ADD last_login DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77B19AA3CB8');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT `FK_73F2F77B19AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_3405975755C54296');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT `FK_3405975755C54296` FOREIGN KEY (id_budget) REFERENCES budget (id_budget)');
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY FK_285F75DDEDF61AC6');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT `FK_285F75DDEDF61AC6` FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire)');
        $this->addSql('ALTER TABLE itineraire DROP FOREIGN KEY FK_487C9A1119AA3CB8');
        $this->addSql('ALTER TABLE itineraire ADD CONSTRAINT `FK_487C9A1119AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E19AA3CB8');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT `FK_B1DC7A1E19AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FBF396750');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F19AA3CB8');
        $this->addSql('ALTER TABLE participation DROP role_participation');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `FK_AB55E24FBF396750` FOREIGN KEY (id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `FK_AB55E24F19AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE reservations CHANGE montant_total montant_total NUMERIC(10, 2) NOT NULL, CHANGE acompte acompte NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE user DROP last_login');
    }
}
