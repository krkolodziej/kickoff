<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903152604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The season calendar.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE fixtures (
                  id INT AUTO_INCREMENT NOT NULL,
                  round_number SMALLINT NOT NULL,
                  leg SMALLINT NOT NULL,
                  kick_off_at DATETIME DEFAULT NULL COMMENT 'UTC',
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  season_id INT NOT NULL,
                  home_team_id INT NOT NULL,
                  away_team_id INT NOT NULL,
                  INDEX IDX_5CB9E5344EC001D1 (season_id),
                  INDEX IDX_5CB9E5349C4C13F6 (home_team_id),
                  INDEX IDX_5CB9E53445185D02 (away_team_id),
                  INDEX idx_fixture_season_round (season_id, round_number),
                  UNIQUE INDEX uniq_fixture_season_direction (
                    season_id, home_team_id, away_team_id
                  ),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  fixtures
                ADD
                  CONSTRAINT FK_5CB9E5344EC001D1 FOREIGN KEY (season_id) REFERENCES seasons (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  fixtures
                ADD
                  CONSTRAINT FK_5CB9E5349C4C13F6 FOREIGN KEY (home_team_id) REFERENCES teams (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  fixtures
                ADD
                  CONSTRAINT FK_5CB9E53445185D02 FOREIGN KEY (away_team_id) REFERENCES teams (id) ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fixtures DROP FOREIGN KEY FK_5CB9E5344EC001D1');
        $this->addSql('ALTER TABLE fixtures DROP FOREIGN KEY FK_5CB9E5349C4C13F6');
        $this->addSql('ALTER TABLE fixtures DROP FOREIGN KEY FK_5CB9E53445185D02');
        $this->addSql('DROP TABLE fixtures');
    }
}
