<?php
/** @var array $page */
$user = $page['user'];
$currentConversationId = (int) ($page['conversation']['id'] ?? 0);
$currentProjectId = (int) ($page['project']['id'] ?? 0);

// Estructura estilo ChatGPT: Fijado · Proyectos · Chats (sueltos, sin proyecto).
$pinnedProjects = [];
$otherProjects = [];
foreach ($page['projects'] as $project) {
    if (!empty($project['pinned_at'])) {
        $pinnedProjects[] = $project;
    } else {
        $otherProjects[] = $project;
    }
}
$pinnedChats = [];
$looseChats = [];
foreach ($page['recent'] as $chat) {
    if (!empty($chat['pinned_at'])) {
        $pinnedChats[] = $chat;
    } elseif ((int) ($chat['project_id'] ?? 0) === 0 || empty($chat['project_name'])) {
        $looseChats[] = $chat;
    }
}
usort($pinnedProjects, function (array $a, array $b): int {
    return strcmp((string) $b['pinned_at'], (string) $a['pinned_at']);
});
usort($pinnedChats, function (array $a, array $b): int {
    return strcmp((string) $b['pinned_at'], (string) $a['pinned_at']);
});
$visibleProjects = array_slice($otherProjects, 0, 5);
$hiddenProjects = count($otherProjects) - count($visibleProjects);

$chatRow = function (array $chat, bool $pinned) use ($currentConversationId): void {
    $id = (int) $chat['id'];
    $active = $currentConversationId === $id;
    ?>
    <li class="side-item" data-chat-row="<?php echo $id; ?>" data-filter-text="<?php echo h(text_fold((string) $chat['title'])); ?>">
      <a class="side-row <?php echo $active ? 'is-active' : ''; ?>" href="index.php?c=<?php echo $id; ?>" <?php echo $active ? 'aria-current="page"' : ''; ?> <?php echo !empty($chat['project_name']) ? 'title="Proyecto: ' . h($chat['project_name']) . '"' : ''; ?>>
        <?php if ($pinned): ?><?php echo icon('chat', 'side-row-icon'); ?><?php endif; ?>
        <span class="side-row-title" data-chat-title-text><?php echo h($chat['title']); ?></span>
      </a>
      <button class="icon-btn icon-btn-sm side-more" type="button" data-chat-menu="<?php echo $id; ?>" data-chat-title="<?php echo h($chat['title']); ?>" data-chat-project="<?php echo (int) ($chat['project_id'] ?? 0); ?>" data-chat-pinned="<?php echo $pinned ? '1' : '0'; ?>" aria-label="Opciones de <?php echo h($chat['title']); ?>" aria-haspopup="menu">
        <?php echo icon('more'); ?>
      </button>
    </li>
    <?php
};

$projectRow = function (array $project, bool $pinned) use ($page, $currentProjectId): void {
    $id = (int) $project['id'];
    $active = $page['view'] === 'project' && $currentProjectId === $id;
    ?>
    <li class="side-item" data-project-row="<?php echo $id; ?>" data-filter-text="<?php echo h(text_fold((string) $project['name'])); ?>">
      <a class="side-row <?php echo $active ? 'is-active' : ''; ?>" href="index.php?p=<?php echo $id; ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>>
        <?php echo project_icon($project, 'side-row-icon'); ?>
        <span class="side-row-title"><?php echo h($project['name']); ?></span>
      </a>
      <button class="icon-btn icon-btn-sm side-more" type="button" data-project-menu="<?php echo $id; ?>" data-project-pinned="<?php echo $pinned ? '1' : '0'; ?>" aria-label="Opciones de <?php echo h($project['name']); ?>" aria-haspopup="menu">
        <?php echo icon('more'); ?>
      </button>
    </li>
    <?php
};
?>
<aside class="sidebar" id="sidebar" aria-label="Navegación">
  <div class="sidebar-head">
    <a class="brand" href="index.php" aria-label="SUILAFT AI, inicio">
      <span class="brand-word">SUILAFT <span>AI</span></span>
    </a>
    <div class="sidebar-head-actions">
      <button class="icon-btn" type="button" id="sidebarSearchToggle" aria-label="Buscar chats y proyectos" aria-controls="sidebarSearch" title="Buscar (Ctrl+K)"><?php echo icon('search'); ?></button>
      <button class="icon-btn" type="button" id="sidebarCollapse" aria-label="Ocultar la barra lateral" title="Ocultar barra lateral (Ctrl+\)"><?php echo icon('sidebar'); ?></button>
    </div>
  </div>

  <div class="sidebar-search" id="sidebarSearchWrap" hidden>
    <?php echo icon('search'); ?>
    <label class="sr-only" for="sidebarSearch">Buscar chats y proyectos</label>
    <input id="sidebarSearch" type="search" placeholder="Buscar chats y proyectos" autocomplete="off">
  </div>

  <nav class="sidebar-nav" aria-label="Principal">
    <a class="nav-item" href="index.php" id="newChatLink" title="Nuevo chat (Ctrl+Shift+O)">
      <?php echo icon('compose'); ?><span>Nuevo chat</span>
    </a>
    <a class="nav-item <?php echo nav_is($page, 'projects') ? 'is-active' : ''; ?>" href="index.php?view=projects" <?php echo nav_is($page, 'projects') ? 'aria-current="page"' : ''; ?>>
      <?php echo icon('folder'); ?><span>Proyectos</span>
    </a>
    <a class="nav-item <?php echo nav_is($page, 'library') ? 'is-active' : ''; ?>" href="index.php?view=library" <?php echo nav_is($page, 'library') ? 'aria-current="page"' : ''; ?>>
      <?php echo icon('library'); ?><span>Biblioteca normativa</span>
    </a>
  </nav>

  <div class="sidebar-scroll" id="sidebarScroll">
    <?php if ($pinnedProjects || $pinnedChats): ?>
      <section class="sidebar-group" aria-labelledby="sgPinned">
        <h2 class="sidebar-label" id="sgPinned">Fijado</h2>
        <ul class="sidebar-list">
          <?php foreach ($pinnedProjects as $project) { $projectRow($project, true); } ?>
          <?php foreach ($pinnedChats as $chat) { $chatRow($chat, true); } ?>
        </ul>
      </section>
    <?php endif; ?>

    <section class="sidebar-group" aria-labelledby="sgProjects">
      <h2 class="sidebar-label sidebar-label-action" id="sgProjects">
        <span>Proyectos</span>
        <button class="icon-btn icon-btn-sm" type="button" data-open-sheet="projectSheet" aria-label="Nuevo proyecto" title="Nuevo proyecto"><?php echo icon('plus'); ?></button>
      </h2>
      <ul class="sidebar-list">
        <?php foreach ($visibleProjects as $project) { $projectRow($project, false); } ?>
        <?php if (!$page['projects']): ?>
          <li>
            <button class="side-row side-row-muted" type="button" data-open-sheet="projectSheet">
              <?php echo icon('plus', 'side-row-icon'); ?><span class="side-row-title">Nuevo proyecto</span>
            </button>
          </li>
        <?php endif; ?>
      </ul>
      <?php if ($hiddenProjects > 0): ?>
        <a class="side-more-link" href="index.php?view=projects">Ver más</a>
      <?php endif; ?>
    </section>

    <section class="sidebar-group" aria-labelledby="sgRecent">
      <h2 class="sidebar-label" id="sgRecent">Chats</h2>
      <ul class="sidebar-list" id="recentList">
        <?php foreach ($looseChats as $chat) { $chatRow($chat, false); } ?>
      </ul>
      <?php if (!$looseChats): ?>
        <p class="sidebar-empty" id="recentEmpty">Tus conversaciones aparecerán aquí.</p>
      <?php endif; ?>
    </section>
    <p class="sidebar-empty" id="sidebarSearchEmpty" hidden>Sin resultados.</p>
  </div>

  <div class="sidebar-foot">
    <button class="account" type="button" id="accountButton" aria-haspopup="menu" aria-expanded="false">
      <span class="avatar" aria-hidden="true"><?php echo h(user_initials($user)); ?></span>
      <span class="account-text">
        <strong><?php echo h($user['name']); ?></strong>
        <span><?php echo h(($user['portal_rol'] ?? '') !== '' ? $user['portal_rol'] : 'Alvarez Barba S.A.'); ?></span>
      </span>
      <?php echo icon('chevron_down', 'account-chevron'); ?>
    </button>
    <div class="menu" id="accountMenu" role="menu" hidden>
      <div class="menu-caption"><?php echo h($user['email']); ?></div>
      <a class="menu-item" role="menuitem" href="/" target="_top"><?php echo icon('logout'); ?><span>Volver al portal</span></a>
    </div>
  </div>
</aside>
