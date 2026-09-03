<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903074457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounts and their refresh tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE refresh_tokens (
                  refresh_token VARCHAR(128) NOT NULL,
                  username VARCHAR(255) NOT NULL,
                  valid DATETIME NOT NULL,
                  id INT AUTO_INCREMENT NOT NULL,
                  UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                CREATE TABLE users (
                  id INT AUTO_INCREMENT NOT NULL,
                  email VARCHAR(180) NOT NULL,
                  password VARCHAR(255) NOT NULL,
                  first_name VARCHAR(100) NOT NULL,
                  last_name VARCHAR(100) NOT NULL,
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  UNIQUE INDEX uniq_user_email (email),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE users');
    }
}
