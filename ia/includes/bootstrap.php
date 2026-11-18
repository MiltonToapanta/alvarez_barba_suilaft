<?php
declare(strict_types=1);

/*
 * Arranque compartido entre index.php y worker_ai_jobs.php.
 * Compatible con PHP 7.4: sin match, sin nullsafe, sin argumentos con nombre.
 */

// Reintentos totales (web + cron) antes de rendirse con un job que solo da timeout.
if (!defined('AI_JOB_MAX_ATTEMPTS')) {
    define('AI_JOB_MAX_ATTEMPTS', 6);
}

define('APP_ROOT', dirname(__DIR__));

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/ai/skills.php';
require_once __DIR__ . '/ai/rag.php';
require_once __DIR__ . '/ai/guard.php';
require_once __DIR__ . '/ai/providers.php';
require_once __DIR__ . '/ai/memory.php';
require_once __DIR__ . '/ai/pipeline.php';

function app_boot(): void
{
    foreach ([APP_ROOT . '/.env', dirname(APP_ROOT) . '/.env'] as $envFile) {
        if (is_file($envFile)) {
            load_env_file($envFile);
            break;
        }
    }

    $timezone = env_value('APP_TIMEZONE', 'America/Guayaquil');
    if (!@date_default_timezone_set($timezone)) {
        date_default_timezone_set('America/Guayaquil');
    }
}

function db_connection(): PDO
{
    $dsn = env_value('DB_DSN');
    if ($dsn === '') {
        $host = env_value('DB_HOST', '127.0.0.1');
        $port = env_value('DB_PORT', '3306');
        $database = env_value('DB_NAME', 'u257567426_verduv');
        $charset = env_value('DB_CHARSET', 'utf8mb4');
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;
    }

    $pdo = new PDO($dsn, env_value('DB_USER', 'root'), env_value('DB_PASSWORD', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Misma zona horaria en PHP y MySQL: fechas relativas y limites mensuales coherentes.
    $pdo->exec("SET time_zone = '" . date('P') . "'");

    return $pdo;
}

function ia_base_url(): string
{
    return rtrim(env_value('IA_BASE_URL', env_value('ia.base-url', 'http://ia.suilaft.tech')), '/');
}

function ia_api_key(): string
{
    return env_value('IA_API_KEY', env_value('ia.api-key', ''));
}

function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $value = expand_env_refs($value);

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function env_value(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : (string) $value;
}

function env_flag(string $key, bool $default): bool
{
    $value = strtolower(env_value($key, $default ? '1' : '0'));
    return in_array($value, ['1', 'true', 'yes', 'on', 'si'], true);
}

function expand_env_refs(string $value): string
{
    return (string) preg_replace_callback('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/', function (array $matches): string {
        return env_value($matches[1], $matches[0]);
    }, $value);
}

function json_text($value): string
{
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json === false ? '' : $json;
}

function json_compact($value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? '' : $json;
}

function json_decode_array($value): array
{
    if (!is_string($value) || $value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function limit_text(string $text, int $maxChars): string
{
    if ($maxChars <= 0) {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars, 'UTF-8');
    }

    if (strlen($text) <= $maxChars) {
        return $text;
    }

    return substr($text, 0, $maxChars);
}

function text_length(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function text_lower(string $text): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

/**
 * Quita tildes para comparar palabras clave sin depender de como escribe el usuario.
 */
function text_fold(string $text): string
{
    $text = text_lower($text);
    return strtr($text, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
    ]);
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function json_response(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function read_json_input(): array
{
    $input = json_decode((string) file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function redirect_to(string $url): void
{
    header('Location: ' . $url);
}

function flash(string $message, string $tone = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'tone' => $tone];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    // Compatibilidad con el mensaje antiguo guardado en page_error.
    if (!$flash && !empty($_SESSION['page_error'])) {
        $flash = ['message' => (string) $_SESSION['page_error'], 'tone' => 'info'];
    }
    unset($_SESSION['page_error']);

    return is_array($flash) ? $flash : null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_valid(): bool
{
    $expected = (string) ($_SESSION['csrf_token'] ?? '');
    if ($expected === '') {
        return false;
    }

    $given = (string) ($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return $given !== '' && hash_equals($expected, $given);
}

function asset_url(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return $path . '?v=' . $version;
}

function relative_time(string $timestamp): string
{
    $time = strtotime($timestamp);
    if ($time === false) {
        return '';
    }

    $diff = time() - $time;
    if ($diff < 60) {
        return 'hace un momento';
    }
    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);
        return 'hace ' . $minutes . ' min';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return 'hace ' . $hours . ' h';
    }
    if ($diff < 86400 * 7) {
        $days = (int) floor($diff / 86400);
        return $days === 1 ? 'ayer' : 'hace ' . $days . ' dias';
    }

    return date('d/m/Y', $time);
}

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}
