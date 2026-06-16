-- FinPulse initial schema (PostgreSQL).
CREATE TABLE IF NOT EXISTS users (
    id            UUID PRIMARY KEY,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS alerts (
    id         UUID PRIMARY KEY,
    user_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    indicator  TEXT NOT NULL,
    operator   TEXT NOT NULL CHECK (operator IN ('>', '<')),
    threshold  NUMERIC NOT NULL,
    channel    TEXT NOT NULL DEFAULT 'log',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS alerts_user_id_idx ON alerts(user_id);

CREATE TABLE IF NOT EXISTS query_logs (
    id          UUID PRIMARY KEY,
    question    TEXT NOT NULL,
    intent_type TEXT NOT NULL,
    data        JSONB NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS query_logs_created_at_idx ON query_logs(created_at);
