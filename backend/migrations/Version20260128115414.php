<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260128115414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rating ADD offer_id INT NOT NULL');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D889262253C674EE FOREIGN KEY (offer_id) REFERENCES offer (id)');
        $this->addSql('CREATE INDEX IDX_D889262253C674EE ON rating (offer_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_offer_user_rating ON rating (offer_id, user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY FK_D889262253C674EE');
        $this->addSql('DROP INDEX IDX_D889262253C674EE ON rating');
        $this->addSql('DROP INDEX uniq_offer_user_rating ON rating');
        $this->addSql('ALTER TABLE rating DROP offer_id');
    }
}
