-- Vacía todas las tablas de la base de datos en el esquema public,
-- reinicia las secuencias y conserva la tabla migrations.
-- Ejecutar dentro de la base servicios_pro.

DO $$
DECLARE
    tablas TEXT;
BEGIN
    SELECT string_agg(format('%I.%I', schemaname, tablename), ', ')
      INTO tablas
      FROM pg_tables
     WHERE schemaname = 'public'
       AND tablename <> 'migrations';

    IF tablas IS NOT NULL THEN
        EXECUTE 'TRUNCATE TABLE ' || tablas || ' RESTART IDENTITY CASCADE';
    END IF;
END
$$;