<?php $bodyClass = 'dashboard-landing-page'; ob_start(); $profile = $portalProfile ?? []; $fieldOptions = $customerFieldOptions ?? []; ?>
<section class="site-page-hero">
  <p class="site-eyebrow">Workspace</p>
  <h1 class="site-title">Your Yobobot workspace is ready to continue from the main site.</h1>
  <p class="site-copy">Use this as the onboarding handoff before you move into business operations, integrations, and the public booking experience.</p>
</section>

<section class="site-section">
  <div class="dashboard-summary-card">
    <div class="site-card-head">
      <span class="site-tag">Business Details</span>
      <span class="site-mini-status">Ready</span>
    </div>
    <h2><?= $h($profile['business_name'] ?? 'Your Business') ?></h2>
    <p class="dashboard-summary-copy">Review the business details below, edit anything you want to change, then continue to the dashboard or open the working booking-site preview.</p>

    <?php if (!empty($workspaceMessage)): ?><div class="auth-feedback auth-feedback-success"><?= $h($workspaceMessage) ?></div><?php endif; ?>
    <?php if (!empty($workspaceError)): ?><div class="auth-feedback auth-feedback-error"><?= $h($workspaceError) ?></div><?php endif; ?>

    <form class="workspace-form" method="post" action="<?= $h($siteWorkspaceHref ?? '/workspace') ?>">
      <div class="dashboard-summary-grid">
        <label class="dashboard-summary-item workspace-field"><span>Business Name</span><input name="business_name" type="text" value="<?= $h($profile['business_name'] ?? '') ?>" required /></label>
        <label class="dashboard-summary-item workspace-field"><span>Phone</span><input name="phone_number" type="tel" value="<?= $h($profile['phone_number'] ?? '') ?>" required /></label>
        <label class="dashboard-summary-item workspace-field"><span>Business Type</span>
          <select name="business_type" required>
            <option value="car_rental" <?= (($profile['business_type'] ?? 'car_rental') === 'car_rental') ? 'selected' : '' ?>>Car Rental Company</option>
            <option value="car_workshop" <?= (($profile['business_type'] ?? '') === 'car_workshop') ? 'selected' : '' ?>>Car Workshop</option>
          </select>
        </label>
        <label class="dashboard-summary-item workspace-field"><span>Brand Colour</span><input name="brand_color" type="color" value="<?= $h($profile['brand_color'] ?? '#f59a3c') ?>" /></label>
        <label class="dashboard-summary-item workspace-field"><span>Requested Domain</span><input name="subdomain" type="text" value="<?= $h($profile['subdomain'] ?? '') ?>" required /></label>
      </div>

      <section class="workspace-field-section">
        <div class="workspace-field-section-head">
          <span>Bot Intake Fields</span>
          <p>Full name and phone are always collected. Choose the extra customer details that should appear in the bot, the dashboard table, and Supabase booking data.</p>
        </div>
        <div class="customer-field-grid">
          <?php foreach ($fieldOptions as $field): ?>
            <label class="customer-field-option">
              <input name="customer_fields[]" type="checkbox" value="<?= $h($field['key'] ?? '') ?>" <?= !empty($field['checked']) ? 'checked' : '' ?> />
              <span class="customer-field-option-copy">
                <strong><?= $h($field['label'] ?? '') ?></strong>
                <small><?= $h($field['description'] ?? '') ?></small>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>

      <?php foreach (($profile['integrations'] ?? []) as $integrationName): ?>
        <input type="hidden" name="integrations[]" value="<?= $h($integrationName) ?>" />
      <?php endforeach; ?>

      <div class="workspace-actions">
        <button type="submit" class="site-button site-button-secondary">Confirm Details</button>
      </div>
    </form>
  </div>
</section>

<section class="site-section">
  <div class="site-split-panel">
    <div>
      <p class="site-eyebrow">Next Step</p>
      <h2>Choose where you want to go next.</h2>
      <p class="site-copy">Your future live domain is <strong><?= $h($requestedDomainHref ?? 'https://yourbrand.yobobot.in') ?></strong>, but until that subdomain is provisioned, use the working booking-site preview below.</p>
    </div>
    <div class="site-card-actions site-card-actions-stacked">
      <a class="site-button site-button-primary" href="<?= $h($dashboardAccessHref ?? '/dashboard/access') ?>">Go to Dashboard</a>
      <a class="site-button site-button-secondary" href="<?= $h($bookingSiteHref ?? '/demo/assistant') ?>" target="_blank" rel="noreferrer">Go to Your Booking Site</a>
    </div>
  </div>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/partials/marketing_layout.php'; ?>
