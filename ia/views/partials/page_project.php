<?php
/** @var array $page */
$project = $page['project'];
$chats = $page['projectChats'];
$documents = $page['projectDocuments'];
$instructions = trim((string) ($project['instructions'] ?? ''));
?>
<section class="page page-project" aria-labelledby="projectHeading">
  <div class="project-layout">
    <div class="project-main">
      <header class="project-head">
        <?php echo project_icon($project, 'project-head-icon'); ?>
        <div class="project-head-text">
          <h2 class="page-title" id="projectHeading"><?php echo h($project['name']); ?></h2>
          <?php if (!empty($project['description'])): ?>
            <p class="page-lead"><?php echo h($project['description']); ?></p>
          <?php endif; ?>
        </div>
        <div class="popover-anchor">
          <button class="icon-btn" type="button" id="projectMenuButton" aria-label="Opciones del proyecto" aria-haspopup="menu" aria-expanded="false"><?php echo icon('more'); ?></button>
          <div class="menu menu-end" id="projectMenu" role="menu" hidden>
            <button class="menu-item" type="button" role="menuitem" data-pin-project="<?php echo (int) $project['id']; ?>"><?php echo icon('pin'); ?><span><?php echo !empty($project['pinned_at']) ? 'Desfijar del sidebar' : 'Fijar en el sidebar'; ?></span></button>
            <button class="menu-item" type="button" role="menuitem" data-open-sheet="projectEditSheet"><?php echo icon('pencil'); ?><span>Editar nombre, ícono y color</span></button>
            <button class="menu-item menu-item-danger" type="button" role="menuitem" data-open-sheet="projectArchiveSheet"><?php echo icon('trash'); ?><span>Archivar proyecto</span></button>
          </div>
        </div>
      </header>

      <?php render_partial('composer', ['page' => $page, 'context' => 'project']); ?>

      <section class="section" aria-labelledby="projectChatsHeading">
        <h3 class="section-title" id="projectChatsHeading">Chats del proyecto</h3>
        <?php if ($chats): ?>
          <ul class="list-rows">
            <?php foreach ($chats as $chat): ?>
              <li class="list-row" data-chat-row="<?php echo (int) $chat['id']; ?>">
                <a class="list-row-link" href="index.php?c=<?php echo (int) $chat['id']; ?>">
                  <?php echo icon('chat', 'list-row-icon'); ?>
                  <span class="list-row-text">
                    <span class="list-row-title" data-chat-title-text><?php echo h($chat['title']); ?></span>
                    <span class="list-row-sub">
                      <?php echo h(relative_time((string) $chat['updated_at'])); ?>
                      <?php if (!empty($chat['skill_slug']) && find_skill((string) $chat['skill_slug'])): ?>
                        · <?php echo h(find_skill((string) $chat['skill_slug'])['name']); ?>
                      <?php endif; ?>
                    </span>
                  </span>
                </a>
                <button class="icon-btn icon-btn-sm" type="button" data-chat-menu="<?php echo (int) $chat['id']; ?>" data-chat-title="<?php echo h($chat['title']); ?>" data-chat-project="<?php echo (int) $project['id']; ?>" aria-label="Opciones de <?php echo h($chat['title']); ?>" aria-haspopup="menu"><?php echo icon('more'); ?></button>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="empty-inline">Aún no hay chats. Escribe arriba para iniciar el primero.</p>
        <?php endif; ?>
      </section>
    </div>

    <aside class="inspector" aria-label="Contexto del proyecto">
      <section class="panel" aria-labelledby="instructionsHeading">
        <header class="panel-head">
          <h3 class="panel-title" id="instructionsHeading">Instrucciones</h3>
          <button class="btn btn-plain btn-sm" type="button" data-open-sheet="instructionsSheet"><?php echo $instructions !== '' ? 'Editar' : 'Agregar'; ?></button>
        </header>
        <div class="panel-lock"><?php echo icon('lock'); ?><span>Instrucción base de cumplimiento activa y bloqueada</span></div>
        <?php if ($instructions !== ''): ?>
          <p class="panel-text clamp-6"><?php echo nl2br(h($instructions)); ?></p>
        <?php else: ?>
          <p class="panel-hint">Agrega contexto adicional: empresa, actividad, organismo de control y cómo quieres las respuestas.</p>
        <?php endif; ?>
      </section>

      <?php $projectMemory = trim((string) ($project['memory_md'] ?? '')); ?>
      <section class="panel" aria-labelledby="memoryHeading">
        <header class="panel-head">
          <h3 class="panel-title" id="memoryHeading">Memoria</h3>
          <button class="btn btn-plain btn-sm" type="button" data-open-sheet="projectMemorySheet"><?php echo $projectMemory !== '' ? 'Ver' : 'Agregar'; ?></button>
        </header>
        <?php if ($projectMemory !== ''): ?>
          <p class="panel-text clamp-6"><?php echo nl2br(h(limit_text(preg_replace('/^#+\s*/m', '', $projectMemory) ?? $projectMemory, 600))); ?></p>
        <?php else: ?>
          <p class="panel-hint">La IA consolida aquí los hechos duraderos de los chats del proyecto (entidad, criterios, decisiones) al compactarlos.</p>
        <?php endif; ?>
      </section>

      <section class="panel" aria-labelledby="knowledgeHeading">
        <header class="panel-head">
          <h3 class="panel-title" id="knowledgeHeading">Conocimiento</h3>
          <button class="btn btn-plain btn-sm" type="button" id="knowledgeUpload" data-upload-target="project" data-project-id="<?php echo (int) $project['id']; ?>">
            <?php echo icon('upload'); ?><span>Subir</span>
          </button>
        </header>
        <div class="dropzone" data-dropzone="project" data-project-id="<?php echo (int) $project['id']; ?>">
          <?php if ($documents): ?>
            <ul class="file-list">
              <?php foreach ($documents as $document): ?>
                <li class="file-row" data-document-row="<?php echo (int) $document['id']; ?>">
                  <span class="file-icon"><?php echo icon('doc'); ?></span>
                  <span class="file-text">
                    <span class="file-name" title="<?php echo h($document['original_name']); ?>"><?php echo h($document['original_name']); ?></span>
                    <span class="file-meta">
                      <?php echo h(file_kind_label((string) $document['extension'])); ?> · <?php echo h(format_bytes((int) $document['file_size'])); ?> ·
                      <span class="<?php echo (string) $document['extraction_status'] === 'ready' ? 'text-ok' : 'text-warn'; ?>"><?php echo h(document_status_label($document)); ?></span>
                    </span>
                  </span>
                  <button class="icon-btn icon-btn-sm" type="button" data-delete-document="<?php echo (int) $document['id']; ?>" data-document-name="<?php echo h($document['original_name']); ?>" aria-label="Eliminar <?php echo h($document['original_name']); ?>"><?php echo icon('trash'); ?></button>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="panel-hint">Sube el manual vigente, la matriz, políticas o actas. Arrastra los archivos aquí o usa Subir.</p>
          <?php endif; ?>
        </div>
      </section>
    </aside>
  </div>
</section>
