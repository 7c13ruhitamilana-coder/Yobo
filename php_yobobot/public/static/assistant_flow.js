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
  const bookingMode = document.body.dataset.bookingMode || 'rental';
  const defaultBizId = document.body.dataset.defaultBizId || '';
  const currentBusinessSlug = document.body.dataset.businessSlug || '';
  const currencyCode = document.body.dataset.currency || 'SGD';
  const marketCity = document.body.dataset.marketCity || '';
  const processingFee = Number(document.body.dataset.processingFee || 50);
  const gstRate = Number(document.body.dataset.gstRate || 0.09);
  const discountType = document.body.dataset.discountType || 'none';
  const discountValue = Number(document.body.dataset.discountValue || 0);
  const discountLabel = document.body.dataset.discountLabel || '';
  const flowConfigNode = document.getElementById('assistantFlowConfig');
  const storageNamespace = currentBusinessSlug || brandWordmark.toLowerCase().replace(/[^a-z0-9]+/g, '-') || 'default';
  const consentKey = `${storageNamespace}_resume_consent`;
  const draftKey = `${storageNamespace}_interest_draft_v2`;
  const cookieName = `${storageNamespace}_resume_consent`;
  const bizStorageKey = `${storageNamespace}_biz_id`;
  const startDateStorageKey = `${storageNamespace}_start_date`;
  const endDateStorageKey = `${storageNamespace}_end_date`;
  const isServiceMode = bookingMode === 'service';
  const fleetPromptExamples = isServiceMode
    ? ['oil change', 'brake inspection', 'battery check', 'AC service']
    : ['family SUV', 'budget daily drive', 'something executive', 'electric car'];
  const interestTypeOptions = ['Daily', 'Weekly', 'Monthly', 'Yearly'];
  const citizenOptions = ['Yes', 'Yes Permanent Resident', 'No'];
  const defaultStageSequence = ['name', 'phone', 'email', 'zipcode', 'vehicle_type', 'dates', 'interest_type', 'contract_company', 'contract_expiry', 'citizen_status', 'fleet', 'quote', 'review', 'summary'];
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
    dates_prompt: 'Please choose your from and to dates.',
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
    summary_title: 'Summary',
    summary_note: 'Your details will be shared with our team together with the information above.',
    summary_submit_label: 'Submit Request',
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
  const customerFieldDefinitions = Array.isArray(rawFlowConfig.customer_fields)
    ? rawFlowConfig.customer_fields
      .map((field) => {
        if (!field || typeof field !== 'object') return null;
        const key = String(field.key || '').trim();
        if (!key) return null;
        return {
          key,
          stage: String(field.stage || '').trim(),
          label: String(field.label || key).trim(),
        };
      })
      .filter(Boolean)
    : [];
  const serviceTimeSlots = Array.isArray(flowText.time_slots)
    ? flowText.time_slots.map((value) => String(value || '').trim()).filter(Boolean)
    : [];
  const pricingEnabled = isServiceMode
    ? flowText.pricing_enabled === true
    : flowText.pricing_enabled !== false;
  const stageAliases = {
    citizen: 'citizen_status',
    citizen_status: 'citizen_status',
    vehicle: 'vehicle_type',
    vehicle_type: 'vehicle_type',
    interest: 'interest_type',
    interest_type: 'interest_type',
  };
  const configuredStageSequence = Array.isArray(rawFlowConfig.stage_sequence)
    ? rawFlowConfig.stage_sequence
      .map((value) => stageAliases[String(value || '').trim().toLowerCase()] || String(value || '').trim().toLowerCase())
      .filter(Boolean)
    : [];
  const activeStageSequence = configuredStageSequence.length
    ? configuredStageSequence.filter((stage, index, array) => defaultStageSequence.includes(stage) && array.indexOf(stage) === index)
    : defaultStageSequence;
  const locationValidationMode = String(flowText.zipcode_validation || 'zipcode').trim().toLowerCase();

  function fieldLabel(key, fallback) {
    const configuredField = customerFieldDefinitions.find((field) => field.stage === key || field.key === key);
    return templateText(configuredField?.label || flowText[`${key}_label`] || fallback);
  }

  function usesZipcodeValidation() {
    return locationValidationMode !== 'text';
  }

  function normalizeLocationValue(value) {
    return usesZipcodeValidation() ? normalizeZipcode(value) : String(value || '').trim();
  }

  function isValidLocationValue(value) {
    return usesZipcodeValidation() ? isValidZipcode(value) : String(value || '').trim().length >= 2;
  }

  function invalidLocationMessage() {
    return flowText.zipcode_invalid
      || (usesZipcodeValidation()
        ? 'Please enter a valid 6-digit Singapore zipcode.'
        : `Please enter your ${fieldLabel('zipcode', 'location').toLowerCase()}.`);
  }

  function stageEnabled(stage) {
    return activeStageSequence.includes(stage);
  }

  function nextStageAfter(stage) {
    const index = activeStageSequence.indexOf(stage);
    if (index === -1) return '';
    return activeStageSequence[index + 1] || '';
  }

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
    if (key === 'email_reprompt') {
      return `Please enter your ${fieldLabel('email', 'email address').toLowerCase()} next.`;
    }
    if (key === 'zipcode_reprompt') {
      return `Please enter your ${fieldLabel('zipcode', 'zipcode').toLowerCase()}.`;
    }
    if (key === 'vehicle_type_reprompt') {
      return `Please tell me your ${fieldLabel('vehicle_type', 'current vehicle type').toLowerCase()}.`;
    }
    if (key === 'interest_type_prompt') {
      return `What should I note for ${fieldLabel('interest_type', 'interest form type').toLowerCase()}?`;
    }
    if (key === 'contract_company_reprompt') {
      return `Please tell me your ${fieldLabel('contract_company', 'details').toLowerCase()}.`;
    }
    if (key === 'contract_company_complete') {
      return `Please select your ${fieldLabel('contract_expiry', 'current contract expiry').toLowerCase()}.`;
    }
    if (key === 'citizen_prompt') {
      return `Please choose your ${fieldLabel('citizen_status', 'citizen status').toLowerCase()}: ${citizenOptions.join(', ')}.`;
    }
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
      appointment_time: '',
      rental_days: 0,
      interest_form_type: '',
      fleet_results: [],
      car_id: '',
      car_make: '',
      car_model: '',
      car_luxury: '',
      price_per_day: 0,
      price_per_week: 0,
      price_per_month: 0,
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
        appointment_time: state.appointment_time,
        rental_days: state.rental_days,
        interest_form_type: state.interest_form_type,
        car_id: state.car_id,
        car_make: state.car_make,
        car_model: state.car_model,
        car_luxury: state.car_luxury,
        price_per_day: state.price_per_day,
        price_per_week: state.price_per_week,
        price_per_month: state.price_per_month,
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

  async function openStage(stage, { prompt = true } = {}) {
    if (!stage) {
      setStage('complete');
      return;
    }

    if (stage === 'name') {
      setStage('name');
      if (prompt) await assistantSay(flowMessage('name_reprompt'));
      return;
    }
    if (stage === 'phone') {
      setStage('phone');
      if (prompt) await assistantSay(flowMessage('phone_reprompt'));
      return;
    }
    if (stage === 'email') {
      setStage('email');
      if (prompt) await assistantSay(flowMessage('email_reprompt'));
      return;
    }
    if (stage === 'zipcode') {
      setStage('zipcode');
      if (prompt) await assistantSay(flowMessage('zipcode_reprompt'));
      return;
    }
    if (stage === 'vehicle_type') {
      setStage('vehicle_type');
      if (prompt) await assistantSay(flowMessage('vehicle_type_reprompt'));
      return;
    }
    if (stage === 'contract_company') {
      setStage('contract_company');
      if (prompt) await assistantSay(flowMessage('contract_company_reprompt'));
      return;
    }
    if (stage === 'dates') {
      renderDatesPanel();
      setStage('dates');
      if (prompt) await assistantSay(flowMessage('dates_prompt'));
      return;
    }
    if (stage === 'interest_type') {
      if (prompt) await assistantSay(flowMessage('interest_type_prompt'));
      renderInterestTypePanel();
      setStage('interest_type');
      return;
    }
    if (stage === 'contract_expiry') {
      if (prompt) await assistantSay(flowMessage('contract_company_complete'));
      renderContractExpiryPanel();
      setStage('contract_expiry');
      return;
    }
    if (stage === 'citizen_status') {
      if (prompt) await assistantSay(flowMessage('citizen_prompt'));
      renderCitizenPanel();
      setStage('citizen_status');
      return;
    }
    if (stage === 'fleet') {
      if (prompt) await assistantBatch([flowMessage('fleet_intro')]);
      await renderFleetPanel();
      setStage('fleet');
      return;
    }
    if (stage === 'quote') {
      if (prompt) await assistantSay(flowMessage('quote_intro'));
      renderQuotePanel();
      setStage('quote');
      return;
    }
    if (stage === 'review') {
      if (prompt) await assistantSay(flowMessage('review_intro'));
      renderReviewPanel();
      setStage('review');
      return;
    }
    if (stage === 'summary') {
      if (prompt) await assistantSay(isServiceMode ? "Here's your appointment summary." : "Here's your enquiry summary.");
      renderSummaryPanel();
      setStage('summary');
      return;
    }

    setStage(stage);
  }

  function setStage(stage) {
    state.stage = stage;
    const placeholders = {
      consent: 'Waiting for your choice...',
      resume: 'Choose an option below...',
      name: 'Type your full name...',
      phone: 'Type your mobile number...',
      email: 'Type your email address...',
      zipcode: flowText.zipcode_placeholder || `Type your ${fieldLabel('zipcode', 'zipcode').toLowerCase()}...`,
      vehicle_type: flowText.vehicle_type_placeholder || 'Type your current vehicle type...',
      contract_company: flowText.contract_company_placeholder || `Type your ${fieldLabel('contract_company', 'details').toLowerCase()}...`,
      fleet: isServiceMode
        ? 'Ask about a service or choose one below...'
        : 'Ask for a recommendation or choose a car below...',
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
    return /(\?|suggest|recommend|what|which|how|can you|could you|help|price|quote|fleet|car|cars|luxury|budget|book|booking|service|appointment|mechanic|repair)/i.test(String(value || '').trim());
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

  const reviewFieldDefinitions = {
    name: {
      name: 'full_name',
      label: 'Contact Person Name',
      inputType: 'text',
      getValue: () => state.full_name,
    },
    phone: {
      name: 'phone',
      label: 'Contact Person Mobile',
      inputType: 'text',
      getValue: () => state.phone,
    },
    email: {
      name: 'email',
      label: 'Email',
      inputType: 'email',
      getValue: () => state.email,
    },
    zipcode: {
      name: 'zipcode',
      label: fieldLabel('zipcode', 'Zipcode'),
      inputType: 'text',
      getValue: () => state.zipcode,
    },
    vehicle_type: {
      name: 'current_vehicle_type',
      label: fieldLabel('vehicle_type', 'Current Vehicle Type'),
      inputType: 'text',
      getValue: () => state.current_vehicle_type,
    },
    interest_type: {
      name: 'interest_form_type',
      label: fieldLabel('interest_type', 'Interest Form Type'),
      inputType: 'text',
      getValue: () => state.interest_form_type,
    },
    contract_company: {
      name: 'contract_company',
      label: fieldLabel('contract_company', 'Current Contract Company'),
      inputType: 'text',
      getValue: () => state.contract_company,
    },
    contract_expiry: {
      name: 'contract_expiry',
      label: fieldLabel('contract_expiry', 'Current Contract Expiry'),
      inputType: 'date',
      getValue: () => state.contract_expiry,
    },
    citizen_status: {
      name: 'citizen_status',
      label: fieldLabel('citizen_status', 'Singapore Citizens'),
      inputType: 'text',
      getValue: () => state.citizen_status,
    },
  };

  function enabledReviewFields() {
    return activeStageSequence
      .filter((stage) => reviewFieldDefinitions[stage])
      .map((stage) => reviewFieldDefinitions[stage]);
  }

  function selectedItemLabel() {
    return `${state.car_make} ${state.car_model}`.trim() || state.car_make || state.car_model || '--';
  }

  function scheduleLabel() {
    if (isServiceMode) {
      if (!state.start_date) return '--';
      return state.appointment_time
        ? `${formatCardDate(state.start_date)} at ${state.appointment_time}`
        : formatCardDate(state.start_date);
    }
    return `${formatCardDate(state.start_date)} to ${formatCardDate(state.end_date)}`;
  }

  function quoteDetailRows(values) {
    const rows = [
      [isServiceMode ? 'Service' : 'Vehicle', selectedItemLabel()],
      [isServiceMode ? 'Appointment' : 'Schedule', scheduleLabel()],
    ];
    if (stageEnabled('interest_type') && state.interest_form_type) {
      rows.splice(1, 0, [fieldLabel('interest_type', 'Interest Form Type'), state.interest_form_type]);
    }
    if (stageEnabled('vehicle_type') && state.current_vehicle_type) {
      rows.push([fieldLabel('vehicle_type', 'Current Vehicle Type'), state.current_vehicle_type]);
    }
    if (pricingEnabled) {
      rows.push([isServiceMode ? 'Estimated price' : values.rateLabel, formatCurrency(Number(values.unitRate || 0))]);
      if (values.discountAmount > 0) {
        rows.push([values.discountLabel, `- ${formatCurrency(values.discountAmount)}`]);
      }
      rows.push(['Estimated total', formatCurrency(values.total)]);
    }
    return rows;
  }

  function summaryRows(values) {
    const rows = [
      ['Contact Person Name', state.full_name],
      ['Contact Person Mobile', state.phone],
      ['Email', state.email],
    ];
    enabledReviewFields().forEach((field) => {
      if (['full_name', 'phone', 'email'].includes(field.name)) return;
      if (field.name === 'contract_expiry') {
        rows.push([field.label, formatCardDate(state.contract_expiry)]);
        return;
      }
      rows.push([field.label, state[field.name] || '']);
    });
    rows.push([isServiceMode ? 'Selected Service' : 'Selected Vehicle', selectedItemLabel()]);
    rows.push([isServiceMode ? 'Appointment' : 'Schedule', scheduleLabel()]);
    if (pricingEnabled) {
      rows.push([isServiceMode ? 'Estimated service price' : 'Base estimate', formatCurrency(values.baseRental)]);
      if (values.discountAmount > 0) {
        rows.push([values.discountLabel, `- ${formatCurrency(values.discountAmount)}`]);
      }
      rows.push(['Processing fee', formatCurrency(values.processingFee)]);
      rows.push([`GST (${Math.round(gstRate * 100)}%)`, formatCurrency(values.gst)]);
      rows.push(['Estimated total', formatCurrency(values.total)]);
    }
    return rows;
  }

  function estimateValues() {
    const days = isServiceMode ? 1 : bookingDays();
    let unitRate = Number(state.price_per_day || 0);
    let baseRental = Number(state.price_per_day || 0) * days;
    let rateLabel = isServiceMode ? 'Estimated price' : 'Daily rate';
    if (!isServiceMode && state.interest_form_type === 'Weekly' && Number(state.price_per_week || 0) > 0) {
      unitRate = Number(state.price_per_week || 0);
      baseRental = unitRate * Math.max(1, Math.ceil(days / 7));
      rateLabel = 'Weekly rate';
    } else if (!isServiceMode && state.interest_form_type === 'Monthly' && Number(state.price_per_month || 0) > 0) {
      unitRate = Number(state.price_per_month || 0);
      baseRental = unitRate * Math.max(1, Math.ceil(days / 30));
      rateLabel = 'Monthly rate';
    }
    let discountAmount = 0;
    if (pricingEnabled && discountType === 'percentage' && discountValue > 0) {
      discountAmount = Math.round((baseRental * discountValue) / 100);
    } else if (pricingEnabled && discountType === 'fixed' && discountValue > 0) {
      discountAmount = Math.min(baseRental, Math.round(discountValue));
    }
    const appliedProcessingFee = pricingEnabled ? processingFee : 0;
    const subtotal = Math.max(0, baseRental - discountAmount) + appliedProcessingFee;
    const gst = pricingEnabled ? Math.round(subtotal * gstRate) : 0;
    const total = pricingEnabled ? subtotal + gst : baseRental;
    state.total_price = total;
    state.rental_days = days;
    return {
      days,
      unitRate,
      rateLabel,
      baseRental,
      discountAmount,
      discountLabel: discountLabel || (discountType === 'percentage' ? `${discountValue}% discount` : 'Discount'),
      processingFee: appliedProcessingFee,
      subtotal,
      gst,
      total,
    };
  }

  function customerFieldValue(fieldKey) {
    switch (String(fieldKey || '').trim()) {
      case 'email':
        return state.email;
      case 'zipcode':
        return state.zipcode;
      case 'current_vehicle_type':
        return state.current_vehicle_type;
      case 'interest_form_type':
        return state.interest_form_type;
      case 'contract_company':
        return state.contract_company;
      case 'contract_expiry':
        return state.contract_expiry;
      case 'citizen_status':
        return state.citizen_status;
      default:
        return '';
    }
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
    if (isServiceMode) {
      const title = escapeHtml(String(car.name || '').trim() || 'Service');
      const subtitle = escapeHtml(String(car.duration_label || '').trim());
      const priceLabel = escapeHtml(String(car.price_label || '').trim() || formatCurrency(Number(car.price || 0)));
      const description = escapeHtml(String(car.description || '').trim());
      return `
        <article class="assistant-flow-fleet-card ${suggested ? 'is-suggested' : ''}">
          <div class="assistant-flow-fleet-media">
            <img src="${image}" alt="${title}" loading="lazy" onerror="this.onerror=null;this.src='${fallback}';" />
          </div>
          <div class="assistant-flow-fleet-meta">
            ${suggested ? '<span class="assistant-flow-card-tag">Suggested for you</span>' : ''}
            <strong>${title}</strong>
            ${subtitle ? `<span>${subtitle}</span>` : ''}
            ${priceLabel ? `<span>${priceLabel}</span>` : ''}
            ${description ? `<span>${description}</span>` : ''}
          </div>
          <button class="btn-black assistant-flow-select" type="button" data-id="${escapeHtml(car.id)}" data-make="${escapeHtml(car.name || '')}" data-model="${escapeHtml(car.duration_label || '')}" data-price="${Number(car.price || 0)}" data-luxury="">Choose this service</button>
        </article>
      `;
    }
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
        <button class="btn-black assistant-flow-select" type="button" data-id="${escapeHtml(car.id)}" data-make="${escapeHtml(car.make || '')}" data-model="${escapeHtml(car.model || '')}" data-price="${Number(car.price_per_day || 0)}" data-price-week="${Number(car.price_per_week || 0)}" data-price-month="${Number(car.price_per_month || 0)}" data-luxury="${escapeHtml(luxury)}">Select this car</button>
      </article>
    `;
  }

  function looksLikeSuggestionRequest(text) {
    return isServiceMode
      ? /(suggest|recommend|recommendation|best service|which service|appointment|repair|service|mechanic|oil|brake|battery|aircon|inspection)/i.test(String(text || ''))
      : /(suggest|recommend|recommendation|best car|which car|what should|need a car|looking for|budget|affordable|cheap|luxury|family|suv|daily|business|electric|fast|performance)/i.test(String(text || ''));
  }

  function chooseSuggestedCar(promptText, fleet) {
    if (!fleet.length) return null;
    const lower = String(promptText || '').toLowerCase();
    if (isServiceMode) {
      const match = fleet.find((item) => {
        const haystack = `${item.name || ''} ${item.description || ''}`.toLowerCase();
        return haystack && lower.split(/\s+/).some((word) => word.length > 2 && haystack.includes(word));
      });
      if (match) return match;
      return fleet[0];
    }
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
            city: state.zipcode || marketCity,
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
    if (!payload.ok) throw new Error(payload.error || (isServiceMode ? 'Unable to load the services.' : 'Unable to load the fleet.'));
    if (payload.error) throw new Error(payload.error);
    state.fleet_results = Array.isArray(payload.data) ? payload.data : [];
    return state.fleet_results;
  }

  function selectCarFromButton(button) {
    state.car_id = button.dataset.id || '';
    state.car_make = button.dataset.make || '';
    state.car_model = button.dataset.model || '';
    state.price_per_day = Number(button.dataset.price || 0);
    state.price_per_week = Number(button.dataset.priceWeek || 0);
    state.price_per_month = Number(button.dataset.priceMonth || 0);
    state.car_luxury = button.dataset.luxury || '';
    saveDraft();
  }

  function bindCarSelectButtons(scope) {
    scope.querySelectorAll('.assistant-flow-select').forEach((button) => {
      button.addEventListener('click', async () => {
        selectCarFromButton(button);
        completePanel('fleet');
        userSay(
          isServiceMode
            ? `I'd like to book ${selectedItemLabel()}.`
            : `I want the ${state.car_make} ${state.car_model}.`
        );
        await openStage(nextStageAfter('fleet') || 'quote');
      });
    });
  }

  async function renderFleetPanel(suggestedCar = null, suggestionText = '') {
    const body = upsertPanel('fleet', 'assistant-flow-card assistant-flow-card-wide');
    body.innerHTML = `<div class="assistant-flow-loading">${isServiceMode ? 'Loading the available services...' : 'Loading the available fleet...'}</div>`;
    try {
      const fleet = await fetchFleetResults();
      if (!fleet.length) {
        body.innerHTML = `<div class="assistant-flow-empty">${isServiceMode ? 'No services are configured right now.' : 'No vehicles are available for those dates right now.'}</div>`;
        return;
      }
      const suggestionMarkup = suggestedCar ? `
        <div class="assistant-flow-suggestion-block">
          ${suggestionText ? `<p class="assistant-chat-panel-note">${escapeHtml(suggestionText)}</p>` : ''}
          ${fleetCardMarkup(suggestedCar, true)}
        </div>
      ` : '';
      body.innerHTML = `
        <div class="assistant-flow-fleet-status">${isServiceMode
          ? `Showing ${fleet.length} service option${fleet.length === 1 ? '' : 's'} for ${escapeHtml(scheduleLabel())}.`
          : `Showing ${fleet.length} available vehicle${fleet.length === 1 ? '' : 's'} for ${escapeHtml(formatCardDate(state.start_date))} to ${escapeHtml(formatCardDate(state.end_date))}.`}</div>
        ${suggestionMarkup}
        <div class="assistant-flow-fleet-grid">
          ${fleet.map((car) => fleetCardMarkup(car)).join('')}
        </div>
      `;
      bindCarSelectButtons(body);
    } catch (error) {
      body.innerHTML = `<div class="assistant-flow-empty">${escapeHtml(error.message || (isServiceMode ? 'I could not load the services right now.' : 'I could not load the fleet right now.'))}</div>`;
    }
  }

  function renderDatesPanel() {
    const body = upsertPanel('dates');
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const defaultStart = state.start_date || toIsoDate(tomorrow);
    const defaultEnd = state.end_date || toIsoDate(new Date(tomorrow.getFullYear(), tomorrow.getMonth(), tomorrow.getDate() + 1));
    const minimumDate = todayIso();

    if (isServiceMode) {
      const defaultTime = state.appointment_time || serviceTimeSlots[0] || '';
      const timeFieldMarkup = serviceTimeSlots.length
        ? `
          <label class="assistant-flow-date-card assistant-flow-date-card-single">
            <span>${escapeHtml(flowText.time_slot_label || 'PREFERRED TIME')}</span>
            <select id="assistantAppointmentTime" required>
              <option value="">Select a time</option>
              ${serviceTimeSlots.map((slot) => `<option value="${escapeHtml(slot)}"${slot === defaultTime ? ' selected' : ''}>${escapeHtml(slot)}</option>`).join('')}
            </select>
          </label>
        `
        : `
          <label class="assistant-flow-date-card assistant-flow-date-card-single">
            <span>${escapeHtml(flowText.time_slot_label || 'PREFERRED TIME')}</span>
            <input id="assistantAppointmentTime" type="text" value="${escapeHtml(defaultTime)}" placeholder="${escapeHtml(flowText.time_slot_placeholder || '09:30 AM')}" />
          </label>
        `;

      body.innerHTML = `
        <div class="assistant-chat-panel-head">
          <strong>${escapeHtml(flowText.dates_title || 'Preferred appointment')}</strong>
          <p class="assistant-chat-panel-note">${escapeHtml(flowText.dates_note || 'Choose your preferred date and time for the appointment request.')}</p>
        </div>
        <div class="assistant-flow-schedule-card">
          <div class="assistant-flow-schedule-grid">
            <label class="assistant-flow-date-card">
              <span>${escapeHtml(flowText.appointment_date_label || 'APPOINTMENT DATE')}</span>
              <input id="assistantFromDate" type="date" min="${escapeHtml(minimumDate)}" value="${escapeHtml(defaultStart)}" required />
            </label>
            ${timeFieldMarkup}
          </div>
          <div class="assistant-flow-card-actions center">
            <button class="btn-black" id="assistantContinueDates" type="button">Continue</button>
          </div>
        </div>
      `;

      const dateInput = body.querySelector('#assistantFromDate');
      const timeInput = body.querySelector('#assistantAppointmentTime');
      body.querySelector('#assistantContinueDates').addEventListener('click', async () => {
        const appointmentDate = dateInput.value;
        const appointmentTime = String(timeInput?.value || '').trim();
        if (!appointmentDate) {
          await assistantSay(flowText.dates_invalid || 'Please choose your preferred appointment date.');
          return;
        }
        if (appointmentDate < minimumDate) {
          await assistantSay('Your appointment date cannot be in the past.');
          return;
        }
        if (serviceTimeSlots.length && !appointmentTime) {
          await assistantSay(flowText.time_slot_invalid || 'Please choose your preferred appointment time.');
          return;
        }
        state.start_date = appointmentDate;
        state.end_date = appointmentDate;
        state.appointment_time = appointmentTime;
        state.rental_days = 1;
        completePanel('dates');
        saveDraft();
        userSay(`${formatCardDate(appointmentDate)}${appointmentTime ? ` at ${appointmentTime}` : ''}`);
        await openStage(nextStageAfter('dates'), { prompt: true });
      });
      return;
    }

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
      await openStage(nextStageAfter('dates'), { prompt: true });
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
    await openStage(nextStageAfter('interest_type'), { prompt: true });
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
      await openStage(nextStageAfter('contract_expiry'), { prompt: true });
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
    await openStage(nextStageAfter('citizen_status'), { prompt: true });
  }

  function renderQuotePanel() {
    const body = upsertPanel('quote');
    const values = estimateValues();
    const detailRows = quoteDetailRows(values);
    body.innerHTML = `
      <div class="assistant-chat-panel-head">
        <strong>Estimated quote</strong>
        <p class="assistant-chat-panel-note">This estimate is shown in ${escapeHtml(currencyCode)} for the dates and vehicle you selected.</p>
      </div>
      <div class="assistant-flow-quote-card">
        <div class="assistant-flow-detail-grid">
          ${detailRows.map(([label, value]) => `
            <div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>
          `).join('')}
        </div>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantContinueQuote" type="button">Continue</button>
        </div>
      </div>
    `;
    body.querySelector('#assistantContinueQuote').addEventListener('click', async () => {
      completePanel('quote');
      await openStage(nextStageAfter('quote') || 'review', { prompt: true });
    });
  }

  function renderReviewPanel() {
    const body = upsertPanel('review');
    const reviewFieldsMarkup = enabledReviewFields().map((field) => `
      <label class="assistant-flow-field">
        <span>${escapeHtml(field.label)} *</span>
        <input name="${escapeHtml(field.name)}" type="${escapeHtml(field.inputType)}" value="${escapeHtml(field.getValue())}" required />
      </label>
    `).join('');
    const appointmentTimeMarkup = isServiceMode
      ? (serviceTimeSlots.length
        ? `
          <label class="assistant-flow-field">
            <span>${escapeHtml(flowText.time_slot_label || 'Preferred Time')} *</span>
            <select name="appointment_time" required>
              <option value="">Select a time</option>
              ${serviceTimeSlots.map((slot) => `<option value="${escapeHtml(slot)}"${slot === state.appointment_time ? ' selected' : ''}>${escapeHtml(slot)}</option>`).join('')}
            </select>
          </label>
        `
        : `
          <label class="assistant-flow-field">
            <span>${escapeHtml(flowText.time_slot_label || 'Preferred Time')}</span>
            <input name="appointment_time" type="text" value="${escapeHtml(state.appointment_time)}" placeholder="${escapeHtml(flowText.time_slot_placeholder || '09:30 AM')}" />
          </label>
        `)
      : '';
    body.innerHTML = `
      <form class="assistant-flow-form" id="assistantReviewForm">
        <div class="assistant-chat-panel-head">
          <strong>${escapeHtml(isServiceMode ? 'Review your appointment details' : 'Review your details')}</strong>
          <p class="assistant-chat-panel-note">${escapeHtml(isServiceMode ? 'You can edit any detail below before submitting your appointment request.' : 'You can edit any detail below before submitting your interest.')}</p>
        </div>
        <div class="assistant-flow-fields two-col">
          ${reviewFieldsMarkup}
          <label class="assistant-flow-field"><span>${escapeHtml(isServiceMode ? (flowText.appointment_date_label || 'Appointment Date') : 'From Date')} *</span><input name="start_date" type="date" value="${escapeHtml(state.start_date)}" required /></label>
          ${isServiceMode
            ? `<input name="end_date" type="hidden" value="${escapeHtml(state.end_date || state.start_date)}" />${appointmentTimeMarkup}`
            : '<label class="assistant-flow-field"><span>To Date *</span><input name="end_date" type="date" value="' + escapeHtml(state.end_date) + '" required /></label>'}
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
      const zipcode = normalizeLocationValue(formData.get('zipcode'));
      const vehicleType = String(formData.get('current_vehicle_type') || '').trim();
      const interestType = normalizeInterestType(formData.get('interest_form_type'));
      const contractCompany = String(formData.get('contract_company') || '').trim();
      const contractExpiry = String(formData.get('contract_expiry') || '').trim();
      const citizenStatus = normalizeCitizenStatus(formData.get('citizen_status'));
      const startDate = String(formData.get('start_date') || '').trim();
      const endDate = String(formData.get('end_date') || startDate).trim();
      const appointmentTime = String(formData.get('appointment_time') || '').trim();
      const minimumDate = todayIso();

      if (!name.full_name) return void await assistantSay('Please enter your full name.');
      if (!isValidPhone(phone)) return void await assistantSay('Please enter a valid mobile number.');
      if (!isValidEmail(email)) return void await assistantSay('Please enter a valid email address.');
      if (stageEnabled('zipcode') && !isValidLocationValue(zipcode)) return void await assistantSay(invalidLocationMessage());
      if (stageEnabled('vehicle_type') && !vehicleType) return void await assistantSay(flowMessage('vehicle_type_reprompt'));
      if (stageEnabled('interest_type') && !interestType) return void await assistantSay(`Please use one of these options: ${interestTypeOptions.join(', ')}.`);
      if (stageEnabled('contract_company') && !contractCompany) return void await assistantSay(flowMessage('contract_company_reprompt'));
      if (stageEnabled('contract_expiry') && (!contractExpiry || contractExpiry < minimumDate)) return void await assistantSay('Please choose a valid current contract expiry date.');
      if (stageEnabled('citizen_status') && !citizenStatus) return void await assistantSay(flowMessage('citizen_prompt'));
      if (!startDate || !endDate) return void await assistantSay(isServiceMode ? (flowText.dates_invalid || 'Please choose your preferred appointment date.') : 'Please choose your from and to dates.');
      if (startDate < minimumDate) return void await assistantSay('Your from date cannot be in the past.');
      if (!isServiceMode && endDate < startDate) return void await assistantSay('Your to date cannot be before your from date.');
      if (isServiceMode && serviceTimeSlots.length && !appointmentTime) return void await assistantSay(flowText.time_slot_invalid || 'Please choose your preferred appointment time.');

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
        return void await assistantSay(availabilityPayload.error || (isServiceMode ? 'I could not re-check the services right now.' : 'I could not re-check the fleet right now.'));
      }
      if (!stillAvailable) {
        clearPanelsFrom('fleet');
        state.start_date = startDate;
        state.end_date = isServiceMode ? startDate : endDate;
        state.appointment_time = appointmentTime;
        state.rental_days = isServiceMode ? 1 : bookingDays();
        saveDraft();
        await assistantBatch([
          isServiceMode
            ? 'That service is no longer available. Please choose another option.'
            : 'The car you selected is no longer available for those dates. Please choose another vehicle.'
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
      state.end_date = isServiceMode ? startDate : endDate;
      state.appointment_time = appointmentTime;
      state.rental_days = isServiceMode ? 1 : bookingDays();
      completePanel('review');
      saveDraft();
      userSay(isServiceMode ? 'These appointment details look good.' : 'These details look good.');
      await openStage(nextStageAfter('review') || 'summary', { prompt: true });
    });
  }

  function renderSummaryPanel() {
    const body = upsertPanel('summary');
    const values = estimateValues();
    const detailRows = summaryRows(values);
    const dividerIndex = pricingEnabled ? detailRows.length - 4 : -1;
    body.innerHTML = `
      <div class="assistant-flow-summary">
        ${detailRows.map(([label, value], index) => `
          ${index === dividerIndex ? '<div class="assistant-flow-summary-divider"></div>' : ''}
          <div class="assistant-flow-summary-row ${label === 'Estimated total' ? 'total' : ''}">
            ${label === 'Estimated total' ? '<strong>Estimated total</strong>' : `<span>${escapeHtml(label)}</span>`}
            <span>${escapeHtml(value)}</span>
          </div>
        `).join('')}
        <p class="assistant-chat-summary-note">${escapeHtml(
          flowText.summary_note
            || (pricingEnabled
              ? `Your enquiry estimate is shown in ${currencyCode} and will be shared with our team together with the details above.`
              : 'Your appointment request will be shared with our team together with the details above.')
        )}</p>
        <div class="assistant-flow-card-actions center">
          <button class="btn-black" id="assistantSubmitInterest" type="button">${escapeHtml(flowText.summary_submit_label || 'Submit Interest')}</button>
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
      status.textContent = isServiceMode ? 'Submitting appointment request...' : 'Submitting enquiry...';
      status.className = 'summary-status pending';

      const serviceDetails = [state.appointment_time, state.current_vehicle_type, state.contract_company]
        .map((value) => String(value || '').trim())
        .filter(Boolean)
        .join(' | ');
      const customFields = {};
      const customFieldLabels = {};
      customerFieldDefinitions.forEach((field) => {
        const value = String(customerFieldValue(field.key) || '').trim();
        if (!value) return;
        customFields[field.key] = value;
        customFieldLabels[field.key] = field.label;
      });

      const payload = {
        biz_id: activeBizId(),
        customer_name: state.full_name,
        phone: state.phone,
        city: marketCity || state.zipcode || companyName,
        start_date: state.start_date,
        end_date: isServiceMode ? state.start_date : state.end_date,
        car_id: state.car_id,
        total_price: state.total_price,
        location: state.zipcode || marketCity || companyName,
        insurance: isServiceMode ? (serviceDetails || 'Appointment request') : 'No insurance',
        email: state.email,
        current_vehicle_type: state.current_vehicle_type,
        contract_company: state.contract_company,
        contract_expiry: state.contract_expiry,
        citizen_status: state.citizen_status,
        interest_form_type: state.interest_form_type,
        custom_fields: customFields,
        custom_field_labels: customFieldLabels,
        service_id: isServiceMode ? state.car_id : '',
        service_name: isServiceMode ? selectedItemLabel() : '',
        appointment_date: isServiceMode ? state.start_date : '',
        appointment_time: isServiceMode ? state.appointment_time : '',
        booking_type: isServiceMode ? 'mechanic_appointment' : 'rental_enquiry',
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
        status.textContent = isServiceMode ? 'Appointment request submitted.' : 'Interest form submitted.';
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
    await openStage(nextStageAfter('name'), { prompt: false });
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
    await openStage(nextStageAfter('phone'), { prompt: false });
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
    await openStage(nextStageAfter('email'), { prompt: false });
  }

  async function handleZipcodeInput(text) {
    if (await maybeAnswerQuestion(text, flowMessage('zipcode_reprompt'))) return;
    if (!isValidLocationValue(text)) {
      await assistantSay(invalidLocationMessage());
      return;
    }
    state.zipcode = normalizeLocationValue(text);
    saveDraft();
    await assistantSay(flowMessage('zipcode_complete'));
    await openStage(nextStageAfter('zipcode'), { prompt: false });
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
    await openStage(nextStageAfter('vehicle_type'), { prompt: false });
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
    await openStage(nextStageAfter('contract_company'), { prompt: false });
  }

  async function handleFleetInput(text) {
    if (!state.fleet_results.length) await renderFleetPanel();
    if (/^(show all|browse|view fleet|view services|normal fleet|skip suggestion|no thanks|no suggestion)/i.test(text.trim())) {
      await assistantSay(isServiceMode
        ? 'Absolutely. Browse the available services below and choose the one you want.'
        : 'Absolutely. Browse the available fleet below and choose the one you like.');
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
      await assistantSay(aiReply || (isServiceMode ? 'I do not have a clear match yet, so please browse the available services below.' : 'I do not have a clear match yet, so please browse the available fleet below.'));
      return;
    }
    await assistantBatch([
      aiReply || (isServiceMode
        ? `Based on what you need, I'd recommend ${suggestion.name}.`
        : `Based on what you need, I'd recommend the ${suggestion.make} ${suggestion.model}.`)
    ]);
    await renderFleetPanel(suggestion, 'This is my best match based on your request.');
  }

  async function handleGeneralQuestion(text) {
    const reply = await fetchAiReply(text);
    if (reply) {
      await assistantSay(reply);
      return;
    }
    await assistantSay(isServiceMode
      ? 'I can help with your appointment request here. If a card is open, complete that step or ask me for a service suggestion.'
      : 'I can help with your enquiry here. If a card is open, complete that step or ask me for a vehicle suggestion.');
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
        <p>With your permission, ${escapeHtml(companyName)} can use cookies and device storage to remember your ${escapeHtml(isServiceMode ? 'appointment request' : 'enquiry')} so you can resume later on this same browser.</p>
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
        <strong>Resume your saved ${escapeHtml(isServiceMode ? 'request' : 'enquiry')}?</strong>
        <p>I found a saved ${escapeHtml(companyName)} ${escapeHtml(isServiceMode ? 'appointment request' : 'enquiry')} on this device${savedTime ? ` from ${escapeHtml(savedTime)}` : ''}. Would you like to continue where you left off?</p>
        <div class="assistant-flow-card-actions center">
          <button class="btn-outline" id="assistantResumeFresh" type="button">Start fresh</button>
          <button class="btn-black" id="assistantResumeContinue" type="button">${escapeHtml(isServiceMode ? 'Resume request' : 'Resume enquiry')}</button>
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
    if ((activeStageSequence[0] || 'name') === 'name') {
      setStage('name');
      await assistantBatch(flowMessages('intro_messages'));
      return;
    }
    await openStage(activeStageSequence[0], { prompt: true });
  }

  async function resumeFromDraft(savedState) {
    Object.assign(state, buildInitialState(), savedState || {});
    thread.innerHTML = '';
    Object.keys(panels).forEach((key) => delete panels[key]);
    composerInput.value = '';
    setComposerEnabled(true);
    await assistantBatch([
      `Welcome back${state.first_name ? `, ${state.first_name}` : ''}. I’ve restored your ${isServiceMode ? 'appointment request' : 'enquiry'} on this device.`
    ]);

    if (stageEnabled('name') && !state.full_name) {
      await openStage('name', { prompt: true });
      return;
    }
    if (stageEnabled('phone') && !state.phone) {
      await openStage('phone', { prompt: true });
      return;
    }
    if (stageEnabled('email') && !state.email) {
      await openStage('email', { prompt: true });
      return;
    }
    if (stageEnabled('zipcode') && !state.zipcode) {
      await openStage('zipcode', { prompt: true });
      return;
    }
    if (stageEnabled('vehicle_type') && !state.current_vehicle_type) {
      await openStage('vehicle_type', { prompt: true });
      return;
    }
    if (stageEnabled('dates') && (!state.start_date || !state.end_date || (isServiceMode && serviceTimeSlots.length && !state.appointment_time))) {
      await openStage('dates', { prompt: true });
      return;
    }
    if (stageEnabled('dates')) completePanel('dates');
    if (stageEnabled('interest_type') && !state.interest_form_type) {
      await openStage('interest_type', { prompt: true });
      return;
    }
    if (stageEnabled('interest_type')) completePanel('interest_type');
    if (stageEnabled('contract_company') && !state.contract_company) {
      await openStage('contract_company', { prompt: true });
      return;
    }
    if (stageEnabled('contract_expiry') && !state.contract_expiry) {
      await openStage('contract_expiry', { prompt: true });
      return;
    }
    if (stageEnabled('contract_expiry')) completePanel('contract_expiry');
    if (stageEnabled('citizen_status') && !state.citizen_status) {
      await openStage('citizen_status', { prompt: true });
      return;
    }
    if (stageEnabled('citizen_status')) completePanel('citizen');
    if (!state.car_id) {
      await assistantSay(isServiceMode ? 'Let’s continue with the available services.' : 'Let’s continue with the available fleet.');
      await openStage('fleet', { prompt: false });
      return;
    }
    await renderFleetPanel();
    completePanel('fleet');
    if (stageEnabled('quote')) renderQuotePanel();
    if (state.stage === 'quote') {
      setStage('quote');
      return;
    }
    if (stageEnabled('quote')) completePanel('quote');
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
        `Hello! I found a saved ${companyName} ${isServiceMode ? 'appointment request' : 'enquiry'} on this device.`
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
        `Hello! I'm ${assistantName}, your ${companyName} ${isServiceMode ? 'appointment' : 'enquiry'} assistant.`,
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
