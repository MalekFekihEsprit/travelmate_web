<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418171522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservations (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, telephone VARCHAR(20) NOT NULL, email VARCHAR(255) NOT NULL, commentaire LONGTEXT DEFAULT NULL, montant_total DOUBLE PRECISION NOT NULL, acompte DOUBLE PRECISION NOT NULL, statut_paiement VARCHAR(50) NOT NULL, methode_confirmation VARCHAR(20) DEFAULT NULL, code_confirmation VARCHAR(10) DEFAULT NULL, qr_code_path VARCHAR(255) DEFAULT NULL, date_reservation DATETIME NOT NULL, date_confirmation DATETIME DEFAULT NULL, date_paiement DATETIME DEFAULT NULL, transaction_id VARCHAR(100) DEFAULT NULL, activite_id INT NOT NULL, INDEX IDX_4DA2399B0F88B1 (activite_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reservations ADD CONSTRAINT FK_4DA2399B0F88B1 FOREIGN KEY (activite_id) REFERENCES activites (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activites ADD latitude DOUBLE PRECISION DEFAULT NULL, ADD longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement ADD latitude DOUBLE PRECISION DEFAULT NULL, ADD longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD failed_login_attempts INT DEFAULT 0 NOT NULL, ADD last_failed_login_at DATETIME DEFAULT NULL, ADD trust_score INT DEFAULT 50 NOT NULL, ADD suspicious_login_count INT DEFAULT 0 NOT NULL, ADD last_login_country_code VARCHAR(10) DEFAULT NULL, ADD security_alert_photo VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservations DROP FOREIGN KEY FK_4DA2399B0F88B1');
        $this->addSql('DROP TABLE reservations');
        $this->addSql('ALTER TABLE activites DROP latitude, DROP longitude');
        $this->addSql('ALTER TABLE evenement DROP latitude, DROP longitude');
        $this->addSql('ALTER TABLE user DROP failed_login_attempts, DROP last_failed_login_at, DROP trust_score, DROP suspicious_login_count, DROP last_login_country_code, DROP security_alert_photo');
    }
}
