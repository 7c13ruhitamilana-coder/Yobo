<?php $bodyClass = 'auth-page'; ob_start(); ?>
<section class="site-page-hero">
  <p class="site-eyebrow">Create Account</p>
  <h1 class="site-title">Create a Yobobot account before you collect business details.</h1>
  <p class="site-copy">This is the first real step in the platform flow: create the account, then move into business setup, domain selection, and the dashboard.</p>
</section>

<section class="site-section">
  <div class="auth-layout auth-layout-centered">
    <div class="auth-stack">
      <form class="auth-card auth-card-compact" method="post" action="<?= $h($siteCreateAccountHref ?? '/create-account') ?>">
        <p class="site-eyebrow">Account Setup</p>
        <h2>Create your account</h2>
        <p class="site-copy">Use your email and password to create the account that owns the business workspace.</p>

        <?php if (!empty($authError)): ?>
          <div class="auth-feedback auth-feedback-error"><?= $h($authError) ?></div>
        <?php endif; ?>

        <div class="onboarding-field-grid">
          <label class="onboarding-field onboarding-field-wide">
            <span>Full Name</span>
            <input name="full_name" type="text" value="<?= $h($formFullName ?? '') ?>" placeholder="John Doe" required />
          </label>
          <label class="onboarding-field onboarding-field-wide">
            <span>Email</span>
            <input name="email" type="email" value="<?= $h($formEmail ?? '') ?>" placeholder="john@gmail.com" required />
          </label>
          <label class="onboarding-field onboarding-field-wide">
            <span>Password</span>
            <input name="password" type="password" placeholder="Create a secure password" required />
          </label>
        </div>

        <div class="auth-actions">
          <button type="submit" class="site-button site-button-primary">Create Account</button>
        </div>

        <div class="auth-links-inline">
          <a class="site-inline-link" href="<?= $h($portalLoginHref ?? '/account/login') ?>">Already created a platform account? Continue setup</a>
        </div>
      </form>
    </div>
  </div>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/partials/marketing_layout.php'; ?>
