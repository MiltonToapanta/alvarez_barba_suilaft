<?php
/** @var array $page */
/** @var string $context chat|project */
$attachments = $page['attachments'] ?? [];
$placeholder = $context === 'project'
    ? 'Inicia un chat en este proyecto…'
    : 'Pregunta sobre PLA/FT, KYC, alertas, matrices o UAFE…';
?>
<form class="composer" id="composer" data-context="<?php echo h($context); ?>" novalidate>
  <div class="composer-tray" id="attachTray" <?php echo $attachments ? '' : 'hidden'; ?>>
    <?php foreach ($attachments as $document): ?>
      <span class="file-chip <?php echo (string) $document['extraction_status'] === 'ready' ? '' : 'is-warning'; ?>" title="<?php echo h($document['original_name']); ?>">
        <?php echo icon('doc'); ?>
        <span class="file-chip-name"><?php echo h($document['original_name']); ?></span>
        <span class="file-chip-meta"><?php echo h(file_kind_label((string) $document['extension'])); ?> · <?php echo h(document_status_label($document)); ?></span>
      </span>
    <?php endforeach; ?>
  </div>

  <div class="menu slash-menu" id="slashMenu" role="listbox" aria-label="Comandos" hidden>
    <button class="menu-item" type="button" role="option" data-slash="/compact"><?php echo icon('memory'); ?>
      <span class="menu-item-text"><strong>/compact</strong><small>Resume la conversación en la memoria del chat. Opcional: añade un enfoque.</small></span>
    </button>
    <button class="menu-item" type="button" role="option" data-slash="/memoria"><?php echo icon('doc'); ?>
      <span class="menu-item-text"><strong>/memoria</strong><small>Ver, corregir o descargar la memoria de este chat</small></span>
    </button>
  </div>

  <label class="sr-only" for="prompt">Mensaje</label>
  <textarea id="prompt" name="prompt" rows="1" placeholder="<?php echo h($placeholder); ?>" autocomplete="off"></textarea>

  <div class="composer-bar">
    <div class="composer-left">
      <div class="popover-anchor">
        <button class="icon-btn" type="button" id="plusButton" aria-label="Adjuntar o exportar" aria-haspopup="menu" aria-expanded="false" title="Adjuntar archivo o exportar">
          <?php echo icon('plus'); ?>
        </button>
        <div class="menu menu-up" id="plusMenu" role="menu" hidden>
          <button class="menu-item" type="button" role="menuitem" id="attachAction">
            <?php echo icon('clip'); ?>
            <span class="menu-item-text"><strong>Adjuntar archivo</strong><small>PDF, Word, TXT, CSV o JSON</small></span>
          </button>
          <?php if ($context === 'chat'): ?>
            <button class="menu-item" type="button" role="menuitem" id="exportAction">
              <?php echo icon('download'); ?>
              <span class="menu-item-text"><strong>Exportar última respuesta</strong><small>Manual, informe, plan, metodología o matriz</small></span>
            </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="popover-anchor">
        <button class="skill-button" type="button" id="skillButton" aria-haspopup="menu" aria-expanded="false" title="Elegir una habilidad">
          <?php echo icon('sparkle'); ?>
          <span id="skillButtonLabel">Habilidades</span>
          <?php echo icon('chevron_down', 'skill-chevron'); ?>
        </button>
        <div class="menu menu-up menu-wide" id="skillMenu" role="menu" hidden></div>
      </div>

      <button class="chip-btn" type="button" id="guidedButton" hidden>
        <?php echo icon('guide'); ?><span>Guía paso a paso</span>
      </button>
    </div>

    <div class="composer-right">
      <button class="icon-btn" type="button" id="micButton" aria-label="Dictar por voz" title="Dictar por voz">
        <?php echo icon('mic'); ?>
      </button>
      <button class="send-btn" type="submit" id="sendButton" aria-label="Enviar mensaje" title="Enviar (Enter)" disabled>
        <?php echo icon('send'); ?>
      </button>
    </div>
  </div>
  <input type="file" id="fileInput" accept=".pdf,.docx,.txt,.md,.csv,.json,.html,.htm,.xml,.log" hidden>
</form>
