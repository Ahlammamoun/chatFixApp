<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101133408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE speciality (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_F3D7A08E5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE professional ADD speciality_id INT NOT NULL, DROP speciality');
        $this->addSql('ALTER TABLE professional ADD CONSTRAINT FK_B3B573AA3B5A08D7 FOREIGN KEY (speciality_id) REFERENCES speciality (id)');
        $this->addSql('CREATE INDEX IDX_B3B573AA3B5A08D7 ON professional (speciality_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE professional DROP FOREIGN KEY FK_B3B573AA3B5A08D7');
        $this->addSql('DROP TABLE speciality');
        $this->addSql('DROP INDEX IDX_B3B573AA3B5A08D7 ON professional');
        $this->addSql('ALTER TABLE professional ADD speciality JSON NOT NULL COMMENT \'(DC2Type:json)\', DROP speciality_id');
    }
}
