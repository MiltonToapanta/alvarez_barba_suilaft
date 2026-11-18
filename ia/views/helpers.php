<?php
declare(strict_types=1);

function render_view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require __DIR__ . '/' . $name . '.php';
}

function render_partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require __DIR__ . '/partials/' . $name . '.php';
}

/**
 * Juego de iconos de linea (24x24, trazo 1.75) para toda la interfaz: un solo lenguaje
 * visual, igual peso que el texto contiguo.
 */
function icon_paths(): array
{
    return [
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'compose' => '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.4 2.6a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'folder' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9l-.8-1.2A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'library' => '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/>',
        'book' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a2.5 2.5 0 0 1 0-5H20"/>',
        'grid' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>',
        'doc' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h6M16 13H8M16 17H8M10 9H8"/>',
        'route' => '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.7 9a1 1 0 0 1-.6 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1.2 1.2 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'clip' => '<path d="m21.4 11.1-9.2 9.2a6 6 0 0 1-8.5-8.5l8.6-8.6a4 4 0 0 1 5.7 5.7l-8.6 8.6a2 2 0 0 1-2.8-2.8l8.5-8.5"/>',
        'mic' => '<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/>',
        'send' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'speaker' => '<path d="M11 4.7a.7.7 0 0 0-1.2-.5L6.4 7.6a1.4 1.4 0 0 1-1 .4H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.4a1.4 1.4 0 0 1 1 .4l3.4 3.4a.7.7 0 0 0 1.2-.5z"/><path d="M16 9a5 5 0 0 1 0 6M19.4 18.4a9 9 0 0 0 0-12.8"/>',
        'speaker_off' => '<path d="M11 4.7a.7.7 0 0 0-1.2-.5L6.4 7.6a1.4 1.4 0 0 1-1 .4H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.4a1.4 1.4 0 0 1 1 .4l3.4 3.4a.7.7 0 0 0 1.2-.5z"/><path d="m22 9-6 6M16 9l6 6"/>',
        'sidebar' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/>',
        'more' => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'copy' => '<rect x="8" y="8" width="14" height="14" rx="2"/><path d="M4 16a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2"/>',
        'download' => '<path d="M12 15V3M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5"/>',
        'upload' => '<path d="M12 3v12M17 8l-5-5-5 5M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>',
        'trash' => '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'chevron_right' => '<path d="m9 18 6-6-6-6"/>',
        'chevron_down' => '<path d="m6 9 6 6 6-6"/>',
        'sparkle' => '<path d="M9.9 15.5a2 2 0 0 0-1.4-1.4l-6.1-1.6a.5.5 0 0 1 0-1l6.1-1.6a2 2 0 0 0 1.4-1.4l1.6-6.1a.5.5 0 0 1 1 0l1.6 6.1a2 2 0 0 0 1.4 1.4l6.1 1.6a.5.5 0 0 1 0 1l-6.1 1.6a2 2 0 0 0-1.4 1.4l-1.6 6.1a.5.5 0 0 1-1 0z"/>',
        'pencil' => '<path d="M21.2 6.8a2.8 2.8 0 0 0-4-4L3.8 16.2a2 2 0 0 0-.5.8L2 21.4a.5.5 0 0 0 .6.6l4.4-1.3a2 2 0 0 0 .8-.5z"/>',
        'retry' => '<path d="M3 12a9 9 0 1 0 9-9 9.8 9.8 0 0 0-6.7 2.7L3 8"/><path d="M3 3v5h5"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'guide' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'move' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9l-.8-1.2A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/><path d="M12 10v6M9 13l3 3 3-3"/>',
        'memory' => '<path d="M12 5a3 3 0 1 0-5.9.8A4 4 0 0 0 4 13a4 4 0 0 0 2 6.9A3 3 0 0 0 12 19Z"/><path d="M12 5a3 3 0 1 1 5.9.8A4 4 0 0 1 20 13a4 4 0 0 1-2 6.9A3 3 0 0 1 12 19Z"/><path d="M12 5v14"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
        'scale' => '<path d="m16 16 3-8 3 8c-.9.7-1.9 1-3 1s-2.1-.3-3-1ZM2 16l3-8 3 8c-.9.7-1.9 1-3 1s-2.1-.3-3-1Z"/><path d="M7 21h10M12 3v18M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 16v-4M12 16V8M17 16v-7"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'flag' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7"/>',
        'star' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'pin' => '<path d="M12 17v5M9 10.8a2 2 0 0 1-1.1 1.8l-1.8.9A2 2 0 0 0 5 15.2V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.8a2 2 0 0 0-1.1-1.7l-1.8-.9A2 2 0 0 1 15 10.8V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'settings' => '<path d="M20 7h-9M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
    ];
}

function icon(string $name, string $class = ''): string
{
    $paths = icon_paths();
    $body = $paths[$name] ?? $paths['sparkle'];

    return '<svg class="icon' . ($class !== '' ? ' ' . h($class) : '') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
}

/**
 * Marca: el escudo de SUILAFT (el mismo del login) sobre un sello azul noche.
 */
function brand_mark(string $class = ''): string
{
    return '<span class="brand-mark' . ($class !== '' ? ' ' . h($class) : '') . '" aria-hidden="true">'
        . '<img src="' . h(asset_url('assets/img/escudo-160.png')) . '" alt="" width="160" height="155" decoding="async"></span>';
}

/**
 * Icono de proyecto con su color (sidebar, tarjetas, encabezados).
 */
function project_icon(array $project, string $class = ''): string
{
    $iconName = valid_project_icon((string) ($project['icon'] ?? 'folder'));
    $color = valid_project_color((string) ($project['color'] ?? 'gray'));

    return '<span class="pc pc-' . h($color) . ($class !== '' ? ' ' . h($class) : '') . '">' . icon($iconName) . '</span>';
}

/**
 * Bloque visible y no editable con la instruccion de primer orden del proyecto.
 */
function base_instruction_block(bool $compact = false): string
{
    return '<div class="locked-instruction' . ($compact ? ' is-compact' : '') . '">'
        . '<div class="locked-head">' . icon('lock') . '<strong>Instrucción base</strong><span>Bloqueada · siempre prevalece</span></div>'
        . '<p>' . nl2br(h(project_base_instruction())) . '</p></div>';
}

function instructions_warning(): string
{
    return '<p class="instructions-warning">' . icon('alert')
        . '<span>Puedes agregar contexto de la entidad o preferencias de formato. Si una instrucción adicional sale del cumplimiento o intenta cambiar las reglas, <strong>se omite</strong> y se aplica la instrucción base.</span></p>';
}

function file_kind_label(string $extension): string
{
    $extension = strtolower($extension);
    $labels = ['pdf' => 'PDF', 'docx' => 'Word', 'txt' => 'Texto', 'md' => 'Markdown', 'csv' => 'CSV', 'json' => 'JSON', 'html' => 'HTML', 'htm' => 'HTML', 'xml' => 'XML', 'log' => 'Texto'];
    return $labels[$extension] ?? strtoupper($extension);
}

function document_status_label(array $document): string
{
    return (string) ($document['extraction_status'] ?? '') === 'ready' ? 'Indexado' : 'Sin texto';
}

function nav_is(array $page, string $view, int $projectId = 0): bool
{
    if ($view === 'project') {
        return $page['view'] === 'project' && (int) ($page['project']['id'] ?? 0) === $projectId;
    }

    return $page['view'] === $view;
}

function app_client_config(array $page): array
{
    return [
        'csrf' => csrf_token(),
        'view' => $page['view'],
        'conversationId' => (int) ($page['conversation']['id'] ?? 0),
        'projectId' => (int) ($page['project']['id'] ?? 0),
        'projectName' => (string) ($page['project']['name'] ?? ''),
        'skill' => (string) $page['skill'],
        'pendingJobId' => (int) ($page['pendingJobId'] ?? 0),
        'skills' => skills_for_client(),
        'projects' => array_map(function (array $project): array {
            return ['id' => (int) $project['id'], 'name' => (string) $project['name']];
        }, $page['projects']),
        'isAdmin' => (bool) $page['isAdmin'],
        'brandImage' => asset_url('assets/img/escudo-160.png'),
        'userName' => (string) ($page['user']['name'] ?? ''),
        'uploadMaxMb' => max(1, (int) env_value('UPLOAD_MAX_MB', '15')),
    ];
}
