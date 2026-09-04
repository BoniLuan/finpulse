-- Normalized, idempotent storage for economic time-series observations.
CREATE TABLE IF NOT EXISTS indicators (
    key           TEXT PRIMARY KEY,
    label         TEXT NOT NULL,
    source        TEXT NOT NULL,
    external_code TEXT,
    unit          TEXT NOT NULL,
    frequency     TEXT NOT NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS indicator_observations (
    indicator_key TEXT NOT NULL REFERENCES indicators(key) ON DELETE CASCADE,
    observed_on   DATE NOT NULL,
    value         NUMERIC NOT NULL,
    collected_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (indicator_key, observed_on)
);
CREATE INDEX IF NOT EXISTS indicator_observations_date_idx
    ON indicator_observations(observed_on DESC);

INSERT INTO indicators (key, label, source, external_code, unit, frequency)
VALUES
    ('selic', 'Selic target rate (annual)', 'BACEN SGS', '432', 'percent_per_year', 'daily'),
    ('ipca', 'IPCA inflation (monthly)', 'BACEN SGS', '433', 'percent_per_month', 'monthly')
ON CONFLICT (key) DO UPDATE SET
    label = EXCLUDED.label,
    source = EXCLUDED.source,
    external_code = EXCLUDED.external_code,
    unit = EXCLUDED.unit,
    frequency = EXCLUDED.frequency;
