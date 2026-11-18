-- Base `suilaft_ai` del módulo de Inteligencia Artificial (la crea tools/crear_base_ia.sh).
-- Compatible con MySQL 5.7/8 y MariaDB. Las tablas de proyectos, documentos, cola de IA,
-- skills y RAG las crea la aplicacion automaticamente al primer acceso (includes/schema.php).

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY roles_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  monthly_message_limit INT UNSIGNED NOT NULL DEFAULT 100,
  max_context_messages INT UNSIGNED NOT NULL DEFAULT 12,
  max_prompt_chars INT UNSIGNED NOT NULL DEFAULT 8000,
  price_monthly DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY plans_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_features (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id BIGINT UNSIGNED NOT NULL,
  feature_key VARCHAR(100) NOT NULL,
  feature_value VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY plan_features_plan_key_unique (plan_id, feature_key),
  CONSTRAINT plan_features_plan_id_fk
    FOREIGN KEY (plan_id) REFERENCES plans (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  username VARCHAR(80) NULL,
  cedula VARCHAR(20) NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  password_hash VARCHAR(255) NULL,
  address VARCHAR(255) NULL,
  mobile VARCHAR(30) NULL,
  phone VARCHAR(30) NULL,
  photo VARCHAR(255) NULL,
  company_id BIGINT UNSIGNED NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active', 'inactive', 'blocked') NOT NULL DEFAULT 'active',
  email_verified_at TIMESTAMP NULL DEFAULT NULL,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email),
  UNIQUE KEY users_username_unique (username),
  KEY users_role_id_index (role_id),
  KEY users_plan_id_index (plan_id),
  CONSTRAINT users_role_id_fk
    FOREIGN KEY (role_id) REFERENCES roles (id),
  CONSTRAINT users_plan_id_fk
    FOREIGN KEY (plan_id) REFERENCES plans (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('trialing', 'active', 'past_due', 'canceled', 'expired') NOT NULL DEFAULT 'active',
  starts_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at TIMESTAMP NULL DEFAULT NULL,
  provider VARCHAR(80) NULL,
  provider_subscription_id VARCHAR(160) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY subscriptions_user_plan_status_unique (user_id, plan_id, status),
  KEY subscriptions_user_id_index (user_id),
  KEY subscriptions_plan_id_index (plan_id),
  CONSTRAINT subscriptions_user_id_fk
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE,
  CONSTRAINT subscriptions_plan_id_fk
    FOREIGN KEY (plan_id) REFERENCES plans (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_providers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  base_url VARCHAR(255) NOT NULL,
  default_endpoint VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ai_providers_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL DEFAULT 'Nueva conversacion',
  status ENUM('active', 'archived', 'deleted') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY conversations_user_status_index (user_id, status),
  KEY conversations_updated_at_index (updated_at),
  CONSTRAINT conversations_user_id_fk
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversation_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  role ENUM('system', 'user', 'assistant') NOT NULL,
  content LONGTEXT NOT NULL,
  token_count INT UNSIGNED NULL,
  metadata LONGTEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY conversation_messages_conversation_id_index (conversation_id, id),
  KEY conversation_messages_user_role_created_index (user_id, role, created_at),
  CONSTRAINT conversation_messages_conversation_id_fk
    FOREIGN KEY (conversation_id) REFERENCES conversations (id)
    ON DELETE CASCADE,
  CONSTRAINT conversation_messages_user_id_fk
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  provider VARCHAR(80) NOT NULL,
  endpoint VARCHAR(160) NOT NULL,
  prompt LONGTEXT NULL,
  response_body LONGTEXT NULL,
  status ENUM('success', 'error') NOT NULL,
  http_status SMALLINT UNSIGNED NULL,
  latency_ms INT UNSIGNED NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY api_requests_user_created_index (user_id, created_at),
  KEY api_requests_conversation_id_index (conversation_id),
  KEY api_requests_provider_status_index (provider, status),
  CONSTRAINT api_requests_user_id_fk
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL,
  CONSTRAINT api_requests_conversation_id_fk
    FOREIGN KEY (conversation_id) REFERENCES conversations (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  usage_date DATE NOT NULL,
  messages_count INT UNSIGNED NOT NULL DEFAULT 0,
  api_requests_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY usage_daily_user_date_unique (user_id, usage_date),
  CONSTRAINT usage_daily_user_id_fk
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, slug, description, is_system)
VALUES
  ('Super administrador', 'super_admin', 'Control total del sistema.', 1),
  ('Administrador', 'admin', 'Gestiona usuarios, planes y conversaciones.', 1),
  ('Usuario', 'user', 'Usa el chat y conserva historial.', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  is_system = VALUES(is_system);

INSERT INTO plans (name, slug, description, monthly_message_limit, max_context_messages, max_prompt_chars, price_monthly, is_active)
VALUES
  ('Gratis', 'free', 'Plan de prueba para validar el chat.', 100, 12, 8000, 0.00, 1),
  ('Pro', 'pro', 'Mas mensajes y mas memoria conversacional.', 2000, 24, 16000, 19.00, 1),
  ('Empresa', 'business', 'Uso avanzado para equipos.', 10000, 40, 28000, 79.00, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  monthly_message_limit = VALUES(monthly_message_limit),
  max_context_messages = VALUES(max_context_messages),
  max_prompt_chars = VALUES(max_prompt_chars),
  price_monthly = VALUES(price_monthly),
  is_active = VALUES(is_active);

INSERT INTO plan_features (plan_id, feature_key, feature_value)
SELECT id, 'memory', 'Historial guardado en base de datos' FROM plans WHERE slug = 'free'
ON DUPLICATE KEY UPDATE feature_value = VALUES(feature_value);

INSERT INTO plan_features (plan_id, feature_key, feature_value)
SELECT id, 'memory', 'Contexto extendido' FROM plans WHERE slug = 'pro'
ON DUPLICATE KEY UPDATE feature_value = VALUES(feature_value);

INSERT INTO plan_features (plan_id, feature_key, feature_value)
SELECT id, 'memory', 'Contexto extendido para equipos' FROM plans WHERE slug = 'business'
ON DUPLICATE KEY UPDATE feature_value = VALUES(feature_value);

INSERT INTO ai_providers (name, slug, base_url, default_endpoint, is_active)
VALUES
  ('Suilaft AI', 'suilaft', 'http://ia.suilaft.tech', '/generate', 1),
  ('DeepSeek', 'deepseek', 'https://api.deepseek.com', '/chat/completions', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  base_url = VALUES(base_url),
  default_endpoint = VALUES(default_endpoint),
  is_active = VALUES(is_active);

-- Sin usuario inicial: en el portal Alvarez Barba los usuarios llegan desde la sesión
-- del portal (login + 2FA) y se crean solos al abrir el módulo de IA.
