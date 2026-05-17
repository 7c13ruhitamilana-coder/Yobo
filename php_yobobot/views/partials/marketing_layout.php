<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $h($pageTitle ?? 'Yobobot') ?></title>
  <link rel="stylesheet" href="/static/style.css" />
  <?php if (!empty($extraHead ?? '')): ?>
    <?= $extraHead ?>
  <?php endif; ?>
</head>
<body class="main-site-page <?= $h($bodyClass ?? '') ?>" style="--accent: <?= $h($themeAccent ?? '#f7d6bf') ?>; --accent-deep: <?= $h($themeAccentDeep ?? '#f59a3c') ?>; --accent-rgb: <?= $h($themeAccentRgb ?? '245, 154, 60') ?>; --border: <?= $h($themeBorder ?? '#f1dfcf') ?>; --bg: <?= $h($themeBg ?? '#fff8f3') ?>; --surface-soft: <?= $h($themeSoft ?? '#fbe8d8') ?>; --font-heading: <?= $h($headingFontCss ?? "'Playfair Display', serif") ?>; --font-body: <?= $h($bodyFontCss ?? "'Poppins', sans-serif") ?>;">
  <main class="site-shell">
    <nav class="site-nav">
      <a class="logo veep-logo site-nav-brand" href="<?= $h($siteHomeHref ?? '/') ?>" aria-label="<?= $h($companyName ?? 'Yobobot') ?>">
        <span class="veep-logo-mark veep-logo-mark-left"></span>
        <span class="veep-logo-word"><?= $h($brandWordmark ?? 'YOBOBOT') ?></span>
        <span class="veep-logo-mark veep-logo-mark-right"></span>
      </a>

      <div class="site-nav-links">
        <a class="<?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>" href="<?= $h($siteHomeHref ?? '/') ?>">Home</a>
        <a class="<?= ($activeNav ?? '') === 'pricing' ? 'active' : '' ?>" href="<?= $h($sitePricingHref ?? '/pricing') ?>">Pricing</a>
        <a class="<?= ($activeNav ?? '') === 'integrations' ? 'active' : '' ?>" href="<?= $h($siteIntegrationsHref ?? '/integrations') ?>">Integrations</a>
        <a class="<?= ($activeNav ?? '') === 'docs' ? 'active' : '' ?>" href="<?= $h($siteDocsHref ?? '/docs') ?>">Documentation</a>
        <a href="<?= $h($siteActionHref ?? '/demo') ?>">See it in Action</a>
      </div>

      <div class="site-nav-actions">
        <?php if (!empty($siteUserLoggedIn)): ?>
          <a class="site-button site-button-secondary" href="<?= $h($siteDashboardHref ?? '/workspace') ?>"><?= $h($siteDashboardLabel ?? 'Dashboard') ?></a>
          <a class="site-profile-link" href="<?= $h($siteProfileHref ?? '/workspace') ?>" aria-label="Open profile details" title="<?= $h($siteUserName ?? 'Profile details') ?>">
            <span class="site-profile-avatar"><?= $h($siteUserInitials ?? 'YO') ?></span>
          </a>
          <a class="site-inline-link site-nav-inline-link" href="<?= $h($siteLogoutHref ?? '/account/logout') ?>">Log Out</a>
        <?php else: ?>
          <a class="site-button site-button-secondary" href="<?= $h($siteLoginHref ?? '/account/login') ?>">Log In</a>
          <a class="site-button site-button-primary" href="<?= $h($siteCreateAccountHref ?? '/create-account') ?>">Create Account</a>
        <?php endif; ?>
      </div>
    </nav>

    <?= $content ?? '' ?>

    <footer class="site-footer">
      <div>
        <p class="site-eyebrow">Yobobot</p>
        <p class="site-footer-copy">Main website, onboarding hub, business booking bots, and team workflows in one place.</p>
      </div>
      <div class="site-footer-links">
        <a href="<?= $h($siteHomeHref ?? '/') ?>">Home</a>
        <a href="<?= $h($sitePricingHref ?? '/pricing') ?>">Pricing</a>
        <a href="<?= $h($siteIntegrationsHref ?? '/integrations') ?>">Integrations</a>
        <a href="<?= $h($siteDocsHref ?? '/docs') ?>">Documentation</a>
        <a href="<?= $h($siteActionHref ?? '/demo') ?>">See it in Action</a>
        <a href="<?= $h($siteOnboardingHref ?? '/onboarding') ?>">Onboarding</a>
      </div>
    </footer>
  </main>

  <?php if (!empty($scripts ?? '')): ?>
    <?= $scripts ?>
  <?php endif; ?>
</body>
</html>
