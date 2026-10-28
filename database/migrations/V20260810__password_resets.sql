-- Migration: create password_resets table for password recovery tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER,
    email TEXT NOT NULL,
    token TEXT NOT NULL UNIQUE, -- hash SHA-256 hex do token; o token cru nunca é armazenado
    expires_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets(email);