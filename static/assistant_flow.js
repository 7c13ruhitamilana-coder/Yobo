(() => {
  const thread = document.getElementById('assistantFlowThread');
  const composerForm = document.getElementById('assistantFlowComposer');
  const composerInput = document.getElementById('assistantFlowInput');
  if (!thread || !composerForm || !composerInput) {
    return;
  }

  const params = new URLSearchParams(window.location.search);
  const defaultBizId = document.body.dataset.defaultBizId || '';
  const supportedCities = [
    'Abu Dhabi',
    'Dubai',
    'Sharjah',
    'Ras Al-Khaimah',
    'Al-Ain',
    'Fujairah',
    'Ajman',
  ];
  const processingFee = 50;
  const panelSequence = ['schedule', 'fleet', 'quote', 'location', 'insurance', 'confirm', 'summary'];

  const initialBizId = params.get('biz_id') || localStorage.getItem('biz_id') || defaultBizId || '';
  if (initialBizId) {
    localStorage.setItem('biz_id', initialBizId);
  }

  function buildInitialState() {
    return {
      biz_id: initialBizId,
      stage: 'name',
      full_name: '',
      first_name: '',
      last_name: '',
      city: '',
      pickup_date: '',
      return_date: '',
      rental_days: 0,
      phone: '',
      luxury_filter: 'all',
      fleet_results: [],
      car_id: '',
      car_make: '',
      car_model: '',
      car_luxury: '',
      price_per_day: 0,
      address: '',
      landmark: '',
      pincode: '',
      location: '',
      insurance_plan: 'No insurance',
      insurance_price: 0,
      total_price: 0,
      history: [],
    };
  }

  const state = buildInitialState();
  const panels = {};

  function activeBizId() {
    return state.biz_id || localStorage.getItem('biz_id') || defaultBizId || '';
  }

  function wait(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function addHistory(role, content) {
    state.history.push({ role, content });
    if (state.history.length > 20) {
      state.history = state.history.slice(-20);
    }
  }

  function scrollToNode(node) {
    if (!node) return;
    requestAnimationFrame(() => {
      node.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  function appendMessage(role, text) {
    const block = document.createElement('div');
    block.className = `assistant-flow-message ${role}`;

    const bubble = document.createElement('div');
    bubble.className = 'assistant-flow-bubble';
    bubble.textContent = text;
    block.appendChild(bubble);

    thread.appendChild(block);
    scrollToNode(block);
    return block;
  }

  function userSay(text) {
    addHistory('user', text);
    return appendMessage('user', text);
  }

  function renderTyping() {
    const node = document.createElement('div');
    node.className = 'assistant-flow-message assistant';
    node.innerHTML = `
      <div class="assistant-flow-bubble assistant-flow-bubble-loading">
        <span class="assistant-flow-loader-dot"></span>
        <span class="assistant-flow-loader-dot"></span>
        <span class="assistant-flow-loader-dot"></span>
      </div>
    `;
    thread.appendChild(node);
    scrollToNode(node);
    return node;
  }

  async function assistantBatch(messages, delay = 420) {
    const typingNode = renderTyping();
    await wait(delay);
    typingNode.remove();
    messages.forEach((message) => {
      addHistory('assistant', message);
      appendMessage('assistant', message);
    });
  }

  async function assistantSay(message, delay = 420) {
    await assistantBatch([message], delay);
  }

  function upsertPanel(key, extraClass = '') {
    let panel = panels[key];
    if (!panel) {
      panel = document.createElement('section');
      panel.className = 'assistant-flow-card';
      panel.innerHTML = '<div class="assistant-flow-card-body"></div>';
      thread.appendChild(panel);
      panels[key] = panel;
    }
    panel.className = ['assistant-flow-card', extraClass].filter(Boolean).join(' ');
    scrollToNode(panel);
    return panel.querySelector('.assistant-flow-card-body');
  }

  function removePanel(key) {
    if (!panels[key]) return;
    panels[key].remove();
    delete panels[key];
  }

  function clearPanelsFrom(key) {
    const startIndex = panelSequence.indexOf(key);
    if (startIndex === -1) return;
    panelSequence.slice(startIndex).forEach(removePanel);
  }

  function completePanel(key) {
    const panel = panels[key];
    if (!panel) return;
    panel.classList.add('is-complete');
    panel.querySelectorAll('input, button, select, textarea').forEach((element) => {
      if (element.dataset.keepEnabled === 'true') {
        return;
      }
      element.disabled = true;
    });
  }

  function setStage(stage) {
    state.stage = stage;
    const placeholders = {
      name: 'Type your full name...',
      city: 'Type your city...',
      phone: 'Type your phone number...',
      fleet: 'Type a preference or choose a car below...',
      complete: 'Ask Yobo another question or restart...',
      default: 'Type your reply here...',
    };
    composerInput.placeholder = placeholders[stage] || placeholders.default;
  }

  function parseDate(value) {
    if (!value) return null;
    const parts = String(value).split('-');
    if (parts.length !== 3) return null;
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  }

  function toIsoDate(value) {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function todayIso() {
    return toIsoDate(new Date());
  }

  function bookingDays() {
    const startDate = parseDate(state.pickup_date);
    const endDate = parseDate(state.return_date);
    if (!startDate || !endDate) return 0;
    return Math.max(Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)), 1);
  }

  function formatCardDate(value) {
    const dateValue = parseDate(value);
    if (!dateValue) return '--';
    return dateValue.toLocaleDateString('en-GB', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  }

  function formatAED(amount) {
    const safe = Number.isFinite(amount) ? amount : 0;
    return `AED ${safe.toLocaleString()}`;
  }

  function cleanNameInput(value) {
    return String(value || '')
      .trim()
      .replace(/^(hi|hello)[,\s-]*/i, '')
      .replace(/^(my name is|i am|i'm)\s+/i, '')
      .replace(/[.!]+$/, '')
      .trim();
  }

  function splitFullName(value) {
    const parts = cleanNameInput(value).split(/\s+/).filter(Boolean);
    return {
      full_name: parts.join(' '),
      first_name: parts[0] || '',
      last_name: parts.slice(1).join(' '),
    };
  }

  function looksLikeHelpIntent(value) {
    return /(\?|suggest|recommend|recommendation|which|what|how|can you|could you|price|cost|car|cars|fleet|insurance|book|booking|luxury|standard|family|budget|cheap|daily|monthly|yearly)/i.test(
      String(value || '').trim()
    );
  }

  function looksLikeNameAnswer(value) {
    const cleaned = cleanNameInput(value);
    if (!cleaned || /\d/.test(cleaned)) {
      return false;
    }
    if (looksLikeHelpIntent(cleaned)) {
      return false;
    }
    return cleaned.split(/\s+/).filter(Boolean).length >= 2;
  }

  function normalizeCityInput(value) {
    const normalized = String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
    const aliases = [
      ['abudhabi', 'Abu Dhabi'],
      ['dubai', 'Dubai'],
      ['sharjah', 'Sharjah'],
      ['rasalkhaimah', 'Ras Al-Khaimah'],
      ['rak', 'Ras Al-Khaimah'],
      ['alain', 'Al-Ain'],
      ['fujairah', 'Fujairah'],
      ['ajman', 'Ajman'],
    ];
    const match = aliases.find(([key]) => normalized === key || normalized.includes(key));
    return match ? match[1] : '';
  }

  function normalizePhoneInput(value) {
    const match = String(value || '').match(/[+()0-9\s-]{7,}/);
    return match ? match[0].trim() : String(value || '').trim();
  }

  function isValidPhone(value) {
    const digits = normalizePhoneInput(value).replace(/\D/g, '');
    return digits.length >= 7;
  }

  function buildLocation() {
    state.location = [state.address, state.landmark, state.pincode].filter(Boolean).join(', ');
    return state.location;
  }

  function normalizeLuxuryLabel(value) {
    const lowered = String(value || '').trim().toLowerCase();
    if (['true', '1', 'yes', 'luxury'].includes(lowered)) return 'Luxury';
    if (['false', '0', 'no', 'standard'].includes(lowered)) return 'Standard';
    return lowered ? lowered.charAt(0).toUpperCase() + lowered.slice(1) : '';
  }

  function refundableDeposit() {
    const luxury = normalizeLuxuryLabel(state.car_luxury);
    if (!state.car_id) return 0;
    return luxury === 'Luxury' ? 5000 : 2000;
  }

  function estimateValues(insuranceFee = state.insurance_price) {
    const days = bookingDays();
    const baseRental = Number(state.price_per_day || 0) * days;
    const safeInsurance = Number(insuranceFee || 0);
    const subtotal = baseRental + safeInsurance + processingFee;
    const vat = Math.round(subtotal * 0.05);
    const total = subtotal + vat;
    state.total_price = total;
    state.rental_days = days;
    return {
      days,
      baseRental,
      insuranceFee: safeInsurance,
      processingFee,
      subtotal,
      vat,
      total,
      deposit: refundableDeposit(),
    };
  }

  function fallbackFleetPhoto() {
    return 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?q=80&w=1200&auto=format&fit=crop';
  }

  function fleetCardMarkup(car, suggested = false) {
    const image = escapeHtml(String(car.photo_url || '').trim() || fallbackFleetPhoto());
    const fallback = escapeHtml(fallbackFleetPhoto());
    const title = escapeHtml(`${car.make || ''} ${car.model || ''}`.trim());
    const luxury = normalizeLuxuryLabel(car.luxury) || 'Standard';
    return `
      <article class="assistant-flow-fleet-card ${suggested ? 'is-suggested' : ''}">
        <div class="assistant-flow-fleet-media">
          <img src="${image}" alt="${title}" loading="lazy" onerror="this.onerror=null;this.src='${fallback}';" />
        </div>
        <div class="assistant-flow-fleet-meta">
          ${suggested ? '<span class="assistant-flow-card-tag">Suggested for you</span>' : ''}
          <strong>${title}</strong>
          <span>${formatAED(Number(car.price_per_day || 0))} per day</span>
          <span>${escapeHtml(luxury)}</span>
        </div>
        <button
          class="btn-black assistant-flow-select"
          type="button"
          data-id="${escapeHtml(car.id)}"
          data-make="${escapeHtml(car.make || '')}"
          data-model="${escapeHtml(car.model || '')}"
          data-price="${Number(car.price_per_day || 0)}"
          data-luxury="${escapeHtml(luxury)}"
        >
          Select this car
        </button>
      </article>
    `;
  }

  function looksLikeSuggestionRequest(text) {
    return /(suggest|recommend|recommendation|best car|which car|what should|need a car|looking for|budget|affordable|cheap|luxury|family|suv|daily|business|electric|fast|performance)/i.test(text);
  }

  function chooseSuggestedCar(promptText, fleet) {
    if (!fleet.length) return null;
    const lower = String(promptText || '').toLowerCase();
    const asc = [...fleet].sort((a, b) => Number(a.price_per_day || 0) - Number(b.price_per_day || 0));
    const desc = [...fleet].sort((a, b) => Number(b.price_per_day || 0) - Number(a.price_per_day || 0));
    const luxuryCars = fleet.filter((car) => normalizeLuxuryLabel(car.luxury) === 'Luxury');
    const standardCars = fleet.filter((car) => normalizeLuxuryLabel(car.luxury) !== 'Luxury');

    const matchByText = (patterns) => fleet.find((car) => {
      const haystack = `${car.make || ''} ${car.model || ''}`.toLowerCase();
      return patterns.some((pattern) => haystack.includes(pattern));
    });

    if (/electric|ev|tesla/.test(lower)) {
      return matchByText(['tesla']) || asc[0];
    }
    if (/family|suv|space|kids|luggage/.test(lower)) {
      return matchByText(['range rover', 'sport', 'creta', 'urus']) || desc.find((car) => /sport|suv|creta|urus/i.test(`${car.make} ${car.model}`)) || fleet[0];
    }
    if (/budget|cheap|affordable|economy|lowest/.test(lower)) {
      return asc[0];
    }
    if (/luxury|premium|business|executive|vip/.test(lower)) {
      return [...luxuryCars, ...desc].find(Boolean) || desc[0];
    }
    if (/fast|sport|performance|fun/.test(lower)) {
      return matchByText(['911', 'm4', 'lamborghini', 'ferrari', 'mustang']) || desc[0];
    }
    if (/daily|city|commute|practical/.test(lower)) {
      return standardCars.sort((a, b) => Number(a.price_per_day || 0) - Number(b.price_per_day || 0))[0] || asc[0];
    }
    return asc[Math.min(1, asc.length - 1)] || asc[0];
  }

  async function fetchAiReply(message) {
    try {
      const response = await fetch('/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message,
          history: state.history.slice(-8),
          biz_id: activeBizId(),
          context: {
            start_date: state.pickup_date,
            end_date: state.return_date,
            city: state.city,
          },
        }),
      });
      const payload = await response.json();
      return payload.reply || payload.error || '';
    } catch (error) {
      return '';
    }
  }

  async function fetchFleetResults() {
    const query = new URLSearchParams();
    const bizId = activeBizId();
    if (bizId) query.set('biz_id', bizId);
    if (state.city) query.set('city', state.city);
    if (state.pickup_date) query.set('start_date', state.pickup_date);
    if (state.return_date) query.set('end_date', state.return_date);
    if (state.luxury_filter !== 'all') query.set('luxury', state.luxury_filter);

    const response = await fetch(`/api/fleet?${query.toString()}`);
    const payload = await response.json();
    if (!payload.ok) {
      throw new Error(payload.error || 'Unable to load fleet.');
    }
    if (payload.error) {
      throw new Error(payload.error);
    }
    state.fleet_results = Array.isArray(payload.data) ? payload.data : [];
    return state.fleet_results;
  }

  async function selectedCarStillAvailable() {
    if (!state.car_id || !state.pickup_date || !state.return_date) {
      return false;
    }
    const query = new URLSearchParams();
    const bizId = activeBizId();
    if (bizId) query.set('biz_id', bizId);
    if (state.city) query.set('city', state.city);
    query.set('start_date', state.pickup_date);
    query.set('end_date', state.return_date);

    try {
      const response = await fetch(`/api/fleet?${query.toString()}`);
      const payload = await response.json();
      const fleet = Array.isArray(payload.data) ? payload.data : [];
      return fleet.some((car) => String(car.id) === String(state.car_id));
    } catch (error) {
      return false;
    }
  }

  function selectCarFromButton(button) {
    state.car_id = button.dataset.id || '';
    state.car_make = button.dataset.make || '';
    state.car_model = button.dataset.model || '';
    state.price_per_day = Number(button.dataset.price || 0);
    state.car_luxury = button.dataset.luxury || '';
    clearPanelsFrom('quote');
  }

  function bindCarSelectButtons(scope) {
    scope.querySelectorAll('.assistant-flow-select').forEach((button) => {
      button.addEventListener('click', async () => {
        selectCarFromButton(button);
        completePanel('fleet');
        userSay(`I'd like the ${state.car_make} ${state.car_model}.`);
        await assistantBatch(["Here's an estimated quote for you to book your car"]);
        renderQuotePanel();
        setStage('quote');
      });
    });
  }

  async function renderFleetPanel(suggestedCar = null, suggestionText = '') {
    const body = upsertPanel('fleet', 'assistant-flow-card assistant-flow-card-wide');
    body.innerHTML = '<div class="assistant-flow-loading">Loading the fleet for your dates...</div>';

    try {
      const fleet = await fetchFleetResults();
      if (!fleet.length) {
        body.innerHTML = `
          <div class="assistant-flow-empty">
            No cars are available right now for ${escapeHtml(state.city || 'this branch')} between ${escapeHtml(formatCardDate(state.pickup_date))} and ${escapeHtml(formatCardDate(state.return_date))}.
          </div>
        `;
        return;
      }

      const suggestionMarkup = suggestedCar
        ? `
          <div class="assistant-flow-suggestion-block">
            ${suggestionText ? `<p class="assistant-chat-panel-note">${escapeHtml(suggestionText)}</p>` : ''}
            ${fleetCardMarkup(suggestedCar, true)}
          </div>
        `
        : '';

      body.innerHTML = `
        <div class="assistant-flow-fleet-status">
          Showing ${fleet.length} available car${fleet.length === 1 ? '' : 's'} in ${escapeHtml(state.city || 'your branch')} for ${escapeHtml(formatCardDate(state.pickup_date))} to ${escapeHtml(formatCardDate(state.return_date))}.
        </div>
        <div class="assistant-flow-fleet-controls">
          <div class="assistant-flow-luxury-toggle">
            <button class="assistant-flow-mini-chip ${state.luxury_filter === 'all' ? 'active' : ''}" data-luxury="all" type="button">All</button>
            <button class="assistant-flow-mini-chip ${state.luxury_filter === 'luxury' ? 'active' : ''}" data-luxury="luxury" type="button">Luxury</button>
            <button class="assistant-flow-mini-chip ${state.luxury_filter === 'standard' ? 'active' : ''}" data-luxury="standard" type="button">Standard</button>
          </div>
        </div>
        ${suggestionMarkup}
        <div class="assistant-flow-fleet-grid">
          ${fleet.map((car) => fleetCardMarkup(car)).join('')}
        </div>
      `;

      body.querySelectorAll('[data-luxury]').forEach((button) => {
        button.addEventListener('click', async () => {
          state.luxury_filter = button.dataset.luxury || 'all';
          await renderFleetPanel();
        });
      });

      bindCarSelectButtons(body);
    } catch (error) {
      body.innerHTML = `<div class="assistant-flow-empty">${escapeHtml(error.message || 'I could not load the fleet right now.')}</div>`;
    }
  }

  function renderSchedulePanel() {
    const body = upsertPanel('schedule');
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const defaultPickup = state.pickup_date || toIsoDate(tomorrow);
    const defaultReturn = state.return_date || toIsoDate(new Date(tomorrow.getFullYear(), tomorrow.getMonth(), tomorrow.getDate() + 1));

    body.innerHTML = `
      <div class="assistant-chat-panel-head">
        <strong>Rental dates</strong>
        <p class="assistant-chat-panel-note">Choose your from and to dates before you browse the available fleet.</p>
      </div>
      <div class="assistant-flow-schedule-card">
        <div class="assistant-flow-schedule-grid">
          <label class="assistant-flow-date-card">
            <span>FROM</span>
            <input id="assistantPickupDate" type="date" value="${escapeHtml(defaultPickup)}" required />
          </label>
          <div class="assistant-flow-schedule-arrow">to</div>
          <label class="assistant-flow-date-card">
            <span>TO</span>
            <input id="assistantReturnDate" type="date" value="${escapeHtml(defaultReturn)}" required />
          </label>
        </div>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantContinueSchedule" type="button">Continue</button>
        </div>
      </div>
    `;

    const pickupInput = body.querySelector('#assistantPickupDate');
    const returnInput = body.querySelector('#assistantReturnDate');
    const continueButton = body.querySelector('#assistantContinueSchedule');
    const minimumDate = todayIso();
    pickupInput.min = minimumDate;
    returnInput.min = pickupInput.value || minimumDate;

    pickupInput.addEventListener('change', () => {
      returnInput.min = pickupInput.value || minimumDate;
      if (returnInput.value && pickupInput.value && returnInput.value < pickupInput.value) {
        returnInput.value = pickupInput.value;
      }
    });

    continueButton.addEventListener('click', async () => {
      if (!pickupInput.value || !returnInput.value) {
        await assistantSay('Please choose both your from and to dates.');
        return;
      }
      if (pickupInput.value < minimumDate) {
        await assistantSay('Your start date cannot be in the past.');
        return;
      }
      if (returnInput.value < pickupInput.value) {
        await assistantSay('Your return date cannot be before the start date.');
        return;
      }

      state.pickup_date = pickupInput.value;
      state.return_date = returnInput.value;
      state.rental_days = bookingDays();
      completePanel('schedule');
      userSay(`${formatCardDate(state.pickup_date)} to ${formatCardDate(state.return_date)}.`);
      await assistantBatch(["What's your phone number?"]);
      setStage('phone');
    });
  }

  function renderQuotePanel() {
    const body = upsertPanel('quote');
    const values = estimateValues(0);
    body.innerHTML = `
      <div class="assistant-chat-panel-head">
        <strong>Estimated quote</strong>
        <p class="assistant-chat-panel-note">This is your estimate before insurance is added.</p>
      </div>
      <div class="assistant-flow-quote-card">
        <div class="assistant-flow-detail-grid">
          <div><span>Vehicle</span><strong>${escapeHtml(`${state.car_make} ${state.car_model}`)}</strong></div>
          <div><span>City</span><strong>${escapeHtml(state.city)}</strong></div>
          <div><span>Schedule</span><strong>${escapeHtml(`${formatCardDate(state.pickup_date)} to ${formatCardDate(state.return_date)}`)}</strong></div>
          <div><span>Daily rate</span><strong>${formatAED(Number(state.price_per_day || 0))}</strong></div>
          <div><span>Rental days</span><strong>${values.days}</strong></div>
          <div><span>Estimated total</span><strong>${formatAED(values.total)}</strong></div>
        </div>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantContinueQuote" type="button">Continue</button>
        </div>
      </div>
    `;

    body.querySelector('#assistantContinueQuote').addEventListener('click', async () => {
      completePanel('quote');
      await assistantSay('Please enter your pickup address so I can continue with your booking.');
      renderLocationPanel();
      setStage('location');
    });
  }

  function renderLocationPanel() {
    const body = upsertPanel('location');
    body.innerHTML = `
      <form class="assistant-flow-form" id="assistantLocationForm">
        <div class="assistant-chat-panel-head">
          <strong>Pickup address</strong>
          <p class="assistant-chat-panel-note">Add the address we should use for this booking.</p>
        </div>
        <div class="assistant-flow-fields">
          <label class="assistant-flow-field">
            <span>Address *</span>
            <input name="address" type="text" value="${escapeHtml(state.address)}" placeholder="Enter address" required />
          </label>
          <label class="assistant-flow-field">
            <span>Landmark</span>
            <input name="landmark" type="text" value="${escapeHtml(state.landmark)}" placeholder="Optional landmark" />
          </label>
          <label class="assistant-flow-field">
            <span>Pincode *</span>
            <input name="pincode" type="text" value="${escapeHtml(state.pincode)}" placeholder="Enter pincode" required />
          </label>
        </div>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" type="submit">Continue</button>
        </div>
      </form>
    `;

    body.querySelector('#assistantLocationForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      if (!form.reportValidity()) {
        return;
      }
      const formData = new FormData(form);
      state.address = String(formData.get('address') || '').trim();
      state.landmark = String(formData.get('landmark') || '').trim();
      state.pincode = String(formData.get('pincode') || '').trim();
      buildLocation();
      completePanel('location');
      userSay(state.location);
      await assistantBatch([
        'Would you like to add an insurance plan to cover any issues that may arise? We offer three tiers of insurance plans covering various contingencies. If not you also have the option to skip insurance.'
      ]);
      await renderInsurancePanel();
      setStage('insurance');
    });
  }

  function normalizeInsurance(items) {
    if (!Array.isArray(items) || !items.length) {
      return [
        {
          name: 'Protection Plan - Basic',
          price: 150,
          description: ['50% Accident Coverage'],
        },
        {
          name: 'Protection Plan - Plus',
          price: 450,
          description: ['100% Accident Coverage', 'Windshield Damage', 'Tyre Damages'],
        },
        {
          name: 'Protection Plan - Premium',
          price: 650,
          description: ['100% Accident Coverage', 'Windshield Damage', 'Tyre Damages', 'Lost Keys', 'Rim Damage'],
        },
      ];
    }

    return items.map((item, index) => {
      const rawDescription = item.details || item.description || '';
      return {
        name: item.plan_name || item.title || item.name || `Protection Plan ${index + 1}`,
        price: Number(item.price_per_day ?? item.price ?? item.amount ?? 0),
        description: String(rawDescription)
          .split(/\n|<br\s*\/?>|;/i)
          .map((line) => line.replace(/^-/, '').trim())
          .filter(Boolean),
      };
    });
  }

  async function renderInsurancePanel() {
    const body = upsertPanel('insurance');
    body.innerHTML = '<div class="assistant-flow-loading">Loading insurance options...</div>';

    const query = new URLSearchParams();
    const bizId = activeBizId();
    if (bizId) query.set('biz_id', bizId);

    try {
      const response = await fetch(`/api/insurance?${query.toString()}`);
      const payload = await response.json();
      const plans = normalizeInsurance(payload.data || []);

      body.innerHTML = `
        <div class="assistant-flow-insurance-grid">
          ${plans.map((plan) => `
            <article class="assistant-flow-insurance-card ${state.insurance_plan === plan.name ? 'selected' : ''}" data-plan="${escapeHtml(plan.name)}" data-price="${Number(plan.price || 0)}" tabindex="0" role="button" aria-pressed="${state.insurance_plan === plan.name ? 'true' : 'false'}">
              <h3>${escapeHtml(plan.name)}</h3>
              <div class="assistant-flow-insurance-price">${formatAED(Number(plan.price || 0))}</div>
              <div class="assistant-flow-insurance-copy">
                ${(plan.description.length ? plan.description : ['Cover details available on request']).map((line) => `<div>- ${escapeHtml(line)}</div>`).join('')}
              </div>
            </article>
          `).join('')}
        </div>
        <div class="assistant-flow-card-actions between assistant-flow-insurance-actions">
          <button class="btn-skip" id="assistantSkipInsurance" type="button">Skip insurance</button>
          <button class="btn-black" id="assistantContinueInsurance" type="button">Continue</button>
        </div>
      `;

      let selectedCard = Array.from(body.querySelectorAll('.assistant-flow-insurance-card')).find((card) => card.dataset.plan === state.insurance_plan) || null;

      function selectCard(card) {
        selectedCard = card;
        body.querySelectorAll('.assistant-flow-insurance-card').forEach((item) => {
          const active = item === card;
          item.classList.toggle('selected', active);
          item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (card) {
          state.insurance_plan = card.dataset.plan || 'No insurance';
          state.insurance_price = Number(card.dataset.price || 0);
        }
      }

      body.querySelectorAll('.assistant-flow-insurance-card').forEach((card) => {
        card.addEventListener('click', () => selectCard(card));
        card.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            selectCard(card);
          }
        });
      });

      body.querySelector('#assistantSkipInsurance').addEventListener('click', async () => {
        state.insurance_plan = 'No insurance';
        state.insurance_price = 0;
        completePanel('insurance');
        userSay('I will skip insurance.');
        await assistantBatch(["Before we finish booking let's confirm all your details"]);
        renderConfirmPanel();
        setStage('confirm');
      });

      body.querySelector('#assistantContinueInsurance').addEventListener('click', async () => {
        if (!selectedCard) {
          await assistantSay('Please choose an insurance plan or skip it.');
          return;
        }
        completePanel('insurance');
        userSay(`Add ${state.insurance_plan}.`);
        await assistantBatch(["Before we finish booking let's confirm all your details"]);
        renderConfirmPanel();
        setStage('confirm');
      });
    } catch (error) {
      body.innerHTML = '<div class="assistant-flow-empty">I could not load insurance plans right now.</div>';
    }
  }

  function renderConfirmPanel() {
    const body = upsertPanel('confirm');
    body.innerHTML = `
      <form class="assistant-flow-form" id="assistantConfirmForm">
        <div class="assistant-chat-panel-head">
          <strong>Review your details</strong>
          <p class="assistant-chat-panel-note">Update anything below if you need to before we finish the booking.</p>
        </div>
        <div class="assistant-flow-fields two-col">
          <label class="assistant-flow-field assistant-flow-field-span">
            <span>Full name *</span>
            <input name="full_name" type="text" value="${escapeHtml(state.full_name)}" required />
          </label>
          <label class="assistant-flow-field">
            <span>City *</span>
            <input name="city" type="text" value="${escapeHtml(state.city)}" required />
          </label>
          <label class="assistant-flow-field">
            <span>Phone *</span>
            <input name="phone" type="text" value="${escapeHtml(state.phone)}" required />
          </label>
          <label class="assistant-flow-field">
            <span>From *</span>
            <input name="pickup_date" type="date" value="${escapeHtml(state.pickup_date)}" required />
          </label>
          <label class="assistant-flow-field">
            <span>To *</span>
            <input name="return_date" type="date" value="${escapeHtml(state.return_date)}" required />
          </label>
          <label class="assistant-flow-field assistant-flow-field-span">
            <span>Address *</span>
            <input name="address" type="text" value="${escapeHtml(state.address)}" required />
          </label>
          <label class="assistant-flow-field">
            <span>Landmark</span>
            <input name="landmark" type="text" value="${escapeHtml(state.landmark)}" />
          </label>
          <label class="assistant-flow-field">
            <span>Pincode *</span>
            <input name="pincode" type="text" value="${escapeHtml(state.pincode)}" required />
          </label>
        </div>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" type="submit">Continue</button>
        </div>
      </form>
    `;

    body.querySelector('#assistantConfirmForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      if (!form.reportValidity()) {
        return;
      }
      const formData = new FormData(form);
      const nameParts = splitFullName(formData.get('full_name'));
      const normalizedCity = normalizeCityInput(formData.get('city'));
      const phone = normalizePhoneInput(formData.get('phone'));
      const pickupDate = String(formData.get('pickup_date') || '').trim();
      const returnDate = String(formData.get('return_date') || '').trim();
      const address = String(formData.get('address') || '').trim();
      const landmark = String(formData.get('landmark') || '').trim();
      const pincode = String(formData.get('pincode') || '').trim();
      const minimumDate = todayIso();

      if (!nameParts.full_name) {
        await assistantSay('Please enter your full name.');
        return;
      }
      if (!normalizedCity) {
        await assistantSay(`I can currently route bookings for ${supportedCities.join(', ')}. Please type one of those cities.`);
        return;
      }
      if (!isValidPhone(phone)) {
        await assistantSay('Please enter a valid phone number.');
        return;
      }
      if (pickupDate < minimumDate) {
        await assistantSay('The start date cannot be in the past.');
        return;
      }
      if (returnDate < pickupDate) {
        await assistantSay('The return date cannot be before the start date.');
        return;
      }

      const previous = { ...state };
      state.full_name = nameParts.full_name;
      state.first_name = nameParts.first_name;
      state.last_name = nameParts.last_name;
      state.city = normalizedCity;
      state.phone = phone;
      state.pickup_date = pickupDate;
      state.return_date = returnDate;
      state.address = address;
      state.landmark = landmark;
      state.pincode = pincode;
      buildLocation();

      const stillAvailable = await selectedCarStillAvailable();
      if (!stillAvailable) {
        Object.assign(state, previous);
        state.car_id = '';
        state.car_make = '';
        state.car_model = '';
        state.price_per_day = 0;
        state.car_luxury = '';
        clearPanelsFrom('fleet');
        await assistantSay('That car is not available for the updated dates or city. Please choose another available car.');
        await renderFleetPanel();
        setStage('fleet');
        return;
      }

      completePanel('confirm');
      userSay('Everything looks correct.');
      await assistantBatch([
        "Here's your booking summary complete with the safety deposit that will be refunded once the car has been returned."
      ]);
      renderSummaryPanel();
      setStage('summary');
    });
  }

  function renderSummaryPanel() {
    const body = upsertPanel('summary');
    const values = estimateValues();
    body.innerHTML = `
      <div class="assistant-flow-summary">
        <div class="assistant-flow-summary-row"><span>Name</span><span>${escapeHtml(state.full_name)}</span></div>
        <div class="assistant-flow-summary-row"><span>Phone</span><span>${escapeHtml(state.phone)}</span></div>
        <div class="assistant-flow-summary-row"><span>City</span><span>${escapeHtml(state.city)}</span></div>
        <div class="assistant-flow-summary-row"><span>Vehicle</span><span>${escapeHtml(`${state.car_make} ${state.car_model}`)}</span></div>
        <div class="assistant-flow-summary-row"><span>Schedule</span><span>${escapeHtml(`${formatCardDate(state.pickup_date)} to ${formatCardDate(state.return_date)}`)}</span></div>
        <div class="assistant-flow-summary-row"><span>Pick-up address</span><span>${escapeHtml(state.location)}</span></div>
        <div class="assistant-flow-summary-divider"></div>
        <div class="assistant-flow-summary-row"><span>Base rental</span><span>${formatAED(values.baseRental)}</span></div>
        <div class="assistant-flow-summary-row"><span>Insurance fee</span><span>${formatAED(values.insuranceFee)}</span></div>
        <div class="assistant-flow-summary-row"><span>Processing fee</span><span>${formatAED(values.processingFee)}</span></div>
        <div class="assistant-flow-summary-divider"></div>
        <div class="assistant-flow-summary-row"><strong>Subtotal</strong><span>${formatAED(values.subtotal)}</span></div>
        <div class="assistant-flow-summary-row"><span>VAT (5%)</span><span>${formatAED(values.vat)}</span></div>
        <div class="assistant-flow-summary-row total"><strong>Total</strong><span>${formatAED(values.total)}</span></div>
        <div class="assistant-flow-summary-divider"></div>
        <div class="assistant-flow-summary-row"><span>Refundable safety deposit</span><span>${formatAED(values.deposit)}</span></div>
        <p class="assistant-chat-summary-note">The refundable safety deposit is shown separately and will be returned once the car has been handed back in line with the rental terms.</p>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantConfirmBooking" type="button">Confirm Booking</button>
        </div>
        <div class="summary-status" id="assistantConfirmStatus"></div>
        <div class="assistant-flow-card-actions center assistant-flow-summary-restart-row">
          <button class="btn-outline assistant-flow-summary-restart" id="assistantRestartBooking" data-keep-enabled="true" type="button">Restart</button>
        </div>
      </div>
    `;

    body.querySelector('#assistantRestartBooking').addEventListener('click', resetFlow);
    body.querySelector('#assistantConfirmBooking').addEventListener('click', async () => {
      const status = body.querySelector('#assistantConfirmStatus');
      const button = body.querySelector('#assistantConfirmBooking');
      button.disabled = true;
      status.textContent = 'Saving booking...';
      status.className = 'summary-status pending';

      const payload = {
        biz_id: activeBizId(),
        customer_name: state.full_name,
        phone: state.phone,
        total_price: state.total_price,
        start_date: state.pickup_date,
        end_date: state.return_date,
        city: state.city,
        car_id: state.car_id,
        location: state.location,
        insurance: state.insurance_plan || 'No insurance',
      };

      try {
        const response = await fetch('/api/bookings', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (!result.ok) {
          status.textContent = result.error || 'Booking failed.';
          status.className = 'summary-status error';
          button.disabled = false;
          return;
        }
        completePanel('summary');
        status.textContent = 'Booking confirmed.';
        status.className = 'summary-status success';
        await assistantBatch([
          'Once you finish one of our consultants will call you within 48 hours to confirm your booking. Thank you for booking with us. Happy driving!'
        ]);
        setStage('complete');
      } catch (error) {
        status.textContent = 'Booking failed. Please try again.';
        status.className = 'summary-status error';
        button.disabled = false;
      }
    });
  }

  async function handleNameInput(text) {
    if (!looksLikeNameAnswer(text)) {
      if (looksLikeHelpIntent(text)) {
        const reply = await fetchAiReply(text);
        await assistantBatch([
          reply || 'I can absolutely help with suggestions and pricing as we go.',
          'First, please enter your full name.'
        ]);
        return;
      }
      await assistantSay('Please enter your full name so I can continue with the booking.');
      return;
    }
    const nameParts = splitFullName(text);
    state.full_name = nameParts.full_name;
    state.first_name = nameParts.first_name;
    state.last_name = nameParts.last_name;
    await assistantBatch([
      `Nice to meet you ${state.first_name}, let's get some details before you choose your car`,
      'Which city are you from?'
    ]);
    setStage('city');
  }

  async function handleCityInput(text) {
    const normalizedCity = normalizeCityInput(text);
    if (!normalizedCity) {
      if (looksLikeHelpIntent(text)) {
        const reply = await fetchAiReply(text);
        await assistantBatch([
          reply || 'I can help with that too.',
          `Before I show the right branch fleet, please type one of these cities: ${supportedCities.join(', ')}.`
        ]);
        return;
      }
      await assistantSay(`I can currently route bookings for ${supportedCities.join(', ')}. Please type one of those cities.`);
      return;
    }
    state.city = normalizedCity;
    await assistantBatch(['How long do you want to rent?']);
    renderSchedulePanel();
    setStage('schedule');
  }

  async function handlePhoneInput(text) {
    if (!isValidPhone(text)) {
      if (looksLikeHelpIntent(text)) {
        const reply = await fetchAiReply(text);
        await assistantBatch([
          reply || 'Happy to help.',
          "Please send your phone number next so I can continue."
        ]);
        return;
      }
      await assistantSay('Please enter a valid phone number.');
      return;
    }
    state.phone = normalizePhoneInput(text);
    await assistantBatch([
      "Great! Now it's time for you to choose your dream car! If you'd like, I could suggest a car tailored to your needs."
    ]);
    await renderFleetPanel();
    setStage('fleet');
  }

  async function handleFleetInput(text) {
    if (!state.fleet_results.length) {
      await renderFleetPanel();
    }
    if (/^(no|skip|show normal|show all|just show|browse|view fleet)/i.test(text.trim())) {
      await assistantSay('Absolutely. Browse the available fleet below and choose the one you like.');
      if (panels.fleet) scrollToNode(panels.fleet);
      return;
    }

    if (!looksLikeSuggestionRequest(text)) {
      const reply = await fetchAiReply(text);
      await assistantSay(reply || 'You can type what kind of car you want, or simply select one from the fleet below.');
      return;
    }

    const suggestion = chooseSuggestedCar(text, state.fleet_results);
    const aiReply = await fetchAiReply(text);
    if (!suggestion) {
      await assistantSay(aiReply || 'I do not have a strong match yet, so please browse the available fleet below.');
      return;
    }
    await assistantBatch([
      aiReply || `Based on what you need, I'd recommend the ${suggestion.make} ${suggestion.model}.`
    ]);
    await renderFleetPanel(
      suggestion,
      `This is my best match for what you asked for. You can select it directly or keep browsing the full fleet below.`
    );
  }

  async function handleGeneralQuestion(text) {
    const reply = await fetchAiReply(text);
    if (reply) {
      await assistantSay(reply);
      return;
    }
    await assistantSay('I can help with your booking here. If a card is open, complete that step or ask me for a car suggestion.');
  }

  async function handleComposerSubmit(text) {
    const trimmed = String(text || '').trim();
    if (!trimmed) return;
    userSay(trimmed);

    if (state.stage === 'name') {
      await handleNameInput(trimmed);
      return;
    }
    if (state.stage === 'city') {
      await handleCityInput(trimmed);
      return;
    }
    if (state.stage === 'phone') {
      await handlePhoneInput(trimmed);
      return;
    }
    if (state.stage === 'fleet') {
      await handleFleetInput(trimmed);
      return;
    }
    await handleGeneralQuestion(trimmed);
  }

  async function resetFlow() {
    Object.assign(state, buildInitialState());
    thread.innerHTML = '';
    Object.keys(panels).forEach((key) => delete panels[key]);
    composerInput.value = '';
    setStage('name');
    await assistantBatch([
      "Hello I'm Yobo! Your AI-powered booking assistant.",
      'Please enter your full name'
    ]);
  }

  composerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const text = composerInput.value;
    composerInput.value = '';
    await handleComposerSubmit(text);
  });

  resetFlow();
})();
