<?php
/** @var array $page */
$project = $page['project'];
$token = csrf_token();

/**
 * Selector de icono y color del proyecto (radio buttons accesibles).
 */
$appearancePicker = function (string $prefix, string $icon, string $color): void {
    $icon = valid_project_icon($icon);
    $color = valid_project_color($color);
    ?>
    <div class="field appearance-picker" data-appearance>
      <span class="field-label">Ícono y color</span>
      <div class="appearance-preview" aria-hidden="true">
        <span class="pc pc-<?php echo h($color); ?>" data-preview><?php echo icon($icon); ?></span>
      </div>
      <div class="swatches" role="radiogroup" aria-label="Color del proyecto">
        <?php foreach (project_color_options() as $value => $label): ?>
          <label class="swatch swatch-<?php echo h($value); ?>" title="<?php echo h($label); ?>">
            <input type="radio" name="color" value="<?php echo h($value); ?>" <?php echo $value === $color ? 'checked' : ''; ?> aria-label="<?php echo h($label); ?>">
          </label>
        <?php endforeach; ?>
      </div>
      <div class="icon-choices" role="radiogroup" aria-label="Ícono del proyecto">
        <?php foreach (project_icon_options() as $value): ?>
          <label class="icon-choice" title="<?php echo h($value); ?>">
            <input type="radio" name="icon" value="<?php echo h($value); ?>" <?php echo $value === $icon ? 'checked' : ''; ?> aria-label="<?php echo h($value); ?>">
            <?php echo icon($value); ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
};
?>
<!-- Nuevo proyecto -->
<dialog class="sheet" id="projectSheet" aria-labelledby="projectSheetTitle">
  <form method="post" action="index.php?action=create_project" class="sheet-form">
    <input type="hidden" name="_token" value="<?php echo h($token); ?>">
    <header class="sheet-head">
      <h2 id="projectSheetTitle">Nuevo proyecto</h2>
      <p>Agrupa chats, instrucciones y archivos de una empresa, caso o auditoría.</p>
    </header>
    <div class="sheet-body">
      <div class="field">
        <label for="newProjectName">Nombre</label>
        <input id="newProjectName" name="name" type="text" maxlength="140" required placeholder="Ej. Inmobiliaria Los Andes · Auditoría 2026">
      </div>
      <div class="field">
        <label for="newProjectDescription">Descripción <span class="optional">opcional</span></label>
        <input id="newProjectDescription" name="description" type="text" maxlength="255" placeholder="Para qué es este proyecto">
      </div>
      <?php $appearancePicker('new', 'folder', 'blue'); ?>
      <?php echo base_instruction_block(true); ?>
      <div class="field">
        <label for="newProjectInstructions">Instrucciones adicionales <span class="optional">opcional</span></label>
        <textarea id="newProjectInstructions" name="instructions" rows="4" maxlength="8000" placeholder="Ej. Sociedad anónima del sector inmobiliario, sujeto obligado ante la UAFE, controlada por la Superintendencia de Compañías. Responde con tablas y marca los datos pendientes."></textarea>
        <?php echo instructions_warning(); ?>
      </div>
    </div>
    <footer class="sheet-foot">
      <button class="btn btn-secondary" type="button" data-close-sheet>Cancelar</button>
      <button class="btn btn-primary" type="submit">Crear proyecto</button>
    </footer>
  </form>
</dialog>

<?php if ($project): ?>
<!-- Editar proyecto -->
<dialog class="sheet" id="projectEditSheet" aria-labelledby="projectEditTitle">
  <form method="post" action="index.php?action=update_project" class="sheet-form">
    <input type="hidden" name="_token" value="<?php echo h($token); ?>">
    <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
    <input type="hidden" name="instructions" value="<?php echo h((string) $project['instructions']); ?>">
    <header class="sheet-head">
      <h2 id="projectEditTitle">Editar proyecto</h2>
    </header>
    <div class="sheet-body">
      <div class="field">
        <label for="editProjectName">Nombre</label>
        <input id="editProjectName" name="name" type="text" maxlength="140" required value="<?php echo h($project['name']); ?>">
      </div>
      <div class="field">
        <label for="editProjectDescription">Descripción <span class="optional">opcional</span></label>
        <input id="editProjectDescription" name="description" type="text" maxlength="255" value="<?php echo h((string) $project['description']); ?>">
      </div>
      <?php $appearancePicker('edit', (string) ($project['icon'] ?? 'folder'), (string) ($project['color'] ?? 'gray')); ?>
    </div>
    <footer class="sheet-foot">
      <button class="btn btn-secondary" type="button" data-close-sheet>Cancelar</button>
      <button class="btn btn-primary" type="submit">Guardar cambios</button>
    </footer>
  </form>
</dialog>

<!-- Instrucciones del proyecto -->
<dialog class="sheet sheet-wide" id="instructionsSheet" aria-labelledby="instructionsSheetTitle">
  <form method="post" action="index.php?action=update_project" class="sheet-form">
    <input type="hidden" name="_token" value="<?php echo h($token); ?>">
    <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
    <input type="hidden" name="name" value="<?php echo h($project['name']); ?>">
    <input type="hidden" name="description" value="<?php echo h((string) $project['description']); ?>">
    <header class="sheet-head">
      <h2 id="instructionsSheetTitle">Instrucciones del proyecto</h2>
      <p>Contexto que la IA aplica en cada chat de <?php echo h($project['name']); ?>.</p>
    </header>
    <div class="sheet-body">
      <?php echo base_instruction_block(); ?>
      <div class="field">
        <label for="projectInstructions">Instrucciones adicionales <span class="optional">opcional</span></label>
        <textarea id="projectInstructions" name="instructions" rows="8" maxlength="8000" placeholder="Empresa, RUC, actividad económica, organismo de control, número de empleados, tipos de clientes, formato preferido de las respuestas…"><?php echo h((string) $project['instructions']); ?></textarea>
        <?php echo instructions_warning(); ?>
      </div>
    </div>
    <footer class="sheet-foot">
      <button class="btn btn-secondary" type="button" data-close-sheet>Cancelar</button>
      <button class="btn btn-primary" type="submit">Guardar instrucciones</button>
    </footer>
  </form>
</dialog>

<!-- Archivar proyecto -->
<dialog class="sheet sheet-alert" id="projectArchiveSheet" aria-labelledby="archiveTitle" aria-describedby="archiveText">
  <form method="post" action="index.php?action=archive_project" class="sheet-form">
    <input type="hidden" name="_token" value="<?php echo h($token); ?>">
    <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
    <header class="sheet-head">
      <h2 id="archiveTitle">¿Archivar “<?php echo h($project['name']); ?>”?</h2>
      <p id="archiveText">El proyecto deja de aparecer en la lista. Sus chats pasan a Recientes y sus archivos dejan de usarse como contexto.</p>
    </header>
    <footer class="sheet-foot">
      <button class="btn btn-secondary" type="button" data-close-sheet autofocus>Cancelar</button>
      <button class="btn btn-danger" type="submit">Archivar</button>
    </footer>
  </form>
</dialog>
<?php endif; ?>

<!-- Exportar -->
<dialog class="sheet" id="exportSheet" aria-labelledby="exportTitle">
  <form class="sheet-form" id="exportForm" method="dialog">
    <header class="sheet-head">
      <h2 id="exportTitle">Exportar documento</h2>
      <p>Descarga la última respuesta de la IA con portada y formato oficial.</p>
    </header>
    <div class="sheet-body">
      <div class="field">
        <label for="exportDocType">Tipo de documento</label>
        <select id="exportDocType">
          <option value="manual">Manual de cumplimiento</option>
          <option value="informe">Informe de cumplimiento</option>
          <option value="plan">Plan de trabajo anual</option>
          <option value="metodologia">Metodología</option>
          <option value="matriz">Matriz de riesgo (solo PDF)</option>
        </select>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="exportCompany">Empresa</label>
          <input id="exportCompany" type="text" placeholder="[NOMBRE DE LA EMPRESA]" value="<?php echo h((string) ($project['name'] ?? '')); ?>">
        </div>
        <div class="field">
          <label for="exportRuc">RUC</label>
          <input id="exportRuc" type="text" inputmode="numeric" placeholder="13 dígitos">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="exportCode">Código del documento</label>
          <input id="exportCode" type="text" placeholder="EMP-PLA-001">
        </div>
        <div class="field">
          <label for="exportVersion">Versión</label>
          <input id="exportVersion" type="text" placeholder="1.0">
        </div>
      </div>
      <div class="field">
        <label for="exportAuthor">Elaborado por</label>
        <input id="exportAuthor" type="text" placeholder="Oficial de cumplimiento" value="<?php echo h((string) $page['user']['name']); ?>">
      </div>
    </div>
    <footer class="sheet-foot">
      <button class="btn btn-secondary" type="button" data-close-sheet>Cancelar</button>
      <span class="sheet-foot-spacer"></span>
      <button class="btn btn-secondary" type="button" id="exportDocx"><?php echo icon('download'); ?><span>Word</span></button>
      <button class="btn btn-primary" type="button" id="exportPdf"><?php echo icon('download'); ?><span>PDF</span></button>
    </footer>
  </form>
</dialog>

<!-- Guia paso a paso -->
<dialog class="sheet" id="guidedSheet" aria-labelledby="guidedTitle">
  <form class="sheet-form" id="guidedForm" method="dialog">
    <header class="sheet-head">
      <p class="sheet-eyebrow" id="guidedSkill">Documento</p>
      <h2 id="guidedTitle">Guía paso a paso</h2>
      <div class="progress" aria-hidden="true"><span class="progress-bar" id="guidedProgress"></span></div>
    </header>
    <div class="sheet-body">
      <p class="guided-step" id="guidedStep">Paso 1 de 1</p>
      <div class="field">
        <label id="guidedLabel" for="guidedInput">Pregunta</label>
        <div id="guidedInputWrap"></div>
        <p class="field-hint" id="guidedHint"></p>
      </div>
    </div>
    <footer class="sheet-foot">
      <button class="btn btn-secondary" type="button" data-close-sheet>Cancelar</button>
      <span class="sheet-foot-spacer"></span>
      <button class="btn btn-secondary" type="button" id="guidedBack">Atrás</button>
      <button class="btn btn-primary" type="submit" id="guidedNext">Siguiente</button>
    </footer>
  </form>
</dialog>

<!-- Renombrar chat -->
<dialog class="sheet sheet-compact" id="renameSheet" aria-labelledby="renameTitle">
  <form class="sheet-form" id="renameForm" method="dialog">
    <header class="sheet-head"><h2 id="renameTitle">Renombrar chat</h2></header>
    <div class="sheet-body">
      <div class="field">
        <label class="sr-only" for="renameInput">Nombre del chat</label>
        <input id="renameInput" type="text" maxlength="80" required>
      </div>
    </div>
    <footer class="sheet-foot">
      <button class="btn btn-secondary" type="button" data-close-sheet>Cancelar</button>
      <button class="btn btn-primary" type="submit">Guardar</button>
    </footer>
  </form>
</dialog>

<!-- Mover chat a un proyecto -->
<dialog class="sheet sheet-compact" id="moveSheet" aria-labelledby="moveTitle">
  <form class="sheet-form" id="moveForm" method="dialog">
    <header class="sheet-head">
      <h2 id="moveTitle">Mover a un proyecto</h2>
      <p>El chat usará las instrucciones y archivos del proyecto elegido.</p>
    </header>
    <div class="sheet-body">
      <div class="field">
        <label for="moveSelect">Proyecto</label>
        <select id="moveSelect">
          <option value="0">Sin proyecto</option>
          <?php foreach ($page['projects'] as $option): ?>
            <option value="<?php echo (int) $option['id']; ?>"><?php echo h($option['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <footer class="sheet-foot">
      <button class="btn btn-secondary" type="button" data-close-sheet>Cancelar</button>
      <button class="btn btn-primary" type="submit">Mover</button>
    </footer>
  </form>
</dialog>

<!-- Confirmacion generica para acciones destructivas -->
<dialog class="sheet sheet-alert" id="confirmSheet" aria-labelledby="confirmTitle" aria-describedby="confirmText">
  <form class="sheet-form" method="dialog">
    <header class="sheet-head">
      <h2 id="confirmTitle">¿Confirmas?</h2>
      <p id="confirmText"></p>
    </header>
    <footer class="sheet-foot">
      <button class="btn btn-secondary" type="button" data-close-sheet autofocus>Cancelar</button>
      <button class="btn btn-danger" type="button" id="confirmAccept">Eliminar</button>
    </footer>
  </form>
</dialog>

<!-- Memoria del chat -->
<dialog class="sheet sheet-wide" id="memorySheet" aria-labelledby="memoryTitle">
  <form class="sheet-form" id="memoryForm" method="dialog">
    <header class="sheet-head">
      <h2 id="memoryTitle">Memoria del chat</h2>
      <p id="memoryMeta">Resumen de los mensajes compactados. La IA la lee en cada respuesta en lugar del historial antiguo.</p>
    </header>
    <div class="sheet-body">
      <div class="field">
        <label class="sr-only" for="memoryText">Memoria</label>
        <textarea id="memoryText" class="memory-text" rows="14" maxlength="20000" placeholder="Aún no hay memoria. Escribe /compact en el chat para crearla, o se generará sola cuando la conversación crezca."></textarea>
        <p class="field-hint">Puedes corregir datos: la IA usará tu versión. Se compacta sola cada ~16 mensajes.</p>
      </div>
    </div>
    <footer class="sheet-foot">
      <a class="btn btn-secondary" id="memoryDownload" href="#" download><?php echo icon('download'); ?><span>Descargar .md</span></a>
      <button class="btn btn-secondary" type="button" id="memoryCompact">Compactar ahora</button>
      <span class="sheet-foot-spacer"></span>
      <button class="btn btn-secondary" type="button" data-close-sheet>Cancelar</button>
      <button class="btn btn-primary" type="submit">Guardar</button>
    </footer>
  </form>
</dialog>

<?php if ($project): ?>
<!-- Memoria del proyecto -->
<dialog class="sheet sheet-wide" id="projectMemorySheet" aria-labelledby="projectMemoryTitle">
  <form method="post" action="index.php?action=update_project_memory" class="sheet-form">
    <input type="hidden" name="_token" value="<?php echo h($token); ?>">
    <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
    <header class="sheet-head">
      <h2 id="projectMemoryTitle">Memoria del proyecto</h2>
      <p>Hechos consolidados de todos los chats de <?php echo h($project['name']); ?>. Se actualiza sola al compactar los chats.</p>
    </header>
    <div class="sheet-body">
      <div class="field">
        <label class="sr-only" for="projectMemoryText">Memoria del proyecto</label>
        <textarea id="projectMemoryText" class="memory-text" name="memory" rows="14" maxlength="20000" placeholder="Aún no hay memoria del proyecto."><?php echo h((string) ($project['memory_md'] ?? '')); ?></textarea>
      </div>
    </div>
    <footer class="sheet-foot">
      <a class="btn btn-secondary" href="index.php?action=memory_download&amp;p=<?php echo (int) $project['id']; ?>" download><?php echo icon('download'); ?><span>Descargar .md</span></a>
      <span class="sheet-foot-spacer"></span>
      <button class="btn btn-secondary" type="button" data-close-sheet>Cancelar</button>
      <button class="btn btn-primary" type="submit">Guardar memoria</button>
    </footer>
  </form>
</dialog>
<?php endif; ?>

<!-- Menu contextual de chats -->
<div class="menu menu-floating" id="chatMenu" role="menu" hidden>
  <button class="menu-item" type="button" role="menuitem" data-chat-op="pin"><?php echo icon('pin'); ?><span data-pin-label>Fijar</span></button>
  <button class="menu-item" type="button" role="menuitem" data-chat-op="rename"><?php echo icon('pencil'); ?><span>Renombrar</span></button>
  <button class="menu-item" type="button" role="menuitem" data-chat-op="move"><?php echo icon('move'); ?><span>Mover a proyecto</span></button>
  <button class="menu-item" type="button" role="menuitem" data-chat-op="memory"><?php echo icon('memory'); ?><span>Memoria</span></button>
  <div class="menu-sep" role="separator"></div>
  <button class="menu-item menu-item-danger" type="button" role="menuitem" data-chat-op="delete"><?php echo icon('trash'); ?><span>Eliminar</span></button>
</div>

<!-- Menu contextual de proyectos (sidebar) -->
<div class="menu menu-floating" id="projectRowMenu" role="menu" hidden>
  <button class="menu-item" type="button" role="menuitem" data-project-op="pin"><?php echo icon('pin'); ?><span data-pin-label>Fijar</span></button>
  <button class="menu-item" type="button" role="menuitem" data-project-op="edit"><?php echo icon('pencil'); ?><span>Editar ícono, nombre y color</span></button>
  <button class="menu-item" type="button" role="menuitem" data-project-op="open"><?php echo icon('folder'); ?><span>Abrir proyecto</span></button>
</div>

<input type="file" id="knowledgeInput" accept=".pdf,.docx,.txt,.md,.csv,.json,.html,.htm,.xml,.log" multiple hidden>
