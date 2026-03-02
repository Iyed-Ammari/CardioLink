<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260302005755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rendezvous (id INT AUTO_INCREMENT NOT NULL, date_heure DATETIME NOT NULL, statut VARCHAR(50) NOT NULL, type VARCHAR(50) NOT NULL, lien_visio VARCHAR(255) DEFAULT NULL, remarques LONGTEXT NOT NULL, patient_id INT DEFAULT NULL, medecin_id INT DEFAULT NULL, lieu_id INT DEFAULT NULL, INDEX IDX_C09A9BA86B899279 (patient_id), INDEX IDX_C09A9BA84F31A84 (medecin_id), INDEX IDX_C09A9BA86AB213CC (lieu_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE rendezvous ADD CONSTRAINT FK_C09A9BA86B899279 FOREIGN KEY (patient_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE rendezvous ADD CONSTRAINT FK_C09A9BA84F31A84 FOREIGN KEY (medecin_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE rendezvous ADD CONSTRAINT FK_C09A9BA86AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id)');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A6B899279');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A4F31A84');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A6AB213CC');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('ALTER TABLE ordonnance DROP FOREIGN KEY FK_924B326C62FF6CDF');
        $this->addSql('ALTER TABLE ordonnance ADD CONSTRAINT FK_924B326C62FF6CDF FOREIGN KEY (consultation_id) REFERENCES rendezvous (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, date_heure DATETIME NOT NULL, statut VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, type VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, lien_visio VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, remarques LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, patient_id INT DEFAULT NULL, medecin_id INT DEFAULT NULL, lieu_id INT DEFAULT NULL, INDEX IDX_65E8AA0A4F31A84 (medecin_id), INDEX IDX_65E8AA0A6AB213CC (lieu_id), INDEX IDX_65E8AA0A6B899279 (patient_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A6B899279 FOREIGN KEY (patient_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A4F31A84 FOREIGN KEY (medecin_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A6AB213CC FOREIGN KEY (lieu_id) REFERENCES lieu (id)');
        $this->addSql('ALTER TABLE rendezvous DROP FOREIGN KEY FK_C09A9BA86B899279');
        $this->addSql('ALTER TABLE rendezvous DROP FOREIGN KEY FK_C09A9BA84F31A84');
        $this->addSql('ALTER TABLE rendezvous DROP FOREIGN KEY FK_C09A9BA86AB213CC');
        $this->addSql('DROP TABLE rendezvous');
        $this->addSql('ALTER TABLE ordonnance DROP FOREIGN KEY FK_924B326C62FF6CDF');
        $this->addSql('ALTER TABLE ordonnance ADD CONSTRAINT FK_924B326C62FF6CDF FOREIGN KEY (consultation_id) REFERENCES rendez_vous (id)');
    }
}
