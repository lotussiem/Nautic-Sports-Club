<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250404190042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity DROP FOREIGN KEY activity_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity DROP FOREIGN KEY activity_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity ADD difficulty_level VARCHAR(50) NOT NULL, ADD image_file VARCHAR(255) NOT NULL, DROP difficultyLevel, DROP imageFile, CHANGE id id INT NOT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE place place VARCHAR(10) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity ADD CONSTRAINT FK_AC74095A12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX category_id ON activity
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_AC74095A12469DE2 ON activity (category_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity ADD CONSTRAINT activity_ibfk_1 FOREIGN KEY (category_id) REFERENCES category (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE category ADD image_file VARCHAR(255) NOT NULL, DROP imageFile, CHANGE id id INT NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A12469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A12469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity ADD difficultyLevel VARCHAR(50) DEFAULT NULL, ADD imageFile VARCHAR(255) DEFAULT NULL, DROP difficulty_level, DROP image_file, CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description VARCHAR(255) DEFAULT NULL, CHANGE place place VARCHAR(10) DEFAULT 'Tunisie' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity ADD CONSTRAINT activity_ibfk_1 FOREIGN KEY (category_id) REFERENCES category (id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_ac74095a12469de2 ON activity
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX category_id ON activity (category_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity ADD CONSTRAINT FK_AC74095A12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE category ADD imageFile VARCHAR(255) DEFAULT NULL, DROP image_file, CHANGE id id INT AUTO_INCREMENT NOT NULL
        SQL);
    }
}
