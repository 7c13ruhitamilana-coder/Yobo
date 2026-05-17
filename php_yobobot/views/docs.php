<?php ob_start(); ?>
<section class="site-page-hero">
  <p class="site-eyebrow">Documentation</p>
  <h1 class="site-title">Everything teams need to understand the Yobobot flow.</h1>
</section>
<section class="site-section">
  <div class="site-feature-grid">
    <article class="site-feature-card"><h3>Main site</h3><p>Home, pricing, integrations, documentation, and the “see it in action” demo all live on the main Yobobot site.</p></article>
    <article class="site-feature-card"><h3>Onboarding</h3><p>Create the portal account, collect business details, choose a subdomain, and sync the business profile to Supabase.</p></article>
    <article class="site-feature-card"><h3>Public booking flow</h3><p>The branded business site drives customers into the assistant flow, fleet browsing, quote review, and booking submission.</p></article>
    <article class="site-feature-card"><h3>Employee dashboard</h3><p>Use the employee login to manage bookings, availability, fleet, and customization after setup is complete.</p></article>
  </div>
</section>
<?php $content = ob_get_clean(); $scripts = '<script src="/static/docs.js"></script>'; require __DIR__ . '/partials/marketing_layout.php'; ?>
