<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231101210403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create index on a jobs project ID and commit';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX IDX_FBD8E0F8166D1F9C4ED42EAD ON job (project_id, commit)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_FBD8E0F8166D1F9C4ED42EAD ON job');
    }
}
