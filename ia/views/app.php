<?php
/** @var array $data Datos de build_page(); tambien extraidos como variables. */
$page = $data;
$firstName = trim(explode(' ', trim((string) $user['name']))[0] ?? '');
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <title><?php echo h($title); ?> · SUILAFT AI · Alvarez Barba</title>
    <link rel="icon" type="image/png" href="<?php echo h(asset_url('assets/img/favicon-64.png')); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,500;0,8..60,600;1,8..60,400&display=swap">
    <link rel="preload" href="assets/fonts/lexend-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?php echo h(asset_url('assets/app.css')); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&display=swap">
    <link rel="stylesheet" href="<?php echo h(asset_url('assets/alvarez-barba.css')); ?>">
  </head>
  <body class="app-body view-<?php echo h($view); ?>">
    <a class="skip-link" href="#main">Saltar al contenido</a>
    <div class="shell" id="shell">
      <?php render_partial('sidebar', ['page' => $page]); ?>
      <div class="sidebar-scrim" id="sidebarScrim" hidden></div>

      <main class="main" id="main" tabindex="-1">
        <header class="toolbar">
          <button class="icon-btn" type="button" id="sidebarToggle" aria-label="Mostrar u ocultar la barra lateral" aria-controls="sidebar" aria-expanded="true" title="Barra lateral (Ctrl+\)">
            <?php echo icon('sidebar'); ?>
          </button>

          <nav class="crumbs" aria-label="Ubicación">
            <?php if ($view === 'chat' && $project): ?>
              <a href="index.php?p=<?php echo (int) $project['id']; ?>"><?php echo icon('folder'); ?><span><?php echo h($project['name']); ?></span></a>
              <?php echo icon('chevron_right', 'crumb-sep'); ?>
            <?php elseif ($view === 'project'): ?>
              <a href="index.php?view=projects"><span>Proyectos</span></a>
              <?php echo icon('chevron_right', 'crumb-sep'); ?>
            <?php endif; ?>
            <h1 class="crumb-current" id="pageTitle" <?php echo $view === 'chat' && !$conversation && !$project ? 'hidden' : ''; ?>><?php echo h($view === 'chat' && !$conversation ? ($project ? 'Nuevo chat' : 'Nuevo chat') : $title); ?></h1>
          </nav>

          <div class="toolbar-actions">
            <span class="status-pill" id="statusPill" role="status" aria-live="polite" hidden>
              <span class="status-dot" aria-hidden="true"></span><span id="statusText">Listo</span>
            </span>
            <?php if ($view === 'chat'): ?>
              <button class="icon-btn" type="button" id="voiceToggle" aria-pressed="false" aria-label="Leer respuestas en voz alta" title="Leer respuestas en voz alta">
                <span class="when-on"><?php echo icon('speaker'); ?></span>
                <span class="when-off"><?php echo icon('speaker_off'); ?></span>
              </button>
              <?php if ($conversation): ?>
                <button class="icon-btn" type="button" data-chat-menu="<?php echo (int) $conversation['id']; ?>" data-chat-title="<?php echo h($conversation['title']); ?>" data-chat-project="<?php echo (int) ($conversation['project_id'] ?? 0); ?>" aria-label="Opciones del chat" aria-haspopup="menu" title="Opciones del chat">
                  <?php echo icon('more'); ?>
                </button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </header>

        <?php if (!empty($flash)): ?>
          <div class="banner-wrap">
            <div class="banner banner-<?php echo h($flash['tone'] === 'error' ? 'error' : ($flash['tone'] === 'success' ? 'success' : 'info')); ?>" role="status">
              <?php echo icon($flash['tone'] === 'error' ? 'alert' : ($flash['tone'] === 'success' ? 'check' : 'info')); ?>
              <span><?php echo h($flash['message']); ?></span>
              <button class="icon-btn icon-btn-sm" type="button" data-dismiss-banner aria-label="Cerrar aviso"><?php echo icon('x'); ?></button>
            </div>
          </div>
        <?php endif; ?>

        <?php
        if ($view === 'projects') {
            render_partial('page_projects', ['page' => $page]);
        } elseif ($view === 'project') {
            render_partial('page_project', ['page' => $page]);
        } elseif ($view === 'library') {
            render_partial('page_library', ['page' => $page]);
        } else {
            render_partial('page_chat', ['page' => $page, 'firstName' => $firstName]);
        }
        ?>
      </main>
    </div>

    <?php render_partial('sheets', ['page' => $page]); ?>

    <div class="toast-region" id="toastRegion" aria-live="polite" aria-atomic="false"></div>

    <script type="application/json" id="appConfig"><?php echo json_encode(app_client_config($page), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
    <script src="<?php echo h(asset_url('assets/app.js')); ?>" defer></script>
  </body>
</html>
