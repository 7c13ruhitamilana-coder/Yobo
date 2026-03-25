(() => {
  const thread = document.getElementById('assistantFlowThread');
  const composerForm = document.getElementById('assistantFlowComposer');
  const composerInput = document.getElementById('assistantFlowInput');
  const params = new URLSearchParams(window.location.search);

  const state = {
    biz_id: params.get('biz_id') || localStorage.getItem('biz_id') || '',
    first_name: '',
    last_name: '',
    phone: '',
    city: '',
    start_date: '',
    end_date: '',
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

  function todayIso() {
    return new Date().toISOString().split('T')[0];
  }

  function parseDate(value) {
    if (!value) return null;
    const parts = value.split('-');
    if (parts.length !== 3) return null;
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
  }

  function bookingDays() {
    const start = parseDate(state.start_date);
    const end = parseDate(state.end_date);
    if (!start || !end) {
      return 1;
    }
    const diff = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
    return Math.max(diff, 1);
  }

  function formatAED(amount) {
    const safe = Number.isFinite(amount) ? amount : 0;
    return `AED ${safe.toLocaleString()}`;
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

  function summaryValues() {
    const days = bookingDays();
    const baseRental = Number(state.price_per_day || 0) * days;
    const insuranceFee = Number(state.insurance_price || 0);
    const processingFee = 50;
    const subtotal = baseRental + insuranceFee + processingFee;
    const vat = Math.round(subtotal * 0.05);
    const total = subtotal + vat;
    state.total_price = total;
    return { days, baseRental, insuranceFee, processingFee, subtotal, vat, total };
  }

  function renderDetailsStep() {
    assistant("Hello! I'm Yobo.");
    assistant("Let's start with the customer details and the rental dates.");

    const body = createStep('details', 'Step 1', 'Customer details');
    body.innerHTML = `
      <form class="assistant-flow-form" id="assistantDetailsForm">
        <div class="assistant-flow-fields two-col">
          <label class="assistant-flow-field">
            <span>First name *</span>
            <input name="first_name" type="text" placeholder="Enter first name" required />
          </label>
          <label class="assistant-flow-field">
            <span>Last name *</span>
            <input name="last_name" type="text" placeholder="Enter last name" required />
          </label>
          <label class="assistant-flow-field">
            <span>Phone *</span>
            <input name="phone" type="text" placeholder="Enter phone number" required />
          </label>
          <label class="assistant-flow-field">
            <span>City *</span>
            <input name="city" list="assistantFlowCities" placeholder="Start typing city" required />
          </label>
          <label class="assistant-flow-field">
            <span>Start date *</span>
            <input id="assistantStartDate" name="start_date" type="date" required />
          </label>
          <label class="assistant-flow-field">
            <span>End date *</span>
            <input id="assistantEndDate" name="end_date" type="date" required />
          </label>
        </div>
        <datalist id="assistantFlowCities">
          <option>Abu Dhabi</option>
          <option>Al Ain</option>
          <option>Dubai</option>
          <option>Sharjah</option>
          <option>Ajman</option>
          <option>Umm Al Quwain</option>
          <option>Ras Al Khaimah</option>
          <option>Fujairah</option>
          <option>Khor Fakkan</option>
          <option>Kalba</option>
          <option>Dibba Al-Fujairah</option>
          <option>Dibba Al-Hisn</option>
          <option>Al Dhaid</option>
          <option>Hatta</option>
          <option>Jebel Ali</option>
        </datalist>
        <div class="assistant-flow-card-actions">
          <button class="btn-black" type="submit">Continue to fleet</button>
        </div>
      </form>
    `;

    const form = document.getElementById('assistantDetailsForm');
    const startInput = document.getElementById('assistantStartDate');
    const endInput = document.getElementById('assistantEndDate');
    const minDate = todayIso();

    startInput.min = minDate;
    endInput.min = minDate;

    startInput.addEventListener('change', () => {
      endInput.min = startInput.value || minDate;
      if (endInput.value && startInput.value && endInput.value < startInput.value) {
        endInput.value = startInput.value;
      }
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;

      if (startInput.value < minDate) {
        alert('Start date cannot be in the past.');
        return;
      }
      if (endInput.value < startInput.value) {
        alert('End date cannot be before start date.');
        return;
      }

      const formData = new FormData(form);
      state.first_name = String(formData.get('first_name') || '').trim();
      state.last_name = String(formData.get('last_name') || '').trim();
      state.phone = String(formData.get('phone') || '').trim();
      state.city = String(formData.get('city') || '').trim();
      state.start_date = String(formData.get('start_date') || '');
      state.end_date = String(formData.get('end_date') || '');

      lockCard('details');
      user(
        `${state.first_name} ${state.last_name}, ${state.city}, ${state.start_date} to ${state.end_date}.`
      );
      assistant(
        `Here is the fleet currently available from ${state.start_date} to ${state.end_date}.`
      );
      await renderFleetStep();
    });
  }

  async function renderFleetStep() {
    const body = createStep('fleet', 'Step 2', 'Choose an available car');
    body.innerHTML = '<div class="assistant-flow-loading">Loading available fleet...</div>';

    const query = new URLSearchParams();
    if (state.biz_id) query.set('biz_id', state.biz_id);
    query.set('start_date', state.start_date);
    query.set('end_date', state.end_date);

    try {
      const response = await fetch(`/api/fleet?${query.toString()}`);
      const payload = await response.json();
      const fleet = payload.data || [];

      if (!fleet.length) {
        body.innerHTML = `
          <div class="assistant-flow-empty">
            No cars are available for those dates. Change the dates and try again.
          </div>
        `;
        return;
      }

      body.innerHTML = `
        <div class="assistant-flow-fleet-grid">
          ${fleet
            .map((car) => {
              const image = escapeHtml(
                (car.photo_url || '').trim() ||
                  'https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1200&auto=format&fit=crop'
              );
              const make = escapeHtml(car.make || '-');
              const model = escapeHtml(car.model || '-');
              const price = Number(car.price_per_day || 0);
              return `
                <article class="assistant-flow-fleet-card">
                  <div class="assistant-flow-fleet-media">
                    <img src="${image}" alt="${make} ${model}" />
                  </div>
                  <div class="assistant-flow-fleet-meta">
                    <strong>${make} ${model}</strong>
                    <span>${formatAED(price)} per day</span>
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

      body.querySelectorAll('.assistant-flow-select').forEach((button) => {
        button.addEventListener('click', () => {
          state.car_id = button.dataset.id || '';
          state.car_make = button.dataset.make || '';
          state.car_model = button.dataset.model || '';
          state.price_per_day = Number(button.dataset.price || 0);

          lockCard('fleet');
          user(`Choose ${state.car_make} ${state.car_model} for this booking.`);
          assistant('Now add the pickup address for the booking.');
          renderLocationStep();
        });
      });
    } catch (error) {
      body.innerHTML = `
        <div class="assistant-flow-empty">
          I could not load the fleet right now. Please try again.
        </div>
      `;
    }
  }

  function renderLocationStep() {
    const body = createStep('location', 'Step 3', 'Pickup location');
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
          <button class="btn-black" type="submit">Continue to insurance</button>
        </div>
      </form>
    `;

    const form = document.getElementById('assistantLocationForm');
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;

      const formData = new FormData(form);
      state.address = String(formData.get('address') || '').trim();
      state.landmark = String(formData.get('landmark') || '').trim();
      state.pincode = String(formData.get('pincode') || '').trim();
      state.location = [state.address, state.landmark, state.pincode]
        .filter(Boolean)
        .join(', ');

      lockCard('location');
      user(`Pickup from ${state.location}.`);
      assistant('Choose an insurance option, or skip it and continue.');
      await renderInsuranceStep();
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
      const rawDescription =
        item.details ||
        item.description ||
        '';
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

  async function renderInsuranceStep() {
    const body = createStep('insurance', 'Step 4', 'Insurance');
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
              (plan, index) => `
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
        <div class="assistant-flow-card-actions between">
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

      body.querySelector('#assistantSkipInsurance').addEventListener('click', () => {
        state.insurance_plan = 'No insurance';
        state.insurance_price = 0;
        lockCard('insurance');
        user('Skip insurance for this booking.');
        assistant('Here is the final booking summary.');
        renderSummaryStep();
      });

      body.querySelector('#assistantContinueInsurance').addEventListener('click', () => {
        if (!selectedCard) {
          alert('Select an insurance plan or skip it.');
          return;
        }

        lockCard('insurance');
        user(`Add ${state.insurance_plan} to this booking.`);
        assistant('Here is the final booking summary.');
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
    const body = createStep('summary', 'Step 5', 'Booking summary');
    const values = summaryValues();

    body.innerHTML = `
      <div class="assistant-flow-summary">
        <div class="assistant-flow-summary-row"><span>Vehicle</span><span>${escapeHtml(
          `${state.car_make} ${state.car_model}`.trim() || '-'
        )}</span></div>
        <div class="assistant-flow-summary-row"><span>Duration</span><span>${values.days} day(s)</span></div>
        <div class="assistant-flow-summary-row"><span>Pick-up</span><span>${escapeHtml(
          state.location || '-'
        )}</span></div>
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

    const confirmButton = document.getElementById('assistantConfirmBooking');
    const status = document.getElementById('assistantConfirmStatus');
    const restartSummaryButton = document.getElementById('summaryRestartFlow');

    if (restartSummaryButton) {
      restartSummaryButton.addEventListener('click', resetFlow);
    }

    confirmButton.addEventListener('click', async () => {
      const payload = {
        biz_id: state.biz_id,
        customer_name: `${state.first_name} ${state.last_name}`.trim(),
        phone: state.phone,
        total_price: state.total_price,
        start_date: state.start_date,
        end_date: state.end_date,
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
        status.textContent = 'Booking confirmed.';
        status.className = 'summary-status success';
        assistant('Booking confirmed. The reservation has been saved.');
      } catch (error) {
        status.textContent = 'Booking failed. Please try again.';
        status.className = 'summary-status error';
        confirmButton.disabled = false;
      }
    });
  }

  function renderTyping() {
    const node = document.createElement('div');
    node.className = 'assistant-flow-message assistant';
    node.innerHTML = '<div class="assistant-flow-bubble">Yobo is typing...</div>';
    thread.appendChild(node);
    scrollToNode(node);
    return node;
  }

  async function sendAiMessage(message) {
    const trimmed = message.trim();
    if (!trimmed) return;

    user(trimmed);
    const typingNode = renderTyping();

    try {
      const response = await fetch('/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: trimmed,
          history: state.history.slice(0, -1),
          biz_id: state.biz_id,
          context: {
            start_date: state.start_date,
            end_date: state.end_date,
            city: state.city,
          },
        }),
      });
      const payload = await response.json();
      typingNode.remove();
      assistant(payload.reply || payload.error || 'I could not respond right now.', payload.action || null);

      if (
        /fleet|available cars|show cars|show fleet|browse fleet|browse cars/i.test(trimmed) &&
        state.start_date &&
        state.end_date
      ) {
        if (!stepNodes.fleet) {
          assistant('I am loading the available fleet below.');
          await renderFleetStep();
        } else {
          assistant('The available fleet section is already on the page.');
          scrollToNode(stepNodes.fleet);
        }
      }
    } catch (error) {
      typingNode.remove();
      assistant('I ran into an issue. Please try again.');
    }
  }

  function resetFlow() {
    state.first_name = '';
    state.last_name = '';
    state.phone = '';
    state.city = '';
    state.start_date = '';
    state.end_date = '';
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
