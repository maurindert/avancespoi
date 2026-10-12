BEGIN;

ALTER TABLE planificacion.metas
    ADD COLUMN IF NOT EXISTS campo_fecha_avance boolean NOT NULL DEFAULT true,
    ADD COLUMN IF NOT EXISTS campo_cantidad boolean NOT NULL DEFAULT true,
    ADD COLUMN IF NOT EXISTS campo_hombres boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS campo_mujeres boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS campo_instituciones boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS campo_dpto_dist_col boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS campo_logros boolean NOT NULL DEFAULT true,
    ADD COLUMN IF NOT EXISTS campo_dificultades boolean NOT NULL DEFAULT true,
    ADD COLUMN IF NOT EXISTS campo_observaciones boolean NOT NULL DEFAULT true;

ALTER TABLE planificacion.avances
    ALTER COLUMN fecha_avance DROP NOT NULL,
    ALTER COLUMN cantidad DROP NOT NULL,
    ALTER COLUMN hombres DROP NOT NULL,
    ALTER COLUMN mujeres DROP NOT NULL,
    ALTER COLUMN instituciones DROP NOT NULL;

ALTER TABLE planificacion.avances
    ADD COLUMN IF NOT EXISTS id_dpto integer,
    ADD COLUMN IF NOT EXISTS id_distrito integer,
    ADD COLUMN IF NOT EXISTS id_colonia integer;

COMMIT;
