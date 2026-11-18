-- ============================================================================
-- Migracion NN - <descripcion corta>
-- ============================================================================
-- Nombra el archivo database/NN_descripcion.sql (siguiente numero disponible).
-- Toda migracion de este proyecto debe ser IDEMPOTENTE: correrla dos veces
-- (a proposito o por error, ej. tras un fallo a medias) nunca debe romper
-- nada ni duplicar datos. Patrones usados en este proyecto:

SET NAMES utf8mb4;

-- Tabla nueva: CREATE TABLE IF NOT EXISTS
CREATE TABLE IF NOT EXISTS `tabla_nueva` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Columna nueva en tabla existente: ADD COLUMN IF NOT EXISTS
-- (MariaDB 10.5+/MySQL 8+ lo soportan directo; este proyecto corre en
-- MariaDB 11.8, asi que esta forma es segura)
ALTER TABLE `usuarios` ADD COLUMN IF NOT EXISTS `columna_nueva` varchar(100) DEFAULT NULL AFTER `otra_columna`;

-- Dato de catalogo/semilla: INSERT ... ON DUPLICATE KEY UPDATE
-- (requiere una UNIQUE KEY en la columna que identifica la fila, ej. `nombre`)
INSERT INTO `roles` (`nombre`, `descripcion`, `modulos`)
VALUES ('Rol Nuevo', 'Descripcion', 'kyc,matriz')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`), `modulos` = VALUES(`modulos`);

-- Agregar un modulo a roles existentes sin duplicarlo si ya estaba:
UPDATE `roles`
SET `modulos` = CONCAT_WS(',', NULLIF(`modulos`, ''), 'nuevo_modulo')
WHERE FIND_IN_SET('nuevo_modulo', `modulos`) = 0;
