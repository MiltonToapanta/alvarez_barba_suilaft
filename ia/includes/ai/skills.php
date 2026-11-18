<?php
declare(strict_types=1);

/*
 * Registro de skills (habilidades).
 *
 * Cada carpeta en /skills/<slug>/ contiene:
 *   - skill.json        Manifiesto: nombre, descripcion, disparadores, tipo de exportacion,
 *                       preguntas guiadas y lista de recursos.
 *   - instructions.md   Instrucciones del especialista.
 *   - references/*.md   Material de apoyo (catalogos, plantillas).
 *
 * Igual que las Skills de Anthropic, el manifiesto es barato y siempre esta disponible
 * (para el selector y el enrutador), pero las instrucciones completas solo se cargan en
 * el prompt cuando la skill esta activa. Asi el contexto no crece con cada skill nueva.
 */

function skills_dir(): string
{
    return APP_ROOT . '/skills';
}

function skill_registry(): array
{
    static $registry = null;
    if ($registry !== null) {
        return $registry;
    }

    $registry = [];
    $manifests = glob(skills_dir() . '/*/skill.json') ?: [];
    foreach ($manifests as $manifestPath) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            continue;
        }

        $slug = (string) ($manifest['slug'] ?? basename(dirname($manifestPath)));
        if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
            continue;
        }

        $registry[$slug] = [
            'slug' => $slug,
            'name' => (string) ($manifest['name'] ?? $slug),
            'category' => (string) ($manifest['category'] ?? 'documento'),
            'icon' => (string) ($manifest['icon'] ?? 'sparkle'),
            'description' => (string) ($manifest['description'] ?? ''),
            'starter' => (string) ($manifest['starter'] ?? ''),
            'export_type' => (string) ($manifest['export_type'] ?? ''),
            'order' => (int) ($manifest['order'] ?? 100),
            'show_in_picker' => (bool) ($manifest['show_in_picker'] ?? true),
            'triggers' => array_values(array_map('strval', (array) ($manifest['triggers'] ?? []))),
            'resources' => array_values(array_map('strval', (array) ($manifest['resources'] ?? ['instructions.md']))),
            'guided_questions' => array_values((array) ($manifest['guided_questions'] ?? [])),
            'dir' => dirname($manifestPath),
        ];
    }

    uasort($registry, function (array $a, array $b): int {
        return $a['order'] <=> $b['order'];
    });

    return $registry;
}

function find_skill(string $slug): ?array
{
    $registry = skill_registry();
    return $registry[$slug] ?? null;
}

function valid_skill_slug(string $slug): string
{
    return find_skill($slug) ? $slug : '';
}

/**
 * Instrucciones completas de la skill (progressive disclosure: solo cuando esta activa).
 */
function skill_instructions(string $slug): string
{
    $skill = find_skill($slug);
    if (!$skill) {
        return '';
    }

    $parts = [];
    foreach ($skill['resources'] as $resource) {
        // Evita rutas fuera de la carpeta de la skill.
        if (strpos($resource, '..') !== false) {
            continue;
        }
        $text = load_prompt_file($skill['dir'] . '/' . $resource);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim(implode("\n\n", $parts));
}

/**
 * Datos de las skills que necesita el navegador (sin instrucciones internas).
 */
function skills_for_client(): array
{
    $items = [];
    foreach (skill_registry() as $skill) {
        $items[] = [
            'slug' => $skill['slug'],
            'name' => $skill['name'],
            'category' => $skill['category'],
            'icon' => $skill['icon'],
            'description' => $skill['description'],
            'starter' => $skill['starter'],
            'export_type' => $skill['export_type'],
            'show_in_picker' => $skill['show_in_picker'],
            'guided_questions' => $skill['guided_questions'],
        ];
    }

    return $items;
}

/**
 * Enrutador determinista: detecta cuando el usuario pide explicitamente una skill
 * ("quiero generar el manual de cumplimiento") aunque no haya usado el selector.
 */
function skill_from_triggers(string $message): string
{
    $folded = text_fold($message);

    // Solo se activa ante una intencion de crear o analizar, no ante una pregunta conceptual.
    $intent = preg_match('/\b(genera|generar|generame|elabora|elaborar|elaborame|redacta|redactar|crea|crear|hazme|haz|arma|armar|prepara|preparar|necesito|quiero|ayudame|analiza|analizar|revisa|revisar|audita|auditar|evalua|evaluar|define|definir)\b/u', $folded) === 1;
    if (!$intent) {
        return '';
    }

    foreach (skill_registry() as $skill) {
        foreach ($skill['triggers'] as $trigger) {
            if ($trigger !== '' && strpos($folded, text_fold($trigger)) !== false) {
                return $skill['slug'];
            }
        }
    }

    return '';
}

function load_prompt_file(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    $contents = file_get_contents($path);
    if (!is_string($contents) || trim($contents) === '') {
        return '';
    }

    return rtrim(str_replace(["\r\n", "\r"], "\n", $contents));
}
