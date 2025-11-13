<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112201943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F778419EB6921');
        $this->addSql('DROP INDEX IDX_C42F778419EB6921 ON report');
        $this->addSql('ALTER TABLE report CHANGE generated_at generated_at DATETIME NOT NULL, CHANGE client_id admin_id INT NOT NULL');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784642B8210 FOREIGN KEY (admin_id) REFERENCES admin (id)');
        $this->addSql('CREATE INDEX IDX_C42F7784642B8210 ON report (admin_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F7784642B8210');
        $this->addSql('DROP INDEX IDX_C42F7784642B8210 ON report');
        $this->addSql('ALTER TABLE report CHANGE generated_at generated_at DATE NOT NULL, CHANGE admin_id client_id INT NOT NULL');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F778419EB6921 FOREIGN KEY (client_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_C42F778419EB6921 ON report (client_id)');
    }
}
