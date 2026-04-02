(function () {
  const config = window.dashboardConfig || {};
  const searchInput = document.getElementById('searchInput');
  const startDateFilter = document.getElementById('startDateFilter');
  const endDateFilter = document.getElementById('endDateFilter');
  const carModelFilter = document.getElementById('carModelFilter');
  const confirmationFilter = document.getElementById('confirmationFilter');
  const paymentFilter = document.getElementById('paymentFilter');
  const resetFiltersButton = document.getElementById('resetFilters');
  const rowsContainer = document.getElementById('rowsContainer');
  const emptyState = document.getElementById('emptyState');
  const feedbackBanner = document.getElementById('feedbackBanner');
  const resultsCount = document.getElementById('resultsCount');
  const statTotal = document.getElementById('statTotal');
  const statConfirmed = document.getElementById('statConfirmed');
  const statPending = document.getElementById('statPending');
  const statPaid = document.getElementById('statPaid');

  const state = {
    items: [],
    models: [],
    timer: null,
  };

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }

  function formatDate(value) {
    if (!value) return 'Not set';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  function phoneMarkup(value) {
    const safe = String(value || '').trim();
    if (!safe) return '<span class="row-subtle">No phone number</span>';
    return `<a class="phone-link" href="tel:${escapeHtml(safe)}">${escapeHtml(safe)}</a>`;
  }

  function paymentClass(status) {
    const lowered = String(status || '').toLowerCase();
    if (lowered === 'paid') return 'paid';
    if (lowered === 'failed') return 'failed';
    if (lowered === 'partially paid') return 'partial';
    return 'pending';
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

  function rowMarkup(item, index) {
    const checked = item.is_confirmed ? 'checked' : '';
    return `
      <article class="data-row" data-booking-id="${escapeHtml(item.id)}">
        <div class="row-index">${index}</div>

        <div class="row-main">
          <strong>${escapeHtml(item.customer_name)}</strong>
          <span>${escapeHtml(item.city || item.location || 'Customer booking')}</span>
        </div>

        <div class="row-main">
          ${phoneMarkup(item.phone)}
          <span>${escapeHtml(item.location || 'Location pending')}</span>
        </div>

        <div class="date-cell">
          <strong>${escapeHtml(formatDate(item.from_date))}</strong>
          <span>Pickup date</span>
        </div>

        <div class="date-cell">
          <strong>${escapeHtml(formatDate(item.to_date))}</strong>
          <span>Return date</span>
        </div>

        <div class="row-main">
          <strong>${escapeHtml(item.car_model)}</strong>
          <span>${escapeHtml(item.insurance || 'No insurance')}</span>
        </div>

        <div class="length-cell">
          <strong>${escapeHtml(item.booking_length_label)}</strong>
          <span>Total AED ${escapeHtml(item.total_price)}</span>
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

  function setFeedback(message, tone) {
    if (!message) {
      feedbackBanner.className = 'feedback-banner hidden';
      feedbackBanner.textContent = '';
      return;
    }
    feedbackBanner.className = `feedback-banner ${tone}`;
    feedbackBanner.textContent = message;
  }

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
    rowsContainer.innerHTML = items.map((item, index) => rowMarkup(item, index + 1)).join('');
    bindRowEvents();
  }

  function updateModelOptions(models) {
    const current = carModelFilter.value;
    carModelFilter.innerHTML = '<option value="">All models</option>';
    models.forEach((model) => {
      const option = document.createElement('option');
      option.value = model;
      option.textContent = model;
      if (model === current) {
        option.selected = true;
      }
      carModelFilter.appendChild(option);
    });
  }

  function buildQuery() {
    const params = new URLSearchParams();
    if (searchInput.value.trim()) params.set('q', searchInput.value.trim());
    if (startDateFilter.value) params.set('start_date', startDateFilter.value);
    if (endDateFilter.value) params.set('end_date', endDateFilter.value);
    if (carModelFilter.value) params.set('car_model', carModelFilter.value);
    if (confirmationFilter.value) params.set('confirmation', confirmationFilter.value);
    if (paymentFilter.value) params.set('payment', paymentFilter.value);
    return params.toString();
  }

  async function loadBookings() {
    rowsContainer.innerHTML = '<article class="data-row loading"><div class="row-main"><strong>Loading bookings...</strong><span>Please wait while the dashboard refreshes.</span></div></article>';
    emptyState.classList.add('hidden');
    setFeedback('', '');

    try {
      const response = await fetch(`${config.bookingsUrl}?${buildQuery()}`, {
        headers: { Accept: 'application/json' },
      });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.error || 'Unable to load dashboard bookings.');
      }
      state.items = data.items || [];
      state.models = data.models || [];
      updateModelOptions(state.models);
      renderStats(data.summary || {});
      renderRows(state.items);
      if (data.warning) {
        setFeedback(data.warning, 'warning');
      }
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

  function applyLocalStateUpdate(bookingId, patch) {
    state.items = state.items.map((item) => {
      if (String(item.id) !== String(bookingId)) {
        return item;
      }
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
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(data.error || 'Unable to save booking state.');
    }
    return data.state || {};
  }

  function bindRowEvents() {
    rowsContainer.querySelectorAll('.data-row').forEach((row) => {
      const bookingId = row.dataset.bookingId;
      const confirmToggle = row.querySelector('.confirm-toggle');
      const paymentSelect = row.querySelector('.payment-select');
      const confirmText = row.querySelector('.confirm-text');

      confirmToggle.addEventListener('change', async () => {
        row.classList.add('loading');
        const previousValue = !confirmToggle.checked;
        try {
          const stateUpdate = await saveState(bookingId, { is_confirmed: confirmToggle.checked });
          const nextConfirmed = Boolean(stateUpdate.is_confirmed);
          confirmToggle.checked = nextConfirmed;
          confirmText.textContent = nextConfirmed ? 'Confirmed' : 'Pending';
          applyLocalStateUpdate(bookingId, { is_confirmed: nextConfirmed });
          setFeedback('Booking confirmation updated.', 'success');
        } catch (error) {
          confirmToggle.checked = previousValue;
          setFeedback(error.message || 'Unable to update confirmation.', 'error');
        } finally {
          row.classList.remove('loading');
        }
      });

      paymentSelect.addEventListener('change', async () => {
        row.classList.add('loading');
        const previousValue = paymentSelect.dataset.previous || paymentSelect.value;
        try {
          const stateUpdate = await saveState(bookingId, { payment_status: paymentSelect.value });
          const nextStatus = stateUpdate.payment_status || paymentSelect.value;
          paymentSelect.value = nextStatus;
          paymentSelect.dataset.previous = nextStatus;
          applyLocalStateUpdate(bookingId, { payment_status: nextStatus });
          setFeedback('Payment status updated.', 'success');
        } catch (error) {
          paymentSelect.value = previousValue;
          setFeedback(error.message || 'Unable to update payment status.', 'error');
        } finally {
          row.classList.remove('loading');
        }
      });

      paymentSelect.dataset.previous = paymentSelect.value;
    });
  }

  resetFiltersButton.addEventListener('click', () => {
    searchInput.value = '';
    startDateFilter.value = '';
    endDateFilter.value = '';
    carModelFilter.value = '';
    confirmationFilter.value = 'all';
    paymentFilter.value = 'all';
    loadBookings();
  });

  searchInput.addEventListener('input', debounceLoad);
  startDateFilter.addEventListener('change', loadBookings);
  endDateFilter.addEventListener('change', loadBookings);
  carModelFilter.addEventListener('change', loadBookings);
  confirmationFilter.addEventListener('change', loadBookings);
  paymentFilter.addEventListener('change', loadBookings);

  loadBookings();
})();
