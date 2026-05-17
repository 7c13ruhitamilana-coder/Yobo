<?php ob_start(); $cards = $plans ?? []; ?>
<section class="site-page-hero">
  <p class="site-eyebrow">Pricing</p>
  <h1 class="site-title">Choose the plan that matches how much control your business needs.</h1>
</section>
<section class="site-section">
  <div class="site-feature-grid">
    <?php foreach ($cards as $plan): ?>
      <article class="site-feature-card">
        <span class="site-tag"><?= $h($plan['highlight'] ?? '') ?></span>
        <h3><?= $h($plan['name'] ?? 'Plan') ?></h3>
        <p><?= $h($plan['price'] ?? '') ?><?= !empty($plan['price_suffix']) ? ' ' . $h($plan['price_suffix']) : '' ?></p>
        <p><?= $h($plan['description'] ?? '') ?></p>
        <ul class="site-mini-list">
          <?php foreach (($plan['features'] ?? []) as $feature): ?><li><?= $h($feature) ?></li><?php endforeach; ?>
        </ul>
        <a class="site-button site-button-primary" href="<?= $h($plan['cta_href'] ?? '/create-account') ?>"><?= $h($plan['cta_label'] ?? 'Choose Plan') ?></a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/partials/marketing_layout.php'; ?>
