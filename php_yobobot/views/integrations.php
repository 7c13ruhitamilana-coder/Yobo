<?php ob_start(); $cards = $integrations ?? []; ?>
<section class="site-page-hero">
  <p class="site-eyebrow">Integrations</p>
  <h1 class="site-title">Connect each business workspace to the tools it already uses.</h1>
</section>
<section class="site-section">
  <div class="site-feature-grid">
    <?php foreach ($cards as $integration): ?>
      <article class="site-feature-card">
        <span class="site-tag"><?= $h($integration['tag'] ?? '') ?></span>
        <h3><?= $h($integration['title'] ?? '') ?></h3>
        <p><?= $h($integration['description'] ?? '') ?></p>
        <a class="site-inline-link" href="/integrations/<?= $h($integration['slug'] ?? '') ?>">Read guide</a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/partials/marketing_layout.php'; ?>
