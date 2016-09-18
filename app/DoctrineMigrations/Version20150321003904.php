<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20150321003904 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE queue ALTER finished_at DROP NOT NULL');
        $this->addSql('ALTER TABLE queue ALTER deleted_at DROP NOT NULL');
        $this->addSql('ALTER TABLE queue ALTER last_download DROP NOT NULL');
        $this->addSql('ALTER TABLE queue ALTER log DROP NOT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE queue ALTER finished_at SET NOT NULL');
        $this->addSql('ALTER TABLE queue ALTER deleted_at SET NOT NULL');
        $this->addSql('ALTER TABLE queue ALTER last_download SET NOT NULL');
        $this->addSql('ALTER TABLE queue ALTER log SET NOT NULL');
    }
}
