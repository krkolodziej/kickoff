<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903160855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Match state on the fixture, and the events it is derived from.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE match_events (
                  id INT AUTO_INCREMENT NOT NULL,
                  type VARCHAR(16) NOT NULL,
                  minute SMALLINT NOT NULL,
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  fixture_id INT NOT NULL,
                  team_id INT NOT NULL,
                  player_id INT NOT NULL,
                  related_player_id INT DEFAULT NULL,
                  INDEX IDX_F2E8AE4BE524616D (fixture_id),
                  INDEX IDX_F2E8AE4B296CD8AE (team_id),
                  INDEX IDX_F2E8AE4B99E6F5DF (player_id),
                  INDEX IDX_F2E8AE4B3127A9C4 (related_player_id),
                  INDEX idx_event_fixture_type (fixture_id, type),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  match_events
                ADD
                  CONSTRAINT FK_F2E8AE4BE524616D FOREIGN KEY (fixture_id) REFERENCES fixtures (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  match_events
                ADD
                  CONSTRAINT FK_F2E8AE4B296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE RESTRICT
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  match_events
                ADD
                  CONSTRAINT FK_F2E8AE4B99E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE RESTRICT
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  match_events
                ADD
                  CONSTRAINT FK_F2E8AE4B3127A9C4 FOREIGN KEY (related_player_id) REFERENCES players (id) ON DELETE RESTRICT
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  fixtures
                ADD
                  status VARCHAR(16) DEFAULT 'SCHEDULED' NOT NULL,
                ADD
                  home_score SMALLINT DEFAULT 0 NOT NULL,
                ADD
                  away_score SMALLINT DEFAULT 0 NOT NULL,
                ADD
                  started_at DATETIME DEFAULT NULL COMMENT 'UTC',
                ADD
                  finished_at DATETIME DEFAULT NULL COMMENT 'UTC'
            SQL);
        $this->addSql('CREATE INDEX idx_fixture_season_status ON fixtures (season_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE match_events DROP FOREIGN KEY FK_F2E8AE4BE524616D');
        $this->addSql('ALTER TABLE match_events DROP FOREIGN KEY FK_F2E8AE4B296CD8AE');
        $this->addSql('ALTER TABLE match_events DROP FOREIGN KEY FK_F2E8AE4B99E6F5DF');
        $this->addSql('ALTER TABLE match_events DROP FOREIGN KEY FK_F2E8AE4B3127A9C4');
        $this->addSql('DROP TABLE match_events');
        $this->addSql('DROP INDEX idx_fixture_season_status ON fixtures');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  fixtures
                DROP
                  status,
                DROP
                  home_score,
                DROP
                  away_score,
                DROP
                  started_at,
                DROP
                  finished_at
            SQL);
    }
}
