(function () {
  const config = window.dashboardConfig || {};
  const page = config.page || document.querySelector('.workspace-shell')?.dataset.page || 'overview';
  const feedbackBanner = document.getElementById('feedbackBanner');
  const settingsToggle = document.getElementById('settingsToggle');
  const settingsPanel = document.getElementById('settingsPanel');
  const profileToggle = document.getElementById('profileToggle');
  const profileMenu = document.getElementById('profileMenu');
  let latestCustomization = null;

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }

  function parseDate(value) {
    if (!value) return null;
    const parsed = new Date(`${value}T00:00:00`);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }

  function toIsoDate(date) {
    return date.toISOString().slice(0, 10);
  }

  function todayIso() {
    return toIsoDate(new Date());
  }

  function formatDate(value) {
    const parsed = parseDate(value);
    if (!parsed) return value || 'Not set';
    return parsed.toLocaleDateString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  function formatCompactDate(value) {
    const parsed = parseDate(value);
    if (!parsed) return value || 'Not set';
    return parsed.toLocaleDateString('en-GB', {
      day: 'numeric',
      month: 'short',
    });
  }

  function formatMoney(value) {
    const amount = Number(value);
    if (Number.isFinite(amount)) {
      return amount.toLocaleString('en-US', {
        maximumFractionDigits: amount % 1 === 0 ? 0 : 2,
      });
    }
    return String(value || '0');
  }

  function setFeedback(message, tone) {
    if (!feedbackBanner) return;
    if (!message) {
      feedbackBanner.className = 'feedback-banner hidden';
      feedbackBanner.textContent = '';
      return;
    }
    feedbackBanner.className = `feedback-banner ${tone || 'warning'}`;
    feedbackBanner.textContent = message;
  }

  async function fetchJson(url, options, fallbackMessage) {
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
      ...options,
    });
    const contentType = response.headers.get('content-type') || '';
    const rawText = await response.text();
    if (!contentType.toLowerCase().includes('application/json')) {
      if (response.redirected && response.url) {
        window.location.href = response.url;
        return null;
      }
      const preview = rawText.trim().replace(/\s+/g, ' ').slice(0, 220);
      throw new Error(
        preview
          ? `${fallbackMessage || 'The dashboard returned an unexpected response.'} ${preview}`
          : (fallbackMessage || 'The dashboard returned an unexpected response.')
      );
    }
    let data;
    try {
      data = rawText ? JSON.parse(rawText) : {};
    } catch (error) {
      const preview = rawText.trim().replace(/\s+/g, ' ').slice(0, 220);
      throw new Error(
        preview
          ? `Dashboard returned invalid JSON: ${preview}`
          : 'Dashboard returned invalid JSON.'
      );
    }
    if (response.status === 401 && data.redirect_to) {
      window.location.href = data.redirect_to;
      return null;
    }
    if (!response.ok || !data.ok) {
      throw new Error(data.error || fallbackMessage || 'The dashboard request failed.');
    }
    return data;
  }

  function updateSelectOptions(select, values, placeholder) {
    if (!select) return;
    const current = select.value;
    select.innerHTML = '';

    const initial = document.createElement('option');
    initial.value = '';
    initial.textContent = placeholder;
    select.appendChild(initial);

    values.forEach((value) => {
      const option = document.createElement('option');
      option.value = value.value ?? value.id ?? value;
      option.textContent = value.label ?? value.text ?? value;
      if (String(option.value) === String(current)) {
        option.selected = true;
      }
      select.appendChild(option);
    });
  }

  function bindSidebarChrome() {
    if (settingsToggle && settingsPanel) {
      settingsToggle.addEventListener('click', () => {
        const expanded = settingsToggle.getAttribute('aria-expanded') === 'true';
        settingsToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        settingsPanel.classList.toggle('hidden', expanded);
      });
    }

    if (profileToggle && profileMenu) {
      profileToggle.addEventListener('click', () => {
        const expanded = profileToggle.getAttribute('aria-expanded') === 'true';
        profileToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        profileMenu.classList.toggle('hidden', expanded);
      });

      document.addEventListener('click', (event) => {
        if (
          !profileMenu.classList.contains('hidden') &&
          !profileMenu.contains(event.target) &&
          !profileToggle.contains(event.target)
        ) {
          profileToggle.setAttribute('aria-expanded', 'false');
          profileMenu.classList.add('hidden');
        }
      });
    }
  }

  async function createInvite(payload) {
    return fetchJson(
      config.invitesUrl,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload),
      },
      'Unable to save employee access.'
    );
  }

  function bindInviteForm() {
    const inviteForm = document.getElementById('inviteForm');
    const inviteFullName = document.getElementById('inviteFullName');
    const inviteEmail = document.getElementById('inviteEmail');
    const inviteRole = document.getElementById('inviteRole');

    if (!inviteForm || !inviteFullName || !inviteEmail || !inviteRole) return;

    inviteForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      inviteForm.classList.add('loading');
      try {
        const result = await createInvite({
          full_name: inviteFullName.value.trim(),
          email: inviteEmail.value.trim(),
          role: inviteRole.value,
        });
        if (!result) return;
        inviteForm.reset();
        const defaultOption = inviteRole.querySelector('option[selected]') || inviteRole.querySelector('option');
        if (defaultOption) inviteRole.value = defaultOption.value;
        setFeedback(
          `Invite saved for ${result.invite.email}. Share this registration page: ${result.register_url}`,
          'success'
        );
      } catch (error) {
        setFeedback(error.message || 'Unable to save employee access.', 'error');
      } finally {
        inviteForm.classList.remove('loading');
      }
    });
  }

  function updateCompanyDisplays(companyName) {
    document.querySelectorAll('[data-company-display]').forEach((element) => {
      element.textContent = companyName;
    });
  }

  function refreshBotQuestionCounter() {
    const counter = document.getElementById('botQuestionCounter');
    if (!counter) return;
    const optionalChecks = Array.from(document.querySelectorAll('.bot-field-toggle'));
    const optionalCount = optionalChecks.filter((input) => input.checked).length;
    const alwaysCount = Number(counter.dataset.alwaysCount || '0');
    const maxCount = Number(counter.dataset.maxCount || '10');
    const totalCount = alwaysCount + optionalCount;
    counter.textContent = `${totalCount} / ${maxCount} questions`;
    counter.classList.toggle('is-over-limit', totalCount > maxCount);
  }

  function renderBotQuestions(botQuestions, editable) {
    const alwaysList = document.getElementById('alwaysCollectedList');
    const fieldList = document.getElementById('botFieldList');
    const counter = document.getElementById('botQuestionCounter');
    if (!alwaysList || !fieldList || !counter || !botQuestions) return;

    const alwaysCollected = Array.isArray(botQuestions.always_collected) ? botQuestions.always_collected : [];
    const optionalFields = Array.isArray(botQuestions.optional_fields) ? botQuestions.optional_fields : [];
    const maxCount = Number(botQuestions.max_total_questions || 10);

    counter.dataset.alwaysCount = String(alwaysCollected.length);
    counter.dataset.maxCount = String(maxCount);

    alwaysList.innerHTML = alwaysCollected
      .map(
        (field) => `
          <article class="always-collected-chip">
            <strong>${escapeHtml(field.label || field.key || 'Required')}</strong>
            <span>Always on</span>
          </article>
        `
      )
      .join('');

    fieldList.innerHTML = optionalFields
      .map((field) => {
        const checked = field.enabled ? ' checked' : '';
        const disabled = editable ? '' : ' disabled';
        const inputDisabled = editable && field.enabled ? '' : ' disabled';
        return `
          <label class="bot-field-card${field.enabled ? ' is-selected' : ''}" data-key="${escapeHtml(field.key)}">
            <div class="bot-field-card-top">
              <span class="bot-field-checkbox">
                <input class="bot-field-toggle" type="checkbox" value="${escapeHtml(field.key)}"${checked}${disabled} />
              </span>
              <span class="bot-field-copy">
                <strong>${escapeHtml(field.default_label || field.label || field.key)}</strong>
                <small>${escapeHtml(field.description || '')}</small>
              </span>
            </div>
            <input
              class="bot-field-label-input"
              type="text"
              value="${escapeHtml(field.label || field.default_label || '')}"
              placeholder="${escapeHtml(field.default_label || field.key || '')}"
              data-default-label="${escapeHtml(field.default_label || field.label || field.key || '')}"
              ${inputDisabled}
            />
          </label>
        `;
      })
      .join('');

    fieldList.querySelectorAll('.bot-field-toggle').forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        const card = checkbox.closest('.bot-field-card');
        const input = card?.querySelector('.bot-field-label-input');
        if (card) card.classList.toggle('is-selected', checkbox.checked);
        if (input) input.disabled = !checkbox.checked || !editable;
        refreshBotQuestionCounter();
      });
    });

    fieldList.querySelectorAll('.bot-field-label-input').forEach((input) => {
      input.addEventListener('input', () => {
        if (!input.value.trim()) {
          input.placeholder = input.dataset.defaultLabel || '';
        }
      });
    });

    refreshBotQuestionCounter();
  }

  function collectBotFields() {
    return Array.from(document.querySelectorAll('.bot-field-card')).map((card) => {
      const checkbox = card.querySelector('.bot-field-toggle');
      const input = card.querySelector('.bot-field-label-input');
      return {
        key: checkbox?.value || card.dataset.key || '',
        enabled: Boolean(checkbox?.checked),
        label: input?.value.trim() || input?.dataset.defaultLabel || '',
      };
    });
  }

  function populateFontSelect(select, options, selected) {
    if (!select) return;
    select.innerHTML = '';
    (options || []).forEach((option) => {
      const item = document.createElement('option');
      item.value = option.id;
      item.textContent = option.label;
      if (option.css) item.dataset.css = option.css;
      if (option.id === selected) item.selected = true;
      select.appendChild(item);
    });
  }

  function applyCustomizationPreview(customization) {
    const previewCard = document.getElementById('customizationPreviewCard');
    const previewWordmark = document.getElementById('previewWordmark');
    const previewBookingLink = document.getElementById('previewBookingLink');
    const previewUpgradeBadge = document.getElementById('previewUpgradeBadge');
    const previewLogoWrap = document.getElementById('previewLogoWrap');
    const previewLogoImage = document.getElementById('previewLogoImage');
    const companyInput = document.getElementById('customizationCompanyName');
    const bookingInput = document.getElementById('customizationBookingLink');
    const logoUrlInput = document.getElementById('customizationLogoUrl');
    const accentInput = document.getElementById('customizationAccentColor');
    const accentText = document.getElementById('customizationAccentText');
    const headingSelect = document.getElementById('customizationHeadingFont');
    const bodySelect = document.getElementById('customizationBodyFont');
    const discountType = document.getElementById('customizationDiscountType');
    const discountValue = document.getElementById('customizationDiscountValue');
    const customDomainRequested = document.getElementById('customizationCustomDomainRequested');
    const demoCompleted = document.getElementById('customizationDemoCompleted');

    if (!previewCard || !customization) return;
    latestCustomization = { ...(latestCustomization || {}), ...customization };

    if (companyInput) companyInput.value = customization.company_name || '';
    if (bookingInput) bookingInput.value = customization.booking_link || customization.booking_link_locked_message || '';
    if (logoUrlInput) logoUrlInput.value = customization.logo_url || '';
    if (accentInput) accentInput.value = customization.accent || '#72a9ff';
    if (accentText) accentText.value = customization.accent || '#72a9ff';
    if (headingSelect) {
      populateFontSelect(headingSelect, customization.font_choices || [], customization.heading_font);
    }
    if (bodySelect) {
      populateFontSelect(bodySelect, customization.font_choices || [], customization.body_font);
    }
    if (discountType) discountType.value = customization.discount_type || 'none';
    if (discountValue) discountValue.value = customization.discount_value ?? 0;
    if (customDomainRequested) customDomainRequested.checked = Boolean(customization.custom_domain_requested);
    if (demoCompleted) demoCompleted.checked = Boolean(customization.demo_completed);

    previewCard.style.setProperty('--preview-accent', customization.accent || '#72a9ff');
    previewCard.style.setProperty('--preview-soft', customization.soft_accent || '#e9f4ff');
    previewCard.style.setProperty('--preview-heading-font', customization.heading_font_css || "'Playfair Display', serif");
    previewCard.style.setProperty('--preview-body-font', customization.body_font_css || "'Poppins', sans-serif");
    if (previewWordmark) previewWordmark.textContent = customization.brand_wordmark || customization.company_name || 'Your Brand';
    if (previewBookingLink) {
      previewBookingLink.textContent = customization.booking_link || customization.booking_link_locked_message || 'https://yourbrand.yobobot.in';
    }
    if (previewUpgradeBadge) {
      previewUpgradeBadge.textContent = customization.custom_domain_requested
        ? 'Custom link upgrade requested'
        : 'Upgrade for custom domain';
    }
    if (previewLogoWrap && previewLogoImage) {
      const logoUrl = customization.logo_url || '';
      previewLogoWrap.classList.toggle('hidden', !logoUrl);
      if (logoUrl) {
        previewLogoImage.src = logoUrl;
      } else {
        previewLogoImage.removeAttribute('src');
      }
    }
    updateCompanyDisplays(customization.company_name || 'Your Rental Company');
    if (customization.bot_questions) {
      renderBotQuestions(
        customization.bot_questions,
        document.getElementById('customizationPanel')?.dataset.canCustomize === 'true'
      );
    }
    renderSetupChecklist(customization);
  }

  function renderSetupChecklist(customization) {
    const checklist = document.getElementById('setupChecklist');
    const bookingLink = document.getElementById('setupBookingLink');
    const bookingHint = document.getElementById('setupBookingHint');
    if (!checklist || !customization?.setup_status) return;

    const status = customization.setup_status;
    const items = [
      [
        'Brand setup',
        status.branding_complete,
        customization.logo_url
          ? 'Colours and logo are ready.'
          : 'Add brand colours and a logo URL in customization.',
      ],
      [
        'Fleet uploaded',
        status.fleet_ready,
        status.fleet_ready
          ? `${status.fleet_count || 0} vehicles saved to this business.`
          : 'Add at least one vehicle in Fleet before launch.',
      ],
      [
        'Pricing complete',
        status.pricing_complete,
        status.pricing_complete
          ? 'Day, week, and month prices are set.'
          : 'Each vehicle needs day, week, and month pricing.',
      ],
      [
        'Demo completed',
        status.demo_completed,
        status.demo_completed
          ? 'Demo and launch review have been marked complete.'
          : 'Mark the Yobobot demo as completed before launch.',
      ],
      [
        'Live link unlocked',
        status.site_enabled,
        status.site_enabled
          ? 'The public booking link is now live.'
          : 'Unlock the live booking link once all setup steps are complete.',
      ],
    ];

    checklist.innerHTML = items
      .map(
        ([label, done, copy]) => `
          <article class="setup-check-item ${done ? 'is-complete' : 'is-pending'}">
            <span class="setup-check-dot" aria-hidden="true"></span>
            <div>
              <strong>${escapeHtml(label)}</strong>
              <small>${escapeHtml(copy)}</small>
            </div>
          </article>
        `
      )
      .join('');

    if (bookingLink) {
      bookingLink.textContent = customization.booking_link || 'Locked until setup is complete';
    }
    if (bookingHint) {
      bookingHint.textContent =
        customization.booking_link_locked_message ||
        (status.site_enabled
          ? 'Your live booking link is ready to share.'
          : 'Complete setup and confirm your Yobobot demo before your public site goes live.');
    }
  }

  async function loadCustomization() {
    if (!config.customizationUrl) return null;
    const data = await fetchJson(config.customizationUrl, {}, 'Unable to load customization.');
    if (!data) return null;
    applyCustomizationPreview(data.customization || {});
    return data.customization || null;
  }

  function bindCustomizationForm() {
    const form = document.getElementById('customizationForm');
    const accentInput = document.getElementById('customizationAccentColor');
    const accentText = document.getElementById('customizationAccentText');
    const headingSelect = document.getElementById('customizationHeadingFont');
    const bodySelect = document.getElementById('customizationBodyFont');
    const companyInput = document.getElementById('customizationCompanyName');
    const logoUrlInput = document.getElementById('customizationLogoUrl');
    const discountTypeInput = document.getElementById('customizationDiscountType');
    const discountValueInput = document.getElementById('customizationDiscountValue');
    const customDomainRequestedInput = document.getElementById('customizationCustomDomainRequested');
    const demoCompletedInput = document.getElementById('customizationDemoCompleted');
    const launchSiteButton = document.getElementById('launchSiteButton');
    const editable = document.getElementById('customizationPanel')?.dataset.canCustomize === 'true';

    if (!form || !accentInput || !accentText || !headingSelect || !bodySelect || !companyInput) return;

    function reflectFormPreview() {
      applyCustomizationPreview({
        company_name: companyInput.value.trim() || 'Your Rental Company',
        brand_wordmark: companyInput.value.trim() || 'Your Brand',
        booking_link: document.getElementById('customizationBookingLink')?.value || '',
        booking_link_locked_message:
          document.getElementById('customizationBookingLink')?.value ? '' : 'Complete setup to unlock your live booking link.',
        accent: accentInput.value || accentText.value || '#72a9ff',
        soft_accent: accentInput.value || accentText.value || '#72a9ff',
        heading_font: headingSelect.value,
        body_font: bodySelect.value,
        heading_font_css: headingSelect.selectedOptions[0]?.dataset.css || "'Playfair Display', serif",
        body_font_css: bodySelect.selectedOptions[0]?.dataset.css || "'Poppins', sans-serif",
        logo_url: logoUrlInput?.value.trim() || '',
        discount_type: discountTypeInput?.value || 'none',
        discount_value: Number(discountValueInput?.value || 0),
        custom_domain_requested: Boolean(customDomainRequestedInput?.checked),
        demo_completed: Boolean(demoCompletedInput?.checked),
        setup_status: latestCustomization?.setup_status || {
          branding_complete: Boolean(companyInput.value.trim() && logoUrlInput?.value.trim()),
          fleet_ready: Boolean(latestCustomization?.setup_status?.fleet_ready),
          pricing_complete: Boolean(latestCustomization?.setup_status?.pricing_complete),
          demo_completed: Boolean(demoCompletedInput?.checked),
          fleet_count: Number(latestCustomization?.setup_status?.fleet_count || 0),
          site_enabled: Boolean(latestCustomization?.site_enabled),
        },
        font_choices: Array.from(headingSelect.options).map((option) => ({
          id: option.value,
          label: option.textContent,
          css: option.dataset.css || '',
        })),
      });
    }

    accentInput.addEventListener('input', () => {
      accentText.value = accentInput.value;
      reflectFormPreview();
    });
    accentText.addEventListener('input', () => {
      if (/^#[0-9a-fA-F]{6}$/.test(accentText.value.trim())) {
        accentInput.value = accentText.value.trim();
      }
      reflectFormPreview();
    });
    [headingSelect, bodySelect, companyInput, logoUrlInput, discountTypeInput, discountValueInput].forEach((input) => {
      if (!input) return;
      input.addEventListener('input', reflectFormPreview);
      input.addEventListener('change', reflectFormPreview);
    });
    [customDomainRequestedInput, demoCompletedInput].forEach((input) => {
      if (!input) return;
      input.addEventListener('change', reflectFormPreview);
    });

    if (!editable) return;

    async function submitCustomization(launchSite) {
      form.classList.add('loading');
      try {
        const maxCount = Number(document.getElementById('botQuestionCounter')?.dataset.maxCount || '10');
        const alwaysCount = Number(document.getElementById('botQuestionCounter')?.dataset.alwaysCount || '0');
        const botFields = collectBotFields();
        const selectedCount = botFields.filter((field) => field.enabled).length;
        if (alwaysCount + selectedCount > maxCount) {
          throw new Error(`Choose at most ${maxCount} total bot questions before saving.`);
        }

        const data = await fetchJson(
          config.customizationUrl,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
              company_name: companyInput.value.trim(),
              brand_wordmark: companyInput.value.trim(),
              accent: accentText.value.trim() || accentInput.value,
              heading_font: headingSelect.value,
              body_font: bodySelect.value,
              bot_fields: botFields,
              logo_url: logoUrlInput?.value.trim() || '',
              discount_type: discountTypeInput?.value || 'none',
              discount_value: Number(discountValueInput?.value || 0),
              discount_label:
                (discountTypeInput?.value || 'none') === 'none'
                  ? ''
                  : `${discountTypeInput?.value === 'percentage' ? `${discountValueInput?.value || 0}%` : discountValueInput?.value || 0} discount`,
              custom_domain_requested: Boolean(customDomainRequestedInput?.checked),
              demo_completed: Boolean(demoCompletedInput?.checked),
              launch_site: Boolean(launchSite),
            }),
          },
          'Unable to save customization.'
        );
        if (!data) return;
        applyCustomizationPreview(data.customization || {});
        setFeedback(
          launchSite
            ? 'Customization saved and the live booking link is now unlocked.'
            : 'Customization saved for the booking brand.',
          'success'
        );
      } catch (error) {
        setFeedback(error.message || 'Unable to save customization.', 'error');
      } finally {
        form.classList.remove('loading');
      }
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      await submitCustomization(false);
    });

    if (launchSiteButton) {
      launchSiteButton.addEventListener('click', async () => {
        await submitCustomization(true);
      });
    }
  }

  function buildPreviewBookings(items) {
    const today = parseDate(todayIso());
    const live = items.filter((item) => {
      const start = parseDate(item.from_date);
      const end = parseDate(item.to_date) || start;
      return start && end && end >= today;
    });
    return (live.length ? live : items).slice(0, 5);
  }

  function renderOverviewBookings(items) {
    const list = document.getElementById('overviewBookingList');
    const empty = document.getElementById('overviewBookingEmpty');
    if (!list || !empty) return;

    const preview = buildPreviewBookings(items || []);
    if (!preview.length) {
      list.innerHTML = '';
      empty.classList.remove('hidden');
      return;
    }

    empty.classList.add('hidden');
    list.innerHTML = preview
      .map(
        (item) => `
          <article class="booking-preview-item">
            <div class="booking-preview-main">
              <strong>${escapeHtml(item.customer_name)}</strong>
              <span>${escapeHtml(item.car_model)} · ${escapeHtml(item.phone || 'No phone')}</span>
            </div>
            <div class="booking-preview-side">
              <strong>${escapeHtml(formatCompactDate(item.from_date))} - ${escapeHtml(formatCompactDate(item.to_date))}</strong>
              <span>${escapeHtml(item.payment_status)} · ${escapeHtml(item.confirmation_label)}</span>
            </div>
          </article>
        `
      )
      .join('');
  }

  function renderOverviewStats(summary) {
    const mappings = [
      ['overviewStatVisits', summary.site_visits || 0],
      ['overviewStatTotal', summary.total || 0],
      ['overviewStatConfirmed', summary.confirmed || 0],
      ['overviewStatPending', summary.pending || 0],
      ['overviewStatPaid', summary.paid || 0],
      ['overviewStatFleet', summary.fleet || 0],
    ];
    mappings.forEach(([id, value]) => {
      const element = document.getElementById(id);
      if (element) element.textContent = value;
    });
  }

  function renderOverviewAvailability(data) {
    const total = document.getElementById('overviewCarsTotal');
    const available = document.getElementById('overviewCarsAvailable');
    const booked = document.getElementById('overviewCarsBooked');
    const weekPreview = document.getElementById('overviewWeekPreview');
    if (!total || !available || !booked || !weekPreview) return;

    total.textContent = data.summary?.total_cars || 0;
    available.textContent = data.summary?.available_today || 0;
    booked.textContent = data.summary?.booked_today || 0;

    const dayCounts = (data.week?.days || []).map((day, dayIndex) => {
      const resources = data.resources || [];
      let free = 0;
      let busy = 0;
      resources.forEach((resource) => {
        const status = resource.days?.[dayIndex]?.status;
        if (status === 'available') free += 1;
        if (status === 'booked') busy += 1;
      });
      return { ...day, free, busy };
    });

    weekPreview.innerHTML = dayCounts
      .map(
        (day) => `
          <article class="mini-week-day">
            <strong>${escapeHtml(day.weekday)}</strong>
            <span>${escapeHtml(String(day.day_number))}</span>
            <small>${escapeHtml(`${day.free} free / ${day.busy} booked`)}</small>
          </article>
        `
      )
      .join('');
  }

  async function initOverview() {
    setFeedback('', '');
    try {
      bindCustomizationForm();
      const [bookingData, availabilityData, customization] = await Promise.all([
        fetchJson(`${config.bookingsUrl}?timeline=all`, {}, 'Unable to load dashboard bookings.'),
        fetchJson(`${config.availabilityUrl}?date=${encodeURIComponent(todayIso())}`, {}, 'Unable to load availability.'),
        loadCustomization().catch((error) => ({
          warning: error.message || 'Unable to load customization.',
        })),
      ]);
      if (!bookingData || !availabilityData) return;
      renderOverviewStats({
        ...(bookingData.summary || {}),
        site_visits: customization?.overview_metrics?.site_visits || 0,
        fleet: availabilityData.summary?.total_cars || 0,
      });
      renderOverviewBookings(bookingData.items || []);
      renderOverviewAvailability(availabilityData);

      const warning = [bookingData.warning, availabilityData.warning, customization?.warning]
        .filter(Boolean)
        .join(' ');
      setFeedback(warning, warning ? 'warning' : '');
    } catch (error) {
      setFeedback(error.message || 'Unable to load the dashboard overview.', 'error');
    }
  }

  function phoneMarkup(value) {
    const safe = String(value || '').trim();
    if (!safe) return '<span>No phone number</span>';
    return `<a class="phone-link" href="tel:${escapeHtml(safe)}">${escapeHtml(safe)}</a>`;
  }

  function paymentOptions(selected) {
    const normalized = String(selected || 'Pending');
    const options = ['Pending', 'Paid', 'Partially Paid', 'Failed'];
    return options
      .map((option) => {
        const isSelected = option.toLowerCase() === normalized.toLowerCase() ? ' selected' : '';
        return `<option value="${escapeHtml(option)}"${isSelected}>${escapeHtml(option)}</option>`;
      })
      .join('');
  }

  function customFieldValue(item, key) {
    const fields = item && typeof item.custom_fields === 'object' ? item.custom_fields : {};
    const value = fields ? fields[key] : '';
    return value === null || value === undefined || value === '' ? 'Not provided' : String(value);
  }

  function bookingGridTemplate(customColumns) {
    const extraColumns = (customColumns || []).map(() => 'minmax(150px, 0.9fr)');
    return [
      '72px',
      '1.1fr',
      '1fr',
      ...extraColumns,
      '0.9fr',
      '0.9fr',
      '1fr',
      '0.9fr',
      '0.95fr',
      '0.95fr',
    ].join(' ');
  }

  function bookingGridMinWidth(customColumns) {
    return `${1180 + ((customColumns || []).length * 170)}px`;
  }

  function renderBookingsTableHead(customColumns) {
    const head = document.getElementById('bookingsTableHead');
    const wrap = document.querySelector('.bookings-table-wrap');
    if (!head || !wrap) return;
    wrap.style.setProperty('--bookings-grid-template', bookingGridTemplate(customColumns));
    wrap.style.setProperty('--bookings-table-min-width', bookingGridMinWidth(customColumns));
    head.innerHTML = [
      '<span>S.No.</span>',
      '<span>Customer</span>',
      '<span>Phone</span>',
      ...(customColumns || []).map((column) => `<span>${escapeHtml(column.label || column.key || 'Detail')}</span>`),
      '<span>From</span>',
      '<span>To</span>',
      '<span>Car</span>',
      '<span>Booked for</span>',
      '<span>Payment</span>',
      '<span>Confirmed</span>',
    ].join('');
  }

  function bookingRowMarkup(item, index, customColumns) {
    const checked = item.is_confirmed ? 'checked' : '';
    return `
      <article class="data-row" data-booking-id="${escapeHtml(item.id)}">
        <div class="row-index">${index}</div>
        <div class="row-main">
          <strong>${escapeHtml(item.customer_name)}</strong>
          <span>${escapeHtml(item.location || item.city || item.car_color || 'Customer booking')}</span>
        </div>
        <div class="row-main">
          ${phoneMarkup(item.phone)}
          <span>${escapeHtml(item.city || 'No city')}</span>
        </div>
        ${(customColumns || [])
          .map(
            (column) => `
              <div class="row-main custom-field-cell">
                <strong>${escapeHtml(customFieldValue(item, column.key))}</strong>
                <span>${escapeHtml(column.label || column.key || 'Detail')}</span>
              </div>
            `
          )
          .join('')}
        <div class="date-cell">
          <strong>${escapeHtml(formatDate(item.from_date))}</strong>
          <span>Pickup</span>
        </div>
        <div class="date-cell">
          <strong>${escapeHtml(formatDate(item.to_date))}</strong>
          <span>Return</span>
        </div>
        <div class="row-main">
          <strong>${escapeHtml(item.car_model)}</strong>
          <span>${escapeHtml(item.car_color || item.car_make || 'Vehicle')}</span>
        </div>
        <div class="length-cell">
          <strong>${escapeHtml(item.booking_length_label)}</strong>
          <span>${escapeHtml(formatMoney(item.total_price))}</span>
        </div>
        <div>
          <select class="status-select payment-select" aria-label="Payment status">
            ${paymentOptions(item.payment_status)}
          </select>
        </div>
        <div class="confirm-wrap">
          <label class="toggle">
            <input class="confirm-toggle" type="checkbox" ${checked} aria-label="Confirm booking" />
            <span class="slider"></span>
          </label>
          <div>
            <span class="confirm-text">${escapeHtml(item.confirmation_label)}</span>
            <span class="confirm-subtext">Employee choice</span>
          </div>
        </div>
      </article>
    `;
  }

  function downloadCsv(filename, rows) {
    const csv = rows
      .map((columns) =>
        columns
          .map((value) => {
            const safe = String(value ?? '');
            return `"${safe.replaceAll('"', '""')}"`;
          })
          .join(',')
      )
      .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
  }

  async function initBookings() {
    const searchInput = document.getElementById('searchInput');
    const startDateFilter = document.getElementById('startDateFilter');
    const endDateFilter = document.getElementById('endDateFilter');
    const carModelFilter = document.getElementById('carModelFilter');
    const confirmationFilter = document.getElementById('confirmationFilter');
    const paymentFilter = document.getElementById('paymentFilter');
    const resetFiltersButton = document.getElementById('resetFilters');
    const rowsContainer = document.getElementById('rowsContainer');
    const emptyState = document.getElementById('emptyState');
    const resultsCount = document.getElementById('resultsCount');
    const statTotal = document.getElementById('statTotal');
    const statConfirmed = document.getElementById('statConfirmed');
    const statPending = document.getElementById('statPending');
    const statPaid = document.getElementById('statPaid');
    const timelineTabs = Array.from(document.querySelectorAll('[data-timeline]'));
    const downloadButton = document.getElementById('downloadBookingsButton');

    const state = {
      items: [],
      customColumns: [],
      timeline: 'current',
      timer: null,
    };

    function renderStats(summary) {
      statTotal.textContent = summary.total || 0;
      statConfirmed.textContent = summary.confirmed || 0;
      statPending.textContent = summary.pending || 0;
      statPaid.textContent = summary.paid || 0;
    }

    function renderRows(items) {
      resultsCount.textContent = `${items.length} result${items.length === 1 ? '' : 's'}`;
      if (!items.length) {
        rowsContainer.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
      }
      emptyState.classList.add('hidden');
      rowsContainer.innerHTML = items
        .map((item, index) => bookingRowMarkup(item, index + 1, state.customColumns))
        .join('');
      bindRowEvents();
    }

    function applyLocalStateUpdate(bookingId, patch) {
      state.items = state.items.map((item) => {
        if (String(item.id) !== String(bookingId)) return item;
        const nextItem = { ...item, ...patch };
        nextItem.confirmation_label = nextItem.is_confirmed ? 'Confirmed' : 'Pending';
        return nextItem;
      });
      renderStats({
        total: state.items.length,
        confirmed: state.items.filter((item) => item.is_confirmed).length,
        pending: state.items.filter((item) => !item.is_confirmed).length,
        paid: state.items.filter((item) => String(item.payment_status).toLowerCase() === 'paid').length,
      });
    }

    async function saveState(bookingId, payload) {
      const url = config.updateUrlTemplate.replace('__BOOKING_ID__', encodeURIComponent(bookingId));
      const data = await fetchJson(
        url,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify(payload),
        },
        'Unable to save booking state.'
      );
      return data?.state || {};
    }

    function bindRowEvents() {
      rowsContainer.querySelectorAll('.data-row').forEach((row) => {
        const bookingId = row.dataset.bookingId;
        const confirmToggle = row.querySelector('.confirm-toggle');
        const paymentSelect = row.querySelector('.payment-select');
        const confirmText = row.querySelector('.confirm-text');

        paymentSelect.dataset.previous = paymentSelect.value;

        confirmToggle.addEventListener('change', async () => {
          row.classList.add('loading');
          const previous = !confirmToggle.checked;
          try {
            const update = await saveState(bookingId, { is_confirmed: confirmToggle.checked });
            const nextConfirmed = Boolean(update.is_confirmed);
            confirmToggle.checked = nextConfirmed;
            confirmText.textContent = nextConfirmed ? 'Confirmed' : 'Pending';
            applyLocalStateUpdate(bookingId, { is_confirmed: nextConfirmed });
            setFeedback('Booking confirmation updated.', 'success');
          } catch (error) {
            confirmToggle.checked = previous;
            setFeedback(error.message || 'Unable to update confirmation.', 'error');
          } finally {
            row.classList.remove('loading');
          }
        });

        paymentSelect.addEventListener('change', async () => {
          row.classList.add('loading');
          const previous = paymentSelect.dataset.previous || paymentSelect.value;
          try {
            const update = await saveState(bookingId, { payment_status: paymentSelect.value });
            const nextStatus = update.payment_status || paymentSelect.value;
            paymentSelect.value = nextStatus;
            paymentSelect.dataset.previous = nextStatus;
            applyLocalStateUpdate(bookingId, { payment_status: nextStatus });
            setFeedback('Payment status updated.', 'success');
          } catch (error) {
            paymentSelect.value = previous;
            setFeedback(error.message || 'Unable to update payment status.', 'error');
          } finally {
            row.classList.remove('loading');
          }
        });
      });
    }

    function buildQuery() {
      const params = new URLSearchParams();
      params.set('timeline', state.timeline);
      if (searchInput.value.trim()) params.set('q', searchInput.value.trim());
      if (startDateFilter.value) params.set('start_date', startDateFilter.value);
      if (endDateFilter.value) params.set('end_date', endDateFilter.value);
      if (carModelFilter.value) params.set('car_model', carModelFilter.value);
      if (confirmationFilter.value) params.set('confirmation', confirmationFilter.value);
      if (paymentFilter.value) params.set('payment', paymentFilter.value);
      return params.toString();
    }

    async function loadBookings() {
      rowsContainer.innerHTML = `
        <article class="data-row loading">
          <div class="row-main">
            <strong>Loading bookings...</strong>
            <span>Please wait while the dashboard refreshes.</span>
          </div>
        </article>
      `;
      emptyState.classList.add('hidden');
      setFeedback('', '');
      try {
        const data = await fetchJson(`${config.bookingsUrl}?${buildQuery()}`, {}, 'Unable to load dashboard bookings.');
        if (!data) return;
        state.items = data.items || [];
        state.customColumns = data.custom_columns || [];
        renderBookingsTableHead(state.customColumns);
        updateSelectOptions(carModelFilter, data.models || [], 'All cars');
        renderStats(data.summary || {});
        renderRows(state.items);
        if (data.warning) setFeedback(data.warning, 'warning');
      } catch (error) {
        renderStats({ total: 0, confirmed: 0, pending: 0, paid: 0 });
        rowsContainer.innerHTML = '';
        emptyState.classList.remove('hidden');
        setFeedback(error.message || 'Unable to load dashboard bookings.', 'error');
      }
    }

    function debounceLoad() {
      window.clearTimeout(state.timer);
      state.timer = window.setTimeout(loadBookings, 220);
    }

    timelineTabs.forEach((button) => {
      button.addEventListener('click', () => {
        state.timeline = button.dataset.timeline || 'current';
        timelineTabs.forEach((item) => item.classList.toggle('is-active', item === button));
        loadBookings();
      });
    });

    resetFiltersButton.addEventListener('click', () => {
      searchInput.value = '';
      startDateFilter.value = '';
      endDateFilter.value = '';
      carModelFilter.value = '';
      confirmationFilter.value = 'all';
      paymentFilter.value = 'all';
      state.timeline = 'current';
      timelineTabs.forEach((button) => button.classList.toggle('is-active', button.dataset.timeline === 'current'));
      loadBookings();
    });

    [searchInput].forEach((input) => input.addEventListener('input', debounceLoad));
    [startDateFilter, endDateFilter, carModelFilter, confirmationFilter, paymentFilter].forEach((input) => {
      input.addEventListener('change', loadBookings);
    });

    if (downloadButton) {
      downloadButton.addEventListener('click', () => {
        const rows = [
          [
            'Customer',
            'Phone',
            ...state.customColumns.map((column) => column.label || column.key || 'Detail'),
            'From Date',
            'To Date',
            'Car',
            'Booked For',
            'Payment',
            'Confirmed',
          ],
          ...state.items.map((item) => [
            item.customer_name,
            item.phone,
            ...state.customColumns.map((column) => customFieldValue(item, column.key)),
            item.from_date,
            item.to_date,
            item.car_model,
            item.booking_length_label,
            item.payment_status,
            item.confirmation_label,
          ]),
        ];
        downloadCsv('employee-bookings.csv', rows);
      });
    }

    renderBookingsTableHead([]);
    loadBookings();
  }

  async function initAvailability() {
    const searchInput = document.getElementById('availabilitySearchInput');
    const dateInput = document.getElementById('availabilityDateInput');
    const carFilter = document.getElementById('availabilityCarFilter');
    const modelFilter = document.getElementById('availabilityModelFilter');
    const colorFilter = document.getElementById('availabilityColorFilter');
    const resetButton = document.getElementById('availabilityResetButton');
    const prevButton = document.getElementById('availabilityPrevButton');
    const nextButton = document.getElementById('availabilityNextButton');
    const todayButton = document.getElementById('availabilityTodayButton');
    const rangeLabel = document.getElementById('availabilityRangeLabel');
    const totalCars = document.getElementById('availabilityTotalCars');
    const todayFree = document.getElementById('availabilityTodayFree');
    const todayBooked = document.getElementById('availabilityTodayBooked');
    const dayHeader = document.getElementById('availabilityDayHeader');
    const boardRows = document.getElementById('availabilityBoardRows');
    const emptyState = document.getElementById('availabilityEmptyState');
    const monthLabel = document.getElementById('availabilityMonthLabel');
    const calendarGrid = document.getElementById('availabilityCalendarGrid');
    const boardPanel = document.getElementById('availabilityBoardPanel');
    const calendarPanel = document.getElementById('availabilityCalendarPanel');
    const viewButtons = Array.from(document.querySelectorAll('[data-view]'));

    const state = {
      view: 'board',
      timer: null,
      selectedDate: todayIso(),
      latestData: null,
    };

    function buildQuery() {
      const params = new URLSearchParams();
      params.set('date', dateInput.value || state.selectedDate || todayIso());
      if (searchInput.value.trim()) params.set('q', searchInput.value.trim());
      if (carFilter.value) params.set('car_id', carFilter.value);
      if (modelFilter.value) params.set('model', modelFilter.value);
      if (colorFilter.value) params.set('color', colorFilter.value);
      return params.toString();
    }

    function renderBoard(data) {
      totalCars.textContent = data.summary?.total_cars || 0;
      todayFree.textContent = data.summary?.available_today || 0;
      todayBooked.textContent = data.summary?.booked_today || 0;
      rangeLabel.textContent =
        state.view === 'calendar'
          ? data.calendar?.month_label || 'Calendar'
          : data.week?.label || 'Range unavailable';

      dayHeader.innerHTML = (data.week?.days || [])
        .map(
          (day) => `
            <span>
              ${escapeHtml(day.weekday)}
              <strong>${escapeHtml(String(day.day_number))}</strong>
            </span>
          `
        )
        .join('');

      if (!(data.resources || []).length) {
        boardRows.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
      }

      emptyState.classList.add('hidden');
      boardRows.innerHTML = (data.resources || [])
        .map(
          (resource) => `
            <article class="availability-row">
              <div class="availability-car-card">
                <strong>${escapeHtml(resource.label)}</strong>
                <span>${escapeHtml(resource.model)} · ${escapeHtml(resource.color)}</span>
                <span>${escapeHtml(resource.location)}</span>
              </div>
              <div class="availability-row-days">
                ${(resource.days || [])
                  .map(
                    (day) => `
                      <div class="availability-day-cell ${escapeHtml(day.status)}">
                        <strong>${escapeHtml(day.headline)}</strong>
                        <span>${escapeHtml(day.label)} ${escapeHtml(String(day.day_number))}</span>
                        <small>${escapeHtml(day.subtext)}</small>
                      </div>
                    `
                  )
                  .join('')}
              </div>
            </article>
          `
        )
        .join('');
    }

    function renderCalendar(data) {
      monthLabel.textContent = data.calendar?.month_label || 'Calendar';
      calendarGrid.innerHTML = (data.calendar?.weeks || [])
        .flat()
        .map(
          (cell) => `
            <article class="calendar-cell ${escapeHtml(cell.status)} ${cell.is_current_month ? '' : 'is-outside'}">
              <strong>${escapeHtml(String(cell.day_number))}</strong>
              <span>${escapeHtml(`${cell.booked_count} booked`)}</span>
              <small>${escapeHtml(`${cell.available_count} available`)}</small>
            </article>
          `
        )
        .join('');
    }

    function setView(nextView) {
      state.view = nextView;
      viewButtons.forEach((button) => {
        button.classList.toggle('is-active', button.dataset.view === nextView);
      });
      boardPanel.classList.toggle('hidden', nextView !== 'board');
      calendarPanel.classList.toggle('hidden', nextView !== 'calendar');
      if (state.latestData) {
        rangeLabel.textContent =
          nextView === 'calendar'
            ? state.latestData.calendar?.month_label || 'Calendar'
            : state.latestData.week?.label || 'Range unavailable';
      }
    }

    async function loadAvailability() {
      boardRows.innerHTML = `
        <article class="availability-row">
          <div class="availability-car-card">
            <strong>Loading availability...</strong>
            <span>Please wait while the schedule refreshes.</span>
          </div>
        </article>
      `;
      emptyState.classList.add('hidden');
      setFeedback('', '');
      try {
        const data = await fetchJson(
          `${config.availabilityUrl}?${buildQuery()}`,
          {},
          'Unable to load availability.'
        );
        if (!data) return;

        state.latestData = data;
        state.selectedDate = data.selected_date || dateInput.value || todayIso();
        if (!dateInput.value || dateInput.value !== state.selectedDate) {
          dateInput.value = state.selectedDate;
        }

        updateSelectOptions(
          carFilter,
          (data.filters?.cars || []).map((car) => ({ value: car.id, label: car.label })),
          'All cars'
        );
        updateSelectOptions(modelFilter, data.filters?.models || [], 'All models');
        updateSelectOptions(colorFilter, data.filters?.colors || [], 'All colors');

        renderBoard(data);
        renderCalendar(data);

        const extraWarnings = [];
        if (data.warning) extraWarnings.push(data.warning);
        if (data.unassigned_count) {
          extraWarnings.push(`${data.unassigned_count} bookings could not be matched to a specific fleet car.`);
        }
        setFeedback(extraWarnings.join(' '), extraWarnings.length ? 'warning' : '');
      } catch (error) {
        boardRows.innerHTML = '';
        emptyState.classList.remove('hidden');
        setFeedback(error.message || 'Unable to load availability.', 'error');
      }
    }

    function debounceLoad() {
      window.clearTimeout(state.timer);
      state.timer = window.setTimeout(loadAvailability, 220);
    }

    function shiftDate(step) {
      const current = parseDate(dateInput.value || state.selectedDate || todayIso()) || new Date();
      const next = new Date(current);
      if (state.view === 'calendar') {
        next.setMonth(next.getMonth() + step, 1);
      } else {
        next.setDate(next.getDate() + step * 7);
      }
      dateInput.value = toIsoDate(next);
      loadAvailability();
    }

    viewButtons.forEach((button) => {
      button.addEventListener('click', () => setView(button.dataset.view || 'board'));
    });

    searchInput.addEventListener('input', debounceLoad);
    [dateInput, carFilter, modelFilter, colorFilter].forEach((input) => {
      input.addEventListener('change', loadAvailability);
    });

    resetButton.addEventListener('click', () => {
      searchInput.value = '';
      carFilter.value = '';
      modelFilter.value = '';
      colorFilter.value = '';
      dateInput.value = state.selectedDate || todayIso();
      loadAvailability();
    });

    prevButton.addEventListener('click', () => shiftDate(-1));
    nextButton.addEventListener('click', () => shiftDate(1));
    if (todayButton) {
      todayButton.addEventListener('click', () => {
        dateInput.value = todayIso();
        loadAvailability();
      });
    }

    setView('board');
    dateInput.value = todayIso();
    loadAvailability();
  }

  function parseCsvLine(line) {
    const values = [];
    let current = '';
    let insideQuotes = false;
    for (let index = 0; index < line.length; index += 1) {
      const character = line[index];
      if (character === '"') {
        const next = line[index + 1];
        if (insideQuotes && next === '"') {
          current += '"';
          index += 1;
        } else {
          insideQuotes = !insideQuotes;
        }
      } else if (character === ',' && !insideQuotes) {
        values.push(current.trim());
        current = '';
      } else {
        current += character;
      }
    }
    values.push(current.trim());
    return values;
  }

  function parseCsvText(text) {
    const lines = String(text || '')
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean);
    if (!lines.length) return [];
    const headers = parseCsvLine(lines[0]).map((value) => value.toLowerCase());
    return lines.slice(1).map((line) => {
      const values = parseCsvLine(line);
      const row = {};
      headers.forEach((header, index) => {
        row[header] = values[index] || '';
      });
      return row;
    });
  }

  function readFileAsText(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result || ''));
      reader.onerror = () => reject(new Error('Unable to read the selected file.'));
      reader.readAsText(file);
    });
  }

  function formatPriceLine(item) {
    return `${formatMoney(item.price_per_day)} / day · ${formatMoney(item.price_per_week)} / week · ${formatMoney(item.price_per_month)} / month`;
  }

  async function initFleet() {
    const form = document.getElementById('fleetForm');
    const idInput = document.getElementById('fleetId');
    const makeInput = document.getElementById('fleetMake');
    const modelInput = document.getElementById('fleetModel');
    const colorInput = document.getElementById('fleetColor');
    const plateInput = document.getElementById('fleetNumberPlate');
    const photoInput = document.getElementById('fleetPhotoUrl');
    const categoryInput = document.getElementById('fleetCategory');
    const branchInput = document.getElementById('fleetBranchLocation');
    const dayInput = document.getElementById('fleetPricePerDay');
    const weekInput = document.getElementById('fleetPricePerWeek');
    const monthInput = document.getElementById('fleetPricePerMonth');
    const availableInput = document.getElementById('fleetAvailable');
    const resetButton = document.getElementById('fleetResetButton');
    const importTrigger = document.getElementById('fleetImportTrigger');
    const importFile = document.getElementById('fleetImportFile');
    const importReplace = document.getElementById('fleetReplaceExisting');
    const importButton = document.getElementById('fleetImportButton');
    const rowsRoot = document.getElementById('fleetRows');
    const emptyState = document.getElementById('fleetEmptyState');
    const countStat = document.getElementById('fleetCountStat');
    const pricedStat = document.getElementById('fleetPricedStat');

    if (!form || !rowsRoot || !emptyState) return;

    const state = {
      items: [],
    };

    function resetForm() {
      form.reset();
      if (idInput) idInput.value = '';
      if (availableInput) availableInput.checked = true;
    }

    function populateForm(item) {
      if (!item) return;
      if (idInput) idInput.value = item.id || '';
      if (makeInput) makeInput.value = item.make || '';
      if (modelInput) modelInput.value = item.model || '';
      if (colorInput) colorInput.value = item.color || '';
      if (plateInput) plateInput.value = item.number_plate || '';
      if (photoInput) photoInput.value = item.photo_url || '';
      if (categoryInput) categoryInput.value = item.category || '';
      if (branchInput) branchInput.value = item.branch_location || item.city || '';
      if (dayInput) dayInput.value = item.price_per_day ?? '';
      if (weekInput) weekInput.value = item.price_per_week ?? '';
      if (monthInput) monthInput.value = item.price_per_month ?? '';
      if (availableInput) availableInput.checked = Boolean(item.available);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function collectPayload() {
      return {
        id: idInput?.value || '',
        make: makeInput?.value.trim() || '',
        model: modelInput?.value.trim() || '',
        color: colorInput?.value.trim() || '',
        number_plate: plateInput?.value.trim() || '',
        photo_url: photoInput?.value.trim() || '',
        category: categoryInput?.value.trim() || '',
        branch_location: branchInput?.value.trim() || '',
        city: branchInput?.value.trim() || '',
        price_per_day: Number(dayInput?.value || 0),
        price_per_week: Number(weekInput?.value || 0),
        price_per_month: Number(monthInput?.value || 0),
        available: Boolean(availableInput?.checked),
      };
    }

    function renderFleet(items) {
      const pricedCount = items.filter((item) =>
        Number(item.price_per_day || 0) > 0 &&
        Number(item.price_per_week || 0) > 0 &&
        Number(item.price_per_month || 0) > 0
      ).length;
      if (countStat) countStat.textContent = items.length;
      if (pricedStat) pricedStat.textContent = pricedCount;

      if (!items.length) {
        rowsRoot.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
      }
      emptyState.classList.add('hidden');
      rowsRoot.innerHTML = items
        .map(
          (item) => `
            <article class="fleet-card" data-fleet-id="${escapeHtml(item.id)}">
              <div class="fleet-card-main">
                <div>
                  <strong>${escapeHtml(`${item.make || ''} ${item.model || ''}`.trim() || 'Unnamed vehicle')}</strong>
                  <span>${escapeHtml(item.category || 'Uncategorized')} · ${escapeHtml(item.color || 'No colour')}</span>
                  <span>${escapeHtml(item.branch_location || item.city || 'No branch')}</span>
                  <small>${escapeHtml(formatPriceLine(item))}</small>
                </div>
                ${item.photo_url ? `<img class="fleet-card-thumb" src="${escapeHtml(item.photo_url)}" alt="${escapeHtml(item.model || item.make || 'Vehicle')}" />` : ''}
              </div>
              <div class="fleet-card-meta">
                <span>${escapeHtml(item.number_plate || 'No number plate')}</span>
                <span>${item.available ? 'Available' : 'Hidden'}</span>
              </div>
              <div class="fleet-card-actions">
                <button type="button" class="ghost-button small" data-action="edit">Edit</button>
                <button type="button" class="ghost-button small" data-action="delete">Delete</button>
              </div>
            </article>
          `
        )
        .join('');

      rowsRoot.querySelectorAll('.fleet-card').forEach((card) => {
        const fleetId = card.dataset.fleetId;
        const item = state.items.find((entry) => String(entry.id) === String(fleetId));
        card.querySelector('[data-action="edit"]')?.addEventListener('click', () => populateForm(item));
        card.querySelector('[data-action="delete"]')?.addEventListener('click', async () => {
          if (!fleetId) return;
          if (!window.confirm('Remove this vehicle from the fleet?')) return;
          try {
            const url = config.fleetDeleteUrlTemplate.replace('__FLEET_ID__', encodeURIComponent(fleetId));
            await fetchJson(url, { method: 'DELETE' }, 'Unable to delete the vehicle.');
            setFeedback('Vehicle removed from the fleet.', 'success');
            await loadFleet();
          } catch (error) {
            setFeedback(error.message || 'Unable to delete the vehicle.', 'error');
          }
        });
      });
    }

    async function loadFleet() {
      try {
        const data = await fetchJson(config.fleetUrl, {}, 'Unable to load fleet details.');
        if (!data) return;
        state.items = data.items || [];
        renderFleet(state.items);
      } catch (error) {
        rowsRoot.innerHTML = '';
        emptyState.classList.remove('hidden');
        setFeedback(error.message || 'Unable to load fleet details.', 'error');
      }
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      form.classList.add('loading');
      try {
        const data = await fetchJson(
          config.fleetUrl,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(collectPayload()),
          },
          'Unable to save the vehicle.'
        );
        if (!data) return;
        setFeedback('Vehicle saved to the business fleet.', 'success');
        resetForm();
        await loadFleet();
      } catch (error) {
        setFeedback(error.message || 'Unable to save the vehicle.', 'error');
      } finally {
        form.classList.remove('loading');
      }
    });

    resetButton?.addEventListener('click', resetForm);
    importTrigger?.addEventListener('click', () => importFile?.click());
    importButton?.addEventListener('click', async () => {
      if (!importFile?.files?.length) {
        setFeedback('Choose a CSV or JSON file first.', 'warning');
        return;
      }
      importButton.disabled = true;
      try {
        const file = importFile.files[0];
        const text = await readFileAsText(file);
        const items = file.name.toLowerCase().endsWith('.json')
          ? JSON.parse(text)
          : parseCsvText(text);
        if (!Array.isArray(items)) {
          throw new Error('The uploaded file must contain a list of vehicles.');
        }
        const data = await fetchJson(
          config.fleetImportUrl,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
              items,
              replace_existing: Boolean(importReplace?.checked),
            }),
          },
          'Unable to import fleet details.'
        );
        if (!data) return;
        setFeedback(`${data.imported || 0} vehicles imported for this business.`, 'success');
        if (importFile) importFile.value = '';
        await loadFleet();
      } catch (error) {
        setFeedback(error.message || 'Unable to import the fleet file.', 'error');
      } finally {
        importButton.disabled = false;
      }
    });

    resetForm();
    loadFleet();
  }

  bindSidebarChrome();
  bindInviteForm();

  if (page === 'overview') {
    initOverview();
  } else if (page === 'bookings') {
    initBookings();
  } else if (page === 'availability') {
    initAvailability();
  } else if (page === 'fleet') {
    initFleet();
  }
})();
