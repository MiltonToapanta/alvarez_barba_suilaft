<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/views/helpers.php';

app_boot();
// Sesión propia solo para CSRF y avisos, limitada a /ia-app/. La identidad la da el portal.
session_name('AB_IA');
session_set_cookie_params(['path' => '/ia-app/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

$action = (string) ($_GET['action'] ?? '');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// Sin login propio: "Cerrar sesión" se hace desde el portal.
if ($action === 'logout' || $action === 'login') {
    redirect_to('index.php');
    exit;
}

/* ---------------------------------------------------------------------------
 * Endpoints JSON
 * ------------------------------------------------------------------------- */

$jsonActions = [
    'chat', 'process_job', 'job_status', 'upload_document', 'delete_document',
    'chat_update', 'export_pdf', 'export_docx', 'memory_download', 'pin',
];

if (in_array($action, $jsonActions, true)) {
    try {
        $pdo = db_connection();
        ensure_app_schema($pdo);
        $user = authenticated_user($pdo);
        if (!$user) {
            json_response(['error' => !empty($GLOBALS['portal_ai_denied']) ? 'Tu rol no tiene acceso al módulo de Inteligencia Artificial.' : 'Tu sesión del portal expiró. Vuelve a iniciar sesión.'], !empty($GLOBALS['portal_ai_denied']) ? 403 : 401);
            exit;
        }

        if ($isPost && !csrf_valid()) {
            json_response(['error' => 'La página quedó desactualizada. Recárgala e inténtalo de nuevo.'], 419);
            exit;
        }

        route_json_action($action, $pdo, $user, $isPost);
    } catch (Throwable $error) {
        json_response(['error' => 'No pude completar la acción.', 'details' => $error->getMessage()], 500);
    }
    exit;
}

/* ---------------------------------------------------------------------------
 * Acciones de formulario (POST + redireccion)
 * ------------------------------------------------------------------------- */

$formActions = ['create_project', 'update_project', 'update_project_memory', 'archive_project', 'new_chat'];

if ($isPost && in_array($action, $formActions, true)) {
    $target = 'index.php';
    try {
        $pdo = db_connection();
        ensure_app_schema($pdo);
        $user = authenticated_user($pdo);
        if (!$user) {
            redirect_to('index.php');
            exit;
        }
        if (!csrf_valid()) {
            throw new RuntimeException('La página quedó desactualizada. Inténtalo de nuevo.');
        }
        $target = route_form_action($action, $pdo, $user);
    } catch (Throwable $error) {
        flash($error->getMessage(), 'error');
        $target = (string) ($_SERVER['HTTP_REFERER'] ?? 'index.php');
        if (strpos($target, 'index.php') === false) {
            $target = 'index.php';
        }
    }
    redirect_to($target);
    exit;
}

// Enlaces antiguos basados en sesion.
if ($action === 'select_chat') {
    redirect_to('index.php?c=' . (int) ($_GET['conversation_id'] ?? 0));
    exit;
}
if ($action === 'select_project') {
    $projectId = (int) ($_GET['project_id'] ?? 0);
    redirect_to($projectId > 0 ? 'index.php?p=' . $projectId : 'index.php');
    exit;
}

/* ---------------------------------------------------------------------------
 * Paginas
 * ------------------------------------------------------------------------- */

try {
    $pdo = db_connection();
    ensure_app_schema($pdo);
    $user = authenticated_user($pdo);
} catch (Throwable $error) {
    error_log('SUILAFT AI: ' . $error->getMessage());
    render_view('acceso', ['motivo' => 'error']);
    exit;
}

if (!$user) {
    http_response_code(!empty($GLOBALS['portal_ai_denied']) ? 403 : 401);
    render_view('acceso', ['motivo' => !empty($GLOBALS['portal_ai_denied']) ? 'permiso' : 'sesion']);
    exit;
}

$page = build_page($pdo, $user);
if (!empty($page['redirect'])) {
    redirect_to($page['redirect']);
    exit;
}
render_view('app', $page);
exit;

/* ===========================================================================
 * Controladores
 * ========================================================================= */

function route_form_action(string $action, PDO $pdo, array $user): string
{
    $userId = (int) $user['id'];

    if ($action === 'create_project') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Escribe un nombre para el proyecto.');
        }
        [$instructions, $warning] = screened_instructions((string) ($_POST['instructions'] ?? ''));
        $projectId = create_project(
            $pdo,
            $userId,
            $name,
            trim((string) ($_POST['description'] ?? '')),
            $instructions,
            (string) ($_POST['icon'] ?? 'folder'),
            (string) ($_POST['color'] ?? 'gray')
        );
        flash($warning !== '' ? 'Proyecto creado. ' . $warning : 'Proyecto creado. Agrega instrucciones y archivos para darle contexto a la IA.', $warning !== '' ? 'error' : 'success');
        return 'index.php?p=' . $projectId;
    }

    if ($action === 'update_project') {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $project = find_user_project($pdo, $userId, $projectId);
        if (!$project) {
            throw new RuntimeException('No encontré el proyecto.');
        }
        $name = trim((string) ($_POST['name'] ?? $project['name']));
        [$instructions, $warning] = screened_instructions((string) ($_POST['instructions'] ?? (string) $project['instructions']));
        update_project(
            $pdo,
            $userId,
            $projectId,
            $name !== '' ? $name : (string) $project['name'],
            trim((string) ($_POST['description'] ?? (string) $project['description'])),
            $instructions
        );
        if (isset($_POST['icon']) || isset($_POST['color'])) {
            set_project_appearance(
                $pdo,
                $userId,
                $projectId,
                (string) ($_POST['icon'] ?? ($project['icon'] ?? 'folder')),
                (string) ($_POST['color'] ?? ($project['color'] ?? 'gray'))
            );
        }
        flash($warning !== '' ? 'Cambios guardados. ' . $warning : 'Cambios guardados.', $warning !== '' ? 'error' : 'success');
        return 'index.php?p=' . $projectId;
    }

    if ($action === 'update_project_memory') {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        if (!find_user_project($pdo, $userId, $projectId)) {
            throw new RuntimeException('No encontré el proyecto.');
        }
        $memory = trim(limit_text((string) ($_POST['memory'] ?? ''), 20000));
        $statement = $pdo->prepare('UPDATE projects SET memory_md = ?, memory_updated_at = NOW() WHERE id = ? AND user_id = ?');
        $statement->execute([$memory !== '' ? $memory : null, $projectId, $userId]);
        flash('Memoria del proyecto guardada.', 'success');
        return 'index.php?p=' . $projectId;
    }

    if ($action === 'archive_project') {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        if (!find_user_project($pdo, $userId, $projectId)) {
            throw new RuntimeException('No encontré el proyecto.');
        }
        archive_project($pdo, $userId, $projectId);
        flash('Proyecto archivado. Sus chats siguen disponibles en Recientes.', 'success');
        return 'index.php?view=projects';
    }

    // new_chat (formulario antiguo)
    $projectId = (int) ($_POST['project_id'] ?? 0);
    return find_user_project($pdo, $userId, $projectId) ? 'index.php?p=' . $projectId : 'index.php';
}

function route_json_action(string $action, PDO $pdo, array $user, bool $isPost): void
{
    $userId = (int) $user['id'];

    if ($action === 'memory_download') {
        handle_memory_download($pdo, $user);
        return;
    }

    if ($action === 'job_status') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $job = find_user_ai_job($pdo, $userId, (int) ($_GET['job_id'] ?? 0));
        if (!$job) {
            json_response(['error' => 'Respuesta no encontrada.'], 404);
            return;
        }
        json_response(ai_job_payload($job));
        return;
    }

    if (!$isPost) {
        json_response(['error' => 'Método no permitido.'], 405);
        return;
    }

    if ($action === 'chat') {
        $result = enqueue_chat_message($pdo, $user, read_json_input());
        json_response($result['body'], $result['status']);
        if ($result['status'] === 202) {
            run_ai_job_after_response((int) $result['body']['job_id']);
        }
        return;
    }

    if ($action === 'process_job') {
        // Libera el bloqueo de sesion: si no, las consultas de estado quedan esperando.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (ia_api_key() === '' && !deepseek_configured()) {
            json_response(['error' => 'Falta configurar DEEPSEEK_API_KEY o IA_API_KEY.'], 500);
            return;
        }
        $input = read_json_input();
        $jobId = (int) ($input['job_id'] ?? 0);
        $job = find_user_ai_job($pdo, $userId, $jobId);
        if (!$job) {
            json_response(['error' => 'Respuesta no encontrada.'], 404);
            return;
        }
        if (!in_array((string) $job['status'], ['done', 'failed'], true)) {
            use_web_ai_timeout();
            process_ai_job($jobId);
        }
        $updated = find_user_ai_job(db_connection(), $userId, $jobId);
        json_response($updated ? ai_job_payload($updated) : ['error' => 'Respuesta no encontrada.'], $updated ? 200 : 404);
        return;
    }

    if ($action === 'upload_document') {
        handle_upload_document($pdo, $user);
        return;
    }

    if ($action === 'delete_document') {
        $input = read_json_input();
        $document = find_document_for_user($pdo, $user, (int) ($input['document_id'] ?? 0));
        if (!$document) {
            json_response(['error' => 'No encontré el documento o no tienes permiso para eliminarlo.'], 404);
            return;
        }
        archive_document($pdo, (int) $document['id']);
        json_response(['ok' => true]);
        return;
    }

    if ($action === 'pin') {
        $input = read_json_input();
        $type = (string) ($input['type'] ?? '');
        $id = (int) ($input['id'] ?? 0);
        $exists = $type === 'project' ? find_user_project($pdo, $userId, $id) : find_user_conversation($pdo, $userId, $id);
        if (!$exists) {
            json_response(['error' => 'No encontré el elemento.'], 404);
            return;
        }
        $pinned = toggle_pin($pdo, $type === 'project' ? 'projects' : 'conversations', $userId, $id);
        json_response(['ok' => true, 'pinned' => $pinned]);
        return;
    }

    if ($action === 'chat_update') {
        handle_chat_update($pdo, $user, read_json_input());
        return;
    }

    if ($action === 'export_pdf' || $action === 'export_docx') {
        handle_export($pdo, $user, read_json_input(), $action === 'export_docx' ? 'docx' : 'pdf');
        return;
    }
}

/**
 * Las instrucciones adicionales fuera del alcance no se guardan: prevalece la instruccion base.
 *
 * @return array{0: string, 1: string} [instrucciones a guardar, aviso para el usuario]
 */
function screened_instructions(string $instructions): array
{
    $instructions = trim($instructions);
    $review = review_project_instructions($instructions);
    if ($review['ok']) {
        return [$instructions, ''];
    }

    return ['', 'La instrucción adicional no se guardó porque ' . $review['reason'] . '. Siempre prevalece la instrucción base del proyecto: solo cumplimiento y PLA/FT/FPADM.'];
}

/**
 * Descarga la memoria (chat o proyecto) como archivo Markdown.
 */
function handle_memory_download(PDO $pdo, array $user): void
{
    $userId = (int) $user['id'];
    $memory = '';
    $name = 'memoria';

    $conversation = find_user_conversation($pdo, $userId, (int) ($_GET['c'] ?? 0));
    if ($conversation) {
        $memory = (string) ($conversation['memory_md'] ?? '');
        $name = 'memoria_chat_' . (int) $conversation['id'];
    } else {
        $project = find_user_project($pdo, $userId, (int) ($_GET['p'] ?? 0));
        if (!$project) {
            json_response(['error' => 'No encontré la memoria solicitada.'], 404);
            return;
        }
        $memory = (string) ($project['memory_md'] ?? '');
        $name = 'memoria_proyecto_' . (int) $project['id'];
    }

    if (trim($memory) === '') {
        $memory = "# Memoria\n\nAún no hay memoria. Se genera al compactar la conversación (/compact) o automáticamente cuando el chat crece.\n";
    }

    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '_' . date('Ymd') . '.md"');
    echo $memory;
}

function handle_upload_document(PDO $pdo, array $user): void
{
    if (empty($_FILES['document']) || !is_array($_FILES['document'])) {
        json_response(['error' => 'Selecciona un archivo.'], 400);
        return;
    }

    $userId = (int) $user['id'];
    $target = (string) ($_POST['target'] ?? 'chat');
    $projectId = 0;
    $conversationId = 0;
    $scope = 'private';

    if ($target === 'library') {
        if (!user_is_admin($user)) {
            json_response(['error' => 'Solo un administrador puede cargar documentos a la biblioteca normativa.'], 403);
            return;
        }
        $scope = 'library';
    } elseif ($target === 'project') {
        $project = find_user_project($pdo, $userId, (int) ($_POST['project_id'] ?? 0));
        if (!$project) {
            json_response(['error' => 'No encontré el proyecto.'], 404);
            return;
        }
        $projectId = (int) $project['id'];
    } else {
        $conversation = find_user_conversation($pdo, $userId, (int) ($_POST['conversation_id'] ?? 0));
        if ($conversation) {
            $conversationId = (int) $conversation['id'];
            $projectId = (int) ($conversation['project_id'] ?? 0);
        } else {
            // Adjuntar antes del primer mensaje crea el chat, como en otros asistentes.
            $project = find_user_project($pdo, $userId, (int) ($_POST['project_id'] ?? 0));
            $projectId = $project ? (int) $project['id'] : 0;
            $fileName = (string) ($_FILES['document']['name'] ?? 'documento');
            $conversationId = create_conversation($pdo, $userId, $projectId, 'Revisión de ' . document_title($fileName), '');
        }
    }

    $document = ingest_uploaded_document($pdo, $userId, $_FILES['document'], $scope, $projectId, $conversationId);
    if ($conversationId > 0) {
        touch_conversation($pdo, $conversationId);
    }
    touch_project($pdo, $projectId);

    json_response([
        'ok' => true,
        'document' => $document,
        'conversation_id' => $conversationId,
        'project_id' => $projectId,
        'message' => $document['ready']
            ? 'Listo para consultar: ' . $document['name']
            : 'Guardado, pero sin texto consultable: ' . ($document['error'] ?: $document['name']),
    ], 201);
}

function handle_chat_update(PDO $pdo, array $user, array $input): void
{
    $userId = (int) $user['id'];
    $conversation = find_user_conversation($pdo, $userId, (int) ($input['conversation_id'] ?? 0));
    if (!$conversation) {
        json_response(['error' => 'No encontré el chat.'], 404);
        return;
    }

    $op = (string) ($input['op'] ?? '');
    $conversationId = (int) $conversation['id'];

    if ($op === 'rename') {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            json_response(['error' => 'Escribe un nombre para el chat.'], 400);
            return;
        }
        rename_conversation($pdo, $userId, $conversationId, $title);
        json_response(['ok' => true, 'title' => conversation_title($title)]);
        return;
    }

    if ($op === 'move') {
        $projectId = (int) ($input['project_id'] ?? 0);
        if ($projectId > 0 && !find_user_project($pdo, $userId, $projectId)) {
            json_response(['error' => 'No encontré el proyecto.'], 404);
            return;
        }
        move_conversation($pdo, $userId, $conversationId, $projectId);
        json_response(['ok' => true, 'project_id' => $projectId]);
        return;
    }

    if ($op === 'delete') {
        delete_conversation($pdo, $userId, $conversationId);
        json_response(['ok' => true, 'redirect' => (int) ($conversation['project_id'] ?? 0) > 0 ? 'index.php?p=' . (int) $conversation['project_id'] : 'index.php']);
        return;
    }

    if ($op === 'memory_get') {
        json_response([
            'ok' => true,
            'memory' => (string) ($conversation['memory_md'] ?? ''),
            'updated' => !empty($conversation['memory_updated_at']) ? relative_time((string) $conversation['memory_updated_at']) : '',
        ]);
        return;
    }

    if ($op === 'memory_save') {
        $memory = trim(limit_text((string) ($input['memory'] ?? ''), 20000));
        $statement = $pdo->prepare('UPDATE conversations SET memory_md = ?, memory_updated_at = NOW() WHERE id = ? AND user_id = ?');
        $statement->execute([$memory !== '' ? $memory : null, $conversationId, $userId]);
        json_response(['ok' => true]);
        return;
    }

    if ($op === 'skill') {
        $skill = valid_skill_slug((string) ($input['skill'] ?? ''));
        set_conversation_skill($pdo, $conversationId, $skill);
        json_response(['ok' => true, 'skill' => $skill]);
        return;
    }

    json_response(['error' => 'Operación no válida.'], 400);
}

function handle_export(PDO $pdo, array $user, array $input, string $format): void
{
    $type = trim((string) ($input['document_type'] ?? ''));
    $content = trim((string) ($input['content'] ?? ''));

    if ($content === '') {
        json_response(['error' => 'No hay contenido para exportar. Genera primero una respuesta.'], 400);
        return;
    }

    $meta = [
        'title' => trim((string) ($input['title'] ?? '')),
        'company' => trim((string) ($input['company'] ?? '')),
        'author' => trim((string) ($input['author'] ?? '')),
        'user_name' => trim((string) ($user['name'] ?? '')),
        'ruc' => trim((string) ($input['ruc'] ?? '')),
        'code' => trim((string) ($input['code'] ?? '')),
        'version' => trim((string) ($input['version'] ?? '')),
    ];

    if ($format === 'docx') {
        require_once __DIR__ . '/includes/docx_exporter.php';
        if (!isset(docx_document_types()[$type])) {
            json_response(['error' => 'Tipo de documento no válido.'], 400);
            return;
        }
        stream_docx_document($type, $content, $meta);
        return;
    }

    require_once __DIR__ . '/includes/pdf_exporter.php';

    if ($type === 'matriz') {
        $matrix = matrix_data_from_content($content);
        if (!$matrix) {
            json_response(['error' => 'No encontré los datos de la matriz. Usa la habilidad Matriz de riesgo y deja que la IA termine la entrevista.'], 400);
            return;
        }
        stream_matrix_pdf($matrix, $meta);
        return;
    }

    if (!isset(pdf_document_types()[$type])) {
        json_response(['error' => 'Tipo de documento no válido.'], 400);
        return;
    }

    stream_pdf_document($type, $content, $meta);
}

/**
 * Arma los datos de la pantalla segun la URL.
 */
function build_page(PDO $pdo, array $user): array
{
    $userId = (int) $user['id'];
    $view = (string) ($_GET['view'] ?? '');
    $conversationId = (int) ($_GET['c'] ?? 0);
    $projectId = (int) ($_GET['p'] ?? 0);

    $page = [
        'user' => $user,
        'isAdmin' => user_is_admin($user),
        'flash' => take_flash(),
        'projects' => user_projects($pdo, $userId),
        'recent' => user_conversation_list($pdo, $userId, null, 120),
        'view' => 'chat',
        'conversation' => null,
        'project' => null,
        'messages' => [],
        'attachments' => [],
        'projectChats' => [],
        'projectDocuments' => [],
        'library' => [],
        'skill' => '',
        'pendingJobId' => 0,
        'title' => 'Nuevo chat',
    ];

    if ($view === 'projects') {
        $page['view'] = 'projects';
        $page['title'] = 'Proyectos';
        return $page;
    }

    if ($view === 'library') {
        $page['view'] = 'library';
        $page['title'] = 'Biblioteca normativa';
        $page['library'] = library_documents($pdo);
        return $page;
    }

    if ($conversationId > 0) {
        $conversation = find_user_conversation($pdo, $userId, $conversationId);
        if (!$conversation) {
            flash('Ese chat ya no está disponible.', 'error');
            return ['redirect' => 'index.php'];
        }
        $page['conversation'] = $conversation;
        $page['title'] = (string) $conversation['title'];
        $page['messages'] = conversation_messages($pdo, $conversationId);
        $page['attachments'] = conversation_documents($pdo, $userId, $conversationId);
        $page['skill'] = valid_skill_slug((string) ($conversation['skill_slug'] ?? ''));
        $page['project'] = find_user_project($pdo, $userId, (int) ($conversation['project_id'] ?? 0));
        $page['pendingJobId'] = pending_job_for_conversation($pdo, $userId, $conversationId);
        return $page;
    }

    if ($projectId > 0) {
        $project = find_user_project($pdo, $userId, $projectId);
        if (!$project) {
            flash('Ese proyecto ya no está disponible.', 'error');
            return ['redirect' => 'index.php?view=projects'];
        }
        $page['view'] = 'project';
        $page['project'] = $project;
        $page['title'] = (string) $project['name'];
        $page['projectChats'] = user_conversation_list($pdo, $userId, $projectId, 100);
        $page['projectDocuments'] = project_documents($pdo, $userId, $projectId);
        return $page;
    }

    return $page;
}
