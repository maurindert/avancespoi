BEGIN;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM planificacion.avances
        WHERE cantidad <> trunc(cantidad)
           OR cantidad > 2147483647
           OR cantidad < -2147483648
    ) THEN
        RAISE EXCEPTION 'Hay avances con cantidad decimal o fuera del rango integer. Revisar antes de migrar.';
    END IF;
END
$$;

ALTER TABLE planificacion.avances
    ALTER COLUMN cantidad TYPE integer USING cantidad::integer;

COMMIT;
