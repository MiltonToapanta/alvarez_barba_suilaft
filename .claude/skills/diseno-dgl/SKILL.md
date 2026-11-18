---
name: diseno-dgl
description: Sistema visual y estructura de pantallas de SUILAFT DGL — layout compartido (views/partials/app_start.php + app_end.php), estilos assets/dgl.css, comportamiento assets/dgl.js, menú desde config/modulos.php, identidad DGL Constructora (tinta #26272b, naranja #c4561f, papel #f6f3ee, Inter + Cinzel, Bootstrap Icons). Úsala siempre que crees o modifiques una pantalla, un módulo del dashboard, un PDF o un correo, y cuando pidan "con el mismo diseño", "como el resto del sistema" o habilitar un módulo. El login (views/home.php) tiene diseño propio y no usa este layout.
---

# Diseño y estructura de pantallas DGL

## Piezas compartidas (no dupliques CSS ni JS en las vistas)

| Archivo | Rol |
|---|---|
| `config/modulos.php` | Módulos del menú y del dashboard: `clave => array(nombre, descripción, icono, url o null)`. Con `null` se muestra como "Próximamente". |
| `views/partials/app_start.php` | `<head>`, barra superior (usuario y Salir), menú lateral, migas y apertura de `<main>`. |
| `views/partials/app_end.php` | Cierre y carga de `assets/dgl.js`. |
| `assets/dgl.css` | Variables de color y todos los componentes. |
| `assets/dgl.js` | Menú lateral, menús por fila, diálogos, copiar, filtro de tablas, zonas de carga, índice del formulario y prevención de doble envío. |

**Habilitar un módulo** = poner su URL en `config/modulos.php`. El menú y el dashboard se actualizan solos. No cambies las claves: las usan los permisos y la auditoría.

## Esqueleto de una vista nueva

```php
<?php
$pageTitle = 'Auditoría — SUILAFT DGL';
$activeNav = 'auditoria';                       // clave de config/modulos.php
$crumbs = array(array('Inicio','index.php'), array('Auditoría', null));
// $layoutPublic = true;                        // solo para páginas sin sesión (sin menú)
require __DIR__ . '/partials/app_start.php';
?>
<div class="page-head"><div><div class="eyebrow">Cumplimiento</div><h1>Auditoría</h1><p>Descripción breve.</p></div>
  <div class="page-actions"><a class="btn btn-primary" href="#"><i class="bi bi-plus-lg" aria-hidden="true"></i> Acción</a></div></div>
<!-- contenido -->
<?php require __DIR__ . '/partials/app_end.php'; ?>
```

Las variables del layout usan el prefijo `$__`. No uses ese prefijo en tus vistas. Antes de cargar el layout, el punto de entrada debe definir `e()` y `$_SESSION['csrf_token']`.

## Componentes (clases de `assets/dgl.css`)

- **Botones**: `btn btn-primary` (oscuro con borde naranja), `btn-accent`, `btn-ghost`, `btn-danger`, `btn-sm`. Iconos con `<i class="bi ..." aria-hidden="true">`.
- **Estructura**: `page-head`, `eyebrow`, `code-tag`, `section-title`, `panel` > `panel-head` / `panel-body`, `grid grid-cards`, `tile` (+ `tile-icon`, `tile-foot`, `is-pending`), `hero`.
- **Indicadores**: `stats` > `stat` (`stat-label`, `stat-value`, `stat-note`).
- **Avisos**: `alert` (`alert-ok`, `alert-err`), `hint`, `legal`, `badge` (`badge-ok`), `empty`.
- **Tablas**: `toolbar` + `search` + `count`, `table-wrap` > `table`. Añade `table-cards` y `data-label="..."` en cada `td` para que en móvil se vean como tarjetas (la primera celda es el título y la última, las acciones). Filtro local: `<input data-filter="idTbody">` + `<span data-count="idTbody">` + `<div data-empty="idTbody" hidden>`.
- **Acciones por fila**: `<details class="menu"><summary class="btn btn-ghost btn-sm">…</summary><div class="menu-list">…</div></details>`.
- **Diálogos**: `<dialog id="x">` con `dlg-head` / `dlg-body` / `dlg-foot`. Se abre con un botón `data-dialog="x"`. Los atributos `data-fill-campo="valor"` copian el valor a `[data-field="campo"]` dentro del diálogo, y `data-close` lo cierra. Úsalos en lugar de `confirm()`.
- **Formularios**: `fields` (2 columnas; `field wide` ocupa todo el ancho), `money` (antepone `$`), `fsection` > `fsection-head` (`fsection-num`) / `fsection-body`, `form-layout` con `form-index` (resalta la sección visible), `savebar` fija abajo, `totals` > `total`. `<form data-once>` + `data-busy="Guardando…"` en el botón evita el doble envío.
- **Archivos**: `<label class="drop">` con un `<input type=file>` dentro y `drop-text > strong`; `file-list`.
- **Copiar**: `<button data-copy="idInput"><span>Copiar</span></button>`.

## Identidad

```css
--ink:#26272b; --orange:#c4561f; --orange-d:#a84b20; --paper:#f6f3ee; --line:#ddd6cc; --muted:#777169
```

Fuentes Inter (texto) y Cinzel (solo marca y saludo). Bootstrap Icons `1.11.3` (jsdelivr, versión fija). No introduzcas otro framework CSS ni otra paleta. Si cambias `assets/dgl.css` o `dgl.js`, sube `$__assetV` en `app_start.php` para invalidar la caché.

PDF (FPDF): tinta `(38,39,43)`, naranja `(196,86,31)`, línea `(224,218,210)`, texto con `iconv('UTF-8','windows-1252//TRANSLIT')`, base en `models/PdfKyc.php`. Correo: `models/EmailKyc.php`.

## Esqueleto de un punto de entrada (`modulo.php`)

```php
<?php
session_set_cookie_params(array('httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax'));session_start();
if(empty($_SESSION['user'])){header('Location: index.php');exit;}
// permiso: Acceso::exigir('clave') si existe; si no, el in_array de roles de kyc.php
require __DIR__.'/models/Database.php';
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
try{ $db=Database::connect(); /* POST: CSRF → validar → acción → auditar → flash → redirect (PRG) */ }
catch(Throwable $ex){error_log('MODULO: '.$ex->getMessage());http_response_code(503);exit('El módulo no está disponible temporalmente.');}
require __DIR__.'/views/modulo.php';
```

PHP 7.4: sin `match`, `enum`, `readonly`, argumentos con nombre ni `str_contains`. `e()` en toda salida. Consultas preparadas.

## Verificación visual

Haz capturas con Playwright a **1440 px** y a **390 px**. En ambos anchos, `document.documentElement.scrollWidth` debe ser igual al ancho de la ventana, sin errores de consola. Comprueba también el menú lateral en móvil (espera unos 400 ms a que termine la animación antes de capturar), los diálogos, el foco visible con el teclado y que no aparezcan `Warning` de PHP en el HTML.
