-- Verificación de catálogos UAFE para SUILAFT de constructoras. Solo LECTURA. Ejecútela en phpMyAdmin
-- o dentro del script temporal de QA. Cada consulta debe devolver 0 filas (salvo los conteos).
-- Los comentarios no llevan punto y coma para poder partir el archivo por sentencias.

-- 1. Cantones: códigos repetidos (deben ser únicos para el RESU)
SELECT TRIM(cod_canton) AS cod, GROUP_CONCAT(canton SEPARATOR ' / ') AS cantones, COUNT(*) AS n
FROM canton WHERE cod_canton IS NOT NULL GROUP BY TRIM(cod_canton) HAVING n > 1;

-- 2. Cantones sin código, con espacios/saltos de línea o que no son de 4 dígitos
SELECT id, cod_canton, canton, id_provincia FROM canton
WHERE cod_canton IS NULL OR cod_canton REGEXP '[[:space:]]' OR cod_canton NOT REGEXP '^[0-9]{4}$';

-- 3. Código de cantón que no empieza con el código de su provincia (INEC: PPCC)
--    (provincias.id coincide con el código INEC de provincia en este catálogo, las zonas no delimitadas son 90xx y la provincia 26 «N/A» usa 0000)
SELECT c.id, c.cod_canton, c.canton, p.id AS prov, p.provincia FROM canton c JOIN provincias p ON p.id = c.id_provincia
WHERE c.id_provincia NOT IN (25, 26) AND LEFT(TRIM(c.cod_canton), 2) <> LPAD(p.id, 2, '0');

-- 4. Ciudad: códigos repetidos o distintos al del cantón homónimo
SELECT TRIM(cod_canton) AS cod, GROUP_CONCAT(ciudad SEPARATOR ' / ') AS ciudades FROM ciudad GROUP BY TRIM(cod_canton) HAVING COUNT(*) > 1;

-- 5. Países sin código ISO alfa-3 válido
SELECT id, cod, pais FROM paises WHERE cod IS NULL OR TRIM(cod) NOT REGEXP '^[A-Z]{3}$';

-- 6. Conteos de referencia (DGL, nov-2026): actividad 3050 · paises 247 · provincias 26 · canton 225 · ciudad 222 · moneda 178 · tipo_bien 22 · tipo_transaccion 9
SELECT 'actividad_economica' t, COUNT(*) n FROM actividad_economica UNION ALL SELECT 'paises', COUNT(*) FROM paises
UNION ALL SELECT 'provincias', COUNT(*) FROM provincias UNION ALL SELECT 'canton', COUNT(*) FROM canton UNION ALL SELECT 'ciudad', COUNT(*) FROM ciudad
UNION ALL SELECT 'moneda', COUNT(*) FROM moneda UNION ALL SELECT 'tipo_bien', COUNT(*) FROM tipo_bien UNION ALL SELECT 'tipo_transaccion', COUNT(*) FROM tipo_transaccion;

-- 7. Tipos de transacción del sector esperados: 91 92 94 95 96 97 98 99 103
SELECT codigo, descripcion FROM tipo_transaccion ORDER BY codigo;
