<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331133817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY `fk_budget_user`');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY `fk_budget_voyage`');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77BBF396750 FOREIGN KEY (id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77B19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE delete_notifications DROP FOREIGN KEY `fk_notification_user`');
        $this->addSql('ALTER TABLE delete_notifications ADD CONSTRAINT FK_2346105BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY `depense_fk`');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_3405975755C54296 FOREIGN KEY (id_budget) REFERENCES budget (id_budget)');
        $this->addSql('ALTER TABLE destination DROP FOREIGN KEY `fk_destination_user`');
        $this->addSql('ALTER TABLE destination ADD CONSTRAINT FK_3EC63EAA699B6BAF FOREIGN KEY (added_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY `fk_etape_activite`');
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY `fk_etape_itineraire`');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT FK_285F75DDE8AEB980 FOREIGN KEY (id_activite) REFERENCES activites (id)');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT FK_285F75DDEDF61AC6 FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire)');
        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY `fk_hebergement_user`');
        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY `hebergement_fk`');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT FK_4852DD9C6D6036A7 FOREIGN KEY (destination_hebergement) REFERENCES destination (id_destination)');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT FK_4852DD9C699B6BAF FOREIGN KEY (added_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE itineraire DROP FOREIGN KEY `fk_itineraire_voyage`');
        $this->addSql('ALTER TABLE itineraire ADD CONSTRAINT FK_487C9A1119AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY `paiement_ibfk_1`');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY `paiement_ibfk_2`');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES user (id)');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `fk_participation_user`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `fk_participation_voyage`');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FBF396750 FOREIGN KEY (id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY `fk_voyage_destination`');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D895526D4F35D FOREIGN KEY (id_destination) REFERENCES destination (id_destination)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77BBF396750');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77B19AA3CB8');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT `fk_budget_user` FOREIGN KEY (id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT `fk_budget_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE delete_notifications DROP FOREIGN KEY FK_2346105BA76ED395');
        $this->addSql('ALTER TABLE delete_notifications ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_3405975755C54296');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT `depense_fk` FOREIGN KEY (id_budget) REFERENCES budget (id_budget) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE destination DROP FOREIGN KEY FK_3EC63EAA699B6BAF');
        $this->addSql('ALTER TABLE destination ADD CONSTRAINT `fk_destination_user` FOREIGN KEY (added_by) REFERENCES user (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY FK_285F75DDE8AEB980');
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY FK_285F75DDEDF61AC6');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT `fk_etape_activite` FOREIGN KEY (id_activite) REFERENCES activites (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT `fk_etape_itineraire` FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY FK_4852DD9C6D6036A7');
        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY FK_4852DD9C699B6BAF');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT `fk_hebergement_user` FOREIGN KEY (added_by) REFERENCES user (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT `hebergement_fk` FOREIGN KEY (destination_hebergement) REFERENCES destination (id_destination) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE itineraire DROP FOREIGN KEY FK_487C9A1119AA3CB8');
        $this->addSql('ALTER TABLE itineraire ADD CONSTRAINT `fk_itineraire_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E19AA3CB8');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E50EAE44');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT `paiement_ibfk_1` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT `paiement_ibfk_2` FOREIGN KEY (id_utilisateur) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FBF396750');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F19AA3CB8');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `fk_participation_user` FOREIGN KEY (id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `fk_participation_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D895526D4F35D');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT `fk_voyage_destination` FOREIGN KEY (id_destination) REFERENCES destination (id_destination) ON UPDATE CASCADE ON DELETE CASCADE');
    }
}
