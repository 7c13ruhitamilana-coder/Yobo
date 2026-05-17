<?php $bodyClass = 'auth-page'; ob_start(); ?>
<section class="site-page-hero">
  <p class="site-eyebrow">Log In</p>
  <h1 class="site-title">Log in to continue your Yobobot setup.</h1>
  <p class="site-copy">If the account already exists, sign in here and Yobobot will send you either to collect details or to your workspace summary.</p>
</section>

<section class="site-section">
  <div class="auth-layout auth-layout-centered">
    <div class="auth-stack">
      <form class="auth-card auth-card-compact" method="post" action="<?= $h($portalLoginHref ?? '/account/login') ?>">
        <input type="hidden" name="next" value="<?= $h($nextPath ?? '') ?>" />
        <p class="site-eyebrow">Platform Access</p>
        <h2>Log in</h2>
        <p class="site-copy">Use the same email and password you used when creating the Yobobot account.</p>

        <?php if (!empty($authError)): ?>
          <div class="auth-feedback auth-feedback-error"><?= $h($authError) ?></div>
        <?php endif; ?>

        <div class="onboarding-field-grid">
          <label class="onboarding-field onboarding-field-wide">
            <span>Email</span>
            <input name="email" type="email" value="<?= $h($formEmail ?? '') ?>" placeholder="john@gmail.com" required />
          </label>
          <label class="onboarding-field onboarding-field-wide">
            <span>Password</span>
            <input name="password" type="password" placeholder="Enter your password" required />
          </label>
        </div>

        <div class="auth-actions">
          <button type="submit" class="site-button site-button-primary">Log In</button>
        </div>

        <div class="auth-links-inline">
          <a class="site-inline-link" href="<?= $h($siteCreateAccountHref ?? '/create-account') ?>">Need an account? Create one</a>
          <a class="site-inline-link" href="/dashboard/login">Employee dashboard login</a>
        </div>
      </form>
    </div>
  </div>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/partials/marketing_layout.php'; ?>
