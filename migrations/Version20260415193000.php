<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set destination foreign keys to ON DELETE CASCADE for voyage and hebergement';
    }

    public function up(Schema $schema): void
    {
        $this->dropForeignKeyIfExists('voyage', 'id_destination', 'destination');
        $this->dropForeignKeyIfExists('hebergement', 'destination_hebergement', 'destination');

        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D895526D4F35D FOREIGN KEY (id_destination) REFERENCES destination (id_destination) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT FK_4423A5E0A76ED395 FOREIGN KEY (destination_hebergement) REFERENCES destination (id_destination) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->dropForeignKeyIfExists('voyage', 'id_destination', 'destination');
        $this->dropForeignKeyIfExists('hebergement', 'destination_hebergement', 'destination');

        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D895526D4F35D FOREIGN KEY (id_destination) REFERENCES destination (id_destination)');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT FK_4423A5E0A76ED395 FOREIGN KEY (destination_hebergement) REFERENCES destination (id_destination)');
    }

    private function dropForeignKeyIfExists(string $table, string $column, string $referencedTable): void
    {
        $sql = <<<'SQL'
SELECT kcu.CONSTRAINT_NAME
FROM information_schema.KEY_COLUMN_USAGE kcu
JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
 AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
WHERE kcu.TABLE_SCHEMA = DATABASE()
  AND kcu.TABLE_NAME = :tableName
  AND kcu.COLUMN_NAME = :columnName
  AND kcu.REFERENCED_TABLE_NAME = :referencedTable
LIMIT 1
SQL;

        $fkName = $this->connection->fetchOne($sql, [
            'tableName' => $table,
            'columnName' => $column,
            'referencedTable' => $referencedTable,
        ]);

        if (is_string($fkName) && $fkName !== '') {
            $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY `%s`', $table, $fkName));
        }
    }
}
