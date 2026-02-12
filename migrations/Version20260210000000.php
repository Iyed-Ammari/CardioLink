<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Lieu, RendezVous and Ordonnance tables';
    }

    public function up(Schema $schema): void
    {
        // Create Lieu table
        $this->addSql('CREATE TABLE lieu (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, ville VARCHAR(100) NOT NULL, contact VARCHAR(20) NULL, est_virtuel TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Create RendezVous table
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, patient_id INT NULL, medecin_id INT NULL, lieu_id INT NULL, date_heure DATETIME NOT NULL, statut VARCHAR(50) NOT NULL, type VARCHAR(50) NOT NULL, lien_visio VARCHAR(255) NULL, remarques LONGTEXT NOT NULL, INDEX IDX_65E8AA0A6B899279 (patient_id), INDEX IDX_65E8AA0A62B8FB64 (medecin_id), INDEX IDX_65E8AA0A8D5614B7 (lieu_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Create Ordonnance table
        $this->addSql('CREATE TABLE ordonnance (id INT AUTO_INCREMENT NOT NULL, consultation_id INT NULL, reference VARCHAR(50) NOT NULL, date_creation DATETIME NOT NULL, contenu LONGTEXT NOT NULL, UNIQUE INDEX UNIQ_924534DFA909EDF (consultation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Add foreign key constraints
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A6B899279 FOREIGN KEY (patient_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A62B8FB64 FOREIGN KEY (medecin_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A8D5614B7 FOREIGN KEY (lieu_id) REFERENCES lieu (id)');
        $this->addSql('ALTER TABLE ordonnance ADD CONSTRAINT FK_924534DFA909EDF FOREIGN KEY (consultation_id) REFERENCES rendez_vous (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ordonnance DROP FOREIGN KEY FK_924534DFA909EDF');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A8D5614B7');
        $this->addSql('DROP TABLE ordonnance');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('DROP TABLE lieu');
    }
}
