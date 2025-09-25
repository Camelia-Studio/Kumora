<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250925191307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
                ALTER TABLE user ADD COLUMN last_login_at DATETIME DEFAULT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
                CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, roles, password, fullname, access_group_id FROM "user"
            SQL);
        $this->addSql(<<<'SQL'
                DROP TABLE "user"
            SQL);
        $this->addSql(<<<'SQL'
                CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, fullname VARCHAR(255) NOT NULL, access_group_id INTEGER DEFAULT NULL, CONSTRAINT FK_8D93D64993411876 FOREIGN KEY (access_group_id) REFERENCES access_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE)
            SQL);
        $this->addSql(<<<'SQL'
                INSERT INTO "user" (id, email, roles, password, fullname, access_group_id) SELECT id, email, roles, password, fullname, access_group_id FROM __temp__user
            SQL);
        $this->addSql(<<<'SQL'
                DROP TABLE __temp__user
            SQL);
        $this->addSql(<<<'SQL'
                CREATE INDEX IDX_8D93D64993411876 ON "user" (access_group_id)
            SQL);
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)
            SQL);
    }
}
