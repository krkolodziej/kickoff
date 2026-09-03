<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903122517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seasons, the clubs registered for them, and squads.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE roster_entries (
                  id INT AUTO_INCREMENT NOT NULL,
                  shirt_number SMALLINT DEFAULT NULL,
                  position VARCHAR(16) DEFAULT NULL,
                  captain TINYINT NOT NULL,
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  season_team_id INT NOT NULL,
                  player_id INT NOT NULL,
                  INDEX IDX_3B1FDA87C1571F0 (season_team_id),
                  INDEX IDX_3B1FDA8799E6F5DF (player_id),
                  UNIQUE INDEX uniq_roster_squad_player (season_team_id, player_id),
                  UNIQUE INDEX uniq_roster_squad_shirt (season_team_id, shirt_number),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                CREATE TABLE season_teams (
                  id INT AUTO_INCREMENT NOT NULL,
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  season_id INT NOT NULL,
                  team_id INT NOT NULL,
                  INDEX IDX_81F3D5874EC001D1 (season_id),
                  INDEX IDX_81F3D587296CD8AE (team_id),
                  UNIQUE INDEX uniq_season_team (season_id, team_id),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                CREATE TABLE seasons (
                  id INT AUTO_INCREMENT NOT NULL,
                  name VARCHAR(32) NOT NULL,
                  start_date DATE NOT NULL,
                  end_date DATE DEFAULT NULL,
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  league_id INT NOT NULL,
                  INDEX IDX_B4F4301C58AFC4DE (league_id),
                  UNIQUE INDEX uniq_season_league_name (league_id, name),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  roster_entries
                ADD
                  CONSTRAINT FK_3B1FDA87C1571F0 FOREIGN KEY (season_team_id) REFERENCES season_teams (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  roster_entries
                ADD
                  CONSTRAINT FK_3B1FDA8799E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  season_teams
                ADD
                  CONSTRAINT FK_81F3D5874EC001D1 FOREIGN KEY (season_id) REFERENCES seasons (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  season_teams
                ADD
                  CONSTRAINT FK_81F3D587296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  seasons
                ADD
                  CONSTRAINT FK_B4F4301C58AFC4DE FOREIGN KEY (league_id) REFERENCES leagues (id) ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE roster_entries DROP FOREIGN KEY FK_3B1FDA87C1571F0');
        $this->addSql('ALTER TABLE roster_entries DROP FOREIGN KEY FK_3B1FDA8799E6F5DF');
        $this->addSql('ALTER TABLE season_teams DROP FOREIGN KEY FK_81F3D5874EC001D1');
        $this->addSql('ALTER TABLE season_teams DROP FOREIGN KEY FK_81F3D587296CD8AE');
        $this->addSql('ALTER TABLE seasons DROP FOREIGN KEY FK_B4F4301C58AFC4DE');
        $this->addSql('DROP TABLE roster_entries');
        $this->addSql('DROP TABLE season_teams');
        $this->addSql('DROP TABLE seasons');
    }
}
