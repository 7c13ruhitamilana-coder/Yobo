<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $h($companyName ?? 'Business') ?> Assistant</title>
  <link rel="stylesheet" href="/static/style.css" />
</head>
<body
  class="assistant-flow-page"
  data-default-biz-id="<?= $h($defaultBizId ?? '') ?>"
  data-business-slug="<?= $h($currentBusinessSlug ?? '') ?>"
  data-company-name="<?= $h($companyName ?? '') ?>"
  data-assistant-name="<?= $h($assistantName ?? 'Yobo') ?>"
  data-brand-wordmark="<?= $h($brandWordmark ?? '') ?>"
  data-booking-mode="<?= $h($bookingMode ?? 'rental') ?>"
  data-currency="<?= $h($currency ?? 'SGD') ?>"
  data-processing-fee="<?= $h($processingFee ?? 50) ?>"
  data-gst-rate="<?= $h($vatRate ?? 0.09) ?>"
  data-discount-type="<?= $h($discountType ?? 'none') ?>"
  data-discount-value="<?= $h($discountValue ?? 0) ?>"
  data-discount-label="<?= $h($discountLabel ?? '') ?>"
  data-market-city="<?= $h($marketCity ?? '') ?>"
  style="--accent: <?= $h($themeAccent ?? '#f7d6bf') ?>; --accent-deep: <?= $h($themeAccentDeep ?? '#f59a3c') ?>; --accent-rgb: <?= $h($themeAccentRgb ?? '245, 154, 60') ?>; --border: <?= $h($themeBorder ?? '#f1dfcf') ?>; --bg: <?= $h($themeBg ?? '#fff8f3') ?>; --surface-soft: <?= $h($themeSoft ?? '#fbe8d8') ?>; --font-heading: <?= $h($headingFontCss ?? "'Playfair Display', serif") ?>; --font-body: <?= $h($bodyFontCss ?? "'Poppins', sans-serif") ?>;"
>
  <main class="assistant-flow-shell assistant-flow-shell-chat">
    <nav class="nav">
      <div class="logo veep-logo" aria-label="<?= $h($companyName ?? '') ?>">
        <span class="veep-logo-mark veep-logo-mark-left"></span>
        <span class="veep-logo-word"><?= $h($brandWordmark ?? '') ?></span>
        <span class="veep-logo-mark veep-logo-mark-right"></span>
      </div>
      <div class="menu">
        <a href="<?= $h($homeHref ?? '/') ?>">Home</a>
        <a href="<?= $h($fleetHref ?? '/fleet') ?>"><?= $h($browseLabel ?? 'Our Fleet') ?></a>
        <a class="active" href="<?= $h($assistantHref ?? '/assistant') ?>"><?= $h($assistantCtaLabel ?? 'Start Enquiry') ?></a>
      </div>
      <div></div>
    </nav>

    <section class="assistant-chat-window">
      <div id="assistantFlowConsentRoot"></div>
      <div class="assistant-chat-thread assistant-flow-thread" id="assistantFlowThread"></div>
      <form class="assistant-chat-composer assistant-flow-composer" id="assistantFlowComposer">
        <input class="assistant-chat-input assistant-flow-input" id="assistantFlowInput" type="text" placeholder="Type your reply here..." autocomplete="off" />
        <button class="btn-black assistant-chat-send" type="submit">Send</button>
      </form>
    </section>
  </main>
  <script id="locationRoutingConfig" type="application/json"><?= $json($locationRoutingConfig ?? []) ?></script>
  <script src="/static/location_routing.js"></script>
  <script id="assistantFlowConfig" type="application/json"><?= $json($assistantFlowConfig ?? []) ?></script>
  <script src="/static/assistant_flow.js"></script>
</body>
</html>
