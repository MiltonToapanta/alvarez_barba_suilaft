<?php
/** @var array $page */
/** @var string $firstName */
$messages = $page['messages'];
$project = $page['project'];
$hasThread = (bool) $messages;
$featured = array_values(array_filter(skill_registry(), function (array $skill): bool {
    return $skill['show_in_picker'];
}));

$name = $firstName !== '' && function_exists('mb_convert_case') ? mb_convert_case($firstName, MB_CASE_TITLE, 'UTF-8') : $firstName;
$hour = (int) date('G');
if ($project) {
    $greeting = 'Nuevo chat en ' . $project['name'];
} elseif ($hour < 5) {
    $greeting = $name !== '' ? '¿Trasnochando, ' . $name . '?' : '¿Trasnochando?';
} elseif ($hour < 12) {
    $greeting = 'Buenos días' . ($name !== '' ? ', ' . $name : '');
} elseif ($hour < 19) {
    $greeting = 'Buenas tardes' . ($name !== '' ? ', ' . $name : '');
} else {
    $greeting = 'Buenas noches' . ($name !== '' ? ', ' . $name : '');
}
?>
<section class="chat <?php echo $hasThread ? 'has-thread' : 'is-empty'; ?>" id="chatView" aria-label="Conversación">
  <div class="thread-scroll" id="threadScroll">
    <div class="thread" id="thread">
      <?php if (!$hasThread): ?>
        <div class="welcome" id="welcome">
          <h2 class="welcome-title"><?php echo h($greeting); ?></h2>
        </div>
      <?php endif; ?>

      <?php foreach ($messages as $message): ?>
        <?php if ($message['role'] === 'user'): ?>
          <article class="msg msg-user">
            <div class="msg-bubble"><?php echo nl2br(h($message['content'])); ?></div>
          </article>
        <?php elseif ($message['role'] === 'assistant'): ?>
          <?php $meta = json_decode_array($message['metadata'] ?? ''); ?>
          <article class="msg msg-ai" data-meta="<?php echo h(json_compact($meta)); ?>">
            <div class="msg-body">
              <div class="md" data-md><?php echo h($message['content']); ?></div>
            </div>
          </article>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="dock">
    <?php render_partial('composer', ['page' => $page, 'context' => 'chat']); ?>

    <?php if (!$hasThread): ?>
      <div class="suggestions" id="suggestions" aria-label="Habilidades sugeridas">
        <?php foreach (array_slice($featured, 0, 6) as $skill): ?>
          <button class="suggestion" type="button" data-start-skill="<?php echo h($skill['slug']); ?>" title="<?php echo h($skill['description']); ?>">
            <?php echo icon($skill['icon']); ?><span><?php echo h($skill['name']); ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="dock-note">La IA puede equivocarse. Valida las referencias normativas en la fuente oficial antes de aplicarlas.</p>
    <?php endif; ?>
  </div>
</section>
