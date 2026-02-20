<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218173022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

   public function up(Schema $schema): void
{
    // 1. Ajouter la colonne nullable en premier
    $this->addSql('ALTER TABLE `user` ADD created_at DATETIME NULL');
    
    // 2. Remplir les données
    $this->addSql('UPDATE `user` SET created_at = NOW() WHERE created_at IS NULL');
    
    // 3. Rendre NOT NULL
    $this->addSql('ALTER TABLE `user` MODIFY created_at DATETIME NOT NULL');
}
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP created_at');
    }
}
