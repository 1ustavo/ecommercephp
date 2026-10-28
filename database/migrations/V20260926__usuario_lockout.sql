-- Migration: lockout por conta (contador de falhas + bloqueio temporário)
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS failed_attempts INT NOT NULL DEFAULT 0;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL;
