-- Migration: invalidação de sessões ativas após troca de senha/reset
-- session_version é incrementado a cada troca de senha; sessões cuja
-- versão gravada em $_SESSION for menor são deslogadas em verificarLogin().
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS session_version BIGINT NOT NULL DEFAULT 0;
