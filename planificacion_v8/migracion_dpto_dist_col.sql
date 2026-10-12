BEGIN;

ALTER TABLE planificacion.metas
    ADD COLUMN IF NOT EXISTS campo_dpto_dist_col boolean NOT NULL DEFAULT false;

ALTER TABLE planificacion.avances
    ADD COLUMN IF NOT EXISTS id_dpto integer,
    ADD COLUMN IF NOT EXISTS id_distrito integer,
    ADD COLUMN IF NOT EXISTS id_colonia integer;

CREATE INDEX IF NOT EXISTS avances_dpto_dist_col_idx
    ON planificacion.avances (id_dpto, id_distrito, id_colonia);

COMMIT;
