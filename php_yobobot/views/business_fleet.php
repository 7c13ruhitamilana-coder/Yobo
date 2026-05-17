<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $h($companyName ?? 'Business') ?> <?= $h($browseLabel ?? 'Our Fleet') ?></title>
  <link rel="stylesheet" href="/static/style.css" />
</head>
<body data-default-biz-id="<?= $h($defaultBizId ?? '') ?>" data-business-slug="<?= $h($currentBusinessSlug ?? '') ?>" data-booking-mode="<?= $h($bookingMode ?? 'rental') ?>" data-currency="<?= $h($currency ?? 'SGD') ?>" style="--accent: <?= $h($themeAccent ?? '#f7d6bf') ?>; --accent-deep: <?= $h($themeAccentDeep ?? '#f59a3c') ?>; --accent-rgb: <?= $h($themeAccentRgb ?? '245, 154, 60') ?>; --border: <?= $h($themeBorder ?? '#f1dfcf') ?>; --bg: <?= $h($themeBg ?? '#fff8f3') ?>; --surface-soft: <?= $h($themeSoft ?? '#fbe8d8') ?>; --font-heading: <?= $h($headingFontCss ?? "'Playfair Display', serif") ?>; --font-body: <?= $h($bodyFontCss ?? "'Poppins', sans-serif") ?>;">
  <main class="container">
    <nav class="nav">
      <div class="logo veep-logo" aria-label="<?= $h($companyName ?? '') ?>">
        <span class="veep-logo-mark veep-logo-mark-left"></span>
        <span class="veep-logo-word"><?= $h($brandWordmark ?? '') ?></span>
        <span class="veep-logo-mark veep-logo-mark-right"></span>
      </div>
      <div class="menu">
        <a href="<?= $h($homeHref ?? '/') ?>">Home</a>
        <a class="active" href="<?= $h($fleetHref ?? '/fleet') ?>"><?= $h($browseLabel ?? 'Our Fleet') ?></a>
        <a href="<?= $h($assistantHref ?? '/assistant') ?>"><?= $h($assistantCtaLabel ?? 'Start Enquiry') ?></a>
      </div>
      <div></div>
    </nav>

    <div class="section-title-row">
      <div class="num">02</div>
      <div class="title"><?= $h($browseTitle ?? 'Our Fleet') ?></div>
      <div class="line"></div>
    </div>

    <div class="assistant-flow-fleet-status"><?= $h($browseSubtitle ?? '') ?></div>
    <section class="fleet-grid" id="fleetGrid"></section>
    <div class="summary-actions"><a class="btn-confirm" href="<?= $h($assistantHref ?? '/assistant') ?>"><?= $h($assistantCtaLabel ?? 'Start Enquiry') ?></a></div>
  </main>

  <script id="locationRoutingConfig" type="application/json"><?= $json($locationRoutingConfig ?? []) ?></script>
  <script src="/static/location_routing.js"></script>
  <script>
    const params = new URLSearchParams(window.location.search);
    const defaultBizId = document.body.dataset.defaultBizId || <?= $json($defaultBizId ?? '') ?>;
    const businessSlug = document.body.dataset.businessSlug || '';
    const bookingMode = document.body.dataset.bookingMode || 'rental';
    const currencyCode = document.body.dataset.currency || 'SGD';
    const bizStorageKey = businessSlug ? `biz_id:${businessSlug}` : 'biz_id';
    const startStorageKey = businessSlug ? `start_date:${businessSlug}` : 'start_date';
    const endStorageKey = businessSlug ? `end_date:${businessSlug}` : 'end_date';
    const cityStorageKey = businessSlug ? `city:${businessSlug}` : 'city';
    const bizId = params.get('biz_id') || defaultBizId || localStorage.getItem(bizStorageKey) || '';
    const startFromQuery = params.get('start_date') || localStorage.getItem(startStorageKey) || '';
    const endFromQuery = params.get('end_date') || localStorage.getItem(endStorageKey) || '';
    if (bizId) localStorage.setItem(bizStorageKey, bizId);
    if (startFromQuery) localStorage.setItem(startStorageKey, startFromQuery);
    if (endFromQuery) localStorage.setItem(endStorageKey, endFromQuery);
    const cityFromQuery = params.get('city') || '';
    if (cityFromQuery) localStorage.setItem(cityStorageKey, cityFromQuery);

    function formatMoney(amount) {
      const value = Number(amount || 0);
      if (!value) return '';
      return new Intl.NumberFormat('en-SG', { style: 'currency', currency: currencyCode, maximumFractionDigits: 0 }).format(value);
    }

    function cardHTML(item) {
      const img = (item.photo_url || '').trim() || 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1200&auto=format&fit=crop';
      if (bookingMode === 'service') {
        const title = item.name || 'Service';
        const duration = item.duration_label || '';
        const price = item.price_label || formatMoney(item.price);
        const description = item.description || 'Appointment details will be confirmed by the team.';
        return `<article class="fleet-card"><div class="fleet-image"><img src="${img}" alt="${title}" /></div><div class="fleet-meta"><div><strong>${title}</strong></div>${duration ? `<div>Duration: ${duration}</div>` : ''}${price ? `<div>${price}</div>` : ''}<div>${description}</div></div></article>`;
      }
      const priceValue = Number(item.price_per_day || 0);
      const priceLabel = priceValue ? priceValue.toLocaleString() : '-';
      return `<article class="fleet-card"><div class="fleet-image"><img src="${img}" alt="${item.make || 'Car'}" /></div><div class="fleet-meta"><div>Make: ${item.make || '-'}</div><div>Model: ${item.model || '-'}</div><div>Price/Day: SGD ${priceLabel}</div></div></article>`;
    }

    async function loadFleet() {
      const startDate = localStorage.getItem(startStorageKey) || '';
      const endDate = localStorage.getItem(endStorageKey) || '';
      const query = new URLSearchParams();
      if (bizId) query.set('biz_id', bizId);
      if (startDate && endDate) {
        query.set('start_date', startDate);
        query.set('end_date', endDate);
      }
      const response = await fetch(`/api/fleet?${query.toString()}`);
      const data = await response.json();
      const grid = document.getElementById('fleetGrid');
      const fleet = data.data || [];
      if (!data.ok) {
        grid.innerHTML = `<div>${data.error || 'Unable to load the list right now.'}</div>`;
        return;
      }
      if (!fleet.length) {
        grid.innerHTML = bookingMode === 'service' ? '<div>No services are configured yet.</div>' : '<div>No available cars found.</div>';
        return;
      }
      grid.innerHTML = fleet.map(cardHTML).join('');
    }
    loadFleet();
  </script>
  <?php require __DIR__ . '/partials/yobo_chat.php'; ?>
</body>
</html>
