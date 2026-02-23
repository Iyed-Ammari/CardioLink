<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223040102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE message_reaction (id INT AUTO_INCREMENT NOT NULL, emoji VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, message_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_ADF1C3E6537A1329 (message_id), INDEX IDX_ADF1C3E6A76ED395 (user_id), UNIQUE INDEX unique_message_user_reaction (message_id, user_id, emoji), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE message_reaction ADD CONSTRAINT FK_ADF1C3E6537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_reaction ADD CONSTRAINT FK_ADF1C3E6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message_reaction DROP FOREIGN KEY FK_ADF1C3E6537A1329');
        $this->addSql('ALTER TABLE message_reaction DROP FOREIGN KEY FK_ADF1C3E6A76ED395');
        $this->addSql('DROP TABLE message_reaction');
    }
}
