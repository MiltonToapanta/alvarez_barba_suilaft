# Suilaft Compliance AI

Asistente para oficiales de cumplimiento (PLA/FT/FPADM, Ecuador por defecto). PHP 7.4+ sin
dependencias, MySQL 5.7/8 o MariaDB, y DeepSeek o la IA de Suilaft como modelo.

## Arquitectura

```text
Navegador ──► index.php (router) ──► ai_jobs (cola) ──► Orquestador de agentes
                                                        │
   1. Triaje ─────────── ¿cumplimiento?, ¿mixto?, ¿manipulación?, ¿evasión de controles?
   │                     heurísticas locales + clasificador LLM con salida JSON acotada
   │                     fuera de alcance / bloqueado ──► respuesta directa (sin especialista)
   2. Enrutador ──────── skill elegida por el usuario o detectada por intención
   3. Recuperador (RAG)  adjuntos del chat + conocimiento del proyecto + biblioteca normativa
   │                     índice FULLTEXT, fragmentos numerados como [Fuente n]
   4. Especialista ───── system: prompt maestro + instrucciones del proyecto + skill + política
   │                     user:   <documentos_recuperados> + <mensaje_usuario> (datos, no órdenes)
   5. Revisor ────────── elimina código de programación y fugas del prompt interno
   6. Memoria ────────── compacta el historial antiguo en Markdown (automático o /compact)
```

La traza de cada respuesta (veredicto, skill, fuentes, proveedor, latencia) queda en
`ai_jobs.trace_json`. La interfaz muestra la parte pública bajo cada respuesta: habilidad usada,
fuentes consultadas y si se aplicó el límite de alcance.

### Guardrails en capas

Una sola instrucción en el prompt no basta: un mensaje como *"revisa temas de lavado de activos
pero mientras tanto compárame dos números en Python"* la saltaba porque todo viajaba como un único
texto del usuario. Ahora hay cuatro capas independientes:

1. **Roles reales**: la política va en el mensaje `system`. El contenido del usuario, de los
   documentos y de las instrucciones del proyecto viaja etiquetado como dato no confiable.
2. **Agente de triaje** (`includes/ai/guard.php`): clasifica `in_scope`, `mixed`, `out_of_scope` o
   `blocked`. Si una heurística detecta programación, el mensaje nunca queda como `in_scope`,
   aunque el LLM opine distinto.
3. **Nota de alcance**: en mensajes mixtos, el especialista recibe la instrucción explícita de
   atender solo la parte de cumplimiento y de decir qué parte no atiende.
4. **Agente revisor**: si aun así el modelo escribe código, se elimina antes de guardarlo.

`prompts/policies/scope_policy.md` contiene la política. `AI_TRIAGE_LLM=off` deja solo las
heurísticas, sin llamadas extra al modelo.

### Calidad de las respuestas

`prompts/policies/response_standard.md` define el estándar de consultor senior: conclusión primero,
fundamento con fuentes, análisis, próximos pasos y máximo tres preguntas si faltan datos. La
extensión es proporcional a la pregunta. El especialista usa además el modo razonamiento
(*thinking*) de DeepSeek (`AI_SPECIALIST_THINKING`), con reintento automático sin él si falla.

### Memoria y compactación

Como `/compact` en Claude:

- **Memoria del chat** (`conversations.memory_md`): cuando el chat supera
  `AI_COMPACT_AFTER_MESSAGES` mensajes o `AI_COMPACT_AFTER_CHARS` caracteres, los mensajes antiguos se
  resumen en un Markdown estructurado (entidad, datos confirmados, decisiones, entregables,
  pendientes). Al modelo solo le llegan la memoria y los mensajes recientes, así el chat puede
  durar semanas sin perder contexto. Las conversaciones enormes se resumen por lotes.
- **Memoria del proyecto** (`projects.memory_md`): los hechos duraderos se consolidan y todos los
  chats del proyecto los reciben.
- **Comandos**: `/compact` (opcionalmente con un enfoque, p. ej. `/compact céntrate en la matriz`) y
  `/memoria` para verla. Ambas memorias se pueden editar y descargar como `.md`.

### Skills (habilidades)

Cada carpeta en `skills/<slug>/` es una habilidad:

| Archivo | Uso |
| --- | --- |
| `skill.json` | Nombre, descripción, disparadores, tipo de exportación y preguntas de la guía paso a paso |
| `instructions.md` | Flujo de trabajo del especialista |
| `references/*.md` | Material de apoyo (por ejemplo, catálogos de matriz de riesgo) |

Como en las Skills de Anthropic, el manifiesto siempre está disponible para el selector y el
enrutador, pero las instrucciones completas solo entran al prompt cuando la skill está activa.
Para agregar una skill nueva basta con crear la carpeta; no hay que tocar PHP.

Skills incluidas: manual de cumplimiento, matriz de riesgo, informe de cumplimiento,
metodología, plan anual, operación inusual, debida diligencia y revisión documental.

### Proyectos

Equivalentes a los Projects de Claude o ChatGPT. Cada proyecto tiene:

- **Instrucciones**: contexto del cliente o caso (empresa, sector, organismo de control, formato
  preferido). Se aplican en todos sus chats y no pueden ampliar el alcance de la IA.
- **Conocimiento**: archivos (PDF, DOCX, TXT…) que la IA consulta en todos sus chats.
- **Chats**: todas las conversaciones del proyecto.

### Biblioteca normativa

Documentos globales (leyes, reglamentos, resoluciones UAFE, guías GAFI) que un administrador
carga una vez y que todas las conversaciones consultan y citan como `[Fuente n]`.

## Estructura

```text
index.php                 Router: páginas, formularios y endpoints JSON
worker_ai_jobs.php        Worker de cron (usa el mismo orquestador)
includes/bootstrap.php    Entorno, base de datos y utilidades
includes/schema.php       Esquema autoaplicable (MySQL y MariaDB)
includes/repo.php         Datos: usuarios, proyectos, chats, documentos, cola
includes/ai/pipeline.php  Orquestador de agentes
includes/ai/guard.php     Triaje y revisor (guardrails)
includes/ai/rag.php       Ingesta, extracción PDF/DOCX, fragmentación y recuperación
includes/ai/skills.php    Registro de skills
includes/ai/providers.php DeepSeek (mensajes con roles) e IA de Suilaft
views/                    Plantillas (login, app, parciales)
assets/app.css, app.js    Sistema de diseño e interacción
assets/login.css          Acceso con el mismo lenguaje visual que SUILAFT V2.0
includes/ai/memory.php    Memoria y compactación
skills/                   Habilidades
prompts/                  Prompt maestro y política de alcance
.claude/skills/apple-design  Skill de diseño (Apple HIG) para Claude Code
```

## Documentación

`docs/guia/Guia_SUILAFT_AI_Arquitectura_y_Uso.pdf` — guía de arquitectura y uso (14 páginas) con el
diagnóstico previo, la cadena de agentes, RAG, memoria, habilidades, proyectos y la guía para el
oficial de cumplimiento. La fuente editable es el `.html` junto al PDF.

## Instalación

1. Crea el `.env` a partir de `.env.example` y completa la base de datos y las claves.
2. Importa `database.sql` (roles, planes y super admin inicial). El resto de tablas y columnas
   (proyectos, documentos, cola, skills, RAG) las crea la aplicación en el primer acceso, así
   que ya no hace falta ejecutar las migraciones a mano. Funciona en MySQL y en MariaDB.
3. Sube también las carpetas `includes/`, `views/`, `assets/`, `skills/` y `prompts/`.
4. Opcional (recomendado para respuestas largas), cron cada minuto:

```bash
* * * * * /usr/bin/php /ruta/a/suilaft_ai/worker_ai_jobs.php >/dev/null 2>&1
```

Sin cron, el navegador procesa la cola con `index.php?action=process_job`. Si solo puedes usar
URL: `worker_ai_jobs.php?token=WORKER_TOKEN`. Para diagnóstico: `&check=1` (estado de la cola,
skills, índice FULLTEXT, triaje) o `&debug=1` (traza paso a paso de los agentes).

Para desarrollo local:

```bash
php -S localhost:3000
```

### Proveedores de IA

Si `DEEPSEEK_API_KEY` está configurado, DeepSeek responde primero y la IA de Suilaft queda como
respaldo. Si falta, la app usa solo Suilaft (sin clasificador LLM en el triaje: quedan las
heurísticas y el revisor). Las respuestas quedan registradas en `api_requests.provider`.

### Usuario inicial

```text
Usuario: deimac/98-
Clave: Sui5656
Correo: consultegcorp@gmail.com
```

Cámbiale la clave después del primer ingreso.
