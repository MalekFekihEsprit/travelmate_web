<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration propre — module Activités/Événements/Avis
 * Ne touche QUE les tables de ce module.
 */
final class Version20260404210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime date_prevue de activites. Crée evenement, participation_evenement, avis.';
    }

    public function up(Schema $schema): void
    {
        // ── 1. Supprimer date_prevue de activites ──────────────────────────
        // On vérifie d'abord si la colonne existe encore avant de la supprimer
        $this->addSql('ALTER TABLE activites DROP COLUMN date_prevue');

        // ── 2. Créer la table evenement ────────────────────────────────────
        $this->addSql('
            CREATE TABLE IF NOT EXISTS evenement (
                id          INT          NOT NULL AUTO_INCREMENT,
                titre       VARCHAR(255) NOT NULL,
                description TEXT         DEFAULT NULL,
                date        DATE         NOT NULL,
                heure       TIME         NOT NULL,
                lieu        VARCHAR(255) NOT NULL,
                nb_places   INT          NOT NULL,
                lien_groupe VARCHAR(500) DEFAULT NULL,
                image_path  VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ');

        // ── 3. Créer la table participation_evenement ──────────────────────
        $this->addSql('
            CREATE TABLE IF NOT EXISTS participation_evenement (
                id           INT      NOT NULL AUTO_INCREMENT,
                user_id      INT      NOT NULL,
                evenement_id INT      NOT NULL,
                created_at   DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX IDX_PARTEV_USER     (user_id),
                INDEX IDX_PARTEV_EVENEMENT (evenement_id),
                CONSTRAINT FK_PARTEV_USER
                    FOREIGN KEY (user_id)      REFERENCES user      (id) ON DELETE CASCADE,
                CONSTRAINT FK_PARTEV_EVENEMENT
                    FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ');

        // ── 4. Créer la table avis ─────────────────────────────────────────
        $this->addSql('
            CREATE TABLE IF NOT EXISTS avis (
                id          INT      NOT NULL AUTO_INCREMENT,
                note        INT      NOT NULL,
                commentaire TEXT     DEFAULT NULL,
                created_at  DATETIME NOT NULL,
                user_id     INT      NOT NULL,
                activite_id INT      NOT NULL,
                PRIMARY KEY (id),
                INDEX IDX_AVIS_USER     (user_id),
                INDEX IDX_AVIS_ACTIVITE (activite_id),
                CONSTRAINT FK_AVIS_USER
                    FOREIGN KEY (user_id)     REFERENCES user     (id) ON DELETE CASCADE,
                CONSTRAINT FK_AVIS_ACTIVITE
                    FOREIGN KEY (activite_id) REFERENCES activites (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS avis');
        $this->addSql('DROP TABLE IF EXISTS participation_evenement');
        $this->addSql('DROP TABLE IF EXISTS evenement');
        $this->addSql('ALTER TABLE activites ADD date_prevue DATE DEFAULT NULL');
    }
}
