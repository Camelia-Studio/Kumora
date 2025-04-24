<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250424205940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modification des rôles assignés aux dossiers';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__parent_directory AS SELECT id, name, user_created_id, is_public FROM parent_directory
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE parent_directory
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE parent_directory (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, user_created_id INTEGER NOT NULL, is_public BOOLEAN NOT NULL, owner_role_id INTEGER DEFAULT NULL, CONSTRAINT FK_B7336B34F987D8A8 FOREIGN KEY (user_created_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B7336B345A75A473 FOREIGN KEY (owner_role_id) REFERENCES access_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO parent_directory (id, name, user_created_id, is_public) SELECT id, name, user_created_id, is_public FROM __temp__parent_directory
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__parent_directory
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B7336B34F987D8A8 ON parent_directory (user_created_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B7336B345A75A473 ON parent_directory (owner_role_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__parent_directory_permission AS SELECT id, read, write, parent_directory_id FROM parent_directory_permission
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE parent_directory_permission
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE parent_directory_permission (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, read BOOLEAN NOT NULL, write BOOLEAN NOT NULL, parent_directory_id INTEGER NOT NULL, role_id INTEGER DEFAULT NULL, CONSTRAINT FK_F93986627CFA5BB1 FOREIGN KEY (parent_directory_id) REFERENCES parent_directory (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F9398662D60322AC FOREIGN KEY (role_id) REFERENCES access_group (id) NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO parent_directory_permission (id, read, write, parent_directory_id) SELECT id, read, write, parent_directory_id FROM __temp__parent_directory_permission
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__parent_directory_permission
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F93986627CFA5BB1 ON parent_directory_permission (parent_directory_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F9398662D60322AC ON parent_directory_permission (role_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__parent_directory AS SELECT id, name, is_public, user_created_id FROM parent_directory
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE parent_directory
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE parent_directory (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, is_public BOOLEAN NOT NULL, user_created_id INTEGER NOT NULL, owner_role VARCHAR(255) NOT NULL, CONSTRAINT FK_B7336B34F987D8A8 FOREIGN KEY (user_created_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO parent_directory (id, name, is_public, user_created_id) SELECT id, name, is_public, user_created_id FROM __temp__parent_directory
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__parent_directory
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B7336B34F987D8A8 ON parent_directory (user_created_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__parent_directory_permission AS SELECT id, read, write, parent_directory_id FROM parent_directory_permission
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE parent_directory_permission
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE parent_directory_permission (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, read BOOLEAN NOT NULL, write BOOLEAN NOT NULL, parent_directory_id INTEGER NOT NULL, role VARCHAR(255) NOT NULL, CONSTRAINT FK_F93986627CFA5BB1 FOREIGN KEY (parent_directory_id) REFERENCES parent_directory (id) NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO parent_directory_permission (id, read, write, parent_directory_id) SELECT id, read, write, parent_directory_id FROM __temp__parent_directory_permission
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__parent_directory_permission
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F93986627CFA5BB1 ON parent_directory_permission (parent_directory_id)
        SQL);
    }
}
