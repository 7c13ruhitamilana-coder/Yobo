<?php ob_start(); ?>
<section class="site-hero site-hero-single">
  <div class="site-hero-copy">
    <p class="site-eyebrow">Main Platform</p>
    <h1 class="site-title">Yobobot turns your website, onboarding, booking bot, and operations dashboard into one connected product.</h1>
    <p class="site-copy">
      Launch a public booking experience, onboard each business on its own Yobobot subdomain,
      manage bookings from a shared dashboard, and connect business-specific workspaces like
      Google Calendar, Google Meet, Zoom, and GPay.
    </p>
    <div class="site-hero-actions">
      <?php if (!empty($siteUserLoggedIn)): ?>
        <a class="site-button site-button-primary" href="<?= $h($siteDashboardHref ?? '/workspace') ?>"><?= $h($siteDashboardLabel ?? 'Dashboard') ?></a>
        <a class="site-profile-link site-profile-link-hero" href="<?= $h($siteProfileHref ?? '/workspace') ?>" aria-label="Open profile details" title="<?= $h($siteUserName ?? 'Profile details') ?>">
          <span class="site-profile-avatar"><?= $h($siteUserInitials ?? 'YO') ?></span>
        </a>
      <?php else: ?>
        <a class="site-button site-button-primary" href="<?= $h($siteCreateAccountHref ?? '/create-account') ?>">Create Account</a>
        <a class="site-button site-button-secondary" href="<?= $h($siteLoginHref ?? '/account/login') ?>">Log In</a>
      <?php endif; ?>
      <a class="site-inline-link site-inline-link-hero" href="<?= $h($siteScheduleDemoHref ?? '/demo') ?>">Schedule a Demo</a>
    </div>
    <div class="site-chip-row">
      <span class="site-chip">Booking Bot</span>
      <span class="site-chip">Business Onboarding</span>
      <span class="site-chip">Availability Calendar</span>
      <span class="site-chip">Per-business Integrations</span>
      <span class="site-chip">Subdomains on yobobot.in</span>
    </div>
  </div>
</section>

<section class="site-section">
  <div class="site-section-heading">
    <p class="site-eyebrow">Platform Features</p>
    <h2>Everything Yobobot needs to explain on the main website</h2>
    <p>These are the features your marketing site can now present clearly before a business signs up.</p>
  </div>
  <div class="site-feature-grid">
    <article class="site-feature-card"><h3>AI Booking Bot</h3><p>Guide customers through dates, questions, services, and vehicle selection without pushing them into a messy form.</p></article>
    <article class="site-feature-card"><h3>Availability Calendar</h3><p>Let teams see when a car is free, booked, blocked, or under maintenance with a calendar-style view for operations.</p></article>
    <article class="site-feature-card"><h3>Business Onboarding</h3><p>Collect account details, business info, domain selection, and integration setup with a guided onboarding flow.</p></article>
    <article class="site-feature-card"><h3>Custom Subdomains</h3><p>Give every business a branded public booking address like <strong>customername.yobobot.in</strong> on the free plan.</p></article>
    <article class="site-feature-card"><h3>Operations Dashboard</h3><p>Manage bookings, review customers, track confirmations, and move the team from enquiry to action.</p></article>
    <article class="site-feature-card"><h3>Per-business Workspaces</h3><p>Each business can connect its own Google Calendar, Meet, Zoom, and GPay setup instead of sharing one global workspace.</p></article>
  </div>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/partials/marketing_layout.php'; ?>
