<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250123225212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__parent_directory AS SELECT id, name, owner_role FROM parent_directory');
        $this->addSql('DROP TABLE parent_directory');
        $this->addSql('CREATE TABLE parent_directory (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, owner_role VARCHAR(255) NOT NULL, user_created_id INTEGER NOT NULL, CONSTRAINT FK_B7336B34F987D8A8 FOREIGN KEY (user_created_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO parent_directory (id, name, owner_role) SELECT id, name, owner_role FROM __temp__parent_directory');
        $this->addSql('DROP TABLE __temp__parent_directory');
        $this->addSql('CREATE INDEX IDX_B7336B34F987D8A8 ON parent_directory (user_created_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__parent_directory AS SELECT id, name, owner_role FROM parent_directory');
        $this->addSql('DROP TABLE parent_directory');
        $this->addSql('CREATE TABLE parent_directory (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, owner_role VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO parent_directory (id, name, owner_role) SELECT id, name, owner_role FROM __temp__parent_directory');
        $this->addSql('DROP TABLE __temp__parent_directory');
    }
}
