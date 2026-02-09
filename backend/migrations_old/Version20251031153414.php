<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251031153414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add company_name to professional (safe if table/column already exists).';
    }

    public function up(Schema $schema): void
    {
        // 1) table professional existe ?
        $this->addSql("
            SET @table_exists := (
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'professional'
            );
        ");

        // 2) colonne company_name existe ?
        $this->addSql("
            SET @col_exists := (
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'professional'
                  AND COLUMN_NAME = 'company_name'
            );
        ");

        // 3) exécuter ALTER seulement si table existe ET colonne absente
        $this->addSql("
            SET @sql := IF(@table_exists = 1 AND @col_exists = 0,
                'ALTER TABLE professional ADD company_name VARCHAR(255) DEFAULT NULL',
                'SELECT 1'
            );
        ");

        $this->addSql("PREPARE stmt FROM @sql;");
        $this->addSql("EXECUTE stmt;");
        $this->addSql("DEALLOCATE PREPARE stmt;");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            SET @table_exists := (
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'professional'
            );
        ");

        $this->addSql("
            SET @col_exists := (
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'professional'
                  AND COLUMN_NAME = 'company_name'
            );
        ");

        $this->addSql("
            SET @sql := IF(@table_exists = 1 AND @col_exists = 1,
                'ALTER TABLE professional DROP company_name',
                'SELECT 1'
            );
        ");

        $this->addSql("PREPARE stmt FROM @sql;");
        $this->addSql("EXECUTE stmt;");
        $this->addSql("DEALLOCATE PREPARE stmt;");
    }
}
