<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260128114053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY FK_D8892622A32EFC6');
        $this->addSql('DROP INDEX IDX_D8892622A32EFC6 ON rating');
        $this->addSql('ALTER TABLE rating ADD professional_id INT NOT NULL, DROP rating_id, CHANGE user_id user_id INT NOT NULL, CHANGE value value DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D8892622DB77003 FOREIGN KEY (professional_id) REFERENCES professional (id)');
        $this->addSql('CREATE INDEX IDX_D8892622DB77003 ON rating (professional_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY FK_D8892622DB77003');
        $this->addSql('DROP INDEX IDX_D8892622DB77003 ON rating');
        $this->addSql('ALTER TABLE rating ADD rating_id INT DEFAULT NULL, DROP professional_id, CHANGE user_id user_id INT DEFAULT NULL, CHANGE value value DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D8892622A32EFC6 FOREIGN KEY (rating_id) REFERENCES professional (id)');
        $this->addSql('CREATE INDEX IDX_D8892622A32EFC6 ON rating (rating_id)');
    }
}
