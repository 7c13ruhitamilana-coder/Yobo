<?php ob_start(); ?>
<section class="site-page-hero">
  <p class="site-eyebrow">Who Can Use Yobobot</p>
  <h1 class="site-title">Built for car rentals and car workshops from the same platform.</h1>
</section>
<section class="site-section">
  <div class="site-use-case-grid">
    <article class="site-use-case-card">
      <span class="site-tag">Car Rentals</span>
      <h3>Rental companies can launch a booking site, show fleet options, collect booking requests, and manage availability in one place.</h3>
      <p>Use Yobobot to guide customers through rental dates, pricing, insurance selection, and the right vehicle before your team confirms the request.</p>
    </article>
    <article class="site-use-case-card">
      <span class="site-tag">Car Workshops</span>
      <h3>Workshops can turn service enquiries into structured appointment requests with issue capture, service selection, and scheduling steps.</h3>
      <p>Use Yobobot to collect vehicle details, issue notes, appointment preferences, and follow-up calls without rebuilding the workflow for every garage.</p>
    </article>
  </div>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/partials/marketing_layout.php'; ?>
