<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331132923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_766B5EB5BCF5E73D ON activites');
        $this->addSql('ALTER TABLE liste_activite DROP FOREIGN KEY `FK_A4A80EEF19AA3CB8`');
        $this->addSql('DROP INDEX fk_liste_activite_voyage ON liste_activite');
        $this->addSql('CREATE INDEX IDX_A4A80EEF19AA3CB8 ON liste_activite (id_voyage)');
        $this->addSql('ALTER TABLE liste_activite ADD CONSTRAINT `FK_A4A80EEF19AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');

        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY `fk_budget_user`');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY `fk_budget_voyage`');
        $this->addSql('ALTER TABLE budget CHANGE libelle_budget libelle_budget VARCHAR(255) NOT NULL, CHANGE devise_budget devise_budget VARCHAR(255) DEFAULT NULL, CHANGE statut_budget statut_budget VARCHAR(255) DEFAULT NULL, CHANGE description_budget description_budget LONGTEXT DEFAULT NULL, CHANGE id id INT DEFAULT NULL, CHANGE id_voyage id_voyage INT DEFAULT NULL');
        $this->addSql('DROP INDEX fk_budget_user ON budget');
        $this->addSql('DROP INDEX fk_budget_voyage ON budget');
        $this->addSql('CREATE INDEX IDX_73F2F77BBF396750 ON budget (id)');
        $this->addSql('CREATE INDEX IDX_73F2F77B19AA3CB8 ON budget (id_voyage)');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT `fk_budget_user` FOREIGN KEY (id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT `fk_budget_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON UPDATE CASCADE ON DELETE CASCADE');

        $this->addSql('ALTER TABLE categories CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE saison saison VARCHAR(255) NOT NULL, CHANGE niveauintensite niveauintensite VARCHAR(255) NOT NULL, CHANGE publiccible publiccible VARCHAR(255) NOT NULL');

        $this->addSql('ALTER TABLE delete_notifications DROP FOREIGN KEY `fk_notification_admin`');
        $this->addSql('ALTER TABLE delete_notifications DROP FOREIGN KEY `fk_notification_user`');
        $this->addSql('DROP INDEX admin_id ON delete_notifications');
        $this->addSql('ALTER TABLE delete_notifications DROP admin_id, CHANGE user_id user_id INT DEFAULT NULL, CHANGE item_type item_type VARCHAR(255) NOT NULL, CHANGE custom_reason custom_reason LONGTEXT DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME NOT NULL, CHANGE is_read is_read TINYINT DEFAULT NULL');
        $this->addSql('DROP INDEX user_id ON delete_notifications');
        $this->addSql('CREATE INDEX IDX_2346105BA76ED395 ON delete_notifications (user_id)');
        $this->addSql('ALTER TABLE delete_notifications ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY `depense_fk`');
        $this->addSql('ALTER TABLE depense CHANGE libelle_depense libelle_depense VARCHAR(255) NOT NULL, CHANGE categorie_depense categorie_depense VARCHAR(255) NOT NULL, CHANGE description_depense description_depense LONGTEXT NOT NULL, CHANGE devise_depense devise_depense VARCHAR(255) DEFAULT NULL, CHANGE type_paiement type_paiement VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX depense_fk ON depense');
        $this->addSql('CREATE INDEX IDX_3405975755C54296 ON depense (id_budget)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT `depense_fk` FOREIGN KEY (id_budget) REFERENCES budget (id_budget) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE destination DROP FOREIGN KEY `fk_destination_user`');
        $this->addSql('ALTER TABLE destination CHANGE nom_destination nom_destination VARCHAR(255) NOT NULL, CHANGE pays_destination pays_destination VARCHAR(255) NOT NULL, CHANGE region_destination region_destination VARCHAR(255) DEFAULT NULL, CHANGE description_destination description_destination LONGTEXT DEFAULT NULL, CHANGE climat_destination climat_destination VARCHAR(255) DEFAULT NULL, CHANGE saison_destination saison_destination VARCHAR(255) DEFAULT NULL, CHANGE flag_destination flag_destination VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX fk_destination_user ON destination');
        $this->addSql('CREATE INDEX IDX_3EC63EAA699B6BAF ON destination (added_by)');
        $this->addSql('ALTER TABLE destination ADD CONSTRAINT `fk_destination_user` FOREIGN KEY (added_by) REFERENCES user (id) ON UPDATE CASCADE ON DELETE SET NULL');

        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY `fk_etape_activite`');
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY `fk_etape_itineraire`');
        $this->addSql('ALTER TABLE etape CHANGE description_etape description_etape LONGTEXT NOT NULL, CHANGE id_activite id_activite INT DEFAULT NULL, CHANGE id_itineraire id_itineraire INT DEFAULT NULL, CHANGE numero_jour numero_jour INT NOT NULL');
        $this->addSql('DROP INDEX fk_etape_activite ON etape');
        $this->addSql('DROP INDEX fk_etape_itineraire ON etape');
        $this->addSql('CREATE INDEX IDX_285F75DDE8AEB980 ON etape (id_activite)');
        $this->addSql('CREATE INDEX IDX_285F75DDEDF61AC6 ON etape (id_itineraire)');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT `fk_etape_activite` FOREIGN KEY (id_activite) REFERENCES activites (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT `fk_etape_itineraire` FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire) ON UPDATE CASCADE ON DELETE CASCADE');

        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY `fk_hebergement_user`');
        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY `hebergement_fk`');
        $this->addSql('ALTER TABLE hebergement CHANGE nom_hebergement nom_hebergement VARCHAR(255) NOT NULL, CHANGE type_hebergement type_hebergement VARCHAR(255) DEFAULT NULL, CHANGE adresse_hebergement adresse_hebergement VARCHAR(255) DEFAULT NULL, CHANGE destination_hebergement destination_hebergement INT DEFAULT NULL, CHANGE prixNuit_hebergement prix_nuit_hebergement DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('DROP INDEX fk_hebergement_user ON hebergement');
        $this->addSql('DROP INDEX hebergement_fk ON hebergement');
        $this->addSql('CREATE INDEX IDX_4852DD9C699B6BAF ON hebergement (added_by)');
        $this->addSql('CREATE INDEX IDX_4852DD9C6D6036A7 ON hebergement (destination_hebergement)');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT `fk_hebergement_user` FOREIGN KEY (added_by) REFERENCES user (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT `hebergement_fk` FOREIGN KEY (destination_hebergement) REFERENCES destination (id_destination) ON UPDATE CASCADE ON DELETE CASCADE');

        $this->addSql('ALTER TABLE itineraire DROP FOREIGN KEY `fk_itineraire_voyage`');
        $this->addSql('ALTER TABLE itineraire CHANGE nom_itineraire nom_itineraire VARCHAR(255) NOT NULL, CHANGE description_itineraire description_itineraire LONGTEXT NOT NULL, CHANGE id_voyage id_voyage INT DEFAULT NULL');
        $this->addSql('DROP INDEX fk_itineraire_voyage ON itineraire');
        $this->addSql('CREATE INDEX IDX_487C9A1119AA3CB8 ON itineraire (id_voyage)');
        $this->addSql('ALTER TABLE itineraire ADD CONSTRAINT `fk_itineraire_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON UPDATE CASCADE ON DELETE CASCADE');

        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY `paiement_ibfk_1`');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY `paiement_ibfk_2`');
        $this->addSql('ALTER TABLE paiement CHANGE id_voyage id_voyage INT DEFAULT NULL, CHANGE id_utilisateur id_utilisateur INT DEFAULT NULL, CHANGE montant montant DOUBLE PRECISION NOT NULL, CHANGE devise devise VARCHAR(255) NOT NULL, CHANGE methode methode VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE date_paiement date_paiement DATETIME NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX id_voyage ON paiement');
        $this->addSql('DROP INDEX id_utilisateur ON paiement');
        $this->addSql('CREATE INDEX IDX_B1DC7A1E19AA3CB8 ON paiement (id_voyage)');
        $this->addSql('CREATE INDEX IDX_B1DC7A1E50EAE44 ON paiement (id_utilisateur)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT `paiement_ibfk_1` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT `paiement_ibfk_2` FOREIGN KEY (id_utilisateur) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql('DROP INDEX email ON user');
        $this->addSql('ALTER TABLE user CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE prenom prenom VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(255) DEFAULT NULL, CHANGE role role VARCHAR(255) NOT NULL, CHANGE photo_url photo_url VARCHAR(255) DEFAULT NULL, CHANGE verification_code verification_code VARCHAR(255) DEFAULT NULL, CHANGE is_verified is_verified TINYINT DEFAULT NULL, CHANGE last_login_ip last_login_ip VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE face_embedding face_embedding LONGTEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `fk_participation_user`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `fk_participation_voyage`');
        $this->addSql('ALTER TABLE participation MODIFY id_participation INT NOT NULL');
        $this->addSql('ALTER TABLE participation DROP id_participation, DROP role_participation, DROP PRIMARY KEY, ADD PRIMARY KEY (id, id_voyage)');
        $this->addSql('DROP INDEX fk_participation_user ON participation');
        $this->addSql('DROP INDEX fk_participation_voyage ON participation');
        $this->addSql('CREATE INDEX IDX_AB55E24FBF396750 ON participation (id)');
        $this->addSql('CREATE INDEX IDX_AB55E24F19AA3CB8 ON participation (id_voyage)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `fk_participation_user` FOREIGN KEY (id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `fk_participation_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON UPDATE CASCADE ON DELETE CASCADE');

        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY `fk_voyage_destination`');
        $this->addSql('ALTER TABLE voyage CHANGE titre_voyage titre_voyage VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE id_destination id_destination INT DEFAULT NULL');
        $this->addSql('DROP INDEX fk_voyage_destination ON voyage');
        $this->addSql('CREATE INDEX IDX_3F9D895526D4F35D ON voyage (id_destination)');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT `fk_voyage_destination` FOREIGN KEY (id_destination) REFERENCES destination (id_destination) ON UPDATE CASCADE ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX IDX_766B5EB5BCF5E73D ON activites (categorie_id)');

        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY `fk_budget_user`');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY `fk_budget_voyage`');
        $this->addSql('ALTER TABLE budget CHANGE libelle_budget libelle_budget VARCHAR(150) NOT NULL, CHANGE devise_budget devise_budget VARCHAR(3) DEFAULT \'EUR\', CHANGE statut_budget statut_budget VARCHAR(20) DEFAULT \'ACTIF\', CHANGE description_budget description_budget TEXT DEFAULT NULL, CHANGE id id INT NOT NULL, CHANGE id_voyage id_voyage INT NOT NULL');
        $this->addSql('DROP INDEX IDX_73F2F77BBF396750 ON budget');
        $this->addSql('DROP INDEX IDX_73F2F77B19AA3CB8 ON budget');
        $this->addSql('CREATE INDEX fk_budget_user ON budget (id)');
        $this->addSql('CREATE INDEX fk_budget_voyage ON budget (id_voyage)');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT `fk_budget_user` FOREIGN KEY (id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT `fk_budget_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');

        $this->addSql('ALTER TABLE categories CHANGE nom nom VARCHAR(100) NOT NULL, CHANGE description description TEXT NOT NULL, CHANGE type type VARCHAR(100) NOT NULL, CHANGE saison saison VARCHAR(100) NOT NULL, CHANGE niveauintensite niveauintensite VARCHAR(100) NOT NULL, CHANGE publiccible publiccible VARCHAR(100) NOT NULL');

        $this->addSql('ALTER TABLE delete_notifications DROP FOREIGN KEY `fk_notification_user`');
        $this->addSql('ALTER TABLE delete_notifications ADD admin_id INT NOT NULL, CHANGE item_type item_type VARCHAR(50) NOT NULL, CHANGE custom_reason custom_reason TEXT DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE is_read is_read TINYINT DEFAULT 0, CHANGE user_id user_id INT NOT NULL');
        $this->addSql('DROP INDEX IDX_2346105BA76ED395 ON delete_notifications');
        $this->addSql('CREATE INDEX user_id ON delete_notifications (user_id)');
        $this->addSql('CREATE INDEX admin_id ON delete_notifications (admin_id)');
        $this->addSql('ALTER TABLE delete_notifications ADD CONSTRAINT `fk_notification_admin` FOREIGN KEY (admin_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE delete_notifications ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (user_id) REFERENCES user (id)');

        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY `depense_fk`');
        $this->addSql('ALTER TABLE depense CHANGE libelle_depense libelle_depense VARCHAR(100) NOT NULL, CHANGE categorie_depense categorie_depense VARCHAR(50) NOT NULL, CHANGE description_depense description_depense TEXT NOT NULL, CHANGE devise_depense devise_depense VARCHAR(3) DEFAULT \'EUR\', CHANGE type_paiement type_paiement VARCHAR(30) NOT NULL');
        $this->addSql('DROP INDEX IDX_3405975755C54296 ON depense');
        $this->addSql('CREATE INDEX depense_fk ON depense (id_budget)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT `depense_fk` FOREIGN KEY (id_budget) REFERENCES budget (id_budget)');

        $this->addSql('ALTER TABLE destination DROP FOREIGN KEY `fk_destination_user`');
        $this->addSql('ALTER TABLE destination CHANGE nom_destination nom_destination VARCHAR(30) NOT NULL, CHANGE pays_destination pays_destination VARCHAR(30) NOT NULL, CHANGE region_destination region_destination VARCHAR(100) DEFAULT NULL, CHANGE description_destination description_destination TEXT DEFAULT NULL, CHANGE climat_destination climat_destination VARCHAR(40) DEFAULT NULL, CHANGE saison_destination saison_destination VARCHAR(40) DEFAULT NULL, CHANGE flag_destination flag_destination VARCHAR(500) DEFAULT NULL');
        $this->addSql('DROP INDEX IDX_3EC63EAA699B6BAF ON destination');
        $this->addSql('CREATE INDEX fk_destination_user ON destination (added_by)');
        $this->addSql('ALTER TABLE destination ADD CONSTRAINT `fk_destination_user` FOREIGN KEY (added_by) REFERENCES user (id)');

        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY `fk_etape_activite`');
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY `fk_etape_itineraire`');
        $this->addSql('ALTER TABLE etape CHANGE description_etape description_etape TEXT NOT NULL, CHANGE numero_jour numero_jour INT DEFAULT 1 NOT NULL, CHANGE id_activite id_activite INT NOT NULL, CHANGE id_itineraire id_itineraire INT NOT NULL');
        $this->addSql('DROP INDEX IDX_285F75DDE8AEB980 ON etape');
        $this->addSql('DROP INDEX IDX_285F75DDEDF61AC6 ON etape');
        $this->addSql('CREATE INDEX fk_etape_activite ON etape (id_activite)');
        $this->addSql('CREATE INDEX fk_etape_itineraire ON etape (id_itineraire)');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT `fk_etape_activite` FOREIGN KEY (id_activite) REFERENCES activites (id)');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT `fk_etape_itineraire` FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire)');

        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY `fk_hebergement_user`');
        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY `hebergement_fk`');
        $this->addSql('ALTER TABLE hebergement CHANGE nom_hebergement nom_hebergement VARCHAR(100) NOT NULL, CHANGE type_hebergement type_hebergement VARCHAR(50) DEFAULT NULL, CHANGE adresse_hebergement adresse_hebergement VARCHAR(100) DEFAULT NULL, CHANGE destination_hebergement destination_hebergement INT NOT NULL, CHANGE prix_nuit_hebergement prixNuit_hebergement DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('DROP INDEX IDX_4852DD9C699B6BAF ON hebergement');
        $this->addSql('DROP INDEX IDX_4852DD9C6D6036A7 ON hebergement');
        $this->addSql('CREATE INDEX fk_hebergement_user ON hebergement (added_by)');
        $this->addSql('CREATE INDEX hebergement_fk ON hebergement (destination_hebergement)');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT `fk_hebergement_user` FOREIGN KEY (added_by) REFERENCES user (id)');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT `hebergement_fk` FOREIGN KEY (destination_hebergement) REFERENCES destination (id_destination)');

        $this->addSql('ALTER TABLE itineraire DROP FOREIGN KEY `fk_itineraire_voyage`');
        $this->addSql('ALTER TABLE itineraire CHANGE nom_itineraire nom_itineraire VARCHAR(100) NOT NULL, CHANGE description_itineraire description_itineraire TEXT NOT NULL, CHANGE id_voyage id_voyage INT NOT NULL');
        $this->addSql('DROP INDEX IDX_487C9A1119AA3CB8 ON itineraire');
        $this->addSql('CREATE INDEX fk_itineraire_voyage ON itineraire (id_voyage)');
        $this->addSql('ALTER TABLE itineraire ADD CONSTRAINT `fk_itineraire_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');

        $this->addSql('ALTER TABLE liste_activite DROP FOREIGN KEY `FK_A4A80EEF19AA3CB8`');
        $this->addSql('DROP INDEX IDX_A4A80EEF19AA3CB8 ON liste_activite');
        $this->addSql('CREATE INDEX fk_liste_activite_voyage ON liste_activite (id_voyage)');
        $this->addSql('ALTER TABLE liste_activite ADD CONSTRAINT `FK_A4A80EEF19AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');

        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY `paiement_ibfk_1`');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY `paiement_ibfk_2`');
        $this->addSql('ALTER TABLE paiement CHANGE montant montant NUMERIC(10, 2) NOT NULL, CHANGE devise devise VARCHAR(10) DEFAULT \'EUR\' NOT NULL, CHANGE methode methode VARCHAR(50) NOT NULL, CHANGE statut statut VARCHAR(50) DEFAULT \'EN_ATTENTE\' NOT NULL, CHANGE date_paiement date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE id_voyage id_voyage INT NOT NULL, CHANGE id_utilisateur id_utilisateur INT NOT NULL');
        $this->addSql('DROP INDEX IDX_B1DC7A1E19AA3CB8 ON paiement');
        $this->addSql('DROP INDEX IDX_B1DC7A1E50EAE44 ON paiement');
        $this->addSql('CREATE INDEX id_voyage ON paiement (id_voyage)');
        $this->addSql('CREATE INDEX id_utilisateur ON paiement (id_utilisateur)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT `paiement_ibfk_1` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT `paiement_ibfk_2` FOREIGN KEY (id_utilisateur) REFERENCES user (id)');

        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `fk_participation_user`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `fk_participation_voyage`');
        $this->addSql('ALTER TABLE participation ADD id_participation INT AUTO_INCREMENT NOT NULL, ADD role_participation VARCHAR(50) NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id_participation)');
        $this->addSql('DROP INDEX IDX_AB55E24FBF396750 ON participation');
        $this->addSql('DROP INDEX IDX_AB55E24F19AA3CB8 ON participation');
        $this->addSql('CREATE INDEX fk_participation_user ON participation (id)');
        $this->addSql('CREATE INDEX fk_participation_voyage ON participation (id_voyage)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `fk_participation_user` FOREIGN KEY (id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `fk_participation_voyage` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');

        $this->addSql('ALTER TABLE user CHANGE nom nom VARCHAR(100) NOT NULL, CHANGE prenom prenom VARCHAR(100) NOT NULL, CHANGE email email VARCHAR(150) NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE role role ENUM(\'USER\', \'ADMIN\') DEFAULT \'USER\' NOT NULL, CHANGE photo_url photo_url VARCHAR(500) DEFAULT NULL, CHANGE verification_code verification_code VARCHAR(6) DEFAULT NULL, CHANGE is_verified is_verified TINYINT DEFAULT 0, CHANGE last_login_ip last_login_ip VARCHAR(45) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE face_embedding face_embedding TEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX email ON user (email)');

        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY `fk_voyage_destination`');
        $this->addSql('ALTER TABLE voyage CHANGE titre_voyage titre_voyage VARCHAR(100) NOT NULL, CHANGE statut statut VARCHAR(50) NOT NULL, CHANGE id_destination id_destination INT NOT NULL');
        $this->addSql('DROP INDEX IDX_3F9D895526D4F35D ON voyage');
        $this->addSql('CREATE INDEX fk_voyage_destination ON voyage (id_destination)');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT `fk_voyage_destination` FOREIGN KEY (id_destination) REFERENCES destination (id_destination)');
    }
}