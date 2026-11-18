-- Ejecutar despues de database.sql.
-- Agrega proyectos y relaciona conversaciones sin modificar el script base.

CREATE TABLE IF NOT EXISTS projects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(140) NOT NULL,
  description VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY projects_user_status_index (user_id, status),
  KEY projects_updated_at_index (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE conversations
  ADD COLUMN IF NOT EXISTS project_id BIGINT UNSIGNED NULL AFTER user_id;

ALTER TABLE conversations
  ADD INDEX IF NOT EXISTS conversations_project_id_index (project_id);

ALTER TABLE conversations
  ADD INDEX IF NOT EXISTS conversations_user_project_status_index (user_id, project_id, status);
