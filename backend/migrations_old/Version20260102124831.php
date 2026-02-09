<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260102124831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE professional ADD assurance_doc VARCHAR(255) DEFAULT NULL, ADD identity_doc VARCHAR(255) DEFAULT NULL, ADD pro_title_doc VARCHAR(255) DEFAULT NULL, ADD rib_iban VARCHAR(34) DEFAULT NULL, ADD rib_doc VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE professional DROP assurance_doc, DROP identity_doc, DROP pro_title_doc, DROP rib_iban, DROP rib_doc');
    }
}
