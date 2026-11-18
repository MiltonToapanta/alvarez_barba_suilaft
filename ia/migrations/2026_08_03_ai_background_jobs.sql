-- Ejecutar despues de las migraciones anteriores.
-- Agrega cola de trabajos para evitar timeouts del navegador/hosting mientras la IA genera.

CREATE TABLE IF NOT EXISTS ai_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  conversation_id BIGINT UNSIGNED NOT NULL,
  prompt LONGTEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'queued',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  http_status SMALLINT UNSIGNED NULL,
  latency_ms INT UNSIGNED NULL,
  response_body LONGTEXT NULL,
  answer LONGTEXT NULL,
  error_message TEXT NULL,
  started_at TIMESTAMP NULL DEFAULT NULL,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ai_jobs_user_status_index (user_id, status, created_at),
  KEY ai_jobs_conversation_index (conversation_id, status),
  KEY ai_jobs_created_index (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
