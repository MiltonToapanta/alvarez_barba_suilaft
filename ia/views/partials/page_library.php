<?php
/** @var array $page */
$documents = $page['library'];
$isAdmin = $page['isAdmin'];
?>
<section class="page" aria-labelledby="libraryHeading">
  <div class="page-inner">
    <header class="page-head">
      <div>
        <h2 class="page-title" id="libraryHeading">Biblioteca normativa</h2>
        <p class="page-lead">Leyes, reglamentos, resoluciones de la UAFE y guías GAFI que la IA consulta en todas las conversaciones. Las respuestas citan estos documentos como [Fuente n] para que puedas verificar cada referencia.</p>
      </div>
      <?php if ($isAdmin): ?>
        <button class="btn btn-primary" type="button" data-upload-target="library">
          <?php echo icon('upload'); ?><span>Agregar documento</span>
        </button>
      <?php endif; ?>
    </header>

    <div class="dropzone dropzone-page" <?php echo $isAdmin ? 'data-dropzone="library"' : ''; ?>>
      <?php if ($documents): ?>
        <ul class="list-rows">
          <?php foreach ($documents as $document): ?>
            <li class="list-row" data-document-row="<?php echo (int) $document['id']; ?>">
              <div class="list-row-link">
                <span class="file-icon"><?php echo icon('doc'); ?></span>
                <span class="list-row-text">
                  <span class="list-row-title"><?php echo h($document['title']); ?></span>
                  <span class="list-row-sub">
                    <?php echo h(file_kind_label((string) $document['extension'])); ?> · <?php echo h(format_bytes((int) $document['file_size'])); ?> ·
                    <?php if ((string) $document['extraction_status'] === 'ready'): ?>
                      <span class="text-ok"><?php echo (int) $document['chunks_count']; ?> fragmentos indexados</span>
                    <?php else: ?>
                      <span class="text-warn" title="<?php echo h((string) $document['extraction_error']); ?>">Sin texto consultable</span>
                    <?php endif; ?>
                    · <?php echo h(relative_time((string) $document['created_at'])); ?>
                  </span>
                </span>
              </div>
              <?php if ($isAdmin): ?>
                <button class="icon-btn icon-btn-sm" type="button" data-delete-document="<?php echo (int) $document['id']; ?>" data-document-name="<?php echo h($document['title']); ?>" aria-label="Eliminar <?php echo h($document['title']); ?>"><?php echo icon('trash'); ?></button>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state">
          <span class="empty-icon"><?php echo icon('library'); ?></span>
          <h3>La biblioteca está vacía</h3>
          <?php if ($isAdmin): ?>
            <p>Carga la Ley Orgánica de Prevención de Lavado de Activos, su reglamento, las resoluciones de la UAFE y las 40 Recomendaciones del GAFI. Puedes arrastrar los archivos aquí.</p>
            <button class="btn btn-primary" type="button" data-upload-target="library"><?php echo icon('upload'); ?><span>Agregar documento</span></button>
          <?php else: ?>
            <p>Un administrador puede cargar aquí la normativa oficial para que las respuestas la citen.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
