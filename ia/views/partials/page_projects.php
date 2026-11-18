<?php
/** @var array $page */
$projects = $page['projects'];
$months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sept', 'oct', 'nov', 'dic'];
$shortDate = function (string $timestamp) use ($months): string {
    $time = strtotime($timestamp);
    if ($time === false) {
        return '';
    }
    $label = (int) date('j', $time) . ' ' . $months[(int) date('n', $time) - 1];
    return date('Y', $time) === date('Y') ? $label : $label . ' ' . date('Y', $time);
};
?>
<section class="page" aria-labelledby="projectsHeading">
  <div class="page-inner">
    <header class="page-head">
      <h2 class="page-title" id="projectsHeading">Proyectos</h2>
      <div class="page-actions">
        <?php if ($projects): ?>
          <div class="search-inline" id="projectSearchWrap">
            <label class="sr-only" for="projectSearch">Buscar proyectos</label>
            <input id="projectSearch" type="search" placeholder="Buscar proyectos" autocomplete="off" data-filter-target="#projectGrid">
            <button class="icon-btn" type="button" id="projectSearchToggle" aria-label="Buscar proyectos" aria-controls="projectSearch"><?php echo icon('search'); ?></button>
          </div>
        <?php endif; ?>
        <button class="btn btn-primary" type="button" data-open-sheet="projectSheet">Nuevo proyecto</button>
      </div>
    </header>

    <?php if ($projects): ?>
      <ul class="card-grid" id="projectGrid">
        <?php foreach ($projects as $project): ?>
          <li data-filter-text="<?php echo h(text_fold($project['name'] . ' ' . (string) $project['description'])); ?>">
            <a class="card project-card" href="index.php?p=<?php echo (int) $project['id']; ?>">
              <span class="project-card-title"><?php echo project_icon($project); ?><span class="project-card-name"><?php echo h($project['name']); ?></span></span>
              <span class="project-card-desc"><?php echo h($project['description'] ?: 'Sin descripción'); ?></span>
              <span class="project-card-meta">
                <span><?php echo h($shortDate((string) $project['updated_at'])); ?></span>
                <span aria-hidden="true">·</span>
                <span><?php echo (int) $project['chats_count']; ?> <?php echo (int) $project['chats_count'] === 1 ? 'chat' : 'chats'; ?></span>
                <span aria-hidden="true">·</span>
                <span><?php echo (int) $project['documents_count']; ?> <?php echo (int) $project['documents_count'] === 1 ? 'archivo' : 'archivos'; ?></span>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="empty-inline" id="projectGridEmpty" hidden>Ningún proyecto coincide con la búsqueda.</p>
    <?php else: ?>
      <div class="empty-state">
        <h3>Crea tu primer proyecto</h3>
        <p>Agrupa los chats de una empresa, caso o auditoría. Sube su manual vigente y su matriz para que la IA responda con ese contexto.</p>
        <button class="btn btn-primary" type="button" data-open-sheet="projectSheet">Nuevo proyecto</button>
      </div>
    <?php endif; ?>
  </div>
</section>
