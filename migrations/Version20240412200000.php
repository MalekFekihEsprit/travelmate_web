<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240412200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reservations table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reservations (
            id INT AUTO_INCREMENT NOT NULL,
            activite_id INT NOT NULL,
            nom VARCHAR(255) NOT NULL,
            prenom VARCHAR(255) NOT NULL,
            telephone VARCHAR(20) NOT NULL,
            email VARCHAR(255) NOT NULL,
            commentaire TEXT DEFAULT NULL,
            montant_total NUMERIC(10, 2) NOT NULL,
            acompte NUMERIC(10, 2) NOT NULL,
            statut_paiement VARCHAR(50) NOT NULL DEFAULT "en_attente",
            methode_confirmation VARCHAR(20) DEFAULT NULL,
            code_confirmation VARCHAR(10) DEFAULT NULL,
            qr_code_path VARCHAR(255) DEFAULT NULL,
            date_reservation DATETIME NOT NULL,
            date_confirmation DATETIME DEFAULT NULL,
            date_paiement DATETIME DEFAULT NULL,
            transaction_id VARCHAR(100) DEFAULT NULL,
            INDEX IDX_RESERVATIONS_ACTIVITE (activite_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE reservations 
            ADD CONSTRAINT FK_RESERVATIONS_ACTIVITE 
            FOREIGN KEY (activite_id) 
            REFERENCES activites (id) 
            ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reservations');
    }
}
