<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250301000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and refresh_tokens tables for auth service.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');

        $this->addSql('
            CREATE TABLE users (
                id UUID NOT NULL DEFAULT uuid_generate_v4(),
                email VARCHAR(180) NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                roles JSON NOT NULL DEFAULT \'["ROLE_USER"]\',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            )
        ');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERS_EMAIL ON users (email)');

        $this->addSql('
            CREATE TABLE refresh_tokens (
                id UUID NOT NULL DEFAULT uuid_generate_v4(),
                user_id UUID NOT NULL,
                token VARCHAR(512) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            )
        ');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_REFRESH_TOKENS_TOKEN ON refresh_tokens (token)');
        $this->addSql('CREATE INDEX idx_refresh_token_user_id ON refresh_tokens (user_id)');

        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN refresh_tokens.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN refresh_tokens.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS refresh_tokens');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
