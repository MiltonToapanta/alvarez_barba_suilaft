<?php
/**
 * Exportador de documentos PDF para Suilaft AI Compliance.
 *
 * Usa FPDF (libreria externa de un solo archivo, sin Composer).
 * La libreria debe ubicarse en: libs/fpdf/fpdf.php
 *
 * Flujo: la IA genera el contenido en texto (Markdown ligero), el usuario
 * elige el tipo de documento y este modulo convierte ese texto en un PDF
 * formateado que se descarga directo en el navegador.
 */

const PDF_LEFT_MARGIN = 18.0;
const PDF_RIGHT_MARGIN = 18.0;
const PDF_TOP_MARGIN = 16.0;
const PDF_BOTTOM_MARGIN = 18.0;

/**
 * Devuelve la ruta absoluta al archivo fpdf.php si existe.
 */
function pdf_fpdf_path(): string
{
    $candidates = [
        dirname(__DIR__) . '/libs/fpdf/fpdf.php',
        dirname(__DIR__) . '/vendor/setasign/fpdf/fpdf.php',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * Umbral de salto de pagina automatico (alto de pagina menos margen inferior).
 */
function pdf_page_break_y(FPDF $pdf): float
{
    return $pdf->GetPageHeight() - PDF_BOTTOM_MARGIN;
}

/**
 * Tipos de documento soportados y su configuracion de portada.
 */
function pdf_document_types(): array
{
    return [
        'manual' => [
            'label' => 'Manual de cumplimiento',
            'subtitle' => 'Manual de prevencion de lavado de activos, FT y FPADM',
            'slug' => 'manual_cumplimiento',
        ],
        'informe' => [
            'label' => 'Informe de actividades',
            'subtitle' => 'Informe de actividades del oficial de cumplimiento',
            'slug' => 'informe_actividades',
        ],
        'plan' => [
            'label' => 'Plan anual de actividades y de cumplimiento',
            'subtitle' => 'Plan anual de trabajo y cumplimiento normativo',
            'slug' => 'plan_anual_cumplimiento',
        ],
        'metodologia' => [
            'label' => 'Metodologia de cumplimiento',
            'subtitle' => 'Metodologia de implementacion del sistema PLA/FT/FPADM',
            'slug' => 'metodologia',
        ],
    ];
}

/**
 * Convierte texto UTF-8 a CP1252 (WinAnsi), que es el encoding que
 * entienden las fuentes nativas de FPDF (Helvetica/Arial, Courier).
 */
function pdf_text(string $text): string
{
    if ($text === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);
        if ($converted !== false) {
            return $converted;
        }
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    return (string) preg_replace('/[^\x00-\xFF]/', '?', $text);
}

/**
 * Genera y descarga el PDF en el navegador.
 *
 * @param string $type    manual|informe|plan
 * @param string $content Texto (Markdown ligero) generado por la IA.
 * @param array  $meta    Datos opcionales: title, company, author, user_name.
 */
function stream_pdf_document(string $type, string $content, array $meta): void
{
    $types = pdf_document_types();
    if (!isset($types[$type])) {
        throw new RuntimeException('Tipo de documento no valido.');
    }

    $content = trim($content);
    // El marcador de fin de entrevista no debe aparecer en el documento.
    $content = (string) preg_replace('/<!--\s*DOCUMENTO_FINAL\s*-->/', '', $content);
    $content = trim($content);
    if ($content === '') {
        throw new RuntimeException('No hay contenido para exportar.');
    }

    $fpdfPath = pdf_fpdf_path();
    if ($fpdfPath === '') {
        throw new RuntimeException('La libreria FPDF no esta instalada. Sube fpdf.php a libs/fpdf/fpdf.php.');
    }

    require_once $fpdfPath;

    $config = $types[$type];
    $company = trim((string) ($meta['company'] ?? ''));
    $author = trim((string) ($meta['author'] ?? ''));

    $pdf = new class('P', 'mm', 'A4') extends FPDF {
        public function Footer(): void
        {
            $this->SetY(-14);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 10, pdf_text('Pagina ' . $this->PageNo() . ' de {nb}'), 0, 0, 'C');
            $this->SetTextColor(0, 0, 0);
        }
    };

    $pdf->SetCreator('Suilaft AI Compliance');
    $pdf->SetTitle(pdf_text($config['label']));
    $pdf->SetAuthor(pdf_text($author !== '' ? $author : 'SUILAFT AI'));
    $pdf->SetSubject(pdf_text($config['label']));
    $pdf->AliasNbPages();
    $pdf->SetMargins(PDF_LEFT_MARGIN, PDF_TOP_MARGIN, PDF_RIGHT_MARGIN);
    $pdf->SetAutoPageBreak(true, PDF_BOTTOM_MARGIN);
    $pdf->AddPage();

    render_cover($pdf, $config, $meta);
    render_blocks($pdf, markdown_blocks($content));

    $pdf->Output('D', pdf_filename($type, $meta));
}

/**
 * Nombre de archivo ASCII para la descarga.
 */
function pdf_filename(string $type, array $meta): string
{
    $config = pdf_document_types()[$type] ?? null;
    $slug = $config['slug'] ?? 'documento';

    return $slug . '_' . date('Ymd_His') . '.pdf';
}

/**
 * Portada del documento: banda de marca, datos generales y aviso de confidencialidad.
 */
function render_cover(FPDF $pdf, array $config, array $meta): void
{
    $company = trim((string) ($meta['company'] ?? ''));
    $author = trim((string) ($meta['author'] ?? ''));
    $simple = ($config['slug'] ?? '') === 'metodologia';

    $left = PDF_LEFT_MARGIN;
    $right = PDF_RIGHT_MARGIN;
    $width = $pdf->GetPageWidth() - $left - $right;

    $title = pdf_text($config['label']);
    $subtitle = pdf_text($config['subtitle']);

    // Alto de la banda segun las lineas reales de titulo y subtitulo (evita superposicion).
    $pdf->SetFont('Arial', 'B', 17);
    $titleLines = max(1, (int) ceil($pdf->GetStringWidth($title) / max(1, $width - 4)));
    $pdf->SetFont('Arial', '', 10.5);
    $subtitleLines = max(1, (int) ceil($pdf->GetStringWidth($subtitle) / max(1, $width - 4)));

    $bandHeight = 16 + $titleLines * 8 + $subtitleLines * 5;
    if ($bandHeight < 34) {
        $bandHeight = 34;
    }

    $pdf->SetFillColor(15, 118, 110);
    $pdf->Rect(0, 0, $pdf->GetPageWidth(), $bandHeight, 'F');

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 17);
    $pdf->SetXY($left, 9);
    $pdf->MultiCell($width, 8, $title, 0, 'L');

    $pdf->SetFont('Arial', '', 10.5);
    $pdf->SetX($left);
    $pdf->MultiCell($width, 5, $subtitle, 0, 'L');
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetY($bandHeight + 8);

    // Datos generales.
    $pdf->SetFont('Arial', 'B', 9.5);
    $pdf->SetTextColor(15, 118, 110);
    $pdf->Cell(0, 6.5, pdf_text('INFORMACION GENERAL'), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);

    $rows = [
        ['Fecha de emision', date('d/m/Y')],
        ['Tipo de documento', $config['label']],
    ];
    if (!$simple) {
        $rows[] = ['Empresa', $company !== '' ? $company : '[NOMBRE DE LA EMPRESA]'];
        $rows[] = ['Elaborado por', $author !== '' ? $author : '[OFICIAL DE CUMPLIMIENTO]'];
    }

    foreach ($rows as $row) {
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(38, 5.8, pdf_text($row[0]), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->MultiCell($width - 38, 5.8, pdf_text($row[1]), 0, 'L');
    }

    $pdf->Ln(2);

    // Aviso de confidencialidad.
    $note = 'Documento confidencial. Contiene informacion sensible sujeta a reserva y proteccion de datos. '
        . 'Debe tratarse conforme a la normativa aplicable y circularse unicamente a personal autorizado.';

    $pdf->SetFont('Arial', 'I', 8.8);
    $lines = cell_lines($pdf, $width - 6, $note, 8.8, 'I');
    $boxHeight = $lines * 4.6 + 4;

    $y = $pdf->GetY();
    if ($y + $boxHeight > pdf_page_break_y($pdf)) {
        $pdf->AddPage();
        $y = $pdf->GetY();
    }

    $pdf->SetDrawColor(254, 202, 202);
    $pdf->SetFillColor(254, 242, 242);
    $pdf->SetTextColor(153, 27, 27);
    $pdf->SetXY($left, $y);
    $pdf->Cell($width, $boxHeight, '', 1, 0, 'L', true);
    $pdf->SetXY($left + 3, $y + 2);
    $pdf->MultiCell($width - 6, 4.6, pdf_text($note), 0, 'L');
    $pdf->SetY($y + $boxHeight);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Ln(3);

    // Linea separadora antes del contenido.
    $pdf->SetDrawColor(15, 118, 110);
    $pdf->SetLineWidth(0.5);
    $pdf->Line($left, $pdf->GetY(), $pdf->GetPageWidth() - $right, $pdf->GetY());
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Ln(4);
}

/**
 * Convierte Markdown ligero en bloques estructurados para renderizar.
 */
function markdown_blocks(string $markdown): array
{
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
    $lines = preg_split('/\n/', $markdown);
    if ($lines === false) {
        return [];
    }

    $blocks = [];
    $count = count($lines);
    $i = 0;

    while ($i < $count) {
        $line = rtrim((string) $lines[$i]);
        $trimmed = trim($line);

        if ($trimmed === '') {
            $i++;
            continue;
        }

        // Regla horizontal.
        if (preg_match('/^\s*([-*_])\s*(?:\1\s*){2,}$/', $trimmed)) {
            $blocks[] = ['type' => 'hr'];
            $i++;
            continue;
        }

        // Tabla con fila de encabezado.
        if (
            isset($lines[$i + 1])
            && preg_match('/^\s*\|.*\|\s*$/', $trimmed)
            && is_table_separator_line((string) $lines[$i + 1])
        ) {
            $rows = [parse_table_row($trimmed)];
            $j = $i + 2;

            while ($j < $count && preg_match('/^\s*\|.*\|\s*$/', rtrim((string) $lines[$j]))) {
                $rowLine = rtrim((string) $lines[$j]);
                if (!is_table_separator_line($rowLine)) {
                    $rows[] = parse_table_row($rowLine);
                }
                $j++;
            }

            $blocks[] = ['type' => 'table', 'rows' => $rows];
            $i = $j;
            continue;
        }

        // Encabezado.
        if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $matches)) {
            $level = min(3, strlen($matches[1]));
            $blocks[] = ['type' => 'h' . $level, 'text' => inline_runs($matches[2])];
            $i++;
            continue;
        }

        // Lista no ordenada (agrupada).
        if (preg_match('/^\s*[-*+]\s+(.*)$/', $trimmed, $matches)) {
            $items = [];
            while ($i < $count && preg_match('/^\s*[-*+]\s+(.*)$/', trim((string) $lines[$i]), $itemMatch)) {
                $items[] = inline_runs($itemMatch[1]);
                $i++;
            }
            $blocks[] = ['type' => 'ul', 'items' => $items];
            continue;
        }

        // Lista ordenada (agrupada).
        if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $trimmed, $matches)) {
            $items = [];
            while ($i < $count && preg_match('/^\s*\d+[.)]\s+(.*)$/', trim((string) $lines[$i]), $itemMatch)) {
                $items[] = inline_runs($itemMatch[1]);
                $i++;
            }
            $blocks[] = ['type' => 'ol', 'items' => $items];
            continue;
        }

        // Cita.
        if (preg_match('/^\s*>\s?(.*)$/', $trimmed, $matches)) {
            $parts = [];
            while ($i < $count && preg_match('/^\s*>\s?(.*)$/', trim((string) $lines[$i]), $quoteMatch)) {
                $parts[] = $quoteMatch[1];
                $i++;
            }
            $blocks[] = ['type' => 'quote', 'text' => inline_runs(implode(' ', $parts))];
            continue;
        }

        // Parrafo.
        $blocks[] = ['type' => 'p', 'text' => inline_runs($trimmed)];
        $i++;
    }

    return $blocks;
}

/**
 * Determina si una linea es el separador de encabezado de una tabla Markdown.
 */
function is_table_separator_line(string $line): bool
{
    $trimmed = trim($line);
    if (!preg_match('/^\s*\|.*\|\s*$/', $trimmed)) {
        return false;
    }

    foreach (parse_table_row($trimmed) as $cell) {
        if (!preg_match('/^:?-{3,}:?$/', trim($cell))) {
            return false;
        }
    }

    return true;
}

/**
 * Divide una fila de tabla Markdown en celdas.
 */
function parse_table_row(string $line): array
{
    $trimmed = trim($line);
    $trimmed = (string) preg_replace('/^\|/', '', $trimmed);
    $trimmed = (string) preg_replace('/\|$/', '', $trimmed);

    return array_map('trim', explode('|', $trimmed));
}

/**
 * Convierte una linea en "runs" con estilo inline (negrita, cursiva, codigo).
 */
function inline_runs(string $text): array
{
    $pattern = '/(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)/';
    $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return [['style' => '', 'text' => $text]];
    }

    $runs = [];

    foreach ($parts as $part) {
        if (preg_match('/^\*\*(.+)\*\*$/', $part, $matches)) {
            $runs[] = ['style' => 'b', 'text' => $matches[1]];
        } elseif (preg_match('/^\*(.+)\*$/', $part, $matches)) {
            $runs[] = ['style' => 'i', 'text' => $matches[1]];
        } elseif (preg_match('/^`(.+)`$/', $part, $matches)) {
            $runs[] = ['style' => 'code', 'text' => $matches[1]];
        } else {
            $runs[] = ['style' => '', 'text' => $part];
        }
    }

    return $runs;
}

/**
 * Une los runs en texto plano (ignorando estilo).
 */
function runs_text(array $runs): string
{
    $parts = [];
    foreach ($runs as $run) {
        $parts[] = (string) ($run['text'] ?? '');
    }

    return implode('', $parts);
}

/**
 * Renderiza todos los bloques dentro del PDF.
 */
function render_blocks(FPDF $pdf, array $blocks): void
{
    foreach ($blocks as $block) {
        $type = (string) ($block['type'] ?? '');

        switch ($type) {
            case 'h1':
            case 'h2':
            case 'h3':
                render_heading($pdf, $block);
                break;

            case 'p':
                render_paragraph($pdf, $block);
                break;

            case 'ul':
            case 'ol':
                render_list($pdf, $block);
                break;

            case 'quote':
                render_quote($pdf, $block);
                break;

            case 'table':
                render_table($pdf, (array) ($block['rows'] ?? []));
                break;

            case 'hr':
                render_hr($pdf);
                break;
        }
    }
}

function render_heading(FPDF $pdf, array $block): void
{
    $level = (int) substr((string) ($block['type'] ?? 'h2'), 1);
    $sizes = [1 => 15, 2 => 12.5, 3 => 11];
    $size = $sizes[$level] ?? 11;

    $pdf->Ln(2.5);
    $pdf->SetFont('Arial', 'B', $size);
    $pdf->SetTextColor(15, 118, 110);
    $pdf->MultiCell(0, 6.2, pdf_text(runs_text($block['text'] ?? [])), 0, 'L');
    $pdf->Ln(1.2);
    $pdf->SetTextColor(0, 0, 0);
}

function render_paragraph(FPDF $pdf, array $block): void
{
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Ln(1.2);
    write_runs($pdf, (array) ($block['text'] ?? []), 5.4, 10.5);
    $pdf->Ln(2.4);
    $pdf->SetTextColor(0, 0, 0);
}

function render_list(FPDF $pdf, array $block): void
{
    $ordered = ((string) ($block['type'] ?? 'ul')) === 'ol';
    $items = (array) ($block['items'] ?? []);

    $pdf->SetTextColor(30, 41, 59);
    $leftMargin = PDF_LEFT_MARGIN;
    $indent = $leftMargin + 7;

    $pdf->Ln(1);

    foreach ($items as $index => $runs) {
        $bullet = $ordered ? ($index + 1) . '.' : pdf_text('•');

        $pdf->SetFont('Arial', '', 10.5);
        $pdf->SetX($leftMargin);
        $pdf->Cell(7, 5.4, $bullet, 0, 0, 'R');

        $pdf->SetLeftMargin($indent);
        $pdf->SetX($indent);
        write_runs($pdf, (array) $runs, 5.4, 10.5);
        $pdf->Ln(1.8);

        $pdf->SetLeftMargin($leftMargin);
        $pdf->SetX($leftMargin);
    }

    $pdf->Ln(1);
    $pdf->SetTextColor(0, 0, 0);
}

function render_quote(FPDF $pdf, array $block): void
{
    $leftMargin = PDF_LEFT_MARGIN;

    $pdf->SetTextColor(71, 85, 105);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetLeftMargin($leftMargin + 6);
    $pdf->Ln(1);
    $pdf->MultiCell(0, 5.4, pdf_text(runs_text($block['text'] ?? [])), 0, 'L');
    $pdf->SetLeftMargin($leftMargin);
    $pdf->Ln(2.2);
    $pdf->SetTextColor(0, 0, 0);
}

function render_hr(FPDF $pdf): void
{
    $y = $pdf->GetY() + 1.5;
    $pdf->SetDrawColor(203, 213, 225);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(PDF_LEFT_MARGIN, $y, $pdf->GetPageWidth() - PDF_RIGHT_MARGIN, $y);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Ln(4);
}

function render_table(FPDF $pdf, array $rows): void
{
    if (!$rows) {
        return;
    }

    $columns = 1;
    foreach ($rows as $row) {
        $columns = max($columns, count((array) $row));
    }

    $contentWidth = $pdf->GetPageWidth() - PDF_LEFT_MARGIN - PDF_RIGHT_MARGIN;
    $columnWidth = $contentWidth / $columns;
    $lineHeight = 4.6;
    $x = PDF_LEFT_MARGIN;

    $pdf->Ln(2);

    // Encabezado.
    $header = array_pad((array) ($rows[0] ?? []), $columns, '');
    $headerLines = 1;
    foreach ($header as $cell) {
        $headerLines = max($headerLines, cell_lines($pdf, $columnWidth - 2.4, $cell, 8.8, 'B'));
    }
    $headerHeight = $headerLines * $lineHeight + 2.6;

    $y = $pdf->GetY();
    if ($y + $headerHeight > pdf_page_break_y($pdf)) {
        $pdf->AddPage();
        $y = $pdf->GetY();
    }

    $pdf->SetFillColor(15, 118, 110);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 8.8);

    foreach ($header as $index => $cell) {
        $pdf->SetXY($x + $index * $columnWidth, $y);
        $pdf->Cell($columnWidth, $headerHeight, '', 1, 0, 'L', true);
    }

    foreach ($header as $index => $cell) {
        $pdf->SetXY($x + $index * $columnWidth + 1.2, $y + 1.3);
        $pdf->MultiCell($columnWidth - 2.4, $lineHeight, pdf_text((string) $cell), 0, 'L');
    }

    $pdf->SetXY($x, $y + $headerHeight);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 8.8);

    // Cuerpo.
    $alternate = false;
    $bodyRows = array_slice($rows, 1);

    foreach ($bodyRows as $row) {
        $row = array_pad((array) $row, $columns, '');
        $maxLines = 1;

        foreach ($row as $cell) {
            $maxLines = max($maxLines, cell_lines($pdf, $columnWidth - 2.4, $cell, 8.8));
        }

        $rowHeight = $maxLines * $lineHeight + 2.2;
        $y = $pdf->GetY();

        if ($y + $rowHeight > pdf_page_break_y($pdf)) {
            $pdf->AddPage();
            $y = $pdf->GetY();
        }

        $pdf->SetFillColor($alternate ? 241 : 255, $alternate ? 245 : 255, $alternate ? 249 : 255);
        $pdf->SetDrawColor(203, 213, 225);

        foreach ($row as $index => $cell) {
            $pdf->SetXY($x + $index * $columnWidth, $y);
            $pdf->Cell($columnWidth, $rowHeight, '', 1, 0, 'L', true);
        }

        foreach ($row as $index => $cell) {
            $pdf->SetXY($x + $index * $columnWidth + 1.2, $y + 1.1);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->MultiCell($columnWidth - 2.4, $lineHeight, pdf_text((string) $cell), 0, 'L');
        }

        $pdf->SetXY($x, $y + $rowHeight);
        $alternate = !$alternate;
    }

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Ln(2);
}

/**
 * Escribe runs con estilo inline (permite negrita/cursiva/codigo en una linea).
 */
function write_runs(FPDF $pdf, array $runs, float $lineHeight, float $size = 10.5): void
{
    foreach ($runs as $run) {
        $style = (string) ($run['style'] ?? '');
        $text = pdf_text((string) ($run['text'] ?? ''));

        if ($text === '') {
            continue;
        }

        switch ($style) {
            case 'b':
                $pdf->SetFont('Arial', 'B', $size);
                break;
            case 'i':
                $pdf->SetFont('Arial', 'I', $size);
                break;
            case 'code':
                $pdf->SetFont('Courier', '', max(8, $size - 1));
                break;
            default:
                $pdf->SetFont('Arial', '', $size);
                break;
        }

        $pdf->Write($lineHeight, $text);
    }
}

/**
 * Calcula cuantas lineas ocupara un texto dentro de un ancho dado.
 */
function cell_lines(FPDF $pdf, float $width, string $text, float $size, string $style = ''): int
{
    $pdf->SetFont('Arial', $style, $size);

    $text = pdf_text($text);
    $width = max(4, $width);

    $words = preg_split('/\s+/', $text);
    if ($words === false || !$words) {
        return 1;
    }

    $lines = 1;
    $line = '';

    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }

        $candidate = $line === '' ? $word : $line . ' ' . $word;

        if ($pdf->GetStringWidth($candidate) > $width) {
            $lines++;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }

    return $lines;
}

/**
 * Extrae el JSON estructurado de una matriz desde el contenido generado por la IA.
 */
function matrix_data_from_content(string $content): ?array
{
    if (!preg_match('/<!--\s*MATRIZ_JSON\s*-->(.*?)<!--\s*\/MATRIZ_JSON\s*-->/s', $content, $matches)) {
        return null;
    }

    $json = trim((string) $matches[1]);
    $json = (string) preg_replace('/^```(?:json)?\s*/i', '', $json);
    $json = (string) preg_replace('/\s*```$/', '', $json);

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }

    if (empty($decoded['riesgos']) || !is_array($decoded['riesgos'])) {
        return null;
    }

    return $decoded;
}

/**
 * Normaliza y calcula los totales de la matriz a partir del JSON de la IA.
 */
function matrix_compute(array $data): array
{
    $riesgos = [];
    foreach (($data['riesgos'] ?? []) as $risk) {
        if (!is_array($risk)) {
            continue;
        }
        $riesgos[] = [
            'codigo' => (string) ($risk['codigo'] ?? ''),
            'factor' => (string) ($risk['factor'] ?? ''),
            'criterio' => (string) ($risk['criterio'] ?? ''),
            'respuesta' => (string) ($risk['respuesta'] ?? ''),
            'nivel' => is_numeric($risk['nivel'] ?? null) ? (float) $risk['nivel'] : 0.0,
            'peso' => is_numeric($risk['peso'] ?? null) ? (float) $risk['peso'] : 0.0,
        ];
    }

    $factores = [];
    foreach (($data['factores'] ?? []) as $factor) {
        if (!is_array($factor)) {
            continue;
        }
        $factores[] = [
            'factor' => (string) ($factor['factor'] ?? ''),
            'peso_general' => is_numeric($factor['peso_general'] ?? null) ? (float) $factor['peso_general'] : 0.0,
            'puntaje' => 0.0,
            'aporte' => 0.0,
            'riesgos' => '',
        ];
    }

    $puntajes = [];
    $riesgosPorFactor = [];
    foreach ($riesgos as $risk) {
        $factor = $risk['factor'];
        $puntajes[$factor] = ($puntajes[$factor] ?? 0.0) + ($risk['nivel'] * $risk['peso']);
        $riesgosPorFactor[$factor][] = $risk['codigo'];
    }

    $total = 0.0;
    foreach ($factores as &$factor) {
        $factor['puntaje'] = round($puntajes[$factor['factor']] ?? 0.0, 2);
        $factor['aporte'] = round($factor['puntaje'] * $factor['peso_general'], 2);
        $factor['riesgos'] = implode(',', $riesgosPorFactor[$factor['factor']] ?? []);
        $total += $factor['aporte'];
    }
    unset($factor);

    $total = round($total, 2);

    return [
        'sujeto' => (string) ($data['sujeto'] ?? ''),
        'codigo_documento' => (string) ($data['codigo_documento'] ?? ''),
        'datos_generales' => is_array($data['datos_generales'] ?? null) ? $data['datos_generales'] : [],
        'datos_financieros' => is_array($data['datos_financieros'] ?? null) ? $data['datos_financieros'] : [],
        'factores' => $factores,
        'riesgos' => $riesgos,
        'total' => $total,
        'nivel' => matrix_classify($total),
        'observaciones' => (string) ($data['observaciones'] ?? ''),
    ];
}

function matrix_classify(float $total): string
{
    if ($total <= 1.0) {
        return 'MUY BAJO';
    }
    if ($total <= 2.0) {
        return 'BAJO';
    }
    if ($total <= 3.0) {
        return 'MEDIO';
    }
    if ($total <= 4.0) {
        return 'ALTO';
    }

    return 'MUY ALTO';
}

function matrix_risk_color(string $nivel): array
{
    switch ($nivel) {
        case 'MUY BAJO':
        case 'BAJO':
            return [16, 118, 110];
        case 'MEDIO':
            return [180, 83, 9];
        case 'ALTO':
            return [194, 65, 12];
        default:
            return [185, 28, 28];
    }
}

function matrix_money($value): string
{
    if (!is_numeric($value)) {
        return (string) $value;
    }

    return number_format((float) $value, 2, ',', '.');
}

/**
 * Genera y descarga la matriz de riesgo con el diseno profesional (horizontal A4).
 */
function stream_matrix_pdf(array $rawData, array $meta = []): void
{
    $data = matrix_compute($rawData);

    $authorName = trim((string) ($meta['author'] ?? ''));
    if ($authorName === '') {
        $authorName = trim((string) ($meta['user_name'] ?? ''));
    }

    $fpdfPath = pdf_fpdf_path();
    if ($fpdfPath === '') {
        throw new RuntimeException('La libreria FPDF no esta instalada. Sube fpdf.php a libs/fpdf/fpdf.php.');
    }
    require_once $fpdfPath;

    $pdf = new class('L', 'mm', 'A4') extends FPDF {
        public $matrixTitle = 'MATRIZ DE RIESGO INHERENTE';
        public $matrixSub = 'Sistema de Prevencion de Riesgos LA/FT';
        public $matrixDoc = '';

        public function Header(): void
        {
            $this->SetFillColor(10, 63, 122);
            $this->Rect(0, 0, 297, 26, 'F');
            $this->SetFillColor(16, 107, 181);
            $this->Rect(0, 26, 297, 3, 'F');

            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 12);
            $this->SetXY(12, 7);
            $this->Cell(273, 6, pdf_text($this->matrixTitle), 0, 1, 'L');
            $this->SetFont('Arial', '', 9);
            $this->SetX(12);
            $this->Cell(273, 5, pdf_text($this->matrixSub), 0, 1, 'L');
            $this->SetFont('Arial', 'B', 9);
            $this->SetXY(12, 19);
            $this->Cell(273, 6, pdf_text($this->matrixDoc), 0, 1, 'R');
            $this->Ln(10);
        }

        public function Footer(): void
        {
            $this->SetY(-11);
            $this->SetFont('Arial', 'I', 7.5);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 8, pdf_text('Documento generado electronicamente - Pagina ' . $this->PageNo() . ' de {nb}'), 0, 0, 'C');
            $this->SetTextColor(30, 30, 30);
        }
    };

    $pdf->matrixTitle = 'MATRIZ DE RIESGO INHERENTE' . ($data['sujeto'] !== '' ? ' - ' . strtoupper($data['sujeto']) : '');
    $pdf->matrixDoc = $data['codigo_documento'] !== '' ? $data['codigo_documento'] : 'Matriz de riesgo LA/FT';
    $pdf->AliasNbPages();
    $pdf->SetMargins(10, 12, 10);
    $pdf->SetAutoPageBreak(true, 14);
    $pdf->AddPage();

    matrix_section($pdf, 'I. DATOS GENERALES');
    foreach ($data['datos_generales'] as $item) {
        matrix_kv($pdf, (string) ($item['etiqueta'] ?? ''), (string) ($item['valor'] ?? ''), 45, 232);
        $pdf->Ln();
    }
    $pdf->Ln(1);

    if (!empty($data['datos_financieros'])) {
        matrix_section($pdf, 'II. DATOS FINANCIEROS');
        foreach ($data['datos_financieros'] as $item) {
            matrix_kv($pdf, (string) ($item['etiqueta'] ?? ''), matrix_money($item['valor'] ?? ''), 50, 227);
            $pdf->Ln();
        }
        $pdf->Ln(1);
    }

    matrix_section($pdf, 'III. RESULTADO DE LA EVALUACION');
    matrix_result_box($pdf, $data['total'], $data['nivel']);

    matrix_section($pdf, 'IV. RESUMEN POR FACTOR');
    matrix_table_header($pdf, [
        [65, 'Factor', 'C'],
        [35, 'Peso General', 'C'],
        [45, 'Puntaje Factor', 'C'],
        [45, 'Aporte al Total', 'C'],
        [87, 'Riesgos incluidos', 'C'],
    ]);
    foreach ($data['factores'] as $factor) {
        matrix_table_row($pdf, [
            [65, $factor['factor'], 'L'],
            [35, $factor['peso_general'] > 0 ? number_format($factor['peso_general'] * 100, 0) . '%' : '-', 'C'],
            [45, matrix_money($factor['puntaje']), 'C'],
            [45, matrix_money($factor['aporte']), 'C'],
            [87, $factor['riesgos'], 'C'],
        ], 8);
    }

    $detailHeader = [
        [18, 'Riesgo', 'C'],
        [72, 'Descripcion del riesgo', 'C'],
        [115, 'Respuesta / Resultado', 'C'],
        [20, 'Nivel', 'C'],
        [25, 'Pond.', 'C'],
        [27, 'Resultado', 'C'],
    ];

    $currentFactor = '';
    $factorWeight = '';

    foreach ($data['riesgos'] as $risk) {
        $factor = $risk['factor'];

        if ($factor !== $currentFactor) {
            $currentFactor = $factor;
            $factorWeight = '';
            foreach ($data['factores'] as $factorInfo) {
                if ($factorInfo['factor'] === $factor) {
                    $factorWeight = $factorInfo['peso_general'] > 0 ? number_format($factorInfo['peso_general'] * 100, 0) . '%' : '';
                    break;
                }
            }

            matrix_section($pdf, 'V. DETALLE DE RIESGOS - FACTOR: ' . $factor . ($factorWeight !== '' ? ' | PESO GENERAL ' . $factorWeight : ''));
            matrix_table_header($pdf, $detailHeader);
        } elseif ($pdf->GetY() > 183) {
            $pdf->AddPage();
            matrix_section($pdf, 'V. DETALLE DE RIESGOS - FACTOR: ' . $factor . ' (CONTINUACION)');
            matrix_table_header($pdf, $detailHeader);
        }

        $nivel = (float) $risk['nivel'];
        $peso = (float) $risk['peso'];
        $resultado = $nivel * $peso;

        matrix_table_row($pdf, [
            [18, $risk['codigo'], 'C'],
            [72, $risk['criterio'], 'L'],
            [115, $risk['respuesta'], 'L'],
            [20, $nivel > 0 ? (string) (int) $nivel : '-', 'C'],
            [25, $peso > 0 ? number_format($peso * 100, 0) . '%' : '-', 'C'],
            [27, $nivel > 0 ? matrix_money($resultado) : '-', 'C'],
        ], 7);
    }

    if ($data['observaciones'] !== '') {
        matrix_section($pdf, 'VI. OBSERVACIONES');
        matrix_observation_box($pdf, $data['observaciones']);
    }

    if ($pdf->GetY() > 78) {
        $pdf->AddPage();
    }
    matrix_reference_table($pdf);
    matrix_signature($pdf, $authorName);

    $pdf->Output('D', 'matriz_riesgo_' . date('Ymd_His') . '.pdf');
}

function matrix_section(FPDF $pdf, string $title): void
{
    $pdf->Ln(4);
    $pdf->SetDrawColor(10, 63, 122);
    $pdf->SetFillColor(10, 63, 122);
    $pdf->Rect(10, $pdf->GetY(), 4, 7.5, 'F');
    $pdf->SetFillColor(232, 241, 249);
    $pdf->SetTextColor(10, 63, 122);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(277, 7.5, pdf_text('   ' . $title), 1, 1, 'L', true);
    $pdf->SetTextColor(30, 30, 30);
}

function matrix_kv(FPDF $pdf, string $label, string $value, float $w1, float $w2): void
{
    $pdf->SetDrawColor(205, 216, 226);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(45, 65, 85);
    $pdf->SetFillColor(241, 246, 251);
    $pdf->Cell($w1, 8, pdf_text($label), 1, 0, 'L', true);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell($w2, 8, pdf_text($value), 1, 0, 'L', true);
}

function matrix_result_box(FPDF $pdf, float $total, string $nivel): void
{
    $rgb = matrix_risk_color($nivel);
    $pdf->SetDrawColor(210, 220, 230);
    $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 22);
    $pdf->Cell(140, 18, pdf_text(matrix_money($total)), 1, 0, 'C', true);
    $pdf->SetFont('Arial', 'B', 19);
    $pdf->Cell(137, 18, pdf_text($nivel), 1, 0, 'C', true);
    $pdf->Ln();
    $pdf->SetTextColor(30, 30, 30);
}

function matrix_table_header(FPDF $pdf, array $cols): void
{
    $pdf->SetDrawColor(210, 220, 230);
    $pdf->SetFillColor(10, 63, 122);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 7.5);
    foreach ($cols as $col) {
        $pdf->Cell($col[0], 7, pdf_text($col[1]), 1, 0, $col[2] ?? 'C', true);
    }
    $pdf->Ln();
    $pdf->SetTextColor(30, 30, 30);
}

function matrix_table_row(FPDF $pdf, array $cols, float $fontSize = 7): void
{
    $pdf->SetDrawColor(210, 220, 230);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(35, 35, 35);
    $pdf->SetFont('Arial', '', $fontSize);

    foreach ($cols as $col) {
        $value = pdf_text((string) $col[1]);
        $available = max(1, $col[0] - 2);
        if ($pdf->GetStringWidth($value) > $available) {
            while ($value !== '' && $pdf->GetStringWidth($value . '...') > $available) {
                $value = substr($value, 0, -1);
            }
            $value = rtrim($value) . '...';
        }
        $pdf->Cell($col[0], 7, $value, 1, 0, $col[2] ?? 'L', true);
    }

    $pdf->Ln();
    $pdf->SetTextColor(30, 30, 30);
}

function matrix_observation_box(FPDF $pdf, string $text): void
{
    $pdf->SetDrawColor(205, 216, 226);
    $pdf->SetFillColor(250, 252, 255);
    $pdf->SetTextColor(35, 35, 35);
    $pdf->SetFont('Arial', '', 8);
    $pdf->MultiCell(277, 6.5, pdf_text($text), 1, 'L', true);
    $pdf->SetTextColor(30, 30, 30);
}

function matrix_reference_table(FPDF $pdf): void
{
    $pdf->Ln(6);
    matrix_section($pdf, 'TABLA DE REFERENCIA - NIVELES DE RIESGO');

    $levels = [
        ['MUY BAJO', 'Riesgo insignificante de LA/FT', '0.00 - 1.00', [209, 250, 229], [6, 95, 70]],
        ['BAJO', 'Poco probable que ocurra un evento LA/FT', '1.01 - 2.00', [209, 250, 229], [6, 95, 70]],
        ['MEDIO', 'Posible que ocurra un evento LA/FT', '2.01 - 3.00', [254, 243, 199], [146, 64, 14]],
        ['ALTO', 'Probable que ocurra un evento LA/FT', '3.01 - 4.00', [254, 215, 170], [154, 52, 18]],
        ['MUY ALTO', 'Muy probable que ocurra un evento LA/FT', '> 4.00', [254, 202, 202], [153, 27, 27]],
    ];

    $pdf->SetDrawColor(10, 63, 122);
    $pdf->SetFillColor(10, 63, 122);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->Cell(50, 7, pdf_text('Nivel'), 1, 0, 'C', true);
    $pdf->Cell(160, 7, pdf_text('Descripcion'), 1, 0, 'C', true);
    $pdf->Cell(67, 7, pdf_text('Rango'), 1, 0, 'C', true);
    $pdf->Ln();

    foreach ($levels as $level) {
        $pdf->SetDrawColor(200, 210, 220);
        $pdf->SetFillColor($level[3][0], $level[3][1], $level[3][2]);
        $pdf->SetTextColor($level[4][0], $level[4][1], $level[4][2]);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(50, 8, pdf_text($level[0]), 1, 0, 'C', true);
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell(160, 8, pdf_text($level[1]), 1, 0, 'L', true);
        $pdf->Cell(67, 8, pdf_text($level[2]), 1, 0, 'C', true);
        $pdf->Ln();
    }

    $pdf->SetTextColor(30, 30, 30);
}

function matrix_signature(FPDF $pdf, string $authorName = ''): void
{
    if ($pdf->GetY() > 148) {
        $pdf->AddPage();
    }

    $pdf->Ln(16);
    $center = 148.5;
    $lineWidth = 92;

    if ($authorName !== '') {
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(277, 5.5, pdf_text($authorName), 0, 1, 'C');
        $pdf->Ln(6);
    } else {
        $pdf->Ln(18);
    }
    $pdf->SetDrawColor(55, 65, 75);
    $pdf->SetLineWidth(0.35);
    $pdf->Line($center - ($lineWidth / 2), $pdf->GetY(), $center + ($lineWidth / 2), $pdf->GetY());
    $pdf->Ln(3);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(277, 5, pdf_text('RESPONSABLE DE CUMPLIMIENTO'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetTextColor(100, 110, 120);
    $pdf->Cell(277, 4, pdf_text('Departamento de Prevencion LA/FT'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 6.5);
    $pdf->SetTextColor(145, 145, 145);
    $pdf->Cell(277, 4, pdf_text('Documento generado electronicamente'), 0, 1, 'C');
    $pdf->SetTextColor(30, 30, 30);
}
