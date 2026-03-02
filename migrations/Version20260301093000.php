<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add isPinned and isArchived columns to message table
 */
final class Version20260301093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isPinned and isArchived columns to message table for pinning and archiving functionality';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD is_pinned TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE message ADD is_archived TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP COLUMN is_pinned');
        $this->addSql('ALTER TABLE message DROP COLUMN is_archived');
    }
}
