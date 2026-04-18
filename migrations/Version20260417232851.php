<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417232851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `FK_AVIS_ACTIVITE`');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `FK_AVIS_USER`');
        $this->addSql('ALTER TABLE participation_evenement DROP FOREIGN KEY `FK_PARTEV_EVENEMENT`');
        $this->addSql('ALTER TABLE participation_evenement DROP FOREIGN KEY `FK_PARTEV_USER`');
        $this->addSql('DROP TABLE avis');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE participation_evenement');
        $this->addSql('ALTER TABLE activites ADD date_prevue DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY `FK_73F2F77B19AA3CB8`');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77B19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('DROP INDEX UNIQ_3AF346686C6E55B5 ON categories');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY `FK_3405975755C54296`');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_3405975755C54296 FOREIGN KEY (id_budget) REFERENCES budget (id_budget)');
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY `FK_285F75DDEDF61AC6`');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT FK_285F75DDEDF61AC6 FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire)');
        $this->addSql('ALTER TABLE itineraire DROP FOREIGN KEY `FK_487C9A1119AA3CB8`');
        $this->addSql('ALTER TABLE itineraire ADD CONSTRAINT FK_487C9A1119AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY `FK_B1DC7A1E19AA3CB8`');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE user ADD failed_login_attempts INT DEFAULT 0 NOT NULL, ADD last_failed_login_at DATETIME DEFAULT NULL, ADD trust_score INT DEFAULT 50 NOT NULL, ADD suspicious_login_count INT DEFAULT 0 NOT NULL, ADD last_login_country_code VARCHAR(10) DEFAULT NULL, ADD security_alert_photo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `FK_AB55E24F19AA3CB8`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `FK_AB55E24FBF396750`');
        $this->addSql('ALTER TABLE participation DROP role_participation');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FBF396750 FOREIGN KEY (id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avis (id INT AUTO_INCREMENT NOT NULL, note INT NOT NULL, commentaire LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, activite_id INT DEFAULT NULL, INDEX IDX_8F91ABF09B0F88B1 (activite_id), INDEX IDX_8F91ABF0A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE evenement (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date DATE NOT NULL, heure TIME NOT NULL, lieu VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, nb_places INT NOT NULL, lien_groupe VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, image_path VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE participation_evenement (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, evenement_id INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_65A14675A76ED395 (user_id), INDEX IDX_65A14675FD02F13 (evenement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `FK_AVIS_ACTIVITE` FOREIGN KEY (activite_id) REFERENCES activites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `FK_AVIS_USER` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation_evenement ADD CONSTRAINT `FK_PARTEV_EVENEMENT` FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation_evenement ADD CONSTRAINT `FK_PARTEV_USER` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activites DROP date_prevue');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77B19AA3CB8');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT `FK_73F2F77B19AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3AF346686C6E55B5 ON categories (nom)');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_3405975755C54296');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT `FK_3405975755C54296` FOREIGN KEY (id_budget) REFERENCES budget (id_budget) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE etape DROP FOREIGN KEY FK_285F75DDEDF61AC6');
        $this->addSql('ALTER TABLE etape ADD CONSTRAINT `FK_285F75DDEDF61AC6` FOREIGN KEY (id_itineraire) REFERENCES itineraire (id_itineraire) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE itineraire DROP FOREIGN KEY FK_487C9A1119AA3CB8');
        $this->addSql('ALTER TABLE itineraire ADD CONSTRAINT `FK_487C9A1119AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E19AA3CB8');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT `FK_B1DC7A1E19AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FBF396750');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F19AA3CB8');
        $this->addSql('ALTER TABLE participation ADD role_participation VARCHAR(50) DEFAULT \'Participant\' NOT NULL');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `FK_AB55E24FBF396750` FOREIGN KEY (id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `FK_AB55E24F19AA3CB8` FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user DROP failed_login_attempts, DROP last_failed_login_at, DROP trust_score, DROP suspicious_login_count, DROP last_login_country_code, DROP security_alert_photo');
    }
}
