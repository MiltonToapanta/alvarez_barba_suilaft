<?php
declare(strict_types=1);

/*
 * RAG (Retrieval-Augmented Generation) sin dependencias externas.
 *
 * Fuentes de conocimiento, de mas especifica a mas general:
 *   1. Archivos adjuntos a la conversacion.
 *   2. Base de conocimiento del proyecto (Project knowledge).
 *   3. Biblioteca normativa global (documentos que carga un administrador: leyes,
 *      resoluciones UAFE, guias GAFI...).
 *
 * Indexacion: fragmentos de ~1.800 caracteres con solapamiento, cortados por parrafos.
 * Recuperacion: indice FULLTEXT de MySQL/MariaDB (ranking por relevancia) con respaldo
 * por LIKE si el servidor no tiene FULLTEXT. Los fragmentos se entregan al modelo
 * numerados como [Fuente n] para que cite de donde sale cada afirmacion.
 */

const RAG_CHUNK_CHARS = 1800;
const RAG_CHUNK_OVERLAP = 200;
const RAG_MAX_DOCUMENT_CHARS = 400000;
const RAG_ALLOWED_EXTENSIONS = ['txt', 'md', 'csv', 'json', 'html', 'htm', 'xml', 'log', 'docx', 'pdf'];

/* ---------------------------------------------------------------------------
 * Ingesta
 * ------------------------------------------------------------------------- */

/**
 * Guarda un archivo subido, extrae su texto y lo indexa.
 *
 * @param string $scope 'private' (usuario/proyecto/chat) o 'library' (biblioteca global)
 */
function ingest_uploaded_document(PDO $pdo, int $userId, array $file, string $scope, int $projectId, int $conversationId): array
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('El archivo esta vacio.');
    }

    $maxMb = max(1, (int) env_value('UPLOAD_MAX_MB', '15'));
    if ($size > $maxMb * 1024 * 1024) {
        throw new RuntimeException('El archivo supera ' . $maxMb . ' MB.');
    }

    $originalName = basename((string) ($file['name'] ?? 'documento'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, RAG_ALLOWED_EXTENSIONS, true)) {
        throw new RuntimeException('Formato no permitido. Usa PDF, DOCX, TXT, MD, CSV, JSON o HTML.');
    }

    $tempPath = (string) ($file['tmp_name'] ?? '');
    if ($tempPath === '' || !is_uploaded_file($tempPath)) {
        throw new RuntimeException('No pude validar el archivo subido.');
    }

    $storageDir = documents_storage_dir();
    $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $storagePath = $storageDir . '/' . $storedName;

    if (!move_uploaded_file($tempPath, $storagePath)) {
        throw new RuntimeException('No pude guardar el archivo en el servidor.');
    }

    $extracted = extract_document_text($storagePath, $extension);
    $text = limit_text(trim($extracted['text']), RAG_MAX_DOCUMENT_CHARS);
    $status = $text !== '' ? 'ready' : 'stored';

    $statement = $pdo->prepare(
        'INSERT INTO documents
            (user_id, project_id, conversation_id, scope, title, original_name, stored_name, storage_path,
             mime_type, extension, file_size, content_text, extraction_status, extraction_error, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', NOW(), NOW())'
    );
    $statement->execute([
        $userId,
        $projectId > 0 ? $projectId : null,
        $conversationId > 0 ? $conversationId : null,
        $scope === 'library' ? 'library' : 'private',
        document_title($originalName),
        $originalName,
        $storedName,
        'storage/documents/' . $storedName,
        detected_mime_type($storagePath),
        $extension,
        $size,
        $text !== '' ? $text : null,
        $status,
        $extracted['error'],
    ]);

    $documentId = (int) $pdo->lastInsertId();
    $chunks = 0;
    if ($text !== '') {
        $chunks = index_document_chunks($pdo, $documentId, $userId, $text);
    }

    return [
        'id' => $documentId,
        'name' => $originalName,
        'title' => document_title($originalName),
        'ready' => $text !== '',
        'chunks' => $chunks,
        'error' => $extracted['error'],
        'extension' => $extension,
        'size' => $size,
    ];
}

function index_document_chunks(PDO $pdo, int $documentId, int $userId, string $text): int
{
    $statement = $pdo->prepare('DELETE FROM document_chunks WHERE document_id = ?');
    $statement->execute([$documentId]);

    $chunks = chunk_document_text($text, RAG_CHUNK_CHARS, RAG_CHUNK_OVERLAP);
    $statement = $pdo->prepare(
        'INSERT INTO document_chunks (document_id, user_id, chunk_index, content, char_count, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );

    foreach ($chunks as $index => $chunk) {
        $statement->execute([$documentId, $userId, $index, $chunk, text_length($chunk)]);
    }

    return count($chunks);
}

/**
 * Corta por parrafos respetando UTF-8 y deja un solapamiento para no partir ideas a la mitad.
 */
function chunk_document_text(string $text, int $size, int $overlap): array
{
    $paragraphs = preg_split('/\n\s*\n/u', $text) ?: [$text];
    $chunks = [];
    $current = '';

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }

        // Parrafos gigantes (tablas o PDFs sin saltos): se parten por oraciones o por tamano.
        if (text_length($paragraph) > $size) {
            if ($current !== '') {
                $chunks[] = $current;
                $current = '';
            }
            foreach (split_long_text($paragraph, $size, $overlap) as $piece) {
                $chunks[] = $piece;
            }
            continue;
        }

        $candidate = $current === '' ? $paragraph : $current . "\n\n" . $paragraph;
        if (text_length($candidate) <= $size) {
            $current = $candidate;
            continue;
        }

        $chunks[] = $current;
        $tail = text_tail($current, $overlap);
        $current = $tail !== '' ? $tail . "\n\n" . $paragraph : $paragraph;
    }

    if (trim($current) !== '') {
        $chunks[] = $current;
    }

    return array_values(array_filter(array_map('trim', $chunks), function (string $chunk): bool {
        return $chunk !== '';
    }));
}

function split_long_text(string $text, int $size, int $overlap): array
{
    $pieces = [];
    $length = text_length($text);
    $step = max(200, $size - $overlap);

    for ($offset = 0; $offset < $length; $offset += $step) {
        $piece = function_exists('mb_substr') ? mb_substr($text, $offset, $size, 'UTF-8') : substr($text, $offset, $size);
        $piece = trim($piece);
        if ($piece !== '') {
            $pieces[] = $piece;
        }
        if ($offset + $size >= $length) {
            break;
        }
    }

    return $pieces;
}

function text_tail(string $text, int $chars): string
{
    if ($chars <= 0) {
        return '';
    }

    $length = text_length($text);
    if ($length <= $chars) {
        return '';
    }

    $tail = function_exists('mb_substr') ? mb_substr($text, $length - $chars, null, 'UTF-8') : substr($text, -$chars);
    // Empieza en el siguiente limite de palabra para no arrancar con media palabra.
    $space = strpos($tail, ' ');
    return trim($space !== false ? substr($tail, $space) : $tail);
}

/* ---------------------------------------------------------------------------
 * Recuperacion
 * ------------------------------------------------------------------------- */

/**
 * Devuelve ['context' => string para el prompt, 'sources' => lista de fuentes citables].
 */
function retrieve_knowledge(PDO $pdo, int $userId, int $conversationId, int $projectId, string $query, int $maxChars): array
{
    $empty = ['context' => '', 'sources' => []];
    if ($maxChars <= 0 || !table_exists($pdo, 'document_chunks')) {
        return $empty;
    }

    [$scopeSql, $scopeParams] = knowledge_scope_sql($userId, $conversationId, $projectId);
    $limit = max(4, min(14, (int) ceil($maxChars / 1200)));
    $rows = [];

    $terms = search_terms($query);
    if ($terms) {
        $rows = fulltext_available($pdo)
            ? search_chunks_fulltext($pdo, $scopeSql, $scopeParams, $terms, $limit)
            : search_chunks_like($pdo, $scopeSql, $scopeParams, $terms, $limit);
    }

    // Si el usuario adjunto archivos a este chat, siempre se incluye su inicio: suele
    // preguntar "revisa este documento" sin terminos que coincidan con el texto.
    if ($conversationId > 0 && count($rows) < $limit) {
        $rows = array_merge($rows, conversation_leading_chunks($pdo, $userId, $conversationId, $limit - count($rows), $rows));
    }

    if (!$rows) {
        return $empty;
    }

    return format_knowledge_context($rows, $maxChars);
}

function knowledge_scope_sql(int $userId, int $conversationId, int $projectId): array
{
    $parts = ['(d.scope = \'library\')'];
    $params = [];

    if ($conversationId > 0) {
        $parts[] = '(d.scope = \'private\' AND d.user_id = ? AND d.conversation_id = ?)';
        $params[] = $userId;
        $params[] = $conversationId;
    }

    if ($projectId > 0) {
        $parts[] = '(d.scope = \'private\' AND d.user_id = ? AND d.project_id = ? AND d.conversation_id IS NULL)';
        $params[] = $userId;
        $params[] = $projectId;
    }

    return ['(' . implode(' OR ', $parts) . ') AND d.status = \'active\' AND d.extraction_status = \'ready\'', $params];
}

function search_chunks_fulltext(PDO $pdo, string $scopeSql, array $scopeParams, array $terms, int $limit): array
{
    $query = implode(' ', $terms);
    $statement = $pdo->prepare(
        'SELECT dc.id AS chunk_id, dc.document_id, dc.chunk_index, dc.content, d.title, d.scope, d.project_id, d.conversation_id,
                MATCH(dc.content) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
         FROM document_chunks dc
         INNER JOIN documents d ON d.id = dc.document_id
         WHERE ' . $scopeSql . '
           AND MATCH(dc.content) AGAINST (? IN NATURAL LANGUAGE MODE)
         ORDER BY score DESC
         LIMIT ' . $limit
    );
    $statement->execute(array_merge([$query], $scopeParams, [$query]));

    return $statement->fetchAll();
}

function search_chunks_like(PDO $pdo, string $scopeSql, array $scopeParams, array $terms, int $limit): array
{
    $scoreParts = [];
    $scoreParams = [];
    foreach ($terms as $term) {
        $scoreParts[] = '(CASE WHEN dc.content LIKE ? THEN 1 ELSE 0 END)';
        $scoreParams[] = '%' . $term . '%';
    }

    $statement = $pdo->prepare(
        'SELECT * FROM (
            SELECT dc.id AS chunk_id, dc.document_id, dc.chunk_index, dc.content, d.title, d.scope, d.project_id, d.conversation_id,
                   (' . implode(' + ', $scoreParts) . ') AS score
            FROM document_chunks dc
            INNER JOIN documents d ON d.id = dc.document_id
            WHERE ' . $scopeSql . '
         ) ranked
         WHERE ranked.score > 0
         ORDER BY ranked.score DESC, ranked.chunk_index ASC
         LIMIT ' . $limit
    );
    $statement->execute(array_merge($scoreParams, $scopeParams));

    return $statement->fetchAll();
}

function conversation_leading_chunks(PDO $pdo, int $userId, int $conversationId, int $limit, array $already): array
{
    if ($limit <= 0) {
        return [];
    }

    $statement = $pdo->prepare(
        'SELECT dc.id AS chunk_id, dc.document_id, dc.chunk_index, dc.content, d.title, d.scope, d.project_id, d.conversation_id, 0 AS score
         FROM document_chunks dc
         INNER JOIN documents d ON d.id = dc.document_id
         WHERE d.user_id = ? AND d.conversation_id = ? AND d.status = \'active\' AND d.extraction_status = \'ready\'
         ORDER BY d.created_at DESC, dc.chunk_index ASC
         LIMIT ' . ($limit + count($already))
    );
    $statement->execute([$userId, $conversationId]);

    $seen = [];
    foreach ($already as $row) {
        $seen[(int) $row['chunk_id']] = true;
    }

    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        if (!isset($seen[(int) $row['chunk_id']])) {
            $rows[] = $row;
        }
        if (count($rows) >= $limit) {
            break;
        }
    }

    return $rows;
}

function format_knowledge_context(array $rows, int $maxChars): array
{
    $sources = [];
    $byDocument = [];
    foreach ($rows as $row) {
        $documentId = (int) $row['document_id'];
        if (!isset($byDocument[$documentId])) {
            $byDocument[$documentId] = count($sources) + 1;
            $sources[] = [
                'n' => $byDocument[$documentId],
                'document_id' => $documentId,
                'title' => (string) $row['title'],
                'scope' => source_scope_label($row),
                'chunks' => [],
            ];
        }
    }

    $blocks = [];
    $used = 0;
    foreach ($rows as $row) {
        $n = $byDocument[(int) $row['document_id']];
        $content = trim((string) $row['content']);
        $remaining = $maxChars - $used;
        if ($remaining < 300) {
            break;
        }
        if (text_length($content) > $remaining) {
            $content = limit_text($content, $remaining);
        }

        $blocks[] = '[Fuente ' . $n . ' · fragmento ' . ((int) $row['chunk_index'] + 1) . "]\n" . $content;
        $sources[$n - 1]['chunks'][] = (int) $row['chunk_index'] + 1;
        $used += text_length($content);
    }

    $sources = array_values(array_filter($sources, function (array $source): bool {
        return (bool) $source['chunks'];
    }));

    $index = [];
    foreach ($sources as $source) {
        $index[] = '[Fuente ' . $source['n'] . '] ' . $source['title'] . ' (' . $source['scope'] . ')';
    }

    $context = "Indice de fuentes:\n" . implode("\n", $index) . "\n\n" . implode("\n\n", $blocks);

    return ['context' => $context, 'sources' => $sources];
}

function source_scope_label(array $row): string
{
    if ((string) ($row['scope'] ?? '') === 'library') {
        return 'Biblioteca normativa';
    }
    if ((int) ($row['conversation_id'] ?? 0) > 0) {
        return 'Adjunto del chat';
    }

    return 'Conocimiento del proyecto';
}

/**
 * Terminos de busqueda: palabras de 4+ letras sin palabras vacias del espanol.
 */
function search_terms(string $text): array
{
    static $stopwords = null;
    if ($stopwords === null) {
        $stopwords = array_flip([
            'para', 'como', 'esta', 'este', 'estos', 'estas', 'esto', 'pero', 'porque', 'cual', 'cuales',
            'donde', 'cuando', 'sobre', 'entre', 'desde', 'hasta', 'tiene', 'tengo', 'tienen', 'hacer',
            'puedes', 'puede', 'pueden', 'quiero', 'quisiera', 'necesito', 'favor', 'ayuda', 'ayudame',
            'dame', 'dime', 'segun', 'tambien', 'mismo', 'misma', 'todo', 'toda', 'todos', 'todas', 'otro',
            'otra', 'otros', 'otras', 'solo', 'cada', 'algun', 'alguna', 'algunos', 'algunas', 'mucho',
            'muchos', 'poco', 'debo', 'debe', 'deben', 'seria', 'sera', 'estan', 'fueron', 'haber', 'hemos',
            'nuestra', 'nuestro', 'ellos', 'ellas', 'usted', 'ustedes', 'aqui', 'alli', 'bien', 'hola',
            'gracias', 'documento', 'archivo', 'revisa', 'revisar', 'explica', 'explicame', 'genera', 'generar',
            'mientras', 'entonces', 'luego', 'antes', 'despues', 'ahora', 'siempre', 'nunca', 'tanto', 'menos',
        ]);
    }

    $plain = text_fold(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '');
    $words = preg_split('/\s+/u', $plain) ?: [];
    $terms = [];

    foreach ($words as $word) {
        $word = trim($word);
        if (text_length($word) < 4 || isset($stopwords[$word]) || ctype_digit($word)) {
            continue;
        }
        $terms[$word] = $word;
        if (count($terms) >= 14) {
            break;
        }
    }

    // Siglas cortas del dominio que si importan.
    if (preg_match_all('/\b(uafe|ros|resu|pep|kyc|kyb|kye|kys|gafi|coip|scvs|seps|sri|ft|la)\b/u', $plain, $matches)) {
        foreach ($matches[1] as $acronym) {
            if (strlen($acronym) >= 3) {
                $terms[$acronym] = $acronym;
            }
        }
    }

    return array_values($terms);
}

function knowledge_context_limit(int $maxPromptChars): int
{
    return max(4000, min(14000, (int) floor($maxPromptChars * 0.25)));
}

/* ---------------------------------------------------------------------------
 * Extraccion de texto
 * ------------------------------------------------------------------------- */

function documents_storage_dir(): string
{
    $dir = APP_ROOT . '/storage/documents';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('No pude crear storage/documents.');
    }

    return $dir;
}

function detected_mime_type(string $path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }

    return 'application/octet-stream';
}

function extract_document_text(string $path, string $extension): array
{
    if ($extension === 'docx') {
        return extract_docx_text($path);
    }

    if ($extension === 'pdf') {
        return extract_pdf_text($path);
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return ['text' => '', 'error' => 'No pude leer el archivo.'];
    }

    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    if (in_array($extension, ['html', 'htm', 'xml'], true)) {
        $raw = html_entity_decode(strip_tags($raw), ENT_QUOTES, 'UTF-8');
    }

    return ['text' => normalize_document_text($raw), 'error' => null];
}

function extract_docx_text(string $path): array
{
    if (!class_exists('ZipArchive')) {
        return ['text' => '', 'error' => 'El servidor no tiene ZipArchive para leer DOCX.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['text' => '', 'error' => 'No pude abrir el DOCX.'];
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if (!is_string($xml) || $xml === '') {
        return ['text' => '', 'error' => 'El DOCX no contiene texto legible.'];
    }

    $xml = preg_replace('/<\/w:p>/', "\n\n", $xml) ?? $xml;
    $xml = preg_replace('/<\/w:tc>/', ' | ', $xml) ?? $xml;
    $xml = preg_replace('/<w:tab\/>/', "\t", $xml) ?? $xml;
    $xml = preg_replace('/<w:br[^>]*\/>/', "\n", $xml) ?? $xml;
    $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

    return ['text' => normalize_document_text($text), 'error' => null];
}

/**
 * Extractor de PDF en PHP puro. Soporta:
 *   - streams comprimidos (FlateDecode) y object streams (PDF 1.5+),
 *   - fuentes con mapa ToUnicode (Word, LibreOffice y la mayoria de PDFs oficiales),
 *   - strings literales y hexadecimales en los operadores Tj, TJ, ' y ".
 * Los PDFs escaneados (solo imagen) necesitan OCR: se informa al usuario.
 */
function extract_pdf_text(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        return ['text' => '', 'error' => 'No pude leer el PDF.'];
    }

    $objects = pdf_parse_objects($raw);

    // Mapas ToUnicode por objeto de fuente y nombre de recurso -> objeto de fuente.
    $fontCmaps = [];
    $fontNames = [];
    foreach ($objects as $number => $object) {
        $dict = $object['dict'];
        if (preg_match('#/Type\s*/Font\b#', $dict) && preg_match('#/ToUnicode\s+(\d+)\s+\d+\s+R#', $dict, $m)) {
            $cmapObject = $objects[(int) $m[1]] ?? null;
            if ($cmapObject && $cmapObject['stream'] !== null) {
                $fontCmaps[$number] = pdf_parse_cmap($cmapObject['stream']);
            }
        }
        foreach (pdf_font_resource_blocks($dict, $objects) as $block) {
            if (preg_match_all('#/([^\s/<>\[\]()]+)\s+(\d+)\s+\d+\s+R#', $block, $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $pair) {
                    $fontNames[$pair[1]] = (int) $pair[2];
                }
            }
        }
    }

    $lines = [];
    foreach ($objects as $object) {
        $content = $object['stream'];
        if ($content === null || strpos($object['dict'], '/Subtype/Image') !== false || strpos($object['dict'], '/Subtype /Image') !== false) {
            continue;
        }
        if (strpos($content, 'BT') === false || (strpos($content, 'Tj') === false && strpos($content, 'TJ') === false)) {
            continue;
        }
        $text = pdf_content_text($content, $fontNames, $fontCmaps);
        if (trim($text) !== '') {
            $lines[] = $text;
        }
    }

    $text = implode("\n\n", $lines);
    if ($text !== '' && !mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
    $text = normalize_document_text($text);

    $letters = preg_match_all('/\p{L}/u', $text);
    if (text_length($text) < 120 || $letters < text_length($text) * 0.4) {
        return ['text' => '', 'error' => 'PDF guardado, pero no tiene texto extraible (puede ser escaneado). Conviertelo a DOCX o TXT para consultarlo.'];
    }

    return ['text' => $text, 'error' => null];
}

/**
 * @return array<int, array{dict: string, stream: ?string}>
 */
function pdf_parse_objects(string $raw): array
{
    $objects = [];
    if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $raw, $matches, PREG_SET_ORDER)) {
        return $objects;
    }

    foreach ($matches as $match) {
        $body = $match[2];
        $stream = null;
        $dict = $body;
        if (preg_match('/^(.*?)\bstream\r?\n(.*)\r?\n?endstream/s', $body, $parts)) {
            $dict = $parts[1];
            $stream = pdf_decode_stream($dict, $parts[2]);
        }
        $objects[(int) $match[1]] = ['dict' => $dict, 'stream' => $stream];
    }

    // Objetos comprimidos dentro de object streams (/Type /ObjStm).
    foreach ($objects as $object) {
        if ($object['stream'] === null || !preg_match('#/Type\s*/ObjStm#', $object['dict'])) {
            continue;
        }
        if (!preg_match('#/N\s+(\d+)#', $object['dict'], $n) || !preg_match('#/First\s+(\d+)#', $object['dict'], $first)) {
            continue;
        }
        $header = preg_split('/\s+/', trim(substr($object['stream'], 0, (int) $first[1]))) ?: [];
        $entries = [];
        for ($i = 0; $i + 1 < count($header) && count($entries) < (int) $n[1]; $i += 2) {
            $entries[] = [(int) $header[$i], (int) $header[$i + 1]];
        }
        foreach ($entries as $index => $entry) {
            $start = (int) $first[1] + $entry[1];
            $next = isset($entries[$index + 1]) ? (int) $first[1] + $entries[$index + 1][1] : strlen($object['stream']);
            if (!isset($objects[$entry[0]])) {
                $objects[$entry[0]] = ['dict' => substr($object['stream'], $start, $next - $start), 'stream' => null];
            }
        }
    }

    return $objects;
}

function pdf_decode_stream(string $dict, string $data): ?string
{
    if (strpos($dict, '/FlateDecode') === false) {
        return strpos($dict, '/Filter') === false ? $data : null;
    }

    foreach ([$data, rtrim($data, "\r\n")] as $candidate) {
        $decoded = @gzuncompress($candidate);
        if ($decoded === false) {
            $decoded = @gzinflate(substr($candidate, 2));
        }
        if ($decoded !== false) {
            return $decoded;
        }
    }

    return null;
}

/**
 * Bloques "/Font << /F1 5 0 R ... >>" de un diccionario (directos o por referencia).
 */
function pdf_font_resource_blocks(string $dict, array $objects): array
{
    $blocks = [];
    if (preg_match_all('#/Font\s*<<(.*?)>>#s', $dict, $inline)) {
        foreach ($inline[1] as $block) {
            $blocks[] = $block;
        }
    }
    if (preg_match_all('#/Font\s+(\d+)\s+\d+\s+R#', $dict, $refs)) {
        foreach ($refs[1] as $ref) {
            if (isset($objects[(int) $ref])) {
                $blocks[] = $objects[(int) $ref]['dict'];
            }
        }
    }

    return $blocks;
}

function pdf_parse_cmap(string $cmap): array
{
    $map = ['len' => 1, 'codes' => []];
    if (preg_match('/begincodespacerange\s*<([0-9A-Fa-f]+)>/', $cmap, $space)) {
        $map['len'] = max(1, (int) (strlen($space[1]) / 2));
    }

    if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $sections)) {
        foreach ($sections[1] as $section) {
            preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $section, $pairs, PREG_SET_ORDER);
            foreach ($pairs as $pair) {
                $map['codes'][strtoupper($pair[1])] = pdf_utf16_hex($pair[2]);
            }
        }
    }

    if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $sections)) {
        foreach ($sections[1] as $section) {
            preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<[0-9A-Fa-f]+>|\[[^\]]*\])/', $section, $ranges, PREG_SET_ORDER);
            foreach ($ranges as $range) {
                $low = hexdec($range[1]);
                $high = min(hexdec($range[2]), $low + 4096);
                $width = strlen($range[1]);
                if ($range[3][0] === '[') {
                    preg_match_all('/<([0-9A-Fa-f]+)>/', $range[3], $targets);
                    foreach ($targets[1] as $offset => $target) {
                        $map['codes'][strtoupper(str_pad(dechex($low + $offset), $width, '0', STR_PAD_LEFT))] = pdf_utf16_hex($target);
                    }
                    continue;
                }
                $base = trim($range[3], '<>');
                $prefix = substr($base, 0, -4);
                $last = hexdec(substr($base, -4));
                for ($code = $low; $code <= $high; $code++) {
                    $target = $prefix . str_pad(dechex($last + $code - $low), 4, '0', STR_PAD_LEFT);
                    $map['codes'][strtoupper(str_pad(dechex($code), $width, '0', STR_PAD_LEFT))] = pdf_utf16_hex($target);
                }
            }
        }
    }

    return $map;
}

function pdf_utf16_hex(string $hex): string
{
    if (strlen($hex) % 4 !== 0) {
        $hex = str_pad($hex, (int) (ceil(strlen($hex) / 4) * 4), '0', STR_PAD_LEFT);
    }
    $binary = @hex2bin($hex);
    if ($binary === false) {
        return '';
    }
    $text = @mb_convert_encoding($binary, 'UTF-8', 'UTF-16BE');
    return is_string($text) ? $text : '';
}

/**
 * Recorre los operadores de texto de un stream de contenido.
 */
function pdf_content_text(string $content, array $fontNames, array $fontCmaps): string
{
    $pattern = '/\/([^\s\/\[\]()<>{}%]+)|\((?:\\\\.|[^\\\\)])*\)|<[0-9A-Fa-f\s]*>|\[|\]|-?\d*\.\d+|-?\d+|[A-Za-z\'"*]+/s';
    if (!preg_match_all($pattern, $content, $tokens)) {
        return '';
    }

    $out = '';
    $operands = [];
    $array = null;
    $cmap = null;
    $lastY = null;

    foreach ($tokens[0] as $token) {
        $firstChar = $token[0];
        if ($token === '[') {
            $array = [];
            continue;
        }
        if ($token === ']') {
            $operands[] = $array ?? [];
            $array = null;
            continue;
        }
        if ($firstChar === '(' || $firstChar === '<' || $firstChar === '/' || is_numeric($token)) {
            if ($array !== null) {
                $array[] = $token;
            } else {
                $operands[] = $token;
            }
            continue;
        }

        // Operador
        switch ($token) {
            case 'Tf':
                $name = ltrim((string) ($operands[count($operands) - 2] ?? ''), '/');
                $fontObject = $fontNames[$name] ?? null;
                $cmap = $fontObject !== null && isset($fontCmaps[$fontObject]) ? $fontCmaps[$fontObject] : null;
                break;
            case 'Tj':
            case "'":
            case '"':
                if ($token !== 'Tj') {
                    $out .= "\n";
                }
                $out .= pdf_decode_string((string) end($operands), $cmap);
                break;
            case 'TJ':
                $items = end($operands);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (is_numeric($item)) {
                            if ((float) $item < -200) {
                                $out .= ' ';
                            }
                        } else {
                            $out .= pdf_decode_string($item, $cmap);
                        }
                    }
                }
                break;
            case 'T*':
                $out .= "\n";
                break;
            case 'Td':
            case 'TD':
                $ty = (float) ($operands[count($operands) - 1] ?? 0);
                $out .= abs($ty) > 0.01 ? "\n" : ' ';
                break;
            case 'Tm':
                $y = (float) ($operands[count($operands) - 1] ?? 0);
                if ($lastY !== null && abs($y - $lastY) > 0.5) {
                    $out .= "\n";
                } elseif ($lastY !== null) {
                    $out .= ' ';
                }
                $lastY = $y;
                break;
            case 'ET':
                $out .= ' ';
                break;
        }
        $operands = [];
    }

    return $out;
}

function pdf_decode_string(string $token, ?array $cmap): string
{
    if ($token === '') {
        return '';
    }

    if ($token[0] === '<') {
        $bytes = (string) @hex2bin(preg_replace('/[^0-9A-Fa-f]/', '', $token) . (strlen(preg_replace('/[^0-9A-Fa-f]/', '', $token)) % 2 ? '0' : ''));
    } elseif ($token[0] === '(') {
        $bytes = pdf_unescape(substr($token, 1, -1));
    } else {
        return '';
    }

    if ($cmap && $cmap['codes']) {
        $length = $cmap['len'];
        $text = '';
        for ($i = 0; $i + $length <= strlen($bytes); $i += $length) {
            $code = strtoupper(bin2hex(substr($bytes, $i, $length)));
            $text .= $cmap['codes'][$code] ?? '';
        }
        return $text;
    }

    return mb_check_encoding($bytes, 'UTF-8') ? $bytes : (string) mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
}

function pdf_unescape(string $text): string
{
    $text = preg_replace_callback('/\\\\([0-7]{1,3})/', function (array $m): string {
        return chr(octdec($m[1]) & 0xFF);
    }, $text) ?? $text;

    return strtr($text, ['\\n' => "\n", '\\r' => '', '\\t' => ' ', '\\(' => '(', '\\)' => ')', '\\\\' => '\\']);
}

function normalize_document_text(string $text): string
{
    $text = str_replace(["\0", "\r\n", "\r"], [' ', "\n", "\n"], $text);
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

    return trim($text);
}

function document_title(string $name): string
{
    $title = trim(pathinfo($name, PATHINFO_FILENAME));
    if ($title === '') {
        return 'Documento';
    }

    $title = preg_replace('/[_-]+/', ' ', $title) ?? $title;
    $title = preg_replace('/\s+/', ' ', $title) ?? $title;

    return limit_text($title, 180);
}

function upload_error_message(int $code): string
{
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'El archivo supera el limite del servidor.',
        UPLOAD_ERR_FORM_SIZE => 'El archivo supera el limite permitido.',
        UPLOAD_ERR_PARTIAL => 'El archivo se subio incompleto.',
        UPLOAD_ERR_NO_FILE => 'Selecciona un archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
        UPLOAD_ERR_CANT_WRITE => 'No pude escribir el archivo en el servidor.',
        UPLOAD_ERR_EXTENSION => 'Una extension de PHP detuvo la subida.',
    ];

    return $messages[$code] ?? 'No pude subir el archivo.';
}
