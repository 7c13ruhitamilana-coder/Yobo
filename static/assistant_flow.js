(() => {
  const thread = document.getElementById('assistantFlowThread');
  const composerForm = document.getElementById('assistantFlowComposer');
  const composerInput = document.getElementById('assistantFlowInput');
  const params = new URLSearchParams(window.location.search);

  const cityOptions = [
    'Abu Dhabi',
    'Dubai',
    'Sharjah',
    'Ras Al-Khaimah',
    'Al-Ain',
    'Fujairah',
    'Ajman',
  ];

  const durationOptions = [
    { key: '1-day', label: '1 Day', days: 1 },
    { key: '3-days', label: '3 Days', days: 3 },
    { key: '1-week', label: '1 Week', days: 7 },
    { key: '1-month', label: '1 Month', days: 30 },
    { key: '3-months', label: '3 Months', days: 90 },
    { key: '6-months', label: '6 Months', days: 180 },
    { key: '12-months', label: '12 Months', days: 365 },
  ];

  const initialPrefill = {
    biz_id: params.get('biz_id') || localStorage.getItem('biz_id') || '',
    first_name: params.get('first_name') || '',
    last_name: params.get('last_name') || '',
    phone: params.get('phone') || '',
  };

  const state = {
    biz_id: initialPrefill.biz_id,
    first_name: initialPrefill.first_name,
    last_name: initialPrefill.last_name,
    phone: initialPrefill.phone,
    city: '',
    duration_label: '',
    rental_days: 0,
    pickup_date: '',
    return_date: '',
    pickup_time: '07:00 PM',
    return_time: '07:00 PM',
    schedule_confirmed: false,
    luxury_filter: 'all',
    car_id: '',
    car_make: '',
    car_model: '',
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

  const stepNodes = {};
  let holdTimerId = null;

  function wait(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  function todayIso() {
    return new Date().toISOString().split('T')[0];
  }

  function addDaysIso(startIso, numberOfDays) {
    const dateValue = parseDate(startIso);
    if (!dateValue) return '';
    dateValue.setDate(dateValue.getDate() + numberOfDays);
    return toIsoDate(dateValue);
  }

  function toIsoDate(value) {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function parseDate(value) {
    if (!value) return null;
    const parts = value.split('-');
    if (parts.length !== 3) return null;
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  }

  function bookingDays() {
    const startDate = parseDate(state.pickup_date);
    const endDate = parseDate(state.return_date);
    if (!startDate || !endDate) return Math.max(state.rental_days, 1);
    const diff = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
    return Math.max(diff, 1);
  }

  function formatAED(amount) {
    const safe = Number.isFinite(amount) ? amount : 0;
    return `AED ${safe.toLocaleString()}`;
  }

  function formatCardDate(value) {
    const dateValue = parseDate(value);
    if (!dateValue) return '--';
    return dateValue.toLocaleDateString('en-GB', {
      day: 'numeric',
      month: 'short',
    });
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function scrollToNode(node) {
    if (!node) return;
    requestAnimationFrame(() => {
      node.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  function pushHistory(role, content) {
    state.history.push({ role, content });
  }

  function appendBubble(role, text, action = null) {
    const block = document.createElement('div');
    block.className = `assistant-flow-message ${role}`;

    const bubble = document.createElement('div');
    bubble.className = 'assistant-flow-bubble';
    bubble.textContent = text;
    block.appendChild(bubble);

    if (action && action.href && action.label) {
      const link = document.createElement('a');
      link.className = 'assistant-flow-inline-action';
      link.href = action.href;
      link.textContent = action.label;
      block.appendChild(link);
    }

    thread.appendChild(block);
    scrollToNode(block);
    return block;
  }

  function assistant(text, action = null) {
    pushHistory('assistant', text);
    return appendBubble('assistant', text, action);
  }

  function user(text) {
    pushHistory('user', text);
    return appendBubble('user', text);
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

  async function assistantWithDelay(text, action = null, minimumDelay = 420) {
    const typingNode = renderTyping();
    await wait(minimumDelay);
    typingNode.remove();
    return assistant(text, action);
  }

  function createStep(key, label, title) {
    if (stepNodes[key]) {
      scrollToNode(stepNodes[key]);
      return stepNodes[key].querySelector('.assistant-flow-card-body');
    }

    const section = document.createElement('section');
    section.className = 'assistant-flow-card';
    section.innerHTML = `
      <div class="assistant-flow-card-top">
        <span class="assistant-flow-step-label">${label}</span>
        <h2>${title}</h2>
      </div>
      <div class="assistant-flow-card-body"></div>
    `;

    thread.appendChild(section);
    stepNodes[key] = section;
    scrollToNode(section);
    return section.querySelector('.assistant-flow-card-body');
  }

  function lockCard(key) {
    const card = stepNodes[key];
    if (!card) return;
    card.classList.add('is-complete');
    card.querySelectorAll('input, button, select').forEach((element) => {
      if (element.dataset.keepEnabled === 'true') {
        return;
      }
      element.disabled = true;
    });
  }

  function unlockCard(key) {
    const card = stepNodes[key];
    if (!card) return;
    card.classList.remove('is-complete');
    card.querySelectorAll('input, button, select').forEach((element) => {
      element.disabled = false;
    });
  }

  function setDefaultSchedule(numberOfDays) {
    const pickupDate = addDaysIso(todayIso(), 1);
    state.rental_days = numberOfDays;
    state.pickup_date = pickupDate;
    state.return_date = addDaysIso(pickupDate, numberOfDays);
  }

  function summaryValues() {
    const days = bookingDays();
    const baseRental = Number(state.price_per_day || 0) * days;
    const insuranceFee = Number(state.insurance_price || 0);
    const processingFee = 50;
    const subtotal = baseRental + insuranceFee + processingFee;
    const vat = Math.round(subtotal * 0.05);
    const total = subtotal + vat;
    state.total_price = total;
    state.rental_days = days;
    return { days, baseRental, insuranceFee, processingFee, subtotal, vat, total };
  }

  function locationString() {
    return [state.address, state.landmark, state.pincode].filter(Boolean).join(', ');
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
      const description = String(rawDescription)
        .split(/\n|<br\s*\/?>|;/i)
        .map((line) => line.replace(/^-/, '').trim())
        .filter(Boolean);

      return {
        name:
          item.plan_name ||
          item.title ||
          item.name ||
          item.code ||
          `Protection Plan ${index + 1}`,
        price: Number(item.price_per_day ?? item.price ?? item.amount ?? 0),
        description,
      };
    });
  }

  function clearHoldTimer() {
    if (holdTimerId) {
      window.clearInterval(holdTimerId);
      holdTimerId = null;
    }
  }

  function startHoldTimer(timerNode) {
    clearHoldTimer();
    let secondsLeft = 5 * 60;

    function renderCountdown() {
      const minutes = Math.floor(secondsLeft / 60);
      const seconds = String(secondsLeft % 60).padStart(2, '0');
      timerNode.textContent = `${minutes}:${seconds}`;
      if (secondsLeft > 0) {
        secondsLeft -= 1;
      }
    }

    renderCountdown();
    holdTimerId = window.setInterval(renderCountdown, 1000);
  }

  async function renderDetailsStep() {
    const body = createStep('details', 'Step 1', 'Your details');
    body.innerHTML = `
      <form class="assistant-flow-form" id="assistantDetailsForm">
        <div class="assistant-flow-fields two-col">
          <label class="assistant-flow-field">
            <span>First name *</span>
            <input name="first_name" type="text" placeholder="Enter first name" value="${escapeHtml(state.first_name)}" required />
          </label>
          <label class="assistant-flow-field">
            <span>Last name *</span>
            <input name="last_name" type="text" placeholder="Enter last name" value="${escapeHtml(state.last_name)}" required />
          </label>
          <label class="assistant-flow-field assistant-flow-field-span">
            <span>Phone *</span>
            <input name="phone" type="text" placeholder="Enter phone number" value="${escapeHtml(state.phone)}" required />
          </label>
        </div>
        <div class="assistant-flow-card-actions">
          <button class="btn-black" type="submit">Continue</button>
        </div>
      </form>
    `;

    const form = document.getElementById('assistantDetailsForm');
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;

      const formData = new FormData(form);
      state.first_name = String(formData.get('first_name') || '').trim();
      state.last_name = String(formData.get('last_name') || '').trim();
      state.phone = String(formData.get('phone') || '').trim();

      lockCard('details');
      user(`${state.first_name} ${state.last_name}, ${state.phone}.`);
      await assistantWithDelay(
        `Hi ${state.first_name}! I'm Yobo, your Smart Car Rentals assistant.\n\nWhich city do you need the car in?`
      );
      renderCityStep();
    });
  }

  function renderCityStep() {
    const body = createStep('city', 'Step 2', 'Choose city');
    body.innerHTML = `
      <div class="assistant-flow-choice-panel">
        <p>Hi ${escapeHtml(state.first_name || 'there')}! I'm Yobo, your Smart Car Rentals assistant.</p>
        <p>Which city do you need the car in?</p>
      </div>
      <div class="assistant-flow-chip-row">
        ${cityOptions
          .map(
            (city) => `
              <button class="assistant-flow-chip" data-city="${escapeHtml(city)}" type="button">
                ${escapeHtml(city)}
              </button>
            `
          )
          .join('')}
      </div>
    `;

    body.querySelectorAll('[data-city]').forEach((button) => {
      button.addEventListener('click', async () => {
        state.city = button.dataset.city || '';
        lockCard('city');
        user(state.city);
        await assistantWithDelay(
          `Perfect! ${state.city} is set. How long do you need the car? You can choose from the options below or type a specific duration if you have something different in mind.`
        );
        renderDurationStep();
      });
    });
  }

  function renderDurationStep() {
    const body = createStep('duration', 'Step 3', 'Duration');
    body.innerHTML = `
      <div class="assistant-flow-choice-panel">
        <p><strong>Duration Options:</strong></p>
        <div class="assistant-flow-copy-list">
          <div>Daily (1-6 days) - Perfect for short trips, daily rates</div>
          <div>Weekly (7-29 days) - Great value for extended stays or long vacations</div>
          <div>Monthly (1-12 months) - Discounted rates for longer periods</div>
          <div>Yearly (1+ years) - Save more with flexible rentals.</div>
        </div>
      </div>
      <div class="assistant-flow-chip-row">
        ${durationOptions
          .map(
            (option) => `
              <button class="assistant-flow-chip" data-duration="${option.key}" type="button">
                ${option.label}
              </button>
            `
          )
          .join('')}
      </div>
    `;

    body.querySelectorAll('[data-duration]').forEach((button) => {
      button.addEventListener('click', async () => {
        const option = durationOptions.find((item) => item.key === button.dataset.duration);
        if (!option) return;

        state.duration_label = option.label;
        setDefaultSchedule(option.days);
        state.schedule_confirmed = false;

        lockCard('duration');
        user(option.label);
        await assistantWithDelay(
          'Perfect. Confirm your pickup and return dates below so I can show the available fleet.'
        );
        renderScheduleStep();
      });
    });
  }

  async function loadFleetInto(container) {
    const query = new URLSearchParams();
    if (state.biz_id) query.set('biz_id', state.biz_id);
    const applyDateFilter =
      Boolean(state.schedule_confirmed) && Boolean(state.pickup_date) && Boolean(state.return_date);
    if (applyDateFilter) query.set('start_date', state.pickup_date);
    if (applyDateFilter) query.set('end_date', state.return_date);
    if (state.luxury_filter !== 'all') query.set('luxury', state.luxury_filter);

    container.innerHTML = '<div class="assistant-flow-loading">Loading available fleet...</div>';

    try {
      const response = await fetch(`/api/fleet?${query.toString()}`);
      const payload = await response.json();
      const fleet = payload.data || [];

      if (payload.error) {
        container.innerHTML = `
          <div class="assistant-flow-empty">
            ${escapeHtml(payload.error)}
          </div>
        `;
        return;
      }

      if (!fleet.length) {
        container.innerHTML = `
          <div class="assistant-flow-empty">
            ${
              applyDateFilter
                ? 'No cars match the selected dates and luxury filter right now.'
                : 'No cars match the selected luxury filter right now.'
            }
          </div>
        `;
        return;
      }

      const fleetCountLabel = `${fleet.length} car${fleet.length === 1 ? '' : 's'} found`;
      const fleetStatusCopy = applyDateFilter
        ? `Showing ${fleetCountLabel} for ${formatCardDate(state.pickup_date)} to ${formatCardDate(state.return_date)}.`
        : `Showing ${fleetCountLabel}. Exact date availability will be rechecked after you confirm the schedule.`;

      container.innerHTML = `
        <div class="assistant-flow-fleet-status">${escapeHtml(fleetStatusCopy)}</div>
        <div class="assistant-flow-fleet-grid">
          ${fleet
            .map((car) => {
              const image = escapeHtml(
                (car.photo_url || '').trim() ||
                  'https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1200&auto=format&fit=crop'
              );
              const make = escapeHtml(car.make || '-');
              const model = escapeHtml(car.model || '-');
              let luxuryText = 'Luxury info unavailable';
              if (car.luxury !== null && car.luxury !== undefined && String(car.luxury).trim() !== '') {
                luxuryText =
                  ['true', '1', 'yes', 'luxury'].includes(String(car.luxury || '').toLowerCase())
                    ? 'Luxury'
                    : 'Standard';
              }
              const price = Number(car.price_per_day || 0);
              return `
                <article class="assistant-flow-fleet-card">
                  <div class="assistant-flow-fleet-media">
                    <img src="${image}" alt="${make} ${model}" />
                  </div>
                  <div class="assistant-flow-fleet-meta">
                    <strong>${make} ${model}</strong>
                    <span>${formatAED(price)} per day</span>
                    <span>${escapeHtml(luxuryText)}</span>
                  </div>
                  <button
                    class="btn-black assistant-flow-select"
                    type="button"
                    data-id="${escapeHtml(car.id)}"
                    data-make="${make}"
                    data-model="${model}"
                    data-price="${price}"
                  >
                    Select this car
                  </button>
                </article>
              `;
            })
            .join('')}
        </div>
      `;

      container.querySelectorAll('.assistant-flow-select').forEach((button) => {
        button.addEventListener('click', async () => {
          state.car_id = button.dataset.id || '';
          state.car_make = button.dataset.make || '';
          state.car_model = button.dataset.model || '';
          state.price_per_day = Number(button.dataset.price || 0);

          lockCard('fleet');
          user(`Choose ${state.car_make} ${state.car_model}.`);
          await assistantWithDelay(
            `Perfect! I've selected the ${state.car_make} ${state.car_model} for you. Add the pickup address to continue.`
          );
          renderLocationStep();
        });
      });
    } catch (error) {
      container.innerHTML = `
        <div class="assistant-flow-empty">
          I could not load the fleet right now. Please try again.
        </div>
      `;
    }
  }

  async function selectedCarIsAvailable() {
    if (!state.car_id || !state.pickup_date || !state.return_date) {
      return false;
    }

    const query = new URLSearchParams();
    if (state.biz_id) query.set('biz_id', state.biz_id);
    query.set('start_date', state.pickup_date);
    query.set('end_date', state.return_date);

    try {
      const response = await fetch(`/api/fleet?${query.toString()}`);
      const payload = await response.json();
      const fleet = payload.data || [];
      return fleet.some((car) => String(car.id) === String(state.car_id));
    } catch (error) {
      return false;
    }
  }

  function renderFleetStep() {
    const body = createStep('fleet', 'Step 5', 'Our fleet');
    body.innerHTML = `
      <div class="assistant-flow-fleet-controls">
        <div class="assistant-flow-luxury-toggle" id="assistantLuxuryToggle">
          <button class="assistant-flow-mini-chip ${state.luxury_filter === 'all' ? 'active' : ''}" data-luxury="all" type="button">All</button>
          <button class="assistant-flow-mini-chip ${state.luxury_filter === 'luxury' ? 'active' : ''}" data-luxury="luxury" type="button">Luxury</button>
          <button class="assistant-flow-mini-chip ${state.luxury_filter === 'standard' ? 'active' : ''}" data-luxury="standard" type="button">Standard</button>
        </div>
        <div class="assistant-flow-card-actions assistant-flow-inline-actions">
          <button class="btn-outline assistant-flow-inline-button" id="assistantClearFilters" type="button">Clear</button>
        </div>
      </div>
      <div id="assistantFleetResults"></div>
    `;

    const results = body.querySelector('#assistantFleetResults');

    function syncLuxuryButtons(value) {
      state.luxury_filter = value;
      body.querySelectorAll('[data-luxury]').forEach((button) => {
        button.classList.toggle('active', button.dataset.luxury === value);
      });
      loadFleetInto(results);
    }

    body.querySelectorAll('[data-luxury]').forEach((button) => {
      button.addEventListener('click', () => syncLuxuryButtons(button.dataset.luxury || 'all'));
    });

    body.querySelector('#assistantClearFilters').addEventListener('click', async () => {
      state.luxury_filter = 'all';
      syncLuxuryButtons('all');
    });

    loadFleetInto(results);
  }

  function renderScheduleStep() {
    const body = createStep('schedule', 'Step 4', 'Rental schedule');
    const days = bookingDays();
    body.innerHTML = `
      <div class="assistant-flow-choice-panel">
        <p>Perfect! Here is your rental schedule with the pickup and return details. Edit the dates if needed, then continue to see the available fleet.</p>
      </div>
      <div class="assistant-flow-schedule-card">
        <div class="assistant-flow-schedule-head">
          <div>
            <h3>Rental Schedule</h3>
            <p>${days} days</p>
          </div>
        </div>
        <div class="assistant-flow-schedule-grid">
          <div class="assistant-flow-date-card">
            <span>PICKUP</span>
            <input id="assistantPickupDate" name="pickup_date" type="date" value="${escapeHtml(state.pickup_date)}" required />
            <small>${state.pickup_time}</small>
          </div>
          <div class="assistant-flow-schedule-arrow">-></div>
          <div class="assistant-flow-date-card">
            <span>RETURN</span>
            <input id="assistantReturnDate" name="return_date" type="date" value="${escapeHtml(state.return_date)}" required />
            <small>${state.return_time}</small>
          </div>
        </div>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantContinueSchedule" type="button">Continue</button>
        </div>
      </div>
    `;

    const pickupInput = body.querySelector('#assistantPickupDate');
    const returnInput = body.querySelector('#assistantReturnDate');
    const continueButton = body.querySelector('#assistantContinueSchedule');
    const minimumPickupDate = todayIso();

    pickupInput.min = minimumPickupDate;
    returnInput.min = state.pickup_date || minimumPickupDate;

    pickupInput.addEventListener('change', () => {
      returnInput.min = pickupInput.value || minimumPickupDate;
      if (returnInput.value && pickupInput.value && returnInput.value < pickupInput.value) {
        returnInput.value = pickupInput.value;
      }
    });

    continueButton.addEventListener('click', async () => {
      if (!pickupInput.value || !returnInput.value) {
        alert('Please select both dates.');
        return;
      }
      if (pickupInput.value < minimumPickupDate) {
        alert('Pickup date cannot be in the past.');
        return;
      }
      if (returnInput.value < pickupInput.value) {
        alert('Return date cannot be before pickup date.');
        return;
      }

      state.pickup_date = pickupInput.value;
      state.return_date = returnInput.value;
      state.rental_days = bookingDays();
      state.schedule_confirmed = true;
      lockCard('schedule');
      user(`${formatCardDate(state.pickup_date)} to ${formatCardDate(state.return_date)}.`);
      await assistantWithDelay('Here is our fleet for your selected dates. You can refine it by luxury before choosing a car.');
      renderFleetStep();
    });
  }

  function renderLocationStep() {
    const body = createStep('location', 'Step 6', 'Pickup address');
    body.innerHTML = `
      <form class="assistant-flow-form" id="assistantLocationForm">
        <div class="assistant-flow-fields">
          <label class="assistant-flow-field">
            <span>Address *</span>
            <input name="address" type="text" placeholder="Enter address" required />
          </label>
          <label class="assistant-flow-field">
            <span>Landmark</span>
            <input name="landmark" type="text" placeholder="Optional landmark" />
          </label>
          <label class="assistant-flow-field">
            <span>Pincode *</span>
            <input name="pincode" type="text" placeholder="Enter pincode" required />
          </label>
        </div>
        <div class="assistant-flow-card-actions">
          <button class="btn-black" type="submit">Continue</button>
        </div>
      </form>
    `;

    const form = body.querySelector('#assistantLocationForm');
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;

      const formData = new FormData(form);
      state.address = String(formData.get('address') || '').trim();
      state.landmark = String(formData.get('landmark') || '').trim();
      state.pincode = String(formData.get('pincode') || '').trim();
      state.location = locationString();

      lockCard('location');
      user(`Pickup from ${state.location}.`);
      await assistantWithDelay('Choose an insurance option, or skip it and continue.');
      renderInsuranceStep();
    });
  }

  async function renderInsuranceStep() {
    const body = createStep('insurance', 'Step 7', 'Insurance');
    body.innerHTML = '<div class="assistant-flow-loading">Loading insurance plans...</div>';

    const query = new URLSearchParams();
    if (state.biz_id) query.set('biz_id', state.biz_id);

    try {
      const response = await fetch(`/api/insurance?${query.toString()}`);
      const payload = await response.json();
      const plans = normalizeInsurance(payload.data || []);

      body.innerHTML = `
        <div class="assistant-flow-insurance-grid">
          ${plans
            .map(
              (plan) => `
                <article
                  class="assistant-flow-insurance-card"
                  data-plan="${escapeHtml(plan.name)}"
                  data-price="${Number(plan.price || 0)}"
                  tabindex="0"
                  role="button"
                  aria-pressed="false"
                >
                  <h3>${escapeHtml(plan.name)}</h3>
                  <div class="assistant-flow-insurance-price">${formatAED(Number(plan.price || 0))}</div>
                  <div class="assistant-flow-insurance-copy">
                    ${(plan.description.length ? plan.description : ['Cover details available on request'])
                      .map((line) => `<div>- ${escapeHtml(line)}</div>`)
                      .join('')}
                  </div>
                </article>
              `
            )
            .join('')}
        </div>
        <div class="assistant-flow-card-actions between assistant-flow-insurance-actions">
          <button class="btn-skip" id="assistantSkipInsurance" type="button">Skip insurance</button>
          <button class="btn-black" id="assistantContinueInsurance" type="button">Continue to summary</button>
        </div>
      `;

      let selectedCard = null;
      const cards = Array.from(body.querySelectorAll('.assistant-flow-insurance-card'));

      function selectCard(card) {
        selectedCard = card;
        cards.forEach((item) => {
          const active = item === card;
          item.classList.toggle('selected', active);
          item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (card) {
          state.insurance_plan = card.dataset.plan || 'No insurance';
          state.insurance_price = Number(card.dataset.price || 0);
        }
      }

      cards.forEach((card) => {
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
        lockCard('insurance');
        user('Skip insurance for this booking.');
        await assistantWithDelay('Here is the booking summary.');
        renderSummaryStep();
      });

      body.querySelector('#assistantContinueInsurance').addEventListener('click', async () => {
        if (!selectedCard) {
          alert('Select an insurance plan or skip it.');
          return;
        }

        lockCard('insurance');
        user(`Add ${state.insurance_plan} to this booking.`);
        await assistantWithDelay('Here is the booking summary.');
        renderSummaryStep();
      });
    } catch (error) {
      body.innerHTML = `
        <div class="assistant-flow-empty">
          I could not load insurance plans right now. Please try again.
        </div>
      `;
    }
  }

  function renderSummaryStep() {
    const body = createStep('summary', 'Step 8', 'Booking summary');
    const values = summaryValues();
    body.innerHTML = `
      <div class="assistant-flow-summary">
        <div class="assistant-flow-summary-row"><span>Customer</span><span>${escapeHtml(`${state.first_name} ${state.last_name}`.trim())}</span></div>
        <div class="assistant-flow-summary-row"><span>City</span><span>${escapeHtml(state.city || '-')}</span></div>
        <div class="assistant-flow-summary-row"><span>Vehicle</span><span>${escapeHtml(`${state.car_make} ${state.car_model}`.trim() || '-')}</span></div>
        <div class="assistant-flow-summary-row"><span>Duration</span><span>${values.days} day(s)</span></div>
        <div class="assistant-flow-summary-row"><span>Schedule</span><span>${escapeHtml(`${formatCardDate(state.pickup_date)} to ${formatCardDate(state.return_date)}`)}</span></div>
        <div class="assistant-flow-summary-row"><span>Pick-up</span><span>${escapeHtml(state.location || '-')}</span></div>
        <div class="assistant-flow-summary-divider"></div>
        <div class="assistant-flow-summary-row"><span>Base Rental</span><span>${formatAED(values.baseRental)}</span></div>
        <div class="assistant-flow-summary-row"><span>Insurance fee</span><span>${formatAED(values.insuranceFee)}</span></div>
        <div class="assistant-flow-summary-row"><span>Processing fee</span><span>${formatAED(values.processingFee)}</span></div>
        <div class="assistant-flow-summary-divider"></div>
        <div class="assistant-flow-summary-row"><strong>Subtotal</strong><span>${formatAED(values.subtotal)}</span></div>
        <div class="assistant-flow-summary-row"><span>VAT (5%)</span><span>${formatAED(values.vat)}</span></div>
        <div class="assistant-flow-summary-divider"></div>
        <div class="assistant-flow-summary-row total"><strong>TOTAL</strong><span>${formatAED(values.total)}</span></div>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantConfirmBooking" type="button">Confirm Booking</button>
        </div>
        <div class="summary-status" id="assistantConfirmStatus"></div>
        <div class="assistant-flow-card-actions center assistant-flow-summary-restart-row">
          <button
            class="btn-outline assistant-flow-summary-restart"
            id="summaryRestartFlow"
            data-keep-enabled="true"
            type="button"
          >
            Restart
          </button>
        </div>
      </div>
    `;

    const confirmButton = body.querySelector('#assistantConfirmBooking');
    const status = body.querySelector('#assistantConfirmStatus');
    const restartSummaryButton = body.querySelector('#summaryRestartFlow');

    restartSummaryButton.addEventListener('click', resetFlow);
    confirmButton.addEventListener('click', async () => {
      const payload = {
        biz_id: state.biz_id,
        customer_name: `${state.first_name} ${state.last_name}`.trim(),
        phone: state.phone,
        total_price: state.total_price,
        start_date: state.pickup_date,
        end_date: state.return_date,
        city: state.city,
        car_id: state.car_id,
        location: state.location,
        insurance: state.insurance_plan || 'No insurance',
      };

      confirmButton.disabled = true;
      status.textContent = 'Saving booking...';
      status.className = 'summary-status pending';

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
          confirmButton.disabled = false;
          return;
        }

        lockCard('summary');
        status.textContent = 'Booking confirmed. Our consultant will call you shortly to confirm the booking. Thank you';
        status.className = 'summary-status success';
        await assistantWithDelay('Booking confirmed. Our consultant will call you shortly to confirm the booking. Thank you');
      } catch (error) {
        status.textContent = 'Booking failed. Please try again.';
        status.className = 'summary-status error';
        confirmButton.disabled = false;
      }
    });
  }

  async function sendAiMessage(message) {
    const trimmed = message.trim();
    if (!trimmed) return;

    user(trimmed);
    const typingNode = renderTyping();
    const startedAt = Date.now();

    try {
      const response = await fetch('/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: trimmed,
          history: state.history.slice(0, -1),
          biz_id: state.biz_id,
          context: {
            start_date: state.pickup_date,
            end_date: state.return_date,
            city: state.city,
          },
        }),
      });
      const payload = await response.json();
      const elapsed = Date.now() - startedAt;
      if (elapsed < 350) {
        await wait(350 - elapsed);
      }
      typingNode.remove();
      assistant(payload.reply || payload.error || 'I could not respond right now.', payload.action || null);

      if (
        /fleet|available cars|show cars|show fleet|browse fleet|browse cars/i.test(trimmed) &&
        state.schedule_confirmed &&
        state.pickup_date &&
        state.return_date
      ) {
        if (!stepNodes.fleet) {
          await assistantWithDelay('I am loading the available fleet below.');
          renderFleetStep();
        } else {
          assistant('The fleet section is already on the page.');
          scrollToNode(stepNodes.fleet);
        }
      } else if (
        /fleet|available cars|show cars|show fleet|browse fleet|browse cars/i.test(trimmed) &&
        !state.schedule_confirmed
      ) {
        assistant('Confirm the rental schedule first and I will show the fleet for those dates.');
        if (stepNodes.schedule) {
          scrollToNode(stepNodes.schedule);
        }
      }
    } catch (error) {
      const elapsed = Date.now() - startedAt;
      if (elapsed < 350) {
        await wait(350 - elapsed);
      }
      typingNode.remove();
      assistant('I ran into an issue. Please try again.');
    }
  }

  async function resetFlow() {
    clearHoldTimer();
    state.first_name = initialPrefill.first_name;
    state.last_name = initialPrefill.last_name;
    state.phone = initialPrefill.phone;
    state.city = '';
    state.duration_label = '';
    state.rental_days = 0;
    state.pickup_date = '';
    state.return_date = '';
    state.pickup_time = '07:00 PM';
    state.return_time = '07:00 PM';
    state.schedule_confirmed = false;
    state.luxury_filter = 'all';
    state.car_id = '';
    state.car_make = '';
    state.car_model = '';
    state.price_per_day = 0;
    state.address = '';
    state.landmark = '';
    state.pincode = '';
    state.location = '';
    state.insurance_plan = 'No insurance';
    state.insurance_price = 0;
    state.total_price = 0;
    state.history = [];

    Object.keys(stepNodes).forEach((key) => delete stepNodes[key]);
    thread.innerHTML = '';
    composerInput.value = '';

    await assistantWithDelay("Hello! I'm Yobo.");
    if (state.first_name && state.last_name && state.phone) {
      user(`${state.first_name} ${state.last_name}, ${state.phone}.`);
      await assistantWithDelay(
        `Hi ${state.first_name}! I'm Yobo, your Smart Car Rentals assistant.\n\nWhich city do you need the car in?`
      );
      renderCityStep();
      return;
    }

    await assistantWithDelay("Let's start with your details.");
    renderDetailsStep();
  }

  composerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const text = composerInput.value;
    composerInput.value = '';
    await sendAiMessage(text);
  });

  resetFlow();
})();
