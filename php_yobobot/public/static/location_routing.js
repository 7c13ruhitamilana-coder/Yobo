(() => {
  const configNode = document.getElementById('locationRoutingConfig');
  if (!configNode?.textContent) return;

  let routingConfig = {};
  try {
    routingConfig = JSON.parse(configNode.textContent);
  } catch (error) {
    routingConfig = {};
  }

  const targets = Array.isArray(routingConfig.targets) ? routingConfig.targets : [];
  if (!routingConfig.enabled || !targets.length) return;

  const body = document.body;
  const cacheKey = 'detected_market_slug_v1';
  const currentPath = window.location.pathname;
  const params = new URLSearchParams(window.location.search);
  const isGenericAssistant = currentPath === '/assistant';
  const isGenericFleet = currentPath === '/fleet';
  const isGenericHome = currentPath === '/';

  if (currentPath.startsWith('/b/') || params.get('biz_id')) return;

  function samePathForSlug(slug) {
    if (isGenericAssistant) return `/b/${slug}/assistant`;
    if (isGenericFleet) return `/b/${slug}/fleet`;
    return `/b/${slug}/`;
  }

  function rewriteGenericLinks(slug) {
    document.querySelectorAll('a[href]').forEach((link) => {
      const href = link.getAttribute('href') || '';
      if (href === '/assistant') {
        link.setAttribute('href', `/b/${slug}/assistant`);
      } else if (href === '/fleet') {
        link.setAttribute('href', `/b/${slug}/fleet`);
      } else if (href === '/') {
        link.setAttribute('href', `/b/${slug}/`);
      }
    });
  }

  function targetForTimeZone(timeZone) {
    if (!timeZone) return null;
    return targets.find((target) => (target.timezones || []).includes(timeZone)) || null;
  }

  function targetForCountryCode(countryCode) {
    if (!countryCode) return null;
    return targets.find((target) => (target.country_codes || []).includes(countryCode)) || null;
  }

  function localeCountryCode() {
    const locales = [navigator.language, ...(navigator.languages || [])].filter(Boolean);
    for (const locale of locales) {
      const match = String(locale).match(/-([A-Z]{2})\b/i);
      if (match) return match[1].toUpperCase();
    }
    return '';
  }

  function inRange(value, min, max) {
    return value >= min && value <= max;
  }

  function targetForCoordinates(latitude, longitude) {
    if (inRange(latitude, 1.15, 1.5) && inRange(longitude, 103.55, 104.1)) {
      return targetForCountryCode('SG');
    }
    if (inRange(latitude, 22.5, 26.5) && inRange(longitude, 51.0, 56.8)) {
      return targetForCountryCode('AE');
    }
    return null;
  }

  function cachedTarget() {
    const slug = sessionStorage.getItem(cacheKey) || '';
    return targets.find((target) => target.slug === slug) || null;
  }

  function rememberTarget(target) {
    if (!target?.slug) return;
    sessionStorage.setItem(cacheKey, target.slug);
  }

  function applyTarget(target) {
    if (!target?.slug) return;
    rememberTarget(target);
    body.dataset.detectedBusinessSlug = target.slug;
    body.dataset.detectedCurrency = target.currency || '';
    body.dataset.detectedCity = target.market_city || '';

    if (isGenericAssistant || isGenericFleet) {
      const destination = new URL(samePathForSlug(target.slug), window.location.origin);
      params.forEach((value, key) => destination.searchParams.set(key, value));
      window.location.replace(destination.toString());
      return;
    }

    if (isGenericHome) {
      rewriteGenericLinks(target.slug);
    }
  }

  async function detectTarget() {
    const cached = cachedTarget();
    if (cached) return cached;

    const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    const byTimeZone = targetForTimeZone(timeZone);
    if (byTimeZone) return byTimeZone;

    const byLocale = targetForCountryCode(localeCountryCode());
    if (byLocale) return byLocale;

    if (!navigator.geolocation || !navigator.permissions) return null;

    try {
      const permission = await navigator.permissions.query({ name: 'geolocation' });
      if (permission.state !== 'granted') return null;
    } catch (error) {
      return null;
    }

    try {
      const position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, {
          enableHighAccuracy: false,
          timeout: 3000,
          maximumAge: 3600000,
        });
      });
      return targetForCoordinates(position.coords.latitude, position.coords.longitude);
    } catch (error) {
      return null;
    }
  }

  detectTarget().then(applyTarget).catch(() => {});
})();
