<?php
declare(strict_types=1);

/*
 * Esquema autoaplicable. Cada cambio se valida contra information_schema antes de
 * ejecutarse, asi funciona igual en MySQL 5.7/8 y MariaDB (no usa ADD COLUMN IF NOT EXISTS,
 * que solo existe en MariaDB). La version aplicada queda en app_meta para no repetir
 * las comprobaciones en cada peticion.
 */

const APP_SCHEMA_VERSION = 6;

function ensure_app_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_meta (
          meta_key VARCHAR(80) NOT NULL,
          meta_value VARCHAR(255) NULL,
          updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (meta_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $statement = $pdo->prepare('SELECT meta_value FROM app_meta WHERE meta_key = ? LIMIT 1');
    $statement->execute(['schema_version']);
    $current = (int) ($statement->fetchColumn() ?: 0);
    if ($current >= APP_SCHEMA_VERSION) {
        return;
    }

    apply_base_tables($pdo);
    apply_architecture_changes($pdo);

    $statement = $pdo->prepare(
        'INSERT INTO app_meta (meta_key, meta_value) VALUES (\'schema_version\', ?)
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)'
    );
    $statement->execute([(string) APP_SCHEMA_VERSION]);
}

function apply_base_tables(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS conversations (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          title VARCHAR(160) NOT NULL DEFAULT \'Nueva conversacion\',
          status VARCHAR(20) NOT NULL DEFAULT \'active\',
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY conversations_user_status_index (user_id, status),
          KEY conversations_updated_at_index (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS conversation_messages (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          conversation_id BIGINT UNSIGNED NOT NULL,
          user_id BIGINT UNSIGNED NULL,
          role VARCHAR(20) NOT NULL,
          content LONGTEXT NOT NULL,
          token_count INT UNSIGNED NULL,
          metadata LONGTEXT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY conversation_messages_conversation_id_index (conversation_id, id),
          KEY conversation_messages_user_role_created_index (user_id, role, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS api_requests (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NULL,
          conversation_id BIGINT UNSIGNED NULL,
          provider VARCHAR(80) NOT NULL,
          endpoint VARCHAR(160) NOT NULL,
          prompt LONGTEXT NULL,
          response_body LONGTEXT NULL,
          status VARCHAR(20) NOT NULL,
          http_status SMALLINT UNSIGNED NULL,
          latency_ms INT UNSIGNED NULL,
          error_message TEXT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY api_requests_user_created_index (user_id, created_at),
          KEY api_requests_conversation_id_index (conversation_id),
          KEY api_requests_provider_status_index (provider, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS usage_daily (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          usage_date DATE NOT NULL,
          messages_count INT UNSIGNED NOT NULL DEFAULT 0,
          api_requests_count INT UNSIGNED NOT NULL DEFAULT 0,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY usage_daily_user_date_unique (user_id, usage_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS projects (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          name VARCHAR(140) NOT NULL,
          description VARCHAR(255) NULL,
          status VARCHAR(20) NOT NULL DEFAULT \'active\',
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY projects_user_status_index (user_id, status),
          KEY projects_updated_at_index (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS documents (
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
          extraction_status VARCHAR(30) NOT NULL DEFAULT \'ready\',
          extraction_error VARCHAR(255) NULL,
          status VARCHAR(20) NOT NULL DEFAULT \'active\',
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY documents_user_project_index (user_id, project_id, status, created_at),
          KEY documents_user_conversation_index (user_id, conversation_id, status, created_at),
          KEY documents_status_index (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS document_chunks (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ai_jobs (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          conversation_id BIGINT UNSIGNED NOT NULL,
          prompt LONGTEXT NOT NULL,
          status VARCHAR(20) NOT NULL DEFAULT \'queued\',
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function apply_architecture_changes(PDO $pdo): void
{
    // Columnas de usuarios agregadas en versiones anteriores (antes con sintaxis solo MariaDB).
    add_column_if_missing($pdo, 'users', 'username', 'VARCHAR(80) NULL AFTER plan_id');
    add_column_if_missing($pdo, 'users', 'cedula', 'VARCHAR(20) NULL AFTER username');
    add_column_if_missing($pdo, 'users', 'address', 'VARCHAR(255) NULL AFTER password_hash');
    add_column_if_missing($pdo, 'users', 'mobile', 'VARCHAR(30) NULL AFTER address');
    add_column_if_missing($pdo, 'users', 'phone', 'VARCHAR(30) NULL AFTER mobile');
    add_column_if_missing($pdo, 'users', 'photo', 'VARCHAR(255) NULL AFTER phone');
    add_column_if_missing($pdo, 'users', 'company_id', 'BIGINT UNSIGNED NULL AFTER photo');
    add_column_if_missing($pdo, 'users', 'must_change_password', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER company_id');

    // Proyectos: hilo de chats + instrucciones propias (equivalente a "Project instructions").
    add_column_if_missing($pdo, 'conversations', 'project_id', 'BIGINT UNSIGNED NULL AFTER user_id');
    add_index_if_missing($pdo, 'conversations', 'conversations_user_project_status_index', '(user_id, project_id, status)');
    add_column_if_missing($pdo, 'conversations', 'skill_slug', 'VARCHAR(80) NULL AFTER title');
    add_column_if_missing($pdo, 'projects', 'instructions', 'TEXT NULL AFTER description');

    // Biblioteca normativa global (scope = library) frente a documentos privados.
    add_column_if_missing($pdo, 'documents', 'scope', 'VARCHAR(20) NOT NULL DEFAULT \'private\' AFTER conversation_id');
    add_index_if_missing($pdo, 'documents', 'documents_scope_status_index', '(scope, status)');

    // RAG: indice de texto completo para recuperar fragmentos relevantes.
    try {
        add_index_if_missing($pdo, 'document_chunks', 'document_chunks_content_fulltext', '(content)', 'FULLTEXT');
    } catch (Throwable $error) {
        // Servidores antiguos sin FULLTEXT en InnoDB: la recuperacion cae a busqueda por LIKE.
    }

    // Agentes: entrada estructurada, etapa visible y traza auditable de cada respuesta.
    add_column_if_missing($pdo, 'ai_jobs', 'input_json', 'LONGTEXT NULL AFTER prompt');
    add_column_if_missing($pdo, 'ai_jobs', 'stage', 'VARCHAR(40) NULL AFTER status');
    add_column_if_missing($pdo, 'ai_jobs', 'trace_json', 'LONGTEXT NULL AFTER answer');

    // Memoria compactada: resumen en Markdown que reemplaza al historial antiguo.
    add_column_if_missing($pdo, 'conversations', 'memory_md', 'LONGTEXT NULL AFTER skill_slug');
    add_column_if_missing($pdo, 'conversations', 'memory_until_id', 'BIGINT UNSIGNED NULL AFTER memory_md');
    add_column_if_missing($pdo, 'conversations', 'memory_updated_at', 'TIMESTAMP NULL DEFAULT NULL AFTER memory_until_id');
    add_column_if_missing($pdo, 'projects', 'memory_md', 'LONGTEXT NULL AFTER instructions');
    add_column_if_missing($pdo, 'projects', 'memory_updated_at', 'TIMESTAMP NULL DEFAULT NULL AFTER memory_md');

    // Sidebar estilo ChatGPT: elementos fijados e identidad visual de cada proyecto.
    add_column_if_missing($pdo, 'conversations', 'pinned_at', 'TIMESTAMP NULL DEFAULT NULL AFTER status');
    add_column_if_missing($pdo, 'projects', 'pinned_at', 'TIMESTAMP NULL DEFAULT NULL AFTER status');
    add_column_if_missing($pdo, 'projects', 'icon', 'VARCHAR(30) NULL AFTER description');
    add_column_if_missing($pdo, 'projects', 'color', 'VARCHAR(20) NULL AFTER icon');
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!table_exists($pdo, $table) || column_exists($pdo, $table, $column)) {
        return;
    }

    $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
}

function add_index_if_missing(PDO $pdo, string $table, string $index, string $columns, string $type = ''): void
{
    if (!table_exists($pdo, $table) || index_exists($pdo, $table, $index)) {
        return;
    }

    $prefix = $type === 'FULLTEXT' ? 'FULLTEXT INDEX' : 'INDEX';
    $pdo->exec('ALTER TABLE `' . $table . '` ADD ' . $prefix . ' `' . $index . '` ' . $columns);
}

function table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table]) && $cache[$table]) {
        return true;
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$table]);
    $cache[$table] = (int) $statement->fetchColumn() > 0;

    return $cache[$table];
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $statement->execute([$table, $column]);

    return (int) $statement->fetchColumn() > 0;
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $statement->execute([$table, $index]);

    return (int) $statement->fetchColumn() > 0;
}

function fulltext_available(PDO $pdo): bool
{
    static $available = null;
    if ($available === null) {
        $available = index_exists($pdo, 'document_chunks', 'document_chunks_content_fulltext');
    }

    return $available;
}
