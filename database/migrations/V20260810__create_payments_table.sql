-- Migration: create payments table for recording external gateway identifiers
CREATE TABLE IF NOT EXISTS payments (
    id SERIAL PRIMARY KEY,
    pedido_id INTEGER NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
    gateway VARCHAR(50) NOT NULL,
    stripe_session_id TEXT,
    stripe_payment_intent_id TEXT,
    amount_cents INTEGER,
    currency VARCHAR(10) DEFAULT 'brl',
    status VARCHAR(50),
    metadata JSONB,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITHOUT TIME ZONE
);

CREATE INDEX IF NOT EXISTS idx_payments_pedido_id ON payments(pedido_id);
CREATE INDEX IF NOT EXISTS idx_payments_gateway ON payments(gateway);