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
  const expandButton = document.getElementById('chat-expand-button');
  const expandLabel = expandButton ? expandButton.querySelector('.chat-expand-label') : null;
  const expandIcon = expandButton ? expandButton.querySelector('.chat-expand-icon') : null;
  const overlay = document.getElementById('chat-overlay');
  const statusElement = document.getElementById('chat-status');

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

    setStatusMessage('');
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

      let data;
      try {
        data = await response.json();
      } catch (parseError) {
        removeBubble(pendingBubble);
        setStatusMessage('回答の解析に失敗しました。時間をおいて再度お試しください。', 'error');
        console.error('Failed to parse chat response', parseError);
        return;
      }

      if (!response.ok || data.error) {
        removeBubble(pendingBubble);
        const message = typeof data.error === 'string' && data.error.trim() !== ''
          ? data.error.trim()
          : 'エラーが発生しました。時間をおいて再度お試しください。';
        setStatusMessage(message, 'error');
        if (typeof data.details === 'string' && data.details.trim() !== '') {
          console.error('[PersonalTutor][chat] OpenAI error details:', data.details);
        }
        return;
      }

      const answer = typeof data.answer === 'string' ? data.answer : '';
      if (answer.trim() === '') {
        removeBubble(pendingBubble);
        setStatusMessage('回答を取得できませんでした。時間をおいて再度お試しください。', 'error');
        return;
      }

      pendingBubble.className = 'chat-bubble assistant';
      pendingBubble.textContent = answer;
      setStatusMessage('');

      conversation.push({ role: 'user', content: question });
      conversation.push({ role: 'assistant', content: answer });
    } catch (error) {
      removeBubble(pendingBubble);
      setStatusMessage('通信に失敗しました。ネットワーク環境を確認してください。', 'error');
      console.error('[PersonalTutor][chat] request failed:', error);
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

    const layoutQuery = window.matchMedia('(min-width: 1024px)');
    let isDesktopLayout = layoutQuery.matches;
    let isExpanded = false;

    const updateExpandUi = () => {
      const expanded = isExpanded && isDesktopLayout;

      if (expandButton) {
        expandButton.setAttribute('aria-pressed', expanded ? 'true' : 'false');
        expandButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (expandLabel) {
          expandLabel.textContent = expanded ? '元の表示に戻す' : '画面いっぱいに表示';
        }
        if (expandIcon) {
          expandIcon.textContent = expanded ? '⤺' : '⤢';
        }
      }

      if (expanded) {
        chatSection.classList.add('is-expanded');
        document.body.classList.add('chat-expanded');
        overlay.classList.add('is-active');
        overlay.removeAttribute('hidden');
        overlay.dataset.chatMode = 'expanded';
        scrollToBottom(historyContainer);
      } else {
        chatSection.classList.remove('is-expanded');
        document.body.classList.remove('chat-expanded');
        if (overlay.dataset.chatMode === 'expanded') {
          overlay.classList.remove('is-active');
          overlay.setAttribute('hidden', 'true');
          delete overlay.dataset.chatMode;
        }
      }
    };

    const openExpanded = () => {
      if (!isDesktopLayout || isExpanded) {
        return;
      }

      isExpanded = true;
      updateExpandUi();
    };

    const closeExpanded = (returnFocus = false) => {
      if (!isExpanded) {
        return;
      }

      isExpanded = false;
      updateExpandUi();

      if (returnFocus && expandButton) {
        expandButton.focus();
      }
    };

    const closeSidebar = (returnFocus = true) => {
      if (isDesktopLayout) {
        return;
      }

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
      if (isDesktopLayout) {
        return;
      }

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
      if (isDesktopLayout) {
        return;
      }

      if (chatSection.classList.contains('is-open')) {
        closeSidebar(true);
      } else {
        openSidebar();
      }
    };

    const applyDesktopLayout = () => {
      isDesktopLayout = true;
      document.body.classList.add('chat-desktop');
      chatSection.classList.add('is-open');
      chatSection.removeAttribute('aria-hidden');
      chatSection.removeAttribute('inert');
      overlay.classList.remove('is-active');
      overlay.setAttribute('hidden', 'true');
      delete overlay.dataset.chatMode;
      document.body.classList.remove('chat-sidebar-open');
      toggleButton.setAttribute('aria-expanded', 'true');
      toggleButton.setAttribute('hidden', 'true');
      updateExpandUi();
    };

    const applyMobileLayout = () => {
      if (isExpanded) {
        closeExpanded(false);
      }
      isDesktopLayout = false;
      document.body.classList.remove('chat-desktop');
      toggleButton.removeAttribute('hidden');
      closeSidebar(false);
      updateExpandUi();
    };

    const handleLayoutChange = (event) => {
      if (event.matches) {
        applyDesktopLayout();
      } else {
        applyMobileLayout();
      }
    };

    if (typeof layoutQuery.addEventListener === 'function') {
      layoutQuery.addEventListener('change', handleLayoutChange);
    } else if (typeof layoutQuery.addListener === 'function') {
      layoutQuery.addListener(handleLayoutChange);
    }

    toggleButton.addEventListener('click', toggleSidebar);
    if (expandButton) {
      expandButton.addEventListener('click', (event) => {
        event.preventDefault();
        if (!isDesktopLayout) {
          return;
        }

        if (isExpanded) {
          closeExpanded(false);
        } else {
          openExpanded();
        }
      });
    }
    closeButton.addEventListener('click', () => {
      if (!isDesktopLayout) {
        closeSidebar(true);
      } else if (isExpanded) {
        closeExpanded(true);
      }
    });
    overlay.addEventListener('click', () => {
      if (!isDesktopLayout) {
        closeSidebar(true);
      } else if (isExpanded) {
        closeExpanded(true);
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        if (chatSection.classList.contains('is-open') && !isDesktopLayout) {
          event.preventDefault();
          closeSidebar(true);
        } else if (isDesktopLayout && isExpanded) {
          event.preventDefault();
          closeExpanded(true);
        }
      }
    });

    if (isDesktopLayout) {
      applyDesktopLayout();
    } else {
      applyMobileLayout();
    }
  }

  function appendBubble(role, text) {
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${role}`;
    bubble.textContent = text;
    historyContainer.appendChild(bubble);
    scrollToBottom(historyContainer);
    return bubble;
  }

  function removeBubble(bubble) {
    if (bubble && bubble.parentNode) {
      bubble.parentNode.removeChild(bubble);
    }
  }

  function scrollToBottom(element) {
    element.scrollTop = element.scrollHeight;
  }

  function setStatusMessage(message, type = 'info') {
    if (!statusElement) {
      return;
    }

    if (!message) {
      statusElement.textContent = '';
      statusElement.classList.remove('is-error');
      statusElement.setAttribute('role', 'status');
      statusElement.setAttribute('aria-live', 'polite');
      statusElement.hidden = true;
      return;
    }

    if (type === 'error') {
      statusElement.classList.add('is-error');
      statusElement.setAttribute('role', 'alert');
      statusElement.setAttribute('aria-live', 'assertive');
    } else {
      statusElement.classList.remove('is-error');
      statusElement.setAttribute('role', 'status');
      statusElement.setAttribute('aria-live', 'polite');
    }

    statusElement.textContent = message;
    statusElement.hidden = false;
  }

  function setFormDisabled(disabled) {
    textarea.disabled = disabled;
    submitButton.disabled = disabled;
  }
});
