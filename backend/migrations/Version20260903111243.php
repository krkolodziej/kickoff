<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903111243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Leagues, clubs and players.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE leagues (
                  id INT AUTO_INCREMENT NOT NULL,
                  name VARCHAR(150) NOT NULL,
                  slug VARCHAR(64) NOT NULL,
                  description LONGTEXT NOT NULL,
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  organization_id INT NOT NULL,
                  INDEX IDX_85CE39E32C8A3DE (organization_id),
                  UNIQUE INDEX uniq_league_organization_slug (organization_id, slug),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                CREATE TABLE players (
                  id INT AUTO_INCREMENT NOT NULL,
                  first_name VARCHAR(100) NOT NULL,
                  last_name VARCHAR(100) NOT NULL,
                  date_of_birth DATE DEFAULT NULL,
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  organization_id INT NOT NULL,
                  INDEX IDX_264E43A632C8A3DE (organization_id),
                  INDEX idx_player_organization_last_name (organization_id, last_name),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                CREATE TABLE teams (
                  id INT AUTO_INCREMENT NOT NULL,
                  name VARCHAR(150) NOT NULL,
                  slug VARCHAR(64) NOT NULL,
                  short_name VARCHAR(32) NOT NULL,
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  organization_id INT NOT NULL,
                  INDEX IDX_96C2225832C8A3DE (organization_id),
                  UNIQUE INDEX uniq_team_organization_slug (organization_id, slug),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  leagues
                ADD
                  CONSTRAINT FK_85CE39E32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  players
                ADD
                  CONSTRAINT FK_264E43A632C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  teams
                ADD
                  CONSTRAINT FK_96C2225832C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leagues DROP FOREIGN KEY FK_85CE39E32C8A3DE');
        $this->addSql('ALTER TABLE players DROP FOREIGN KEY FK_264E43A632C8A3DE');
        $this->addSql('ALTER TABLE teams DROP FOREIGN KEY FK_96C2225832C8A3DE');
        $this->addSql('DROP TABLE leagues');
        $this->addSql('DROP TABLE players');
        $this->addSql('DROP TABLE teams');
    }
}
