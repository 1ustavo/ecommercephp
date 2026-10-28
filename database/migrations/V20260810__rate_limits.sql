-- Migration: simple rate_limits table for basic rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    id SERIAL PRIMARY KEY,
    key_name TEXT NOT NULL UNIQUE,
    count INTEGER NOT NULL DEFAULT 0,
    window_start TIMESTAMP WITHOUT TIME ZONE NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_key ON rate_limits(key_name);