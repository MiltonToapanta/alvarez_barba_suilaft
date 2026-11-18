<?php
declare(strict_types=1);

/*
 * Proveedores de modelo. DeepSeek (API compatible con OpenAI) recibe mensajes con roles
 * reales (system / user / assistant): la politica viaja en "system" y no se mezcla con lo
 * que escribe el usuario. La IA de Suilaft (/generate) solo acepta un texto, asi que para
 * ella se aplana la conversacion con delimitadores claros.
 */

function deepseek_configured(): bool
{
    return env_value('DEEPSEEK_API_KEY', '') !== '';
}

function provider_endpoint(string $provider): string
{
    return $provider === 'deepseek' ? '/chat/completions' : '/generate';
}

/**
 * @param array $options max_tokens, temperature, json (bool), timeout, thinking
 */
function call_deepseek_messages(array $messages, array $options = []): array
{
    $apiKey = env_value('DEEPSEEK_API_KEY', '');
    $baseUrl = rtrim(env_value('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'), '/');
    $model = env_value('DEEPSEEK_MODEL', 'deepseek-v4-flash');
    $timeout = (int) ($options['timeout'] ?? max(5, (int) env_value('DEEPSEEK_HTTP_TIMEOUT', '60')));

    $payload = [
        'model' => $model,
        'messages' => $messages,
    ];

    $thinking = (string) ($options['thinking'] ?? env_value('DEEPSEEK_THINKING', 'disabled'));
    if (in_array($thinking, ['enabled', 'disabled'], true)) {
        $payload['thinking'] = ['type' => $thinking];
    }

    $maxTokens = (int) ($options['max_tokens'] ?? env_value('DEEPSEEK_MAX_TOKENS', '4096'));
    if ($maxTokens > 0) {
        $payload['max_tokens'] = $maxTokens;
    }

    if (isset($options['temperature'])) {
        $payload['temperature'] = (float) $options['temperature'];
    }

    if (!empty($options['json'])) {
        $payload['response_format'] = ['type' => 'json_object'];
    }

    return http_post_json(
        $baseUrl . '/chat/completions',
        ['Authorization: Bearer ' . $apiKey],
        $payload,
        $timeout,
        'DeepSeek'
    );
}

/**
 * Compatibilidad: llamada con un solo texto (trabajos antiguos en cola).
 */
function call_deepseek(string $prompt): array
{
    return call_deepseek_messages([['role' => 'user', 'content' => $prompt]]);
}

function call_generate(string $url, string $apiKey, string $prompt): array
{
    $timeout = max(5, (int) env_value('IA_HTTP_TIMEOUT', '180'));

    return http_post_json($url, ['x-api-key: ' . $apiKey], ['prompt' => $prompt], $timeout, 'El servicio de IA');
}

function http_post_json(string $url, array $headers, array $payload, int $timeout, string $serviceName): array
{
    @set_time_limit($timeout + 20);
    @ini_set('default_socket_timeout', (string) ($timeout + 10));

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n" . implode("\r\n", $headers) . "\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $status = response_status_code($http_response_header ?? []);

    if ($raw === false) {
        return [
            'ok' => false,
            'status' => 504,
            'body' => $serviceName . ' no respondio dentro de ' . $timeout . ' segundos.',
        ];
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status > 0 ? $status : 502,
        'body' => parse_response($raw),
    ];
}

function faster_prompt(string $prompt): string
{
    return $prompt . "\n\n"
        . 'IMPORTANTE: responde en una version breve y directa porque el servidor tiene limite de tiempo. '
        . 'No hagas una respuesta extensa. Usa maximo 8 parrafos cortos o una lista clara. '
        . 'Si el usuario pidio un documento largo, entrega un borrador resumido listo para ampliar por partes.';
}

function normalize_deepseek_answer($payload): string
{
    if (is_array($payload)) {
        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (is_string($content)) {
            return $content;
        }
    }

    return normalize_answer($payload);
}

function normalize_answer($payload): string
{
    if (is_string($payload)) {
        return $payload;
    }

    if (is_array($payload)) {
        foreach (['answer', 'response', 'result', 'text'] as $key) {
            if (isset($payload[$key])) {
                return is_string($payload[$key]) ? $payload[$key] : json_text($payload[$key]);
            }
        }
    }

    return json_text($payload);
}

function parse_response(string $raw)
{
    $decoded = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
}

function response_status_code(array $headers): int
{
    // Con redirecciones hay varias lineas HTTP/; la ultima es la respuesta final.
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $status = (int) $matches[1];
        }
    }

    return $status;
}

/**
 * Convierte mensajes con roles a un solo texto para proveedores que solo aceptan "prompt".
 */
function flatten_messages(array $messages): string
{
    $parts = [];
    foreach ($messages as $message) {
        $content = trim((string) $message['content']);
        if ($message['role'] === 'system') {
            $parts[] = "=== INSTRUCCIONES DEL SISTEMA (prioridad maxima) ===\n" . $content . "\n=== FIN DE INSTRUCCIONES DEL SISTEMA ===";
        } elseif ($message['role'] === 'assistant') {
            $parts[] = 'Asistente: ' . $content;
        } else {
            $parts[] = 'Usuario: ' . $content;
        }
    }
    $parts[] = 'Asistente:';

    return implode("\n\n", $parts);
}
