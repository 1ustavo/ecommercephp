-- Migration: tokens de reset passam a ser armazenados como hash SHA-256.
-- Tokens em texto plano criados antes desta mudança tornam-se inválidos
-- (não correspondem a nenhum hash); o usuário simplesmente solicita um novo.
COMMENT ON COLUMN password_resets.token IS 'SHA-256 hex do token de reset; o token cru nunca e armazenado';
