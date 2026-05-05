<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505103559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix: decimal for money fields, nullable=false on id_voyage in budget, cascade persist on reservation';
    }

    public function up(Schema $schema): void
    {
        // ✅ ÉTAPE 1 : Supprimer la FK avant de modifier la colonne
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77B19AA3CB8');

        // ✅ ÉTAPE 2 : Modifier montant_total de float → decimal et id_voyage → NOT NULL
        $this->addSql('ALTER TABLE budget 
            CHANGE montant_total montant_total NUMERIC(10, 2) NOT NULL,
            CHANGE id_voyage id_voyage INT NOT NULL
        ');

        // ✅ ÉTAPE 3 : Recréer la FK
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77B19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');

        // ✅ Modifier montantTotal et acompte dans reservations
        $this->addSql('ALTER TABLE reservations 
            CHANGE montant_total montant_total NUMERIC(10, 2) NOT NULL,
            CHANGE acompte acompte NUMERIC(10, 2) NOT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        // Rollback : remettre float et nullable
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77B19AA3CB8');

        $this->addSql('ALTER TABLE budget 
            CHANGE montant_total montant_total DOUBLE PRECISION NOT NULL,
            CHANGE id_voyage id_voyage INT DEFAULT NULL
        ');

        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77B19AA3CB8 FOREIGN KEY (id_voyage) REFERENCES voyage (id_voyage) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE reservations 
            CHANGE montant_total montant_total DOUBLE PRECISION NOT NULL,
            CHANGE acompte acompte DOUBLE PRECISION NOT NULL
        ');
    }
}