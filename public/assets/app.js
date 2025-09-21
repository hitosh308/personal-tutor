document.addEventListener('DOMContentLoaded', () => {
  const chatSection = document.getElementById('chat-section');
  if (!chatSection) {
    return;
  }

  const subjectId = chatSection.dataset.subject;
  const unitId = chatSection.dataset.unit;
  const form = document.getElementById('tutor-form');
  const textarea = document.getElementById('question');
  const historyContainer = document.getElementById('chat-history');
  const submitButton = form ? form.querySelector('button[type="submit"]') : null;
  const toggleButton = document.getElementById('chat-toggle-button');
  const closeButton = document.getElementById('chat-close-button');
  const overlay = document.getElementById('chat-overlay');

  if (!subjectId || !unitId || !form || !textarea || !historyContainer || !submitButton) {
    return;
  }

  const conversation = [];

  setupSidebarToggle();

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const question = textarea.value.trim();

    if (!question) {
      textarea.focus();
      textarea.setAttribute('aria-invalid', 'true');
      return;
    }

    textarea.removeAttribute('aria-invalid');
    appendBubble('user', question);
    textarea.value = '';
    textarea.focus();

    const payload = {
      subject: subjectId,
      unit: unitId,
      question,
      history: conversation.slice(),
    };

    setFormDisabled(true);
    const pendingBubble = appendBubble('system', '家庭教師が考えています…');

    try {
      const response = await fetch('chat.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json();

      if (!response.ok || data.error) {
        const message = data.error || 'エラーが発生しました。時間をおいて再度お試しください。';
        const detail = typeof data.details === 'string' ? data.details.trim() : '';
        const composedMessage = detail ? `${message}\n${detail}` : message;
        pendingBubble.className = 'chat-bubble system';
        pendingBubble.textContent = composedMessage;
        return;
      }

      pendingBubble.className = 'chat-bubble assistant';
      pendingBubble.textContent = data.answer || '回答を取得できませんでした。';

      conversation.push({ role: 'user', content: question });
      if (data.answer) {
        conversation.push({ role: 'assistant', content: data.answer });
      }
    } catch (error) {
      pendingBubble.className = 'chat-bubble system';
      pendingBubble.textContent = '通信に失敗しました。ネットワーク環境を確認してください。';
    } finally {
      setFormDisabled(false);
      scrollToBottom(historyContainer);
    }
  });

  textarea.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  function setupSidebarToggle() {
    if (!toggleButton || !closeButton || !overlay) {
      return;
    }

    const closeSidebar = (returnFocus = true) => {
      chatSection.classList.remove('is-open');
      overlay.classList.remove('is-active');
      overlay.setAttribute('hidden', 'true');
      document.body.classList.remove('chat-sidebar-open');
      chatSection.setAttribute('aria-hidden', 'true');
      chatSection.setAttribute('inert', '');
      toggleButton.setAttribute('aria-expanded', 'false');

      if (returnFocus) {
        toggleButton.focus();
      }
    };

    const openSidebar = () => {
      chatSection.classList.add('is-open');
      overlay.classList.add('is-active');
      overlay.removeAttribute('hidden');
      document.body.classList.add('chat-sidebar-open');
      chatSection.removeAttribute('aria-hidden');
      chatSection.removeAttribute('inert');
      toggleButton.setAttribute('aria-expanded', 'true');
      closeButton.focus();
      scrollToBottom(historyContainer);
    };

    const toggleSidebar = () => {
      if (chatSection.classList.contains('is-open')) {
        closeSidebar(true);
      } else {
        openSidebar();
      }
    };

    toggleButton.addEventListener('click', toggleSidebar);
    closeButton.addEventListener('click', () => closeSidebar(true));
    overlay.addEventListener('click', () => closeSidebar(true));

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && chatSection.classList.contains('is-open')) {
        event.preventDefault();
        closeSidebar(true);
      }
    });

    closeSidebar(false);
  }

  function appendBubble(role, text) {
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${role}`;
    bubble.textContent = text;
    historyContainer.appendChild(bubble);
    scrollToBottom(historyContainer);
    return bubble;
  }

  function scrollToBottom(element) {
    element.scrollTop = element.scrollHeight;
  }

  function setFormDisabled(disabled) {
    textarea.disabled = disabled;
    submitButton.disabled = disabled;
  }
});
