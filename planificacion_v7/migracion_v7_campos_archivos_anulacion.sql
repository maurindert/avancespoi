BEGIN;

-- Los campo_* de metas determinan que controles se muestran en el formulario.
ALTER TABLE planificacion.metas
    ADD COLUMN IF NOT EXISTS campo_expediente boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS campo_resolucion boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS campo_excel boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS campo_pdf boolean NOT NULL DEFAULT false,
    ADD COLUMN IF NOT EXISTS activo boolean NOT NULL DEFAULT true;

-- Los valores cargados se guardan en avances. Los archivos se almacenan en BYTEA
-- junto con el nombre y tipo MIME originales para permitir su descarga posterior.
ALTER TABLE planificacion.avances
    ADD COLUMN IF NOT EXISTS expediente text,
    ADD COLUMN IF NOT EXISTS resolucion text,
    ADD COLUMN IF NOT EXISTS excel_archivo bytea,
    ADD COLUMN IF NOT EXISTS excel_nombre text,
    ADD COLUMN IF NOT EXISTS excel_tipo text,
    ADD COLUMN IF NOT EXISTS pdf_archivo bytea,
    ADD COLUMN IF NOT EXISTS pdf_nombre text,
    ADD COLUMN IF NOT EXISTS pdf_tipo text,
    ADD COLUMN IF NOT EXISTS activo boolean NOT NULL DEFAULT true;

-- Protege instalaciones donde la columna activo pudo haberse creado antes sin DEFAULT.
UPDATE planificacion.metas SET activo = true WHERE activo IS NULL;
UPDATE planificacion.avances SET activo = true WHERE activo IS NULL;

ALTER TABLE planificacion.metas
    ALTER COLUMN activo SET DEFAULT true,
    ALTER COLUMN activo SET NOT NULL;

ALTER TABLE planificacion.avances
    ALTER COLUMN activo SET DEFAULT true,
    ALTER COLUMN activo SET NOT NULL;

COMMIT;
