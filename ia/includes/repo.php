<?php
declare(strict_types=1);

/* ---------------------------------------------------------------------------
 * Identidad (la da el portal; esta app ya no tiene login propio)
 * ------------------------------------------------------------------------- */

/**
 * Sin login propio: el usuario es el de la sesión del portal Alvarez Barba (contraseña + 2FA).
 * Se le pregunta a Laravel (/api/yo) reenviando la cookie del navegador; si la sesión del portal
 * no existe o expiró, aquí no hay usuario.
 */
function portal_identity(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;

    $cookie = (string) ($_SERVER['HTTP_COOKIE'] ?? '');
    if ($cookie === '' || !function_exists('curl_init')) {
        return null;
    }

    $origin = 'http://' . preg_replace('/[^A-Za-z0-9.:\-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $curl = curl_init(rtrim(env_value('PORTAL_API_URL', 'http://web'), '/') . '/api/yo');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest',
            'Cookie: ' . $cookie,
            'Referer: ' . $origin . '/',
            'Origin: ' . $origin,
        ],
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($status !== 200 || !is_string($body)) {
        return null;
    }
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['id'])) {
        return null;
    }

    $cache = $data;
    return $cache;
}

/** ¿El rol del portal tiene habilitado el módulo de IA? */
function portal_can_use_ai(array $identity): bool
{
    return !empty($identity['es_admin']) || in_array('ia', (array) ($identity['modulos'] ?? []), true);
}

function authenticated_user(PDO $pdo): ?array
{
    $identity = portal_identity();
    if (!$identity) {
        return null;
    }
    if (!portal_can_use_ai($identity)) {
        $GLOBALS['portal_ai_denied'] = true;
        return null;
    }

    $username = 'portal:' . (int) $identity['id'];
    $name = trim((string) ($identity['nombres'] ?? '')) ?: (string) ($identity['usuario'] ?? 'Usuario');
    $email = trim((string) ($identity['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = 'usuario' . (int) $identity['id'] . '@portal.local';
    }
    $roleSlug = !empty($identity['es_admin']) ? 'admin' : 'user';

    $role = $pdo->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
    $role->execute([$roleSlug]);
    $roleId = (int) $role->fetchColumn();
    $plan = $pdo->query("SELECT id FROM plans WHERE slug = 'business' LIMIT 1");
    $planId = (int) $plan->fetchColumn();

    $find = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $find->execute([$username]);
    $userId = (int) $find->fetchColumn();

    try {
        if ($userId === 0) {
            $insert = $pdo->prepare(
                'INSERT INTO users (role_id, plan_id, username, name, email, password_hash, status, last_login_at)
                 VALUES (?, ?, ?, ?, ?, NULL, \'active\', NOW())'
            );
            $insert->execute([$roleId, $planId, $username, $name, $email]);
            $userId = (int) $pdo->lastInsertId();
        } else {
            $update = $pdo->prepare('UPDATE users SET role_id = ?, name = ?, email = ?, status = \'active\', last_login_at = NOW() WHERE id = ?');
            $update->execute([$roleId, $name, $email, $userId]);
        }
    } catch (PDOException $error) {
        // Correo repetido entre dos usuarios del portal: se usa uno interno.
        $email = 'usuario' . (int) $identity['id'] . '@portal.local';
        if ($userId === 0) {
            $insert = $pdo->prepare(
                'INSERT INTO users (role_id, plan_id, username, name, email, password_hash, status, last_login_at)
                 VALUES (?, ?, ?, ?, ?, NULL, \'active\', NOW())'
            );
            $insert->execute([$roleId, $planId, $username, $name, $email]);
            $userId = (int) $pdo->lastInsertId();
        } else {
            $update = $pdo->prepare('UPDATE users SET role_id = ?, name = ?, email = ? WHERE id = ?');
            $update->execute([$roleId, $name, $email, $userId]);
        }
    }

    $statement = $pdo->prepare(
        'SELECT u.*, r.slug AS role_slug, r.name AS role_name, p.slug AS plan_slug, p.name AS plan_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         INNER JOIN plans p ON p.id = u.plan_id
         WHERE u.id = ?
         LIMIT 1'
    );
    $statement->execute([$userId]);
    $user = $statement->fetch();
    if (!$user) {
        return null;
    }
    $user['portal_rol'] = (string) ($identity['rol'] ?? '');

    return $user;
}

function user_is_admin(array $user): bool
{
    return in_array((string) ($user['role_slug'] ?? ''), ['super_admin', 'admin'], true);
}

function user_initials(array $user): string
{
    $name = trim((string) ($user['name'] ?? ''));
    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/u', $name) ?: [$name];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
    }

    return function_exists('mb_strtoupper') ? mb_strtoupper($initials, 'UTF-8') : strtoupper($initials);
}

/* ---------------------------------------------------------------------------
 * Proyectos
 * ------------------------------------------------------------------------- */

function user_projects(PDO $pdo, int $userId, int $limit = 60): array
{
    $statement = $pdo->prepare(
        'SELECT p.id, p.name, p.description, p.icon, p.color, p.pinned_at, p.updated_at,
                (SELECT COUNT(*) FROM conversations c
                  WHERE c.project_id = p.id AND c.user_id = p.user_id AND c.status = \'active\') AS chats_count,
                (SELECT COUNT(*) FROM documents d
                  WHERE d.project_id = p.id AND d.user_id = p.user_id AND d.status = \'active\') AS documents_count
         FROM projects p
         WHERE p.user_id = ?
           AND p.status = \'active\'
         ORDER BY p.updated_at DESC, p.id DESC
         LIMIT ' . max(1, $limit)
    );
    $statement->execute([$userId]);

    return $statement->fetchAll();
}

function find_user_project(PDO $pdo, int $userId, int $projectId): ?array
{
    if ($projectId <= 0) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT * FROM projects WHERE id = ? AND user_id = ? AND status = \'active\' LIMIT 1'
    );
    $statement->execute([$projectId, $userId]);
    $project = $statement->fetch();

    return $project ?: null;
}

/**
 * Iconos y colores disponibles para proyectos (como en ChatGPT).
 */
function project_icon_options(): array
{
    return ['folder', 'briefcase', 'building', 'shield', 'scale', 'doc', 'chart', 'users', 'globe', 'flag', 'search', 'star'];
}

function project_color_options(): array
{
    return [
        'gray' => 'Gris', 'blue' => 'Azul', 'green' => 'Verde', 'red' => 'Rojo', 'orange' => 'Naranja',
        'yellow' => 'Amarillo', 'purple' => 'Morado', 'pink' => 'Rosa', 'teal' => 'Turquesa',
    ];
}

function valid_project_icon(string $icon): string
{
    return in_array($icon, project_icon_options(), true) ? $icon : 'folder';
}

function valid_project_color(string $color): string
{
    return array_key_exists($color, project_color_options()) ? $color : 'gray';
}

function create_project(PDO $pdo, int $userId, string $name, string $description, string $instructions, string $icon = 'folder', string $color = 'gray'): int
{
    $statement = $pdo->prepare(
        'INSERT INTO projects (user_id, name, description, icon, color, instructions, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, \'active\', NOW(), NOW())'
    );
    $statement->execute([
        $userId,
        limit_text($name, 140),
        $description !== '' ? limit_text($description, 255) : null,
        valid_project_icon($icon),
        valid_project_color($color),
        $instructions !== '' ? limit_text($instructions, 8000) : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function update_project(PDO $pdo, int $userId, int $projectId, string $name, string $description, string $instructions): void
{
    $statement = $pdo->prepare(
        'UPDATE projects
         SET name = ?, description = ?, instructions = ?, updated_at = NOW()
         WHERE id = ? AND user_id = ?'
    );
    $statement->execute([
        limit_text($name, 140),
        $description !== '' ? limit_text($description, 255) : null,
        $instructions !== '' ? limit_text($instructions, 8000) : null,
        $projectId,
        $userId,
    ]);
}

function set_project_appearance(PDO $pdo, int $userId, int $projectId, string $icon, string $color): void
{
    $statement = $pdo->prepare('UPDATE projects SET icon = ?, color = ? WHERE id = ? AND user_id = ?');
    $statement->execute([valid_project_icon($icon), valid_project_color($color), $projectId, $userId]);
}

function toggle_pin(PDO $pdo, string $table, int $userId, int $id): bool
{
    if (!in_array($table, ['projects', 'conversations'], true)) {
        return false;
    }
    $statement = $pdo->prepare('UPDATE ' . $table . ' SET pinned_at = IF(pinned_at IS NULL, NOW(), NULL) WHERE id = ? AND user_id = ?');
    $statement->execute([$id, $userId]);

    $statement = $pdo->prepare('SELECT pinned_at FROM ' . $table . ' WHERE id = ? AND user_id = ?');
    $statement->execute([$id, $userId]);

    return (bool) $statement->fetchColumn();
}

function archive_project(PDO $pdo, int $userId, int $projectId): void
{
    $statement = $pdo->prepare('UPDATE projects SET status = \'archived\', updated_at = NOW() WHERE id = ? AND user_id = ?');
    $statement->execute([$projectId, $userId]);

    // Los chats del proyecto vuelven a la lista general para no perder historial.
    $statement = $pdo->prepare('UPDATE conversations SET project_id = NULL WHERE project_id = ? AND user_id = ?');
    $statement->execute([$projectId, $userId]);
}

function touch_project(PDO $pdo, int $projectId): void
{
    if ($projectId <= 0) {
        return;
    }

    $statement = $pdo->prepare('UPDATE projects SET updated_at = NOW() WHERE id = ?');
    $statement->execute([$projectId]);
}

/* ---------------------------------------------------------------------------
 * Conversaciones y mensajes
 * ------------------------------------------------------------------------- */

function user_conversation_list(PDO $pdo, int $userId, ?int $projectId = null, int $limit = 50): array
{
    $conditions = ['c.user_id = ?', 'c.status = \'active\''];
    $params = [$userId];

    if ($projectId !== null) {
        $conditions[] = 'c.project_id = ?';
        $params[] = $projectId;
    }

    $statement = $pdo->prepare(
        'SELECT c.id, c.project_id, c.title, c.skill_slug, c.pinned_at, c.updated_at, p.name AS project_name
         FROM conversations c
         LEFT JOIN projects p ON p.id = c.project_id AND p.status = \'active\'
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY c.updated_at DESC, c.id DESC
         LIMIT ' . max(1, $limit)
    );
    $statement->execute($params);

    return $statement->fetchAll();
}

function find_user_conversation(PDO $pdo, int $userId, int $conversationId): ?array
{
    if ($conversationId <= 0) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT * FROM conversations WHERE id = ? AND user_id = ? AND status = \'active\' LIMIT 1'
    );
    $statement->execute([$conversationId, $userId]);
    $conversation = $statement->fetch();

    return $conversation ?: null;
}

function create_conversation(PDO $pdo, int $userId, int $projectId, string $firstPrompt, string $skill): int
{
    $statement = $pdo->prepare(
        'INSERT INTO conversations (user_id, project_id, title, skill_slug, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, \'active\', NOW(), NOW())'
    );
    $statement->execute([
        $userId,
        $projectId > 0 ? $projectId : null,
        conversation_title($firstPrompt),
        $skill !== '' ? $skill : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function set_conversation_skill(PDO $pdo, int $conversationId, string $skill): void
{
    $statement = $pdo->prepare('UPDATE conversations SET skill_slug = ? WHERE id = ?');
    $statement->execute([$skill !== '' ? $skill : null, $conversationId]);
}

function rename_conversation(PDO $pdo, int $userId, int $conversationId, string $title): void
{
    $statement = $pdo->prepare('UPDATE conversations SET title = ? WHERE id = ? AND user_id = ?');
    $statement->execute([conversation_title($title), $conversationId, $userId]);
}

function move_conversation(PDO $pdo, int $userId, int $conversationId, int $projectId): void
{
    $statement = $pdo->prepare('UPDATE conversations SET project_id = ? WHERE id = ? AND user_id = ?');
    $statement->execute([$projectId > 0 ? $projectId : null, $conversationId, $userId]);
}

function delete_conversation(PDO $pdo, int $userId, int $conversationId): void
{
    $statement = $pdo->prepare('UPDATE conversations SET status = \'deleted\' WHERE id = ? AND user_id = ?');
    $statement->execute([$conversationId, $userId]);
}

function conversation_messages(PDO $pdo, int $conversationId): array
{
    $statement = $pdo->prepare(
        'SELECT id, role, content, metadata, created_at
         FROM conversation_messages
         WHERE conversation_id = ?
         ORDER BY id ASC
         LIMIT 400'
    );
    $statement->execute([$conversationId]);

    return $statement->fetchAll();
}

/**
 * Historial previo a un mensaje concreto (excluye el mensaje actual del usuario).
 */
function history_before(PDO $pdo, int $conversationId, int $beforeMessageId, int $limit, int $afterMessageId = 0): array
{
    $sql = 'SELECT role, content FROM conversation_messages WHERE conversation_id = ? AND id > ?';
    $params = [$conversationId, $afterMessageId];
    if ($beforeMessageId > 0) {
        $sql .= ' AND id < ?';
        $params[] = $beforeMessageId;
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . max(1, $limit);

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return array_reverse($statement->fetchAll());
}

function store_message(PDO $pdo, int $conversationId, int $userId, string $role, string $content, array $metadata = []): int
{
    $statement = $pdo->prepare(
        'INSERT INTO conversation_messages (conversation_id, user_id, role, content, metadata, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $statement->execute([$conversationId, $userId, $role, $content, $metadata ? json_compact($metadata) : null]);
    $messageId = (int) $pdo->lastInsertId();

    if ($role === 'user') {
        increment_daily_usage($pdo, $userId, 'messages_count');
    }

    return $messageId;
}

function touch_conversation(PDO $pdo, int $conversationId): void
{
    $statement = $pdo->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = ?');
    $statement->execute([$conversationId]);
}

function conversation_title(string $prompt): string
{
    $title = trim(preg_replace('/\s+/u', ' ', $prompt) ?? '');
    $title = trim(preg_replace('/^(hola|buenos dias|buenas tardes|buenas noches|buen dia)[\s,.!]*/iu', '', $title) ?? $title);
    if ($title === '') {
        return 'Nueva conversacion';
    }

    // Primera oracion si es corta; si no, corte en limite de palabra.
    if (preg_match('/^(.{12,70}?[.?!])(\s|$)/u', $title, $sentence)) {
        $title = $sentence[1];
    }
    if (text_length($title) > 60) {
        $cut = limit_text($title, 57);
        $space = strrpos($cut, ' ');
        $title = rtrim(($space !== false && $space > 30 ? substr($cut, 0, $space) : $cut), ' ,;:') . '…';
    }

    // Mayuscula inicial, respetando los signos de apertura del espanol.
    return (string) preg_replace_callback('/^([¿¡"«]*)(\p{Ll})/u', function (array $m): string {
        return $m[1] . (function_exists('mb_strtoupper') ? mb_strtoupper($m[2], 'UTF-8') : strtoupper($m[2]));
    }, $title);
}

function set_conversation_title_if_unchanged(PDO $pdo, int $conversationId, string $expected, string $title): void
{
    $statement = $pdo->prepare('UPDATE conversations SET title = ? WHERE id = ? AND title = ?');
    $statement->execute([limit_text($title, 80), $conversationId, $expected]);
}

/* ---------------------------------------------------------------------------
 * Documentos
 * ------------------------------------------------------------------------- */

function project_documents(PDO $pdo, int $userId, int $projectId): array
{
    $statement = $pdo->prepare(
        'SELECT id, title, original_name, extension, file_size, extraction_status, extraction_error, created_at
         FROM documents
         WHERE user_id = ? AND project_id = ? AND conversation_id IS NULL
           AND scope = \'private\' AND status = \'active\'
         ORDER BY created_at DESC, id DESC
         LIMIT 100'
    );
    $statement->execute([$userId, $projectId]);

    return $statement->fetchAll();
}

function conversation_documents(PDO $pdo, int $userId, int $conversationId): array
{
    if ($conversationId <= 0) {
        return [];
    }

    $statement = $pdo->prepare(
        'SELECT id, title, original_name, extension, file_size, extraction_status, extraction_error, created_at
         FROM documents
         WHERE user_id = ? AND conversation_id = ? AND scope = \'private\' AND status = \'active\'
         ORDER BY created_at ASC, id ASC
         LIMIT 50'
    );
    $statement->execute([$userId, $conversationId]);

    return $statement->fetchAll();
}

function library_documents(PDO $pdo): array
{
    $statement = $pdo->query(
        'SELECT d.id, d.title, d.original_name, d.extension, d.file_size, d.extraction_status,
                d.extraction_error, d.created_at, u.name AS uploaded_by,
                (SELECT COUNT(*) FROM document_chunks dc WHERE dc.document_id = d.id) AS chunks_count
         FROM documents d
         LEFT JOIN users u ON u.id = d.user_id
         WHERE d.scope = \'library\' AND d.status = \'active\'
         ORDER BY d.created_at DESC, d.id DESC
         LIMIT 300'
    );

    return $statement ? $statement->fetchAll() : [];
}

function find_document_for_user(PDO $pdo, array $user, int $documentId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM documents WHERE id = ? AND status = \'active\' LIMIT 1');
    $statement->execute([$documentId]);
    $document = $statement->fetch();
    if (!$document) {
        return null;
    }

    if ((string) $document['scope'] === 'library') {
        return user_is_admin($user) ? $document : null;
    }

    return (int) $document['user_id'] === (int) $user['id'] ? $document : null;
}

function archive_document(PDO $pdo, int $documentId): void
{
    $statement = $pdo->prepare('UPDATE documents SET status = \'deleted\', updated_at = NOW() WHERE id = ?');
    $statement->execute([$documentId]);

    $statement = $pdo->prepare('DELETE FROM document_chunks WHERE document_id = ?');
    $statement->execute([$documentId]);
}

/* ---------------------------------------------------------------------------
 * Planes, uso y registro de llamadas
 * ------------------------------------------------------------------------- */

function user_plan_limits(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare(
        'SELECT p.monthly_message_limit, p.max_context_messages, p.max_prompt_chars
         FROM users u
         INNER JOIN plans p ON p.id = u.plan_id
         WHERE u.id = ?
         LIMIT 1'
    );
    $statement->execute([$userId]);
    $plan = $statement->fetch();

    return [
        'monthly_message_limit' => (int) ($plan['monthly_message_limit'] ?? 0),
        'max_context_messages' => max(2, (int) ($plan['max_context_messages'] ?? 12)),
        'max_prompt_chars' => max(60000, (int) ($plan['max_prompt_chars'] ?? 60000)),
    ];
}

function monthly_usage_count(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM conversation_messages
         WHERE user_id = ? AND role = \'user\'
           AND created_at >= DATE_FORMAT(CURRENT_DATE, \'%Y-%m-01\')'
    );
    $statement->execute([$userId]);

    return (int) $statement->fetchColumn();
}

function increment_daily_usage(PDO $pdo, int $userId, string $column): void
{
    if (!in_array($column, ['messages_count', 'api_requests_count'], true)) {
        return;
    }

    $statement = $pdo->prepare(
        'INSERT INTO usage_daily (user_id, usage_date, ' . $column . ', created_at, updated_at)
         VALUES (?, CURRENT_DATE, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE ' . $column . ' = ' . $column . ' + 1, updated_at = NOW()'
    );
    $statement->execute([$userId]);
}

function log_api_request(
    PDO $pdo,
    int $userId,
    int $conversationId,
    string $provider,
    string $prompt,
    $responseBody,
    string $status,
    int $httpStatus,
    int $latencyMs,
    ?string $errorMessage
): void {
    $statement = $pdo->prepare(
        'INSERT INTO api_requests
            (user_id, conversation_id, provider, endpoint, prompt, response_body, status, http_status, latency_ms, error_message, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $statement->execute([
        $userId,
        $conversationId,
        $provider,
        provider_endpoint($provider),
        $prompt,
        is_string($responseBody) ? $responseBody : json_text($responseBody),
        $status,
        $httpStatus,
        $latencyMs,
        $errorMessage,
    ]);

    increment_daily_usage($pdo, $userId, 'api_requests_count');
}

/* ---------------------------------------------------------------------------
 * Cola de trabajos de IA
 * ------------------------------------------------------------------------- */

function create_ai_job(PDO $pdo, int $userId, int $conversationId, string $prompt, array $input): int
{
    $statement = $pdo->prepare(
        'INSERT INTO ai_jobs (user_id, conversation_id, prompt, input_json, status, stage, attempts, created_at, updated_at)
         VALUES (?, ?, ?, ?, \'queued\', \'queued\', 0, NOW(), NOW())'
    );
    $statement->execute([$userId, $conversationId, $prompt, json_compact($input)]);

    return (int) $pdo->lastInsertId();
}

function find_user_ai_job(PDO $pdo, int $userId, int $jobId): ?array
{
    if ($jobId <= 0) {
        return null;
    }

    $statement = $pdo->prepare('SELECT * FROM ai_jobs WHERE id = ? AND user_id = ? LIMIT 1');
    $statement->execute([$jobId, $userId]);
    $job = $statement->fetch();

    return $job ?: null;
}

function claim_ai_job(PDO $pdo, int $jobId, int $staleMinutes = 3): ?array
{
    $statement = $pdo->prepare(
        'UPDATE ai_jobs
         SET status = \'processing\', attempts = attempts + 1, started_at = NOW(), updated_at = NOW()
         WHERE id = ?
           AND (status = \'queued\'
             OR (status = \'processing\' AND started_at < DATE_SUB(NOW(), INTERVAL ' . max(1, $staleMinutes) . ' MINUTE)))'
    );
    $statement->execute([$jobId]);

    if ($statement->rowCount() < 1) {
        return null;
    }

    $statement = $pdo->prepare('SELECT * FROM ai_jobs WHERE id = ? LIMIT 1');
    $statement->execute([$jobId]);
    $job = $statement->fetch();

    return $job ?: null;
}

function next_queued_ai_job_id(PDO $pdo): int
{
    $statement = $pdo->query(
        'SELECT id FROM ai_jobs
         WHERE status = \'queued\'
            OR (status = \'processing\' AND started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE))
         ORDER BY created_at ASC, id ASC
         LIMIT 1'
    );

    return $statement ? (int) ($statement->fetchColumn() ?: 0) : 0;
}

function pending_job_for_conversation(PDO $pdo, int $userId, int $conversationId): int
{
    $statement = $pdo->prepare(
        'SELECT id FROM ai_jobs
         WHERE user_id = ? AND conversation_id = ? AND status IN (\'queued\', \'processing\')
           AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
         ORDER BY id DESC LIMIT 1'
    );
    $statement->execute([$userId, $conversationId]);

    return (int) ($statement->fetchColumn() ?: 0);
}

function set_job_stage(PDO $pdo, int $jobId, string $stage): void
{
    $statement = $pdo->prepare('UPDATE ai_jobs SET stage = ?, updated_at = NOW() WHERE id = ?');
    $statement->execute([$stage, $jobId]);
}

function complete_ai_job(PDO $pdo, int $jobId, int $httpStatus, int $latencyMs, $responseBody, string $answer, array $trace): void
{
    $statement = $pdo->prepare(
        'UPDATE ai_jobs
         SET status = \'done\', stage = \'done\', http_status = ?, latency_ms = ?, response_body = ?,
             answer = ?, trace_json = ?, error_message = NULL, completed_at = NOW(), updated_at = NOW()
         WHERE id = ?'
    );
    $statement->execute([
        $httpStatus,
        $latencyMs,
        is_string($responseBody) ? $responseBody : json_text($responseBody),
        $answer,
        $trace ? json_compact($trace) : null,
        $jobId,
    ]);
}

function fail_ai_job(PDO $pdo, int $jobId, int $httpStatus, int $latencyMs, $responseBody, string $errorMessage): void
{
    $statement = $pdo->prepare(
        'UPDATE ai_jobs
         SET status = \'failed\', stage = \'failed\', http_status = ?, latency_ms = ?, response_body = ?,
             error_message = ?, completed_at = NOW(), updated_at = NOW()
         WHERE id = ?'
    );
    $statement->execute([
        $httpStatus,
        $latencyMs,
        is_string($responseBody) ? $responseBody : json_text($responseBody),
        $errorMessage,
        $jobId,
    ]);
}

function ai_job_payload(array $job): array
{
    $trace = json_decode_array($job['trace_json'] ?? '');

    return [
        'job_id' => (int) $job['id'],
        'conversation_id' => (int) $job['conversation_id'],
        'status' => (string) $job['status'],
        'stage' => (string) ($job['stage'] ?? ''),
        'attempts' => (int) ($job['attempts'] ?? 0),
        'created_at' => (string) ($job['created_at'] ?? ''),
        'started_at' => (string) ($job['started_at'] ?? ''),
        'updated_at' => (string) ($job['updated_at'] ?? ''),
        'answer' => (string) ($job['answer'] ?? ''),
        'error' => (string) ($job['error_message'] ?? ''),
        'meta' => public_trace($trace),
    ];
}

/**
 * Parte de la traza que se muestra al usuario (sin prompts internos).
 */
function public_trace(array $trace): array
{
    if (!$trace) {
        return [];
    }

    return [
        'skill' => (string) ($trace['skill'] ?? ''),
        'skill_label' => (string) ($trace['skill_label'] ?? ''),
        'verdict' => (string) ($trace['triage']['verdict'] ?? ''),
        'off_topic' => array_values(array_map('strval', (array) ($trace['triage']['off_topic'] ?? []))),
        'sources' => array_values(array_map(function ($source): array {
            return [
                'n' => (int) ($source['n'] ?? 0),
                'title' => (string) ($source['title'] ?? ''),
                'scope' => (string) ($source['scope'] ?? ''),
            ];
        }, (array) ($trace['sources'] ?? []))),
        'reviewed' => !empty($trace['review']['changed']),
        'compact' => !empty($trace['compact']),
        'memory_updated' => !empty($trace['memory_updated']),
        'provider' => (string) ($trace['provider'] ?? ''),
    ];
}
