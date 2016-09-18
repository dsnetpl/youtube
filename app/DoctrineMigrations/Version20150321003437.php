<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20150321003437 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE Task_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE queue_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE Task (id INT NOT NULL, type VARCHAR(255) NOT NULL, data TEXT NOT NULL, log TEXT DEFAULT NULL, tube VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE queue (id INT NOT NULL, hash VARCHAR(255) NOT NULL, format VARCHAR(255) NOT NULL, filesize INT NOT NULL, width INT NOT NULL, height INT NOT NULL, downloads INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by INT NOT NULL, progress INT NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_download TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, log TEXT NOT NULL, filename TEXT NOT NULL, title TEXT NOT NULL, PRIMARY KEY(id))');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP SEQUENCE Task_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE queue_id_seq CASCADE');
        $this->addSql('DROP TABLE Task');
        $this->addSql('DROP TABLE queue');
    }
}
