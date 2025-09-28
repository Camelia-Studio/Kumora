<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250928195528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_actions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, action_type VARCHAR(50) NOT NULL, description VARCHAR(500) NOT NULL, target_path VARCHAR(255) DEFAULT NULL, old_value VARCHAR(255) DEFAULT NULL, new_value VARCHAR(255) DEFAULT NULL, metadata CLOB DEFAULT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_5D45EFE5A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_5D45EFE5A76ED395 ON user_actions (user_id)');
        $this->addSql('CREATE INDEX idx_user_created ON user_actions (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_action_type ON user_actions (action_type)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE user_actions');
    }
}
