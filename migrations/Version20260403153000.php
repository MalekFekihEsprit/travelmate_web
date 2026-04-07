<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore cascade delete on voyage-related foreign keys and join tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77B19AA3CB8');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT `fk_budget_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON UPDATE CASCADE ON DELETE CASCADE');

        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_3405975755C54296');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT `depense_fk` FOREIGN KEY (id_budget) REFERENCES budget (id_budget) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE itineraire DROP FOREIGN KEY FK_487C9A1119AA3CB8');
        $this->addSql('ALTER TABLE itineraire ADD CONSTRAINT `fk_itineraire_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON UPDATE CASCADE ON DELETE CASCADE');

        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY FK_285F75DDEDF61AC6');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT `fk_etape_itineraire` FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire) ON UPDATE CASCADE ON DELETE CASCADE');

        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E19AA3CB8');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT `paiement_ibfk_1` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F19AA3CB8');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `fk_participation_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON UPDATE CASCADE ON DELETE CASCADE');

        $this->addSql('ALTER TABLE liste_activite DROP FOREIGN KEY `FK_A4A80EEF19AA3CB8`');
        $this->addSql('ALTER TABLE liste_activite ADD CONSTRAINT `FK_A4A80EEF19AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY `fk_budget_voyage`');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77B19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');

        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY `depense_fk`');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_3405975755C54296 FOREIGN KEY (id_budget) REFERENCES budget (id_budget)');

        $this->addSql('ALTER TABLE itineraire DROP FOREIGN KEY `fk_itineraire_voyage`');
        $this->addSql('ALTER TABLE itineraire ADD CONSTRAINT FK_487C9A1119AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');

        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY `fk_etape_itineraire`');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT FK_285F75DDEDF61AC6 FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire)');

        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY `paiement_ibfk_1`');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');

        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `fk_participation_voyage`');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');

        $this->addSql('ALTER TABLE liste_activite DROP FOREIGN KEY `FK_A4A80EEF19AA3CB8`');
        $this->addSql('ALTER TABLE liste_activite ADD CONSTRAINT `FK_A4A80EEF19AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
    }
}