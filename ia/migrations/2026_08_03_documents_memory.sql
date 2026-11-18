-- Ejecutar despues de database.sql y de migrations/2026_08_03_projects_and_chat_threads.sql.
-- Agrega documentos para que el chat pueda responder usando archivos cargados.

CREATE TABLE IF NOT EXISTS documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NULL,
  extension VARCHAR(16) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  content_text LONGTEXT NULL,
  extraction_status VARCHAR(30) NOT NULL DEFAULT 'ready',
  extraction_error VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY documents_user_project_index (user_id, project_id, status, created_at),
  KEY documents_user_conversation_index (user_id, conversation_id, status, created_at),
  KEY documents_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_chunks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  chunk_index INT UNSIGNED NOT NULL DEFAULT 0,
  content LONGTEXT NOT NULL,
  char_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY document_chunks_document_index (document_id, chunk_index),
  KEY document_chunks_user_index (user_id, document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
