<?php $bodyClass = 'onboarding-page'; ob_start(); $profile = $portalProfile ?? []; $fieldOptions = $customerFieldOptions ?? []; ?>
<section class="site-page-hero onboarding-hero">
  <p class="site-eyebrow">Collect Details</p>
  <h1 class="site-title">You’re logged in. Now collect the business details, choose the domain, and continue to the dashboard.</h1>
  <p class="site-copy">This keeps the main platform flow clear: home, create account, log in, collect details, then move into the Yobobot dashboard.</p>
</section>

<section class="site-section">
  <div class="onboarding-layout">
    <div class="onboarding-panel">
      <div class="onboarding-progress-head">
        <div>
          <p class="site-eyebrow">Onboarding Progress</p>
          <h2>Collect business details</h2>
        </div>
        <span id="progressLabel" class="onboarding-progress-label">Step 1 of 3</span>
      </div>

      <div class="onboarding-progress-track" aria-hidden="true">
        <span id="progressFill" class="onboarding-progress-fill"></span>
      </div>

      <div class="onboarding-progress-steps">
        <button type="button" class="onboarding-step-dot active" data-step-target="0">Business</button>
        <button type="button" class="onboarding-step-dot" data-step-target="1">Domain</button>
        <button type="button" class="onboarding-step-dot" data-step-target="2">Integrations</button>
      </div>

      <form id="onboardingForm" class="onboarding-form" method="post" action="<?= $h($siteOnboardingHref ?? '/onboarding') ?>">
        <div class="auth-feedback auth-feedback-inline">Signed in and ready to set up the business.</div>
        <?php if (!empty($onboardingError)): ?>
          <div class="auth-feedback auth-feedback-error auth-feedback-inline"><?= $h($onboardingError) ?></div>
        <?php endif; ?>

        <section class="onboarding-step active" data-step-index="0">
          <p class="onboarding-step-title">Tell us about the business</p>
          <div class="onboarding-field-grid">
            <label class="onboarding-field">
              <span>Phone Number</span>
              <input name="phone_number" type="tel" value="<?= $h($profile['phone_number'] ?? '') ?>" placeholder="+91 98765 43210" required />
            </label>
            <label class="onboarding-field">
              <span>Business Name</span>
              <input id="businessNameInput" name="business_name" type="text" value="<?= $h($profile['business_name'] ?? '') ?>" placeholder="Smart Car Rentals" required />
            </label>
            <label class="onboarding-field">
              <span>Brand Colour</span>
              <input name="brand_color" type="color" value="<?= $h($profile['brand_color'] ?? '#f59a3c') ?>" />
            </label>
            <label class="onboarding-field onboarding-field-wide">
              <span>Business Type</span>
              <select name="business_type" required>
                <option value="car_rental" <?= (($profile['business_type'] ?? 'car_rental') === 'car_rental') ? 'selected' : '' ?>>Car Rental Company</option>
                <option value="car_workshop" <?= (($profile['business_type'] ?? '') === 'car_workshop') ? 'selected' : '' ?>>Car Workshop</option>
              </select>
            </label>
          </div>
        </section>

        <section class="onboarding-step" data-step-index="1">
          <p class="onboarding-step-title">Choose your free domain</p>
          <div class="onboarding-field-grid">
            <label class="onboarding-field onboarding-field-wide">
              <span>Subdomain Name</span>
              <input id="subdomainInput" name="subdomain" type="text" value="<?= $h($profile['subdomain'] ?? '') ?>" placeholder="smartcars" required />
            </label>
          </div>
          <div class="onboarding-domain-preview">
            <span>Your public booking site</span>
            <strong id="domainPreview">yourbrand.yobobot.in</strong>
          </div>
        </section>

        <section class="onboarding-step" data-step-index="2">
          <p class="onboarding-step-title">Choose integrations to set up first</p>
          <div class="onboarding-checkbox-grid">
            <?php foreach (['Google Calendar', 'Google Meet', 'Zoom', 'GPay'] as $integration): ?>
              <?php $checked = in_array($integration, $profile['integrations'] ?? [], true) || (($profile['integrations'] ?? []) === [] && in_array($integration, ['Google Calendar', 'Google Meet'], true)); ?>
              <label class="onboarding-checkbox">
                <input name="integrations[]" type="checkbox" value="<?= $h($integration) ?>" <?= $checked ? 'checked' : '' ?> />
                <span><?= $h($integration) ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="onboarding-subsection">
            <p class="onboarding-step-title">Choose what Yobo should collect from customers</p>
            <p class="onboarding-help">Full name and phone number are always collected. Choose the extra customer details you want the bot to ask before the booking reaches your dashboard.</p>
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
          </div>
        </section>

        <div class="onboarding-actions">
          <button id="backButton" type="button" class="site-button site-button-secondary">Back</button>
          <button id="nextButton" type="button" class="site-button site-button-primary">Continue</button>
        </div>
      </form>
    </div>

    <aside class="onboarding-sidecard">
      <p class="site-eyebrow">Sample Website</p>
      <h2>Show customers the current Yobobot sample site</h2>
      <div class="site-card-actions site-card-actions-stacked">
        <a class="site-button site-button-secondary" href="<?= $h($currentSampleHref ?? '/demo/assistant') ?>" target="_blank" rel="noreferrer">Open current sample website</a>
        <a class="site-inline-link" href="<?= $h($rentalSampleHref ?? '/b/veep/assistant') ?>" target="_blank" rel="noreferrer">Open rental sample</a>
        <a class="site-inline-link" href="<?= $h($garageSampleHref ?? '/b/mechanicappointments/assistant') ?>" target="_blank" rel="noreferrer">Open workshop sample</a>
      </div>
    </aside>
  </div>
</section>
<?php $content = ob_get_clean(); $scripts = '<script src="/static/onboarding.js"></script>'; require __DIR__ . '/partials/marketing_layout.php'; ?>
