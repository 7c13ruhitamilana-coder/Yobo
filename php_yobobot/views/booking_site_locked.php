<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $h($companyName ?? 'Booking Site') ?> Coming Soon</title>
  <link rel="stylesheet" href="/static/style.css" />
</head>
<body style="--accent: <?= $h($themeAccent ?? '#f7d6bf') ?>; --accent-deep: <?= $h($themeAccentDeep ?? '#f59a3c') ?>; --accent-rgb: <?= $h($themeAccentRgb ?? '245, 154, 60') ?>; --border: <?= $h($themeBorder ?? '#f1dfcf') ?>; --bg: <?= $h($themeBg ?? '#fff8f3') ?>; --surface-soft: <?= $h($themeSoft ?? '#fbe8d8') ?>;">
  <main class="container">
    <section class="hero">
      <h1><?= $h($companyName ?? 'This booking site') ?> is not live yet.</h1>
      <p>The business still needs to complete setup before the public booking link can be opened.</p>
      <a class="btn-pill" href="/demo/assistant">View the Yobobot sample instead <span class="arrow">&#8594;</span></a>
    </section>
  </main>
</body>
</html>
