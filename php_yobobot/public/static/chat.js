(() => {
  const panel = document.getElementById('yoboPanel');
  const toggle = document.getElementById('yoboToggle');
  const floatingForm = document.getElementById('yoboForm');
  const floatingInput = document.getElementById('yoboInput');
  const floatingMessages = document.getElementById('yoboMessages');

  const pageForm = document.getElementById('pageAssistantForm');
  const pageInput = document.getElementById('pageAssistantInput');
  const pageMessages = document.getElementById('pageAssistantMessages');

  const MAX_HISTORY = 8;
  const STORAGE_KEY = 'yobo_history';

  function loadHistory() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    } catch {
      return [];
    }
  }

  function saveHistory(history) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(-MAX_HISTORY)));
  }

  function getContext() {
    const params = new URLSearchParams(window.location.search);
    return {
      biz_id: params.get('biz_id') || localStorage.getItem('biz_id') || '',
      start_date: params.get('start_date') || localStorage.getItem('start_date') || '',
      end_date: params.get('end_date') || localStorage.getItem('end_date') || '',
      city: params.get('city') || localStorage.getItem('city') || '',
    };
  }

  function allContainers() {
    return [floatingMessages, pageMessages].filter(Boolean);
  }

  function scrollToBottom(container) {
    container.scrollTop = container.scrollHeight;
  }

  function appendMessage(container, text, role, action) {
    const msg = document.createElement('div');
    msg.className = `yobo-msg ${role}`;
    msg.textContent = text;
    container.appendChild(msg);

    if (action && action.href && action.label) {
      const link = document.createElement('a');
      link.className = 'yobo-action';
      link.href = action.href;
      link.textContent = action.label;
      container.appendChild(link);
    }

    scrollToBottom(container);
  }

  function addMessage(text, role, action = null) {
    allContainers().forEach((container) => appendMessage(container, text, role, action));
  }

  function clearMessages() {
    allContainers().forEach((container) => {
      container.innerHTML = '';
    });
  }

  function renderHistory() {
    clearMessages();
    const history = loadHistory();
    history.forEach((entry) => addMessage(entry.content, entry.role, entry.action || null));
  }

  function ensurePanelOpen() {
    if (!panel) return;
    if (!panel.classList.contains('open')) {
      panel.classList.add('open');
      panel.setAttribute('aria-hidden', 'false');
    }
  }

  function ensureGreeting() {
    const history = loadHistory();
    if (history.length) {
      return;
    }
    const greeting = {
      role: 'assistant',
      content: "Hi! I'm Yobo. Tell me your dates, budget, or the kind of car you want.",
    };
    saveHistory([greeting]);
    renderHistory();
  }

  function resetChatUI() {
    localStorage.removeItem(STORAGE_KEY);
    if (panel) {
      panel.classList.remove('open');
      panel.setAttribute('aria-hidden', 'true');
    }
    if (floatingInput) {
      floatingInput.value = '';
    }
    if (pageInput) {
      pageInput.value = '';
    }
    clearMessages();
  }

  function setTyping(visible) {
    allContainers().forEach((container) => {
      const existing = container.querySelector('.yobo-typing');
      if (visible && !existing) {
        const msg = document.createElement('div');
        msg.className = 'yobo-msg assistant yobo-typing';
        msg.textContent = 'Yobo is typing...';
        container.appendChild(msg);
        scrollToBottom(container);
      }
      if (!visible && existing) {
        existing.remove();
      }
    });
  }

  async function sendMessage(text) {
    const trimmed = text.trim();
    if (!trimmed) return;

    ensureGreeting();
    ensurePanelOpen();

    const history = loadHistory();
    const userEntry = { role: 'user', content: trimmed };
    history.push(userEntry);
    saveHistory(history);
    addMessage(trimmed, 'user');
    setTyping(true);

    try {
      const res = await fetch('/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: trimmed,
          history,
          biz_id: getContext().biz_id,
          context: getContext(),
        }),
      });
      const data = await res.json();
      setTyping(false);
      const reply = data.reply || data.error || 'Sorry, I could not respond.';
      const assistantEntry = { role: 'assistant', content: reply, action: data.action || null };
      history.push(assistantEntry);
      saveHistory(history);
      addMessage(reply, 'assistant', data.action || null);

      if (data.context) {
        if (data.context.start_date) localStorage.setItem('start_date', data.context.start_date);
        if (data.context.end_date) localStorage.setItem('end_date', data.context.end_date);
      }
    } catch {
      setTyping(false);
      const reply = 'Sorry, I ran into an error.';
      history.push({ role: 'assistant', content: reply });
      saveHistory(history);
      addMessage(reply, 'assistant');
    }
  }

  if (toggle) {
    toggle.addEventListener('click', () => {
      const isOpen = panel.classList.toggle('open');
      panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
      if (isOpen) {
        renderHistory();
        ensureGreeting();
      }
    });
  }

  if (floatingForm && floatingInput) {
    floatingForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const text = floatingInput.value;
      floatingInput.value = '';
      await sendMessage(text);
    });
  }

  if (pageForm && pageInput) {
    pageForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const text = pageInput.value;
      pageInput.value = '';
      await sendMessage(text);
    });
  }

  resetChatUI();
})();
