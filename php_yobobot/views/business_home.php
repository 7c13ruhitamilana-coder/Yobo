<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $h($companyName ?? 'Business') ?></title>
  <link rel="stylesheet" href="/static/style.css" />
</head>
<body style="--accent: <?= $h($themeAccent ?? '#f7d6bf') ?>; --accent-deep: <?= $h($themeAccentDeep ?? '#f59a3c') ?>; --accent-rgb: <?= $h($themeAccentRgb ?? '245, 154, 60') ?>; --border: <?= $h($themeBorder ?? '#f1dfcf') ?>; --bg: <?= $h($themeBg ?? '#fff8f3') ?>; --surface-soft: <?= $h($themeSoft ?? '#fbe8d8') ?>; --font-heading: <?= $h($headingFontCss ?? "'Playfair Display', serif") ?>; --font-body: <?= $h($bodyFontCss ?? "'Poppins', sans-serif") ?>;">
  <main class="container">
    <nav class="nav">
      <div class="logo veep-logo" aria-label="<?= $h($companyName ?? '') ?>">
        <span class="veep-logo-mark veep-logo-mark-left"></span>
        <span class="veep-logo-word"><?= $h($brandWordmark ?? '') ?></span>
        <span class="veep-logo-mark veep-logo-mark-right"></span>
      </div>
      <div class="menu">
        <a class="active" href="<?= $h($homeHref ?? '/') ?>">Home</a>
        <a href="<?= $h($fleetHref ?? '/fleet') ?>"><?= $h($browseLabel ?? 'Our Fleet') ?></a>
        <a href="<?= $h($assistantHref ?? '/assistant') ?>"><?= $h($assistantCtaLabel ?? 'Start Enquiry') ?></a>
      </div>
      <div></div>
    </nav>

    <section class="hero">
      <h1><?= $h($heroTitle ?? '') ?></h1>
      <p><?= $h($heroSubtitle ?? '') ?></p>
      <a class="btn-pill" href="<?= $h($assistantHref ?? '/assistant') ?>">
        <?= $h($homeCtaLabel ?? 'Start Enquiry') ?><span class="arrow">&#8594;</span>
      </a>
    </section>
  </main>
  <?php require __DIR__ . '/partials/yobo_chat.php'; ?>
  <script id="locationRoutingConfig" type="application/json"><?= $json($locationRoutingConfig ?? []) ?></script>
  <script src="/static/location_routing.js"></script>
</body>
</html>
