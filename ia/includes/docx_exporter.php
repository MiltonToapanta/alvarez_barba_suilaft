<?php
/**
 * Exportador de documentos Word (DOCX) para Suilaft AI Compliance.
 *
 * Genera un .docx real (OOXML) sin Composer: arma el ZIP con ZipArchive y
 * convierte el Markdown ligero de la IA en WordprocessingML con portada,
 * titulos, tablas, listas y numeracion de paginas.
 *
 * Reutiliza el parseador de Markdown de pdf_exporter.php (markdown_blocks,
 * inline_runs, etc.), por lo que requiere que ese archivo exista.
 */

require_once __DIR__ . '/pdf_exporter.php';

/**
 * Tipos de documento soportados para Word.
 */
function docx_document_types(): array
{
    return [
        'manual' => [
            'label' => 'Manual de cumplimiento',
            'subtitle' => 'Manual de prevencion de lavado de activos, financiamiento del terrorismo y FPADM',
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
            'label' => 'Metodologia',
            'subtitle' => 'Metodologia de implementacion del sistema PLA/FT/FPADM',
            'slug' => 'metodologia',
        ],
        'matriz' => [
            'label' => 'Matriz de riesgo',
            'subtitle' => 'Matriz de riesgo LA/FT/FPADM',
            'slug' => 'matriz_riesgo',
        ],
    ];
}

/**
 * Genera y descarga el DOCX en el navegador.
 *
 * @param string $type    manual|informe|plan|matriz
 * @param string $content Texto (Markdown ligero) generado por la IA.
 * @param array  $meta    Datos opcionales: title, company, author, user_name.
 */
function stream_docx_document(string $type, string $content, array $meta): void
{
    $types = docx_document_types();
    if (!isset($types[$type])) {
        throw new RuntimeException('Tipo de documento no valido.');
    }

    $content = trim($content);
    // La matriz guarda un bloque JSON interno para el PDF; no debe ir al Word.
    $content = (string) preg_replace('/<!--\s*MATRIZ_JSON\s*-->[\s\S]*?<!--\s*\/MATRIZ_JSON\s*-->/', '', $content);
    // El marcador de fin de entrevista no debe aparecer en el documento.
    $content = (string) preg_replace('/<!--\s*DOCUMENTO_FINAL\s*-->/', '', $content);
    $content = trim($content);
    if ($content === '') {
        throw new RuntimeException('No hay contenido para exportar.');
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('El servidor no tiene ZipArchive para generar DOCX.');
    }

    $config = $types[$type];
    $company = trim((string) ($meta['company'] ?? ''));
    $author = trim((string) ($meta['author'] ?? ''));
    $ruc = trim((string) ($meta['ruc'] ?? ''));
    $code = trim((string) ($meta['code'] ?? ''));
    $version = trim((string) ($meta['version'] ?? ''));

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . docx_filename($type) . '"');
    header('Cache-Control: max-age=0');

    $tmp = tempnam(sys_get_temp_dir(), 'suilaft_docx_');
    if ($tmp === false) {
        throw new RuntimeException('No pude crear el archivo temporal DOCX.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        throw new RuntimeException('No pude crear el archivo DOCX.');
    }

    $template = docx_template_path();

    if ($template !== '') {
        // Modo plantilla: se conservan estilos, tema, encabezado con logotipo,
        // pie de pagina, numeracion y tipografia del documento de referencia.
        $src = new ZipArchive();
        if ($src->open($template) === true) {
            for ($i = 0; $i < $src->numFiles; $i++) {
                $name = (string) $src->getNameIndex($i);
                if (in_array($name, [
                    'word/document.xml',
                    'docProps/core.xml',
                    'docProps/app.xml',
                    'word/header1.xml',
                    'word/_rels/header1.xml.rels',
                    'word/media/image1.png',
                ], true)) {
                    continue;
                }
                $zip->addFromString($name, (string) $src->getFromIndex($i));
            }
            $src->close();
        }

        $zip->addFromString('word/header1.xml', docx_clean_header());
        $zip->addFromString('word/_rels/header1.xml.rels', docx_clean_header_rels());
        $zip->addFromString('word/document.xml', docx_templated_document_xml($config, $content, $company, $author, $ruc, $code, $version));
        $zip->addFromString('docProps/core.xml', docx_core($config, $company, $author));
        $zip->addFromString('docProps/app.xml', docx_app());
    } else {
        $zip->addFromString('[Content_Types].xml', docx_content_types());
        $zip->addFromString('_rels/.rels', docx_root_rels());
        $zip->addFromString('word/document.xml', docx_build_document_xml($config, $content, $company, $author));
        $zip->addFromString('word/_rels/document.xml.rels', docx_document_rels());
        $zip->addFromString('word/styles.xml', docx_styles());
        $zip->addFromString('word/footer1.xml', docx_footer());
        $zip->addFromString('docProps/core.xml', docx_core($config, $company, $author));
        $zip->addFromString('docProps/app.xml', docx_app());
    }

    $zip->close();

    readfile($tmp);
    unlink($tmp);
    exit;
}

/**
 * Ruta a la plantilla DOCX de referencia (mismo diseno del manual entregado).
 */
function docx_template_path(): string
{
    $path = dirname(__DIR__) . '/storage/templates/manual_template.docx';
    return is_file($path) ? $path : '';
}

/**
 * Nombre de archivo ASCII para la descarga.
 */
function docx_filename(string $type): string
{
    $config = docx_document_types()[$type] ?? null;
    $slug = $config['slug'] ?? 'documento';

    return $slug . '_' . date('Ymd_His') . '.docx';
}

/* -------------------------------------------------------------------------
 * Construccion del XML
 * ---------------------------------------------------------------------- */

function docx_escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Construye un <w:r> (run de texto) con propiedades opcionales.
 */
function docx_run(string $text, array $props = []): string
{
    $rPr = '';
    if (!empty($props['b'])) {
        $rPr .= '<w:b/>';
    }
    if (!empty($props['i'])) {
        $rPr .= '<w:i/>';
    }
    if (!empty($props['sz'])) {
        $rPr .= '<w:sz w:val="' . (int) $props['sz'] . '"/><w:szCs w:val="' . (int) $props['sz'] . '"/>';
    }
    if (!empty($props['color'])) {
        $rPr .= '<w:color w:val="' . $props['color'] . '"/>';
    }
    if (!empty($props['font'])) {
        $rPr .= '<w:rFonts w:ascii="' . $props['font'] . '" w:hAnsi="' . $props['font'] . '"/>';
    }

    $xml = '<w:r>';
    if ($rPr !== '') {
        $xml .= '<w:rPr>' . $rPr . '</w:rPr>';
    }
    $xml .= '<w:t xml:space="preserve">' . docx_escape($text) . '</w:t></w:r>';

    return $xml;
}

/**
 * Construye un <w:p> a partir de runs (strings ya formados por docx_run).
 */
function docx_p(array $runs, array $props = []): string
{
    $pPr = '';
    if (!empty($props['style'])) {
        $pPr .= '<w:pStyle w:val="' . $props['style'] . '"/>';
    }
    if (!empty($props['jc'])) {
        $pPr .= '<w:jc w:val="' . $props['jc'] . '"/>';
    }
    $before = (int) ($props['spacing_before'] ?? 0);
    $after = (int) ($props['spacing_after'] ?? 0);
    if ($before || $after) {
        $pPr .= '<w:spacing w:before="' . $before . '" w:after="' . $after . '"/>';
    }
    if (!empty($props['indent_left'])) {
        $pPr .= '<w:ind w:left="' . (int) $props['indent_left'] . '"/>';
    }

    $xml = '<w:p>';
    if ($pPr !== '') {
        $xml .= '<w:pPr>' . $pPr . '</w:pPr>';
    }
    foreach ($runs as $run) {
        $xml .= $run;
    }
    if (!empty($props['page_break'])) {
        $xml .= '<w:r><w:br w:type="page"/></w:r>';
    }
    $xml .= '</w:p>';

    return $xml;
}

/**
 * Convierte el resultado de inline_runs() en runs XML de Word.
 */
function docx_runs_from_inline(array $runs, bool $forceBold = false): array
{
    $out = [];
    foreach ($runs as $run) {
        $style = (string) ($run['style'] ?? '');
        $props = [];
        if ($forceBold || $style === 'b') {
            $props['b'] = true;
        }
        if ($style === 'i') {
            $props['i'] = true;
        }
        if ($style === 'code') {
            $props['font'] = 'Courier New';
        }
        $out[] = docx_run((string) ($run['text'] ?? ''), $props);
    }

    return $out;
}

function docx_build_document_xml(array $config, string $content, string $company, string $author): string
{
    $body = docx_cover($config, $company, $author);
    $body .= docx_blocks_xml(markdown_blocks($content));

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<w:body>' . $body . docx_sect_pr() . '</w:body></w:document>';
}

function docx_cover(array $config, string $company, string $author): string
{
    $company = $company !== '' ? $company : '[NOMBRE DE LA EMPRESA]';
    $author = $author !== '' ? $author : '[OFICIAL DE CUMPLIMIENTO]';

    $xml = '';
    $xml .= docx_p([docx_run($company, ['b' => true, 'sz' => 30, 'color' => '1D4ED8'])], ['jc' => 'center', 'spacing_after' => 200]);
    $xml .= docx_p([docx_run($config['label'], ['b' => true, 'sz' => 48, 'color' => '0F766E'])], ['jc' => 'center', 'spacing_after' => 120]);
    $xml .= docx_p([docx_run($config['subtitle'], ['i' => true, 'sz' => 24, 'color' => '475569'])], ['jc' => 'center', 'spacing_after' => 480]);

    $xml .= docx_p([docx_run('INFORMACION GENERAL', ['b' => true, 'sz' => 20, 'color' => '0F766E'])], ['spacing_after' => 140]);

    $meta = [
        'Fecha de emision' => date('d/m/Y'),
        'Tipo de documento' => $config['label'],
        'Empresa' => $company,
        'Elaborado por' => $author,
    ];
    foreach ($meta as $key => $value) {
        $xml .= docx_p(
            [docx_run($key . ': ', ['b' => true]), docx_run($value)],
            ['spacing_after' => 80]
        );
    }

    $xml .= docx_p(
        [docx_run('Documento confidencial. Contiene informacion sensible sujeta a reserva y proteccion de datos. Debe tratarse conforme a la normativa aplicable y circularse unicamente a personal autorizado.', ['i' => true, 'sz' => 18, 'color' => '991B1B'])],
        ['spacing_before' => 240, 'spacing_after' => 120, 'page_break' => true]
    );

    return $xml;
}

function docx_blocks_xml(array $blocks): string
{
    $xml = '';
    foreach ($blocks as $block) {
        $type = (string) ($block['type'] ?? 'p');

        switch ($type) {
            case 'h1':
                $xml .= docx_p(docx_runs_from_inline($block['text']), ['style' => 'Heading1']);
                break;
            case 'h2':
                $xml .= docx_p(docx_runs_from_inline($block['text']), ['style' => 'Heading2']);
                break;
            case 'h3':
                $xml .= docx_p(docx_runs_from_inline($block['text']), ['style' => 'Heading3']);
                break;
            case 'ul':
                foreach ((array) ($block['items'] ?? []) as $item) {
                    $runs = docx_runs_from_inline($item);
                    array_unshift($runs, docx_run("\u{2022}\t", ['color' => '2563EB']));
                    $xml .= docx_p($runs, ['style' => 'ListParagraph']);
                }
                break;
            case 'ol':
                $index = 1;
                foreach ((array) ($block['items'] ?? []) as $item) {
                    $runs = docx_runs_from_inline($item);
                    array_unshift($runs, docx_run($index . ".\t", ['b' => true, 'color' => '2563EB']));
                    $xml .= docx_p($runs, ['style' => 'ListParagraph']);
                    $index++;
                }
                break;
            case 'quote':
                $xml .= docx_p(docx_runs_from_inline($block['text']), ['style' => 'Quote']);
                break;
            case 'table':
                $xml .= docx_table_xml((array) ($block['rows'] ?? []));
                break;
            case 'hr':
                $xml .= docx_p([], []);
                break;
            default:
                $xml .= docx_p(docx_runs_from_inline($block['text']), []);
        }
    }

    return $xml;
}

function docx_table_xml(array $rows): string
{
    if (!$rows) {
        return '';
    }

    $xml = '<w:tbl><w:tblPr>'
        . '<w:tblW w:w="0" w:type="auto"/>'
        . '<w:tblBorders>'
        . '<w:top w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
        . '<w:left w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
        . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
        . '<w:right w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
        . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
        . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
        . '</w:tblBorders>'
        . '<w:tblCellMar><w:top w:w="80" w:type="dxa"/><w:left w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar>'
        . '</w:tblPr>';

    foreach ($rows as $rowIndex => $row) {
        $isHeader = $rowIndex === 0;
        $xml .= '<w:tr>';
        foreach ((array) $row as $cell) {
            $shd = $isHeader ? '<w:shd w:val="clear" w:color="auto" w:fill="D9F3F0"/>' : '';
            $runs = docx_runs_from_inline(inline_runs((string) $cell), $isHeader);
            $xml .= '<w:tc><w:tcPr>' . $shd . '</w:tcPr>'
                . '<w:p><w:pPr><w:spacing w:after="0"/></w:pPr>' . implode('', $runs) . '</w:p>'
                . '</w:tc>';
        }
        $xml .= '</w:tr>';
    }

    $xml .= '</w:tbl>';
    $xml .= docx_p([], []);

    return $xml;
}

/* -------------------------------------------------------------------------
 * Partes fijas del paquete OOXML
 * ---------------------------------------------------------------------- */

function docx_content_types(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '</Types>';
}

function docx_root_rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
}

function docx_document_rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
        . '</Relationships>';
}

function docx_styles(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr>'
        . '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>'
        . '<w:sz w:val="22"/><w:szCs w:val="22"/>'
        . '</w:rPr></w:rPrDefault><w:pPrDefault><w:pPr>'
        . '<w:spacing w:after="160" w:line="276" w:lineRule="auto"/>'
        . '</w:pPr></w:pPrDefault></w:docDefaults>'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
        . '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
        . '<w:pPr><w:keepNext/><w:spacing w:before="360" w:after="160"/></w:pPr>'
        . '<w:rPr><w:b/><w:color w:val="0F766E"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
        . '<w:pPr><w:keepNext/><w:spacing w:before="280" w:after="120"/></w:pPr>'
        . '<w:rPr><w:b/><w:color w:val="1D4ED8"/><w:sz w:val="26"/><w:szCs w:val="26"/></w:rPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
        . '<w:pPr><w:keepNext/><w:spacing w:before="240" w:after="100"/></w:pPr>'
        . '<w:rPr><w:b/><w:color w:val="0F172A"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:basedOn w:val="Normal"/>'
        . '<w:pPr><w:ind w:left="720"/><w:spacing w:after="80"/></w:pPr></w:style>'
        . '<w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/><w:basedOn w:val="Normal"/>'
        . '<w:pPr><w:ind w:left="720"/></w:pPr><w:rPr><w:i/><w:color w:val="475569"/></w:rPr></w:style>'
        . '</w:styles>';
}

function docx_footer(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
        . '<w:r><w:rPr><w:color w:val="64748B"/><w:sz w:val="16"/><w:szCs w:val="16"/></w:rPr><w:t>Pagina </w:t></w:r>'
        . '<w:r><w:fldChar w:fldCharType="begin"/></w:r>'
        . '<w:r><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>'
        . '<w:r><w:fldChar w:fldCharType="end"/></w:r>'
        . '<w:r><w:rPr><w:color w:val="64748B"/><w:sz w:val="16"/><w:szCs w:val="16"/></w:rPr><w:t> de </w:t></w:r>'
        . '<w:r><w:fldChar w:fldCharType="begin"/></w:r>'
        . '<w:r><w:instrText xml:space="preserve"> NUMPAGES </w:instrText></w:r>'
        . '<w:r><w:fldChar w:fldCharType="end"/></w:r>'
        . '</w:p></w:ftr>';
}

function docx_sect_pr(): string
{
    return '<w:sectPr>'
        . '<w:footerReference w:type="default" r:id="rId2"/>'
        . '<w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/>'
        . '</w:sectPr>';
}

function docx_core(array $config, string $company, string $author): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
        . 'xmlns:dcterms="http://purl.org/dc/terms/" '
        . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>' . docx_escape($config['label'] . ($company !== '' ? ' - ' . $company : '')) . '</dc:title>'
        . '<dc:creator>' . docx_escape($author !== '' ? $author : 'SUILAFT AI') . '</dc:creator>'
        . '<cp:lastModifiedBy>SUILAFT AI</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:modified>'
        . '</cp:coreProperties>';
}

function docx_app(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
        . '<Application>Suilaft AI Compliance</Application>'
        . '</Properties>';
}

/* -------------------------------------------------------------------------
 * Modo plantilla: replica el diseno del manual de referencia
 * (Times New Roman, titulos en negrita, tablas TableGrid con encabezado
 * gris y encabezado/pie con logotipo heredados de la plantilla).
 * ---------------------------------------------------------------------- */

function docx_templated_document_xml(
    array $config,
    string $content,
    string $company,
    string $author,
    string $ruc,
    string $code,
    string $version
): string {
    $body = docx_reference_cover($config, $company, $author, $ruc, $code, $version);
    $body .= docx_reference_blocks(markdown_blocks($content));

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<w:body>' . $body
        . '<w:sectPr><w:headerReference w:type="default" r:id="rId9"/><w:footerReference w:type="default" r:id="rId10"/>'
        . '<w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="1181" w:right="1238" w:bottom="1152" w:left="1238" w:header="432" w:footer="403" w:gutter="0"/>'
        . '<w:cols w:space="720"/><w:docGrid w:linePitch="360"/></w:sectPr>'
        . '</w:body></w:document>';
}

function docx_reference_cover(array $config, string $company, string $author, string $ruc, string $code, string $version): string
{
    $company = $company !== '' ? $company : '[NOMBRE DE LA EMPRESA]';
    $ruc = $ruc !== '' ? $ruc : '[POR COMPLETAR]';
    $code = $code !== '' ? $code : '[POR COMPLETAR]';
    $version = $version !== '' ? $version : '1.0';

    $xml = '';
    $xml .= docx_ref_p([docx_ref_run('', ['sz' => 19])], ['before' => 0, 'after' => 0]);

    // Titulo principal + subtitulo.
    $titleLines = array_merge(
        [strtoupper($config['label'])],
        docx_wrap_upper(strtoupper($config['subtitle']), 44)
    );
    $first = array_shift($titleLines);
    $xml .= docx_ref_p([docx_ref_run($first, ['b' => true, 'sz' => 34])], ['keepNext' => true, 'before' => 600, 'after' => 40, 'jc' => 'center']);
    foreach ($titleLines as $line) {
        $xml .= docx_ref_p([docx_ref_run($line, ['b' => true, 'sz' => 30])], ['keepNext' => true, 'before' => 0, 'after' => 40, 'jc' => 'center']);
    }

    // Empresa y RUC (solo para documentos que llevan datos de empresa).
    $simple = ($config['slug'] ?? '') === 'metodologia';
    if (!$simple) {
        $xml .= docx_ref_p([docx_ref_run($company, ['b' => true, 'sz' => 30])], ['keepNext' => true, 'before' => 120, 'after' => 40, 'jc' => 'center']);
        $xml .= docx_ref_p([docx_ref_run('RUC ' . $ruc, ['sz' => 18, 'color' => '404040'])], ['keepNext' => true, 'before' => 0, 'after' => 200, 'jc' => 'center']);
    }

    // Codigo, version, fecha y clasificacion.
    if ($simple) {
        $meta = [
            'Fecha de emisión: ' . docx_spanish_month_year(),
            'Clasificación: Uso interno y reservado',
        ];
    } else {
        $meta = [
            'Código: ' . $code . '   |   Versión: ' . $version,
            'Fecha de emisión: ' . docx_spanish_month_year() . '   |   Revisión: Primera',
            'Clasificación: Uso interno y reservado',
        ];
    }
    foreach ($meta as $line) {
        $xml .= docx_ref_p([docx_ref_run($line, ['sz' => 19])], ['before' => 0, 'after' => 60, 'jc' => 'both']);
    }

    // Advertencia de control documental.
    $xml .= docx_ref_p([
        docx_ref_run('ADVERTENCIA DE CONTROL DOCUMENTAL: ', ['b' => true, 'sz' => 17, 'color' => '404040']),
        docx_ref_run('Este documento debe revisarse y ajustarse a la condición real de la empresa como sujeto obligado, a su código de registro UAFE, a las instrucciones sectoriales vigentes y a su estructura operativa. Los campos marcados [POR COMPLETAR] deben cerrarse antes de su aprobación.', ['i' => true, 'sz' => 17, 'color' => '404040']),
    ], ['before' => 140, 'after' => 140, 'jc' => 'both']);

    // Localidad.
    $xml .= docx_ref_p([docx_ref_run('Quito - Ecuador', ['b' => true, 'sz' => 19])], ['before' => 0, 'after' => 0, 'jc' => 'center']);

    $xml .= docx_ref_p([], ['pageBreak' => true]);

    return $xml;
}

function docx_reference_blocks(array $blocks): string
{
    $xml = '';
    foreach ($blocks as $block) {
        $type = (string) ($block['type'] ?? 'p');

        switch ($type) {
            case 'h1':
                $xml .= docx_ref_p(docx_ref_inline_runs($block['text'], true), ['style' => 'Heading1', 'keepNext' => true, 'before' => 240, 'after' => 80, 'jc' => 'left']);
                break;
            case 'h2':
                $xml .= docx_ref_p(docx_ref_inline_runs($block['text'], true), ['style' => 'Heading2', 'keepNext' => true, 'before' => 160, 'after' => 60, 'jc' => 'left']);
                break;
            case 'h3':
                $xml .= docx_ref_p(docx_ref_inline_runs($block['text'], true), ['style' => 'Heading3', 'keepNext' => true, 'before' => 120, 'after' => 40, 'jc' => 'left']);
                break;
            case 'ul':
                foreach ((array) ($block['items'] ?? []) as $item) {
                    $runs = docx_ref_inline_runs($item);
                    array_unshift($runs, docx_ref_run("\u{2022}\t", ['sz' => 19]));
                    $xml .= docx_ref_p($runs, ['before' => 0, 'after' => 40, 'jc' => 'both', 'indentLeft' => 360]);
                }
                break;
            case 'ol':
                $index = 1;
                foreach ((array) ($block['items'] ?? []) as $item) {
                    $runs = docx_ref_inline_runs($item);
                    array_unshift($runs, docx_ref_run($index . ".\t", ['b' => true, 'sz' => 19]));
                    $xml .= docx_ref_p($runs, ['before' => 0, 'after' => 40, 'jc' => 'both', 'indentLeft' => 360]);
                    $index++;
                }
                break;
            case 'quote':
                $xml .= docx_ref_p(docx_ref_inline_runs($block['text']), ['before' => 0, 'after' => 60, 'jc' => 'both', 'indentLeft' => 360]);
                break;
            case 'table':
                $xml .= docx_ref_table((array) ($block['rows'] ?? []));
                break;
            case 'hr':
                $xml .= docx_ref_p([], ['before' => 0, 'after' => 0]);
                break;
            default:
                $xml .= docx_ref_p(docx_ref_inline_runs($block['text']), ['before' => 0, 'after' => 80, 'jc' => 'both']);
        }
    }

    return $xml;
}

function docx_ref_table(array $rows): string
{
    if (!$rows) {
        return '';
    }

    $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:type="auto" w:w="0"/>'
        . '<w:jc w:val="center"/><w:tblLayout w:type="autofit"/>'
        . '<w:tblLook w:firstColumn="1" w:firstRow="1" w:lastColumn="0" w:lastRow="0" w:noHBand="0" w:noVBand="1" w:val="04A0"/>'
        . '</w:tblPr>';

    foreach ($rows as $rowIndex => $row) {
        $isHeader = $rowIndex === 0;
        $xml .= '<w:tr>';
        foreach ((array) $row as $cell) {
            $shd = $isHeader ? '<w:shd w:val="clear" w:color="auto" w:fill="E7E6E6"/>' : '';
            $runs = docx_ref_inline_runs(inline_runs((string) $cell), $isHeader);
            $xml .= '<w:tc><w:tcPr><w:vAlign w:val="center"/>' . $shd . '</w:tcPr>'
                . '<w:p><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr>' . implode('', $runs) . '</w:p>'
                . '</w:tc>';
        }
        $xml .= '</w:tr>';
    }

    $xml .= '</w:tbl>';
    $xml .= docx_ref_p([], ['before' => 0, 'after' => 40]);

    return $xml;
}

function docx_ref_run(string $text, array $props = []): string
{
    $rPr = '';

    $font = $props['font'] ?? null;
    if ($font !== null) {
        $rPr .= '<w:rFonts w:ascii="' . $font . '" w:hAnsi="' . $font . '"/>';
    }
    if (!empty($props['b'])) {
        $rPr .= '<w:b/>';
    }
    if (!empty($props['i'])) {
        $rPr .= '<w:i/>';
    }
    if (isset($props['sz'])) {
        $rPr .= '<w:sz w:val="' . (int) $props['sz'] . '"/><w:szCs w:val="' . (int) $props['sz'] . '"/>';
    }
    if (!empty($props['color'])) {
        $rPr .= '<w:color w:val="' . $props['color'] . '"/>';
    }

    $xml = '<w:r>';
    if ($rPr !== '') {
        $xml .= '<w:rPr>' . $rPr . '</w:rPr>';
    }
    $xml .= '<w:t xml:space="preserve">' . docx_escape($text) . '</w:t></w:r>';

    return $xml;
}

function docx_ref_p(array $runs, array $props = []): string
{
    $pPr = '';

    if (!empty($props['style'])) {
        $pPr .= '<w:pStyle w:val="' . $props['style'] . '"/>';
    }
    if (!empty($props['keepNext'])) {
        $pPr .= '<w:keepNext/>';
    }
    if (!empty($props['pageBreakBefore'])) {
        $pPr .= '<w:pageBreakBefore/>';
    }

    $before = (int) ($props['before'] ?? 0);
    $after = (int) ($props['after'] ?? 0);
    $line = (int) ($props['line'] ?? 240);
    $pPr .= '<w:spacing w:before="' . $before . '" w:after="' . $after . '" w:line="' . $line . '" w:lineRule="auto"/>';

    if (!empty($props['jc'])) {
        $pPr .= '<w:jc w:val="' . $props['jc'] . '"/>';
    }
    if (!empty($props['indentLeft'])) {
        $pPr .= '<w:ind w:left="' . (int) $props['indentLeft'] . '"/>';
    }

    $xml = '<w:p>';
    if ($pPr !== '') {
        $xml .= '<w:pPr>' . $pPr . '</w:pPr>';
    }
    foreach ($runs as $run) {
        $xml .= $run;
    }
    if (!empty($props['pageBreak'])) {
        $xml .= '<w:r><w:br w:type="page"/></w:r>';
    }
    $xml .= '</w:p>';

    return $xml;
}

function docx_ref_inline_runs(array $runs, bool $forceBold = false): array
{
    $out = [];
    foreach ($runs as $run) {
        $style = (string) ($run['style'] ?? '');
        $props = [];
        if ($forceBold || $style === 'b') {
            $props['b'] = true;
        }
        if ($style === 'i') {
            $props['i'] = true;
        }
        if ($style === 'code') {
            $props['font'] = 'Courier New';
        }
        $out[] = docx_ref_run((string) ($run['text'] ?? ''), $props);
    }

    return $out;
}

function docx_wrap_upper(string $text, int $max): array
{
    $words = preg_split('/\s+/', trim($text));
    if ($words === false) {
        return [$text];
    }

    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        if (strlen($candidate) > $max && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $candidate;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}

function docx_spanish_month_year(): string
{
    $months = [
        1 => 'ENERO',
        2 => 'FEBRERO',
        3 => 'MARZO',
        4 => 'ABRIL',
        5 => 'MAYO',
        6 => 'JUNIO',
        7 => 'JULIO',
        8 => 'AGOSTO',
        9 => 'SEPTIEMBRE',
        10 => 'OCTUBRE',
        11 => 'NOVIEMBRE',
        12 => 'DICIEMBRE',
    ];

    return $months[(int) date('n')] . ' ' . date('Y');
}

/**
 * Encabezado limpio (sin logotipo) para el modo plantilla.
 */
function docx_clean_header(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:p><w:pPr><w:pStyle w:val="Header"/></w:pPr></w:p>'
        . '</w:hdr>';
}

/**
 * Relaciones del encabezado limpio (sin imagen).
 */
function docx_clean_header_rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
}
