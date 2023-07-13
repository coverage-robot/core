<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230712193835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD graph_token VARCHAR(100) NOT NULL, CHANGE token upload_token VARCHAR(100) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE766AEC24 ON project (upload_token)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EEDB088D03 ON project (graph_token)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE92C4739CCF60E67C5CFE57CD ON project (provider, owner, repository)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_2FB3D0EE766AEC24 ON project');
        $this->addSql('DROP INDEX UNIQ_2FB3D0EEDB088D03 ON project');
        $this->addSql('DROP INDEX UNIQ_2FB3D0EE92C4739CCF60E67C5CFE57CD ON project');
        $this->addSql('ALTER TABLE project ADD token VARCHAR(100) NOT NULL, DROP upload_token, DROP graph_token');
    }
}
