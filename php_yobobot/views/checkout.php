<?php ob_start(); ?>
<section class="site-page-hero">
  <p class="site-eyebrow">Checkout</p>
  <h1 class="site-title"><?= $h($plan['name'] ?? 'Plan') ?> plan</h1>
  <p class="site-copy"><?= $h($plan['description'] ?? '') ?></p>
</section>
<section class="site-section">
  <div class="site-split-panel">
    <div>
      <h2><?= $h($plan['price'] ?? '') ?><?= !empty($plan['price_suffix']) ? ' ' . $h($plan['price_suffix']) : '' ?></h2>
      <ul class="site-mini-list">
        <?php foreach (($plan['features'] ?? []) as $feature): ?><li><?= $h($feature) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <div class="site-card-actions site-card-actions-stacked">
      <?php if (!empty($paymentUrl)): ?>
        <a class="site-button site-button-primary" href="<?= $h($paymentUrl) ?>" target="_blank" rel="noreferrer">Continue to payment</a>
      <?php else: ?>
        <a class="site-button site-button-primary" href="/create-account">Start setup</a>
      <?php endif; ?>
      <a class="site-button site-button-secondary" href="/pricing">Back to pricing</a>
    </div>
  </div>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/partials/marketing_layout.php'; ?>
