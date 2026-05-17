<?php ob_start(); $guide = $integration ?? []; ?>
<section class="site-page-hero">
  <p class="site-eyebrow"><?= $h($guide['status'] ?? 'Integration') ?></p>
  <h1 class="site-title"><?= $h($guide['title'] ?? 'Integration') ?></h1>
  <p class="site-copy"><?= $h($guide['summary'] ?? '') ?></p>
</section>
<section class="site-section">
  <div class="site-split-panel">
    <div>
      <h2>Benefits</h2>
      <ul class="site-mini-list"><?php foreach (($guide['benefits'] ?? []) as $item): ?><li><?= $h($item) ?></li><?php endforeach; ?></ul>
    </div>
    <div>
      <h2>Setup steps</h2>
      <ol class="site-mini-list"><?php foreach (($guide['steps'] ?? []) as $item): ?><li><?= $h($item) ?></li><?php endforeach; ?></ol>
    </div>
  </div>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/partials/marketing_layout.php'; ?>
