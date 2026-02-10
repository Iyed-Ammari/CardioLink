<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209125400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Suivi and Intervention tables for monitoring module';
    }

    public function up(Schema $schema): void
    {
        // Create Suivi table
        $this->addSql('CREATE TABLE suivi (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, type_donnee VARCHAR(255) NOT NULL, valeur DOUBLE PRECISION NOT NULL, unite VARCHAR(20) NOT NULL, date_saisie DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', niveau_urgence VARCHAR(50) NOT NULL, INDEX IDX_DA45A6DD6B899279 (patient_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Create Intervention table
        $this->addSql('CREATE TABLE intervention (id INT AUTO_INCREMENT NOT NULL, medecin_id INT NULL, suivi_origine_id INT NULL, type VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, statut VARCHAR(50) NOT NULL, date_planifiee DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', date_completion DATETIME NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_D3D97B002F5A0E14B (suivi_origine_id), INDEX IDX_D3D97B0062B8FB64 (medecin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Add foreign key constraints
        $this->addSql('ALTER TABLE suivi ADD CONSTRAINT FK_DA45A6DD6B899279 FOREIGN KEY (patient_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D3D97B0062B8FB64 FOREIGN KEY (medecin_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D3D97B002F5A0E14B FOREIGN KEY (suivi_origine_id) REFERENCES suivi (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_D3D97B002F5A0E14B');
        $this->addSql('ALTER TABLE suivi DROP FOREIGN KEY FK_DA45A6DD6B899279');
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_D3D97B0062B8FB64');
        $this->addSql('DROP TABLE intervention');
        $this->addSql('DROP TABLE suivi');
    }
}
