(() => {
  const thread = document.getElementById('assistantFlowThread');
  const composerForm = document.getElementById('assistantFlowComposer');
  const composerInput = document.getElementById('assistantFlowInput');
  const consentRoot = document.getElementById('assistantFlowConsentRoot');
  if (!thread || !composerForm || !composerInput) {
    return;
  }

  const params = new URLSearchParams(window.location.search);
  const companyName = document.body.dataset.companyName || 'Veep';
  const assistantName = document.body.dataset.assistantName || 'Yobo';
  const brandWordmark = document.body.dataset.brandWordmark || companyName;
  const defaultBizId = document.body.dataset.defaultBizId || '';
  const currentBusinessSlug = document.body.dataset.businessSlug || '';
  const currencyCode = document.body.dataset.currency || 'SGD';
  const processingFee = Number(document.body.dataset.processingFee || 50);
  const gstRate = Number(document.body.dataset.gstRate || 0.09);
  const flowConfigNode = document.getElementById('assistantFlowConfig');
  const storageNamespace = currentBusinessSlug || brandWordmark.toLowerCase().replace(/[^a-z0-9]+/g, '-') || 'default';
  const consentKey = `${storageNamespace}_resume_consent`;
  const draftKey = `${storageNamespace}_interest_draft_v2`;
  const cookieName = `${storageNamespace}_resume_consent`;
  const bizStorageKey = `${storageNamespace}_biz_id`;
  const startDateStorageKey = `${storageNamespace}_start_date`;
  const endDateStorageKey = `${storageNamespace}_end_date`;
  const fleetPromptExamples = ['family SUV', 'budget daily drive', 'something executive', 'electric car'];
  const interestTypeOptions = ['Daily', 'Weekly', 'Monthly', 'Yearly'];
  const citizenOptions = ['Yes', 'Yes Permanent Resident', 'No'];
  const panelSequence = ['dates', 'interest_type', 'contract_expiry', 'citizen', 'fleet', 'quote', 'review', 'summary'];

  const defaultFlowText = {
    intro_messages: [
      "Hello I'm {assistant_name}! Your AI-powered booking assistant.",
      'Please enter your full name.'
    ],
    name_reprompt: 'Please enter your full name.',
    name_complete: 'Nice to meet you {first_name}. What is your mobile number?',
    phone_reprompt: 'Please enter your mobile number next.',
    phone_complete: 'What is your email address?',
    email_reprompt: 'Please enter your email address next.',
    email_complete: 'What is your 6-digit Singapore zipcode?',
    zipcode_reprompt: 'Please enter your 6-digit Singapore zipcode.',
    zipcode_complete: 'What is your current vehicle type?',
    vehicle_type_reprompt: 'Please tell me your current vehicle type.',
    vehicle_type_complete: 'How long would you like the enquiry to run for? Please choose your from and to dates.',
    interest_type_prompt: 'What should I note as your interest form type?',
    contract_company_reprompt: 'Please tell me your current contract company.',
    contract_company_complete: 'Please select your current contract expiry date.',
    contract_expiry_complete: 'Are you a Singapore citizen?',
    citizen_prompt: `Please choose one of these options: ${citizenOptions.join(', ')}.`,
    fleet_intro: "Great! Now it's time for you to choose your dream car! If you'd like, I could suggest a car tailored to your needs.",
    quote_intro: "Here's your estimated quote.",
    review_intro: 'Before you submit, please review your enquiry details.',
    completion_message: 'Thank you. One of our consultants will contact you soon to continue your {company_name} enquiry.',
    missing_biz: 'This {company_name} enquiry link is missing business details. Please use the correct business link before continuing.',
  };

  let rawFlowConfig = {};
  if (flowConfigNode?.textContent) {
    try {
      rawFlowConfig = JSON.parse(flowConfigNode.textContent);
    } catch (error) {
      rawFlowConfig = {};
    }
  }
  const flowText = { ...defaultFlowText, ...(rawFlowConfig || {}) };

  function templateText(value, vars = {}) {
    return String(value || '').replace(/\{(\w+)\}/g, (_, key) => {
      if (key === 'assistant_name') return assistantName;
      if (key === 'company_name') return companyName;
      if (key === 'brand_wordmark') return brandWordmark;
      if (key === 'first_name') return state.first_name || '';
      return vars[key] ?? '';
    });
  }

  function flowMessage(key, vars = {}) {
    return templateText(flowText[key], vars);
  }

  function flowMessages(key, vars = {}) {
    const raw = flowText[key];
    const values = Array.isArray(raw) ? raw : [raw];
    return values.map((value) => templateText(value, vars)).filter(Boolean);
  }

  const initialBizId = params.get('biz_id') || defaultBizId || localStorage.getItem(bizStorageKey) || '';
  if (initialBizId) {
    localStorage.setItem(bizStorageKey, initialBizId);
  }

  function buildInitialState() {
    return {
      biz_id: initialBizId,
      stage: 'consent',
      full_name: '',
      first_name: '',
      phone: '',
      email: '',
      zipcode: '',
      current_vehicle_type: '',
      contract_company: '',
      contract_expiry: '',
      citizen_status: '',
      start_date: '',
      end_date: '',
      rental_days: 0,
      interest_form_type: '',
      fleet_results: [],
      car_id: '',
      car_make: '',
      car_model: '',
      car_luxury: '',
      price_per_day: 0,
      total_price: 0,
      history: [],
    };
  }

  const state = buildInitialState();
  const panels = {};

  function activeBizId() {
    return state.biz_id || localStorage.getItem(bizStorageKey) || defaultBizId || '';
  }

  function wait(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
  }

  function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 86400000).toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
  }

  function getCookie(name) {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : '';
  }

  function getConsentChoice() {
    return localStorage.getItem(consentKey) || getCookie(cookieName) || '';
  }

  function setConsentChoice(value) {
    localStorage.setItem(consentKey, value);
    setCookie(cookieName, value, 180);
  }

  function clearDraft() {
    localStorage.removeItem(draftKey);
    localStorage.removeItem(startDateStorageKey);
    localStorage.removeItem(endDateStorageKey);
  }

  function saveDraft() {
    if (getConsentChoice() !== 'accepted') return;
    const payload = {
      state: {
        biz_id: activeBizId(),
        stage: state.stage,
        full_name: state.full_name,
        first_name: state.first_name,
        phone: state.phone,
        email: state.email,
        zipcode: state.zipcode,
        current_vehicle_type: state.current_vehicle_type,
        contract_company: state.contract_company,
        contract_expiry: state.contract_expiry,
        citizen_status: state.citizen_status,
        start_date: state.start_date,
        end_date: state.end_date,
        rental_days: state.rental_days,
        interest_form_type: state.interest_form_type,
        car_id: state.car_id,
        car_make: state.car_make,
        car_model: state.car_model,
        car_luxury: state.car_luxury,
        price_per_day: state.price_per_day,
        total_price: state.total_price,
      },
      savedAt: Date.now(),
    };
    localStorage.setItem(draftKey, JSON.stringify(payload));
    if (activeBizId()) {
      localStorage.setItem(bizStorageKey, activeBizId());
    }
    if (state.start_date) {
      localStorage.setItem(startDateStorageKey, state.start_date);
    } else {
      localStorage.removeItem(startDateStorageKey);
    }
    if (state.end_date) {
      localStorage.setItem(endDateStorageKey, state.end_date);
    } else {
      localStorage.removeItem(endDateStorageKey);
    }
  }

  function loadDraft() {
    try {
      const raw = localStorage.getItem(draftKey);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return parsed && parsed.state ? parsed : null;
    } catch (error) {
      return null;
    }
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

  async function assistantBatch(messages, delay = 380) {
    const typingNode = renderTyping();
    await wait(delay);
    typingNode.remove();
    messages.forEach((message) => {
      addHistory('assistant', message);
      appendMessage('assistant', message);
    });
  }

  async function assistantSay(message, delay = 380) {
    await assistantBatch([message], delay);
  }

  function setComposerEnabled(enabled) {
    composerInput.disabled = !enabled;
    composerForm.querySelector('button')?.toggleAttribute('disabled', !enabled);
  }

  function clearConsentCard() {
    if (consentRoot) consentRoot.innerHTML = '';
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
      if (element.dataset.keepEnabled === 'true') return;
      element.disabled = true;
    });
  }

  function setStage(stage) {
    state.stage = stage;
    const placeholders = {
      consent: 'Waiting for your choice...',
      resume: 'Choose an option below...',
      name: 'Type your full name...',
      phone: 'Type your mobile number...',
      email: 'Type your email address...',
      zipcode: 'Type your 6-digit zipcode...',
      vehicle_type: 'Type your current vehicle type...',
      contract_company: 'Type your current contract company...',
      fleet: 'Ask for a recommendation or choose a car below...',
      complete: 'You can ask another question or start again...',
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
    const startDate = parseDate(state.start_date);
    const endDate = parseDate(state.end_date);
    if (!startDate || !endDate) return 0;
    return Math.max(Math.ceil((endDate - startDate) / 86400000), 1);
  }

  function formatCardDate(value) {
    const dateValue = parseDate(value);
    if (!dateValue) return '--';
    return dateValue.toLocaleDateString('en-SG', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  }

  function formatCurrency(amount) {
    const safe = Number.isFinite(amount) ? amount : 0;
    return new Intl.NumberFormat('en-SG', {
      style: 'currency',
      currency: currencyCode,
      maximumFractionDigits: 0,
    }).format(safe);
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
    };
  }

  function looksLikeQuestion(value) {
    return /(\?|suggest|recommend|what|which|how|can you|could you|help|price|quote|fleet|car|cars|luxury|budget|book|booking)/i.test(String(value || '').trim());
  }

  function normalizePhone(value) {
    const match = String(value || '').match(/[+()0-9\s-]{7,}/);
    return match ? match[0].trim() : String(value || '').trim();
  }

  function isValidPhone(value) {
    const digits = normalizePhone(value).replace(/\D/g, '');
    return digits.length >= 8;
  }

  function normalizeEmail(value) {
    return String(value || '').trim().toLowerCase();
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizeEmail(value));
  }

  function normalizeZipcode(value) {
    const match = String(value || '').match(/\d{6}/);
    return match ? match[0] : String(value || '').trim();
  }

  function isValidZipcode(value) {
    return /^\d{6}$/.test(normalizeZipcode(value));
  }

  function normalizeCitizenStatus(value) {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === 'yes') return 'Yes';
    if (normalized.includes('permanent') || normalized === 'pr' || normalized === 'yes pr') return 'Yes Permanent Resident';
    if (normalized === 'no') return 'No';
    return '';
  }

  function normalizeInterestType(value) {
    const normalized = String(value || '').trim().toLowerCase();
    const match = interestTypeOptions.find((option) => option.toLowerCase() === normalized);
    return match || '';
  }

  function estimateValues() {
    const days = bookingDays();
    const baseRental = Number(state.price_per_day || 0) * days;
    const subtotal = baseRental + processingFee;
    const gst = Math.round(subtotal * gstRate);
    const total = subtotal + gst;
    state.total_price = total;
    state.rental_days = days;
    return { days, baseRental, processingFee, subtotal, gst, total };
  }

  function fallbackFleetPhoto() {
    return 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?q=80&w=1200&auto=format&fit=crop';
  }

  function normalizeLuxuryLabel(value) {
    const lowered = String(value || '').trim().toLowerCase();
    if (['true', '1', 'yes', 'luxury'].includes(lowered)) return 'Luxury';
    if (['false', '0', 'no', 'standard'].includes(lowered)) return 'Standard';
    return lowered ? lowered.charAt(0).toUpperCase() + lowered.slice(1) : '';
  }

  function fleetCardMarkup(car, suggested = false) {
    const image = escapeHtml(String(car.photo_url || '').trim() || fallbackFleetPhoto());
    const fallback = escapeHtml(fallbackFleetPhoto());
    const title = escapeHtml(`${car.make || ''} ${car.model || ''}`.trim());
    const luxury = normalizeLuxuryLabel(car.luxury);
    return `
      <article class="assistant-flow-fleet-card ${suggested ? 'is-suggested' : ''}">
        <div class="assistant-flow-fleet-media">
          <img src="${image}" alt="${title}" loading="lazy" onerror="this.onerror=null;this.src='${fallback}';" />
        </div>
        <div class="assistant-flow-fleet-meta">
          ${suggested ? '<span class="assistant-flow-card-tag">Suggested for you</span>' : ''}
          <strong>${title}</strong>
          <span>${formatCurrency(Number(car.price_per_day || 0))} / day</span>
          ${luxury ? `<span>${escapeHtml(luxury)}</span>` : ''}
        </div>
        <button class="btn-black assistant-flow-select" type="button" data-id="${escapeHtml(car.id)}" data-make="${escapeHtml(car.make || '')}" data-model="${escapeHtml(car.model || '')}" data-price="${Number(car.price_per_day || 0)}" data-luxury="${escapeHtml(luxury)}">Select this car</button>
      </article>
    `;
  }

  function looksLikeSuggestionRequest(text) {
    return /(suggest|recommend|recommendation|best car|which car|what should|need a car|looking for|budget|affordable|cheap|luxury|family|suv|daily|business|electric|fast|performance)/i.test(String(text || ''));
  }

  function chooseSuggestedCar(promptText, fleet) {
    if (!fleet.length) return null;
    const lower = String(promptText || '').toLowerCase();
    const asc = [...fleet].sort((a, b) => Number(a.price_per_day || 0) - Number(b.price_per_day || 0));
    const desc = [...fleet].sort((a, b) => Number(b.price_per_day || 0) - Number(a.price_per_day || 0));
    const luxuryCars = fleet.filter((car) => normalizeLuxuryLabel(car.luxury) === 'Luxury');

    const matchByText = (patterns) => fleet.find((car) => {
      const haystack = `${car.make || ''} ${car.model || ''}`.toLowerCase();
      return patterns.some((pattern) => haystack.includes(pattern));
    });

    if (/electric|ev|tesla/.test(lower)) return matchByText(['tesla']) || asc[0];
    if (/family|suv|space|kids|luggage/.test(lower)) return matchByText(['range rover', 'sport', 'creta', 'urus']) || desc[0];
    if (/budget|cheap|affordable|economy/.test(lower)) return asc[0];
    if (/luxury|premium|business|executive|vip/.test(lower)) return luxuryCars[0] || desc[0];
    if (/fast|sport|performance|fun/.test(lower)) return matchByText(['911', 'm4', 'lamborghini', 'ferrari', 'mustang']) || desc[0];
    return asc[0];
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
            start_date: state.start_date,
            end_date: state.end_date,
          },
        }),
      });
      const payload = await response.json();
      return payload.reply || payload.error || '';
    } catch (error) {
      return '';
    }
  }

  async function maybeAnswerQuestion(text, reprompt) {
    if (!looksLikeQuestion(text)) return false;
    const reply = await fetchAiReply(text);
    await assistantBatch([
      reply || 'I can help with that as we go.',
      reprompt,
    ]);
    return true;
  }

  async function fetchFleetResults() {
    const query = new URLSearchParams();
    const bizId = activeBizId();
    if (bizId) query.set('biz_id', bizId);
    if (state.start_date) query.set('start_date', state.start_date);
    if (state.end_date) query.set('end_date', state.end_date);
    const response = await fetch(`/api/fleet?${query.toString()}`);
    const payload = await response.json();
    if (!payload.ok) throw new Error(payload.error || 'Unable to load the fleet.');
    if (payload.error) throw new Error(payload.error);
    state.fleet_results = Array.isArray(payload.data) ? payload.data : [];
    return state.fleet_results;
  }

  function selectCarFromButton(button) {
    state.car_id = button.dataset.id || '';
    state.car_make = button.dataset.make || '';
    state.car_model = button.dataset.model || '';
    state.price_per_day = Number(button.dataset.price || 0);
    state.car_luxury = button.dataset.luxury || '';
    saveDraft();
  }

  function bindCarSelectButtons(scope) {
    scope.querySelectorAll('.assistant-flow-select').forEach((button) => {
      button.addEventListener('click', async () => {
        selectCarFromButton(button);
        completePanel('fleet');
        userSay(`I want the ${state.car_make} ${state.car_model}.`);
        await assistantSay(flowMessage('quote_intro'));
        renderQuotePanel();
        setStage('quote');
      });
    });
  }

  async function renderFleetPanel(suggestedCar = null, suggestionText = '') {
    const body = upsertPanel('fleet', 'assistant-flow-card assistant-flow-card-wide');
    body.innerHTML = '<div class="assistant-flow-loading">Loading the available fleet...</div>';
    try {
      const fleet = await fetchFleetResults();
      if (!fleet.length) {
        body.innerHTML = '<div class="assistant-flow-empty">No vehicles are available for those dates right now.</div>';
        return;
      }
      const suggestionMarkup = suggestedCar ? `
        <div class="assistant-flow-suggestion-block">
          ${suggestionText ? `<p class="assistant-chat-panel-note">${escapeHtml(suggestionText)}</p>` : ''}
          ${fleetCardMarkup(suggestedCar, true)}
        </div>
      ` : '';
      body.innerHTML = `
        <div class="assistant-flow-fleet-status">Showing ${fleet.length} available vehicle${fleet.length === 1 ? '' : 's'} for ${escapeHtml(formatCardDate(state.start_date))} to ${escapeHtml(formatCardDate(state.end_date))}.</div>
        ${suggestionMarkup}
        <div class="assistant-flow-fleet-grid">
          ${fleet.map((car) => fleetCardMarkup(car)).join('')}
        </div>
      `;
      bindCarSelectButtons(body);
    } catch (error) {
      body.innerHTML = `<div class="assistant-flow-empty">${escapeHtml(error.message || 'I could not load the fleet right now.')}</div>`;
    }
  }

  function renderDatesPanel() {
    const body = upsertPanel('dates');
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const defaultStart = state.start_date || toIsoDate(tomorrow);
    const defaultEnd = state.end_date || toIsoDate(new Date(tomorrow.getFullYear(), tomorrow.getMonth(), tomorrow.getDate() + 1));
    body.innerHTML = `
      <div class="assistant-chat-panel-head">
        <strong>Rental dates</strong>
        <p class="assistant-chat-panel-note">Choose your enquiry period before I show the available fleet.</p>
      </div>
      <div class="assistant-flow-schedule-card">
        <div class="assistant-flow-schedule-grid">
          <label class="assistant-flow-date-card">
            <span>FROM DATE</span>
            <input id="assistantFromDate" type="date" value="${escapeHtml(defaultStart)}" required />
          </label>
          <div class="assistant-flow-schedule-arrow">to</div>
          <label class="assistant-flow-date-card">
            <span>TO DATE</span>
            <input id="assistantToDate" type="date" value="${escapeHtml(defaultEnd)}" required />
          </label>
        </div>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantContinueDates" type="button">Continue</button>
        </div>
      </div>
    `;
    const fromInput = body.querySelector('#assistantFromDate');
    const toInput = body.querySelector('#assistantToDate');
    const continueButton = body.querySelector('#assistantContinueDates');
    const minimumDate = todayIso();
    fromInput.min = minimumDate;
    toInput.min = fromInput.value || minimumDate;

    fromInput.addEventListener('change', () => {
      toInput.min = fromInput.value || minimumDate;
      if (toInput.value && fromInput.value && toInput.value < fromInput.value) {
        toInput.value = fromInput.value;
      }
    });

    continueButton.addEventListener('click', async () => {
      if (!fromInput.value || !toInput.value) {
        await assistantSay('Please choose both your from and to dates.');
        return;
      }
      if (fromInput.value < minimumDate) {
        await assistantSay('Your from date cannot be in the past.');
        return;
      }
      if (toInput.value < fromInput.value) {
        await assistantSay('Your to date cannot be before your from date.');
        return;
      }
      state.start_date = fromInput.value;
      state.end_date = toInput.value;
      state.rental_days = bookingDays();
      completePanel('dates');
      saveDraft();
      userSay(`${formatCardDate(state.start_date)} to ${formatCardDate(state.end_date)}`);
      await assistantSay(flowMessage('interest_type_prompt'));
      renderInterestTypePanel();
      setStage('interest_type');
    });
  }

  function renderInterestTypePanel() {
    const body = upsertPanel('interest_type');
    body.innerHTML = `
      <div class="assistant-chat-panel-head">
        <strong>Interest form type</strong>
        <p class="assistant-chat-panel-note">Choose a type below or type one in the chat.</p>
      </div>
      <div class="assistant-flow-chip-row">
        ${interestTypeOptions.map((option) => `<button class="assistant-flow-chip ${state.interest_form_type === option ? 'active' : ''}" type="button" data-interest-type="${escapeHtml(option)}">${escapeHtml(option)}</button>`).join('')}
      </div>
    `;
    body.querySelectorAll('[data-interest-type]').forEach((button) => {
      button.addEventListener('click', async () => {
        await completeInterestType(button.dataset.interestType || '');
      });
    });
  }

  async function completeInterestType(value) {
    const choice = normalizeInterestType(value);
    if (!choice) {
      await assistantSay(`Please choose one of these options: ${interestTypeOptions.join(', ')}.`);
      return;
    }
    state.interest_form_type = choice;
    completePanel('interest_type');
    saveDraft();
    userSay(choice);
    await assistantSay(flowMessage('contract_company_reprompt'));
    setStage('contract_company');
  }

  function renderContractExpiryPanel() {
    const body = upsertPanel('contract_expiry');
    const minimumDate = todayIso();
    const defaultDate = state.contract_expiry || minimumDate;
    body.innerHTML = `
      <div class="assistant-chat-panel-head">
        <strong>Current contract expiry</strong>
        <p class="assistant-chat-panel-note">Select the expiry date of your current contract.</p>
      </div>
      <div class="assistant-flow-schedule-card assistant-flow-single-date">
        <label class="assistant-flow-date-card assistant-flow-date-card-single">
          <span>CONTRACT EXPIRY</span>
          <input id="assistantContractExpiry" type="date" min="${escapeHtml(minimumDate)}" value="${escapeHtml(defaultDate)}" required />
        </label>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantContinueContractExpiry" type="button">Continue</button>
        </div>
      </div>
    `;
    body.querySelector('#assistantContinueContractExpiry').addEventListener('click', async () => {
      const value = body.querySelector('#assistantContractExpiry').value;
      if (!value) {
        await assistantSay(flowMessage('contract_company_complete'));
        return;
      }
      if (value < minimumDate) {
        await assistantSay('The contract expiry cannot be in the past.');
        return;
      }
      state.contract_expiry = value;
      completePanel('contract_expiry');
      saveDraft();
      userSay(formatCardDate(value));
      await assistantSay(flowMessage('contract_expiry_complete'));
      renderCitizenPanel();
      setStage('citizen_status');
    });
  }

  function renderCitizenPanel() {
    const body = upsertPanel('citizen');
    body.innerHTML = `
      <div class="assistant-chat-panel-head">
        <strong>Singapore citizenship</strong>
        <p class="assistant-chat-panel-note">Choose one below or type your answer in the chat.</p>
      </div>
      <div class="assistant-flow-chip-row">
        ${citizenOptions.map((option) => `<button class="assistant-flow-chip ${state.citizen_status === option ? 'active' : ''}" type="button" data-citizen-status="${escapeHtml(option)}">${escapeHtml(option)}</button>`).join('')}
      </div>
    `;
    body.querySelectorAll('[data-citizen-status]').forEach((button) => {
      button.addEventListener('click', async () => {
        await completeCitizenStatus(button.dataset.citizenStatus || '');
      });
    });
  }

  async function completeCitizenStatus(value) {
    const status = normalizeCitizenStatus(value);
    if (!status) {
      await assistantSay(flowMessage('citizen_prompt'));
      return;
    }
    state.citizen_status = status;
    completePanel('citizen');
    saveDraft();
    userSay(status);
    await assistantBatch([
      flowMessage('fleet_intro')
    ]);
    await renderFleetPanel();
    setStage('fleet');
  }

  function renderQuotePanel() {
    const body = upsertPanel('quote');
    const values = estimateValues();
    body.innerHTML = `
      <div class="assistant-chat-panel-head">
        <strong>Estimated quote</strong>
        <p class="assistant-chat-panel-note">This estimate is shown in ${escapeHtml(currencyCode)} for the dates and vehicle you selected.</p>
      </div>
      <div class="assistant-flow-quote-card">
        <div class="assistant-flow-detail-grid">
          <div><span>Vehicle</span><strong>${escapeHtml(`${state.car_make} ${state.car_model}`)}</strong></div>
          <div><span>Interest type</span><strong>${escapeHtml(state.interest_form_type)}</strong></div>
          <div><span>Schedule</span><strong>${escapeHtml(`${formatCardDate(state.start_date)} to ${formatCardDate(state.end_date)}`)}</strong></div>
          <div><span>Current vehicle type</span><strong>${escapeHtml(state.current_vehicle_type)}</strong></div>
          <div><span>Daily rate</span><strong>${formatCurrency(Number(state.price_per_day || 0))}</strong></div>
          <div><span>Estimated total</span><strong>${formatCurrency(values.total)}</strong></div>
        </div>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantContinueQuote" type="button">Continue</button>
        </div>
      </div>
    `;
    body.querySelector('#assistantContinueQuote').addEventListener('click', async () => {
      completePanel('quote');
      await assistantSay(flowMessage('review_intro'));
      renderReviewPanel();
      setStage('review');
    });
  }

  function renderReviewPanel() {
    const body = upsertPanel('review');
    body.innerHTML = `
      <form class="assistant-flow-form" id="assistantReviewForm">
        <div class="assistant-chat-panel-head">
          <strong>Review your details</strong>
          <p class="assistant-chat-panel-note">You can edit any detail below before submitting your interest.</p>
        </div>
        <div class="assistant-flow-fields two-col">
          <label class="assistant-flow-field"><span>Contact Person Name *</span><input name="full_name" type="text" value="${escapeHtml(state.full_name)}" required /></label>
          <label class="assistant-flow-field"><span>Contact Person Mobile *</span><input name="phone" type="text" value="${escapeHtml(state.phone)}" required /></label>
          <label class="assistant-flow-field"><span>Email *</span><input name="email" type="email" value="${escapeHtml(state.email)}" required /></label>
          <label class="assistant-flow-field"><span>Zipcode *</span><input name="zipcode" type="text" value="${escapeHtml(state.zipcode)}" required /></label>
          <label class="assistant-flow-field"><span>Current Vehicle Type *</span><input name="current_vehicle_type" type="text" value="${escapeHtml(state.current_vehicle_type)}" required /></label>
          <label class="assistant-flow-field"><span>Interest Form Type *</span><input name="interest_form_type" type="text" value="${escapeHtml(state.interest_form_type)}" required /></label>
          <label class="assistant-flow-field"><span>Current Contract Company *</span><input name="contract_company" type="text" value="${escapeHtml(state.contract_company)}" required /></label>
          <label class="assistant-flow-field"><span>Current Contract Expiry *</span><input name="contract_expiry" type="date" value="${escapeHtml(state.contract_expiry)}" required /></label>
          <label class="assistant-flow-field"><span>Singapore Citizens *</span><input name="citizen_status" type="text" value="${escapeHtml(state.citizen_status)}" required /></label>
          <label class="assistant-flow-field"><span>From Date *</span><input name="start_date" type="date" value="${escapeHtml(state.start_date)}" required /></label>
          <label class="assistant-flow-field"><span>To Date *</span><input name="end_date" type="date" value="${escapeHtml(state.end_date)}" required /></label>
        </div>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" type="submit">Continue to summary</button>
        </div>
      </form>
    `;
    body.querySelector('#assistantReviewForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      if (!form.reportValidity()) return;
      const formData = new FormData(form);
      const name = splitFullName(formData.get('full_name'));
      const phone = normalizePhone(formData.get('phone'));
      const email = normalizeEmail(formData.get('email'));
      const zipcode = normalizeZipcode(formData.get('zipcode'));
      const vehicleType = String(formData.get('current_vehicle_type') || '').trim();
      const interestType = normalizeInterestType(formData.get('interest_form_type'));
      const contractCompany = String(formData.get('contract_company') || '').trim();
      const contractExpiry = String(formData.get('contract_expiry') || '').trim();
      const citizenStatus = normalizeCitizenStatus(formData.get('citizen_status'));
      const startDate = String(formData.get('start_date') || '').trim();
      const endDate = String(formData.get('end_date') || '').trim();
      const minimumDate = todayIso();

      if (!name.full_name) return void await assistantSay('Please enter your full name.');
      if (!isValidPhone(phone)) return void await assistantSay('Please enter a valid mobile number.');
      if (!isValidEmail(email)) return void await assistantSay('Please enter a valid email address.');
      if (!isValidZipcode(zipcode)) return void await assistantSay('Please enter a valid 6-digit Singapore zipcode.');
      if (!vehicleType) return void await assistantSay('Please enter your current vehicle type.');
      if (!interestType) return void await assistantSay(`Please use one of these interest form types: ${interestTypeOptions.join(', ')}.`);
      if (!contractCompany) return void await assistantSay('Please enter your current contract company.');
      if (!contractExpiry || contractExpiry < minimumDate) return void await assistantSay('Please choose a valid current contract expiry date.');
      if (!citizenStatus) return void await assistantSay(`Please choose one of these options: ${citizenOptions.join(', ')}.`);
      if (!startDate || !endDate) return void await assistantSay('Please choose your from and to dates.');
      if (startDate < minimumDate) return void await assistantSay('Your from date cannot be in the past.');
      if (endDate < startDate) return void await assistantSay('Your to date cannot be before your from date.');

      const availabilityQuery = new URLSearchParams();
      const activeBiz = activeBizId();
      if (activeBiz) availabilityQuery.set('biz_id', activeBiz);
      availabilityQuery.set('start_date', startDate);
      availabilityQuery.set('end_date', endDate);
      const availabilityResponse = await fetch(`/api/fleet?${availabilityQuery.toString()}`);
      const availabilityPayload = await availabilityResponse.json();
      const stillAvailable = Array.isArray(availabilityPayload.data)
        && availabilityPayload.data.some((car) => String(car.id) === String(state.car_id));
      if (!availabilityPayload.ok) {
        return void await assistantSay(availabilityPayload.error || 'I could not re-check the fleet right now.');
      }
      if (!stillAvailable) {
        clearPanelsFrom('fleet');
        state.start_date = startDate;
        state.end_date = endDate;
        state.rental_days = bookingDays();
        saveDraft();
        await assistantBatch([
          'The car you selected is no longer available for those dates. Please choose another vehicle.'
        ]);
        await renderFleetPanel();
        setStage('fleet');
        return;
      }

      state.full_name = name.full_name;
      state.first_name = name.first_name;
      state.phone = phone;
      state.email = email;
      state.zipcode = zipcode;
      state.current_vehicle_type = vehicleType;
      state.interest_form_type = interestType;
      state.contract_company = contractCompany;
      state.contract_expiry = contractExpiry;
      state.citizen_status = citizenStatus;
      state.start_date = startDate;
      state.end_date = endDate;
      state.rental_days = bookingDays();
      completePanel('review');
      saveDraft();
      userSay('These details look good.');
      await assistantSay("Here's your enquiry summary.");
      renderSummaryPanel();
      setStage('summary');
    });
  }

  function renderSummaryPanel() {
    const body = upsertPanel('summary');
    const values = estimateValues();
    body.innerHTML = `
      <div class="assistant-flow-summary">
        <div class="assistant-flow-summary-row"><span>Contact Person Name</span><span>${escapeHtml(state.full_name)}</span></div>
        <div class="assistant-flow-summary-row"><span>Contact Person Mobile</span><span>${escapeHtml(state.phone)}</span></div>
        <div class="assistant-flow-summary-row"><span>Email</span><span>${escapeHtml(state.email)}</span></div>
        <div class="assistant-flow-summary-row"><span>Zipcode</span><span>${escapeHtml(state.zipcode)}</span></div>
        <div class="assistant-flow-summary-row"><span>Current Vehicle Type</span><span>${escapeHtml(state.current_vehicle_type)}</span></div>
        <div class="assistant-flow-summary-row"><span>Current Contract Company</span><span>${escapeHtml(state.contract_company)}</span></div>
        <div class="assistant-flow-summary-row"><span>Current Contract Expiry</span><span>${escapeHtml(formatCardDate(state.contract_expiry))}</span></div>
        <div class="assistant-flow-summary-row"><span>Singapore Citizens</span><span>${escapeHtml(state.citizen_status)}</span></div>
        <div class="assistant-flow-summary-row"><span>Interest Form Type</span><span>${escapeHtml(state.interest_form_type)}</span></div>
        <div class="assistant-flow-summary-row"><span>Selected Vehicle</span><span>${escapeHtml(`${state.car_make} ${state.car_model}`)}</span></div>
        <div class="assistant-flow-summary-row"><span>Schedule</span><span>${escapeHtml(`${formatCardDate(state.start_date)} to ${formatCardDate(state.end_date)}`)}</span></div>
        <div class="assistant-flow-summary-divider"></div>
        <div class="assistant-flow-summary-row"><span>Base estimate</span><span>${formatCurrency(values.baseRental)}</span></div>
        <div class="assistant-flow-summary-row"><span>Processing fee</span><span>${formatCurrency(values.processingFee)}</span></div>
        <div class="assistant-flow-summary-row"><span>GST (9%)</span><span>${formatCurrency(values.gst)}</span></div>
        <div class="assistant-flow-summary-row total"><strong>Estimated total</strong><span>${formatCurrency(values.total)}</span></div>
        <p class="assistant-chat-summary-note">Your enquiry estimate is shown in ${escapeHtml(currencyCode)} and will be shared with our team together with the details above.</p>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantSubmitInterest" type="button">Submit Interest</button>
        </div>
        <div class="summary-status" id="assistantSubmitStatus"></div>
        <div class="assistant-flow-card-actions center assistant-flow-summary-restart-row">
          <button class="btn-outline assistant-flow-summary-restart" id="assistantRestartFlow" data-keep-enabled="true" type="button">Start again</button>
        </div>
      </div>
    `;

    body.querySelector('#assistantRestartFlow').addEventListener('click', async () => {
      clearDraft();
      await resetFlow();
    });

    body.querySelector('#assistantSubmitInterest').addEventListener('click', async () => {
      const status = body.querySelector('#assistantSubmitStatus');
      const button = body.querySelector('#assistantSubmitInterest');
      button.disabled = true;
      status.textContent = 'Submitting enquiry...';
      status.className = 'summary-status pending';

      const payload = {
        biz_id: activeBizId(),
        customer_name: state.full_name,
        phone: state.phone,
        city: 'Singapore',
        start_date: state.start_date,
        end_date: state.end_date,
        car_id: state.car_id,
        total_price: state.total_price,
        location: state.zipcode,
        insurance: 'No insurance',
        email: state.email,
        current_vehicle_type: state.current_vehicle_type,
        contract_company: state.contract_company,
        contract_expiry: state.contract_expiry,
        citizen_status: state.citizen_status,
        interest_form_type: state.interest_form_type,
      };

      try {
        const response = await fetch('/api/bookings', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (!result.ok) {
          status.textContent = result.error || 'Submission failed.';
          status.className = 'summary-status error';
          button.disabled = false;
          return;
        }
        completePanel('summary');
        clearDraft();
        status.textContent = 'Interest form submitted.';
        status.className = 'summary-status success';
        await assistantBatch([
          flowMessage('completion_message')
        ]);
        setStage('complete');
      } catch (error) {
        status.textContent = 'Submission failed. Please try again.';
        status.className = 'summary-status error';
        button.disabled = false;
      }
    });
  }

  async function handleNameInput(text) {
    if (await maybeAnswerQuestion(text, flowMessage('name_reprompt'))) return;
    const name = splitFullName(text);
    if (!name.full_name || name.full_name.split(/\s+/).length < 2) {
      await assistantSay(flowMessage('name_reprompt'));
      return;
    }
    state.full_name = name.full_name;
    state.first_name = name.first_name;
    saveDraft();
    await assistantBatch([
      flowMessage('name_complete', { first_name: state.first_name })
    ]);
    setStage('phone');
  }

  async function handlePhoneInput(text) {
    if (await maybeAnswerQuestion(text, flowMessage('phone_reprompt'))) return;
    if (!isValidPhone(text)) {
      await assistantSay('Please enter a valid mobile number.');
      return;
    }
    state.phone = normalizePhone(text);
    saveDraft();
    await assistantSay(flowMessage('phone_complete'));
    setStage('email');
  }

  async function handleEmailInput(text) {
    if (await maybeAnswerQuestion(text, flowMessage('email_reprompt'))) return;
    if (!isValidEmail(text)) {
      await assistantSay('Please enter a valid email address.');
      return;
    }
    state.email = normalizeEmail(text);
    saveDraft();
    await assistantSay(flowMessage('email_complete'));
    setStage('zipcode');
  }

  async function handleZipcodeInput(text) {
    if (await maybeAnswerQuestion(text, flowMessage('zipcode_reprompt'))) return;
    if (!isValidZipcode(text)) {
      await assistantSay('Please enter a valid 6-digit Singapore zipcode.');
      return;
    }
    state.zipcode = normalizeZipcode(text);
    saveDraft();
    await assistantSay(flowMessage('zipcode_complete'));
    setStage('vehicle_type');
  }

  async function handleVehicleTypeInput(text) {
    if (await maybeAnswerQuestion(text, flowMessage('vehicle_type_reprompt'))) return;
    const value = String(text || '').trim();
    if (!value) {
      await assistantSay(flowMessage('vehicle_type_reprompt'));
      return;
    }
    state.current_vehicle_type = value;
    saveDraft();
    await assistantSay(flowMessage('vehicle_type_complete'));
    renderDatesPanel();
    setStage('dates');
  }

  async function handleContractCompanyInput(text) {
    if (await maybeAnswerQuestion(text, flowMessage('contract_company_reprompt'))) return;
    const value = String(text || '').trim();
    if (!value) {
      await assistantSay(flowMessage('contract_company_reprompt'));
      return;
    }
    state.contract_company = value;
    saveDraft();
    await assistantSay(flowMessage('contract_company_complete'));
    renderContractExpiryPanel();
    setStage('contract_expiry');
  }

  async function handleFleetInput(text) {
    if (!state.fleet_results.length) await renderFleetPanel();
    if (/^(show all|browse|view fleet|normal fleet|skip suggestion|no thanks|no suggestion)/i.test(text.trim())) {
      await assistantSay('Absolutely. Browse the available fleet below and choose the one you like.');
      if (panels.fleet) scrollToNode(panels.fleet);
      return;
    }
    if (!looksLikeSuggestionRequest(text)) {
      const reply = await fetchAiReply(text);
      await assistantSay(reply || `You can ask me for a recommendation, for example: ${fleetPromptExamples.join(', ')}.`);
      return;
    }
    const suggestion = chooseSuggestedCar(text, state.fleet_results);
    const aiReply = await fetchAiReply(text);
    if (!suggestion) {
      await assistantSay(aiReply || 'I do not have a clear match yet, so please browse the available fleet below.');
      return;
    }
    await assistantBatch([
      aiReply || `Based on what you need, I'd recommend the ${suggestion.make} ${suggestion.model}.`
    ]);
    await renderFleetPanel(suggestion, 'This is my best match based on your request.');
  }

  async function handleGeneralQuestion(text) {
    const reply = await fetchAiReply(text);
    if (reply) {
      await assistantSay(reply);
      return;
    }
    await assistantSay('I can help with your enquiry here. If a card is open, complete that step or ask me for a vehicle suggestion.');
  }

  async function handleComposerSubmit(text) {
    const trimmed = String(text || '').trim();
    if (!trimmed) return;
    if (state.stage !== 'consent' && state.stage !== 'resume') {
      userSay(trimmed);
    }

    if (state.stage === 'name') return handleNameInput(trimmed);
    if (state.stage === 'phone') return handlePhoneInput(trimmed);
    if (state.stage === 'email') return handleEmailInput(trimmed);
    if (state.stage === 'zipcode') return handleZipcodeInput(trimmed);
    if (state.stage === 'vehicle_type') return handleVehicleTypeInput(trimmed);
    if (state.stage === 'interest_type') return completeInterestType(trimmed);
    if (state.stage === 'contract_company') return handleContractCompanyInput(trimmed);
    if (state.stage === 'citizen_status') return completeCitizenStatus(trimmed);
    if (state.stage === 'fleet') return handleFleetInput(trimmed);
    return handleGeneralQuestion(trimmed);
  }

  function renderConsentCard() {
    if (!consentRoot) return;
    consentRoot.innerHTML = `
      <section class="assistant-flow-consent-card">
        <strong>Save progress on this device?</strong>
        <p>With your permission, ${escapeHtml(companyName)} can use cookies and device storage to remember your enquiry so you can resume later on this same browser.</p>
        <div class="assistant-flow-card-actions center">
          <button class="btn-outline" id="assistantConsentDecline" type="button">Not now</button>
          <button class="btn-black" id="assistantConsentAccept" type="button">Allow and continue</button>
        </div>
      </section>
    `;
    document.getElementById('assistantConsentAccept')?.addEventListener('click', async () => {
      setConsentChoice('accepted');
      clearConsentCard();
      await beginAfterConsent();
    });
    document.getElementById('assistantConsentDecline')?.addEventListener('click', async () => {
      setConsentChoice('declined');
      clearDraft();
      clearConsentCard();
      await beginAfterConsent();
    });
  }

  function renderResumeCard(saved) {
    if (!consentRoot) return;
    const savedTime = saved?.savedAt ? new Date(saved.savedAt).toLocaleString('en-SG') : '';
    consentRoot.innerHTML = `
      <section class="assistant-flow-consent-card">
        <strong>Resume your saved enquiry?</strong>
        <p>I found a saved ${escapeHtml(companyName)} enquiry on this device${savedTime ? ` from ${escapeHtml(savedTime)}` : ''}. Would you like to continue where you left off?</p>
        <div class="assistant-flow-card-actions center">
          <button class="btn-outline" id="assistantResumeFresh" type="button">Start fresh</button>
          <button class="btn-black" id="assistantResumeContinue" type="button">Resume enquiry</button>
        </div>
      </section>
    `;
    document.getElementById('assistantResumeFresh')?.addEventListener('click', async () => {
      clearDraft();
      clearConsentCard();
      await startFreshIntro();
    });
    document.getElementById('assistantResumeContinue')?.addEventListener('click', async () => {
      clearConsentCard();
      await resumeFromDraft(saved.state);
    });
  }

  async function startFreshIntro() {
    thread.innerHTML = '';
    Object.keys(panels).forEach((key) => delete panels[key]);
    composerInput.value = '';
    if (!activeBizId()) {
      setStage('complete');
      setComposerEnabled(false);
      await assistantBatch([
        flowMessage('missing_biz')
      ]);
      return;
    }
    setComposerEnabled(true);
    setStage('name');
    await assistantBatch(flowMessages('intro_messages'));
  }

  async function resumeFromDraft(savedState) {
    Object.assign(state, buildInitialState(), savedState || {});
    thread.innerHTML = '';
    Object.keys(panels).forEach((key) => delete panels[key]);
    composerInput.value = '';
    setComposerEnabled(true);
    await assistantBatch([
      `Welcome back${state.first_name ? `, ${state.first_name}` : ''}. I’ve restored your enquiry on this device.`
    ]);

    if (!state.full_name) {
      setStage('name');
      await assistantSay(flowMessage('name_reprompt'));
      return;
    }
    if (!state.phone) {
      setStage('phone');
      await assistantSay(flowMessage('phone_reprompt'));
      return;
    }
    if (!state.email) {
      setStage('email');
      await assistantSay(flowMessage('email_reprompt'));
      return;
    }
    if (!state.zipcode) {
      setStage('zipcode');
      await assistantSay(flowMessage('zipcode_reprompt'));
      return;
    }
    if (!state.current_vehicle_type) {
      setStage('vehicle_type');
      await assistantSay(flowMessage('vehicle_type_reprompt'));
      return;
    }
    if (!state.start_date || !state.end_date) {
      setStage('dates');
      await assistantSay('Please choose your from and to dates.');
      renderDatesPanel();
      return;
    }
    completePanel('dates');
    if (!state.interest_form_type) {
      setStage('interest_type');
      await assistantSay(flowMessage('interest_type_prompt'));
      renderInterestTypePanel();
      return;
    }
    completePanel('interest_type');
    if (!state.contract_company) {
      setStage('contract_company');
      await assistantSay(flowMessage('contract_company_reprompt'));
      return;
    }
    if (!state.contract_expiry) {
      setStage('contract_expiry');
      await assistantSay(flowMessage('contract_company_complete'));
      renderContractExpiryPanel();
      return;
    }
    completePanel('contract_expiry');
    if (!state.citizen_status) {
      setStage('citizen_status');
      await assistantSay(flowMessage('citizen_prompt'));
      renderCitizenPanel();
      return;
    }
    completePanel('citizen');
    if (!state.car_id) {
      setStage('fleet');
      await assistantSay('Let’s continue with the available fleet.');
      await renderFleetPanel();
      return;
    }
    await renderFleetPanel();
    completePanel('fleet');
    renderQuotePanel();
    if (state.stage === 'quote') {
      setStage('quote');
      return;
    }
    completePanel('quote');
    renderReviewPanel();
    if (state.stage === 'review') {
      setStage('review');
      return;
    }
    completePanel('review');
    renderSummaryPanel();
    setStage(state.stage || 'summary');
  }

  async function beginAfterConsent() {
    const saved = getConsentChoice() === 'accepted' ? loadDraft() : null;
    if (saved?.state) {
      setStage('resume');
      setComposerEnabled(false);
      await assistantBatch([
        `Hello! I found a saved ${companyName} enquiry on this device.`
      ]);
      renderResumeCard(saved);
      return;
    }
    await startFreshIntro();
  }

  async function resetFlow() {
    Object.assign(state, buildInitialState());
    if (initialBizId) state.biz_id = initialBizId;
    clearConsentCard();
    const consent = getConsentChoice();
    if (!consent) {
      thread.innerHTML = '';
      setStage('consent');
      setComposerEnabled(false);
      await assistantBatch([
        `Hello! I'm ${assistantName}, your ${companyName} enquiry assistant.`,
        'Before we begin, may I save your progress on this device so you can continue later if needed?'
      ]);
      renderConsentCard();
      return;
    }
    await beginAfterConsent();
  }

  composerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const text = composerInput.value;
    composerInput.value = '';
    await handleComposerSubmit(text);
    saveDraft();
  });

  resetFlow();
})();
