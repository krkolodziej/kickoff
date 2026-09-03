<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903085047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organizations and their memberships.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE organization_memberships (
                  id INT AUTO_INCREMENT NOT NULL,
                  role VARCHAR(10) NOT NULL,
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  organization_id INT NOT NULL,
                  user_id INT NOT NULL,
                  INDEX IDX_B606E30D32C8A3DE (organization_id),
                  INDEX IDX_B606E30DA76ED395 (user_id),
                  UNIQUE INDEX uniq_membership_organization_user (organization_id, user_id),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                CREATE TABLE organizations (
                  id INT AUTO_INCREMENT NOT NULL,
                  name VARCHAR(150) NOT NULL,
                  slug VARCHAR(64) NOT NULL,
                  created_at DATETIME NOT NULL COMMENT 'UTC',
                  updated_at DATETIME NOT NULL COMMENT 'UTC',
                  created_by_id INT NOT NULL,
                  INDEX IDX_427C1C7FB03A8386 (created_by_id),
                  UNIQUE INDEX uniq_organization_slug (slug),
                  PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  organization_memberships
                ADD
                  CONSTRAINT FK_B606E30D32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  organization_memberships
                ADD
                  CONSTRAINT FK_B606E30DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  organizations
                ADD
                  CONSTRAINT FK_427C1C7FB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE RESTRICT
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_memberships DROP FOREIGN KEY FK_B606E30D32C8A3DE');
        $this->addSql('ALTER TABLE organization_memberships DROP FOREIGN KEY FK_B606E30DA76ED395');
        $this->addSql('ALTER TABLE organizations DROP FOREIGN KEY FK_427C1C7FB03A8386');
        $this->addSql('DROP TABLE organization_memberships');
        $this->addSql('DROP TABLE organizations');
    }
}
