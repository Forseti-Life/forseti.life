(function (Drupal, drupalSettings) {
  'use strict';

  const settings = drupalSettings.dungeoncrawlerTester || {};
  const endpoints = settings.routes || {};
  const token = settings.csrfToken || '';
  let countdownTimer = null;
  const refreshIntervalMs = 5000;
  let countdownSeconds = refreshIntervalMs / 1000;
  let currentState = 'idle';

  Drupal.behaviors.dungeoncrawlerQueueManagement = {
    attach: function (context) {
      if (context.__dcQueueInit) {
        return;
      }
      context.__dcQueueInit = true;

      const runBtn = context.querySelector('.btn-run-all');
      const refreshBtn = context.querySelector('.btn-refresh');
      const refreshLogsBtn = context.querySelector('.btn-refresh-logs');
      const autoToggle = context.querySelector('#dc-auto-refresh');

      if (runBtn) {
        runBtn.addEventListener('click', () => runQueue());
      }
      if (refreshBtn) {
        refreshBtn.addEventListener('click', () => refreshStatus());
      }
      if (refreshLogsBtn) {
        refreshLogsBtn.addEventListener('click', () => refreshLogs());
      }
      if (autoToggle) {
        autoToggle.addEventListener('change', () => {
          if (autoToggle.checked) {
            startAutoRefresh();
          } else {
            stopAutoRefresh();
          }
        });
      }

      // Initial loads
      refreshStatus();
      refreshLogs();
      startAutoRefresh();
    }
  };

  function runQueue() {
    setStatus('running');
    fetch(endpoints.run, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token,
      },
      body: JSON.stringify({ limit: 5 }),
    })
      .then(r => r.json())
      .then(data => {
        setStatus('idle');
        showMessage(data.message || 'Queue processed', data.success ? 'success' : 'error');
        refreshStatus();
        refreshLogs();
      })
      .catch(err => {
        setStatus('idle');
        showMessage('Error running queue: ' + err.message, 'error');
      });
  }

  function refreshStatus() {
    setStatusText('Refreshing…', true);
    fetch(endpoints.status)
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          showMessage(data.message || 'Failed to load status', 'error');
          return;
        }
        const body = document.querySelector('#dc-queue-status-body');
        let total = 0;
        if (body && data.queues) {
          body.querySelectorAll('tr').forEach(row => {
            const id = row.getAttribute('data-queue-id');
            const q = data.queues[id];
            if (!q) return;
            const badge = row.querySelector('[data-count]');
            if (badge) {
              badge.textContent = q.items;
            }
            total += q.items;
          });
        }
        const totalEl = document.querySelector('[data-total-count]');
        if (totalEl) {
          totalEl.textContent = total;
        }
        setStatus(total > 0 ? 'pending' : 'idle');
        markUpdated('status');
        resetCountdown();
      })
      .catch(err => {
        showMessage('Error refreshing status: ' + err.message, 'error');
      })
      .finally(() => {
        setStatus(currentState);
      });
  }

  function refreshLogs() {
    setStatusText('Refreshing…', true);
    fetch(endpoints.logs)
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          showMessage(data.message || 'Failed to load logs', 'error');
          return;
        }
        const container = document.querySelector('#dc-log-entries');
        if (!container) return;
        container.innerHTML = '';
        (data.logs || []).forEach(entry => {
          const div = document.createElement('div');
          div.className = 'log-entry';
          const ts = new Date(entry.timestamp * 1000).toLocaleString();
          div.textContent = `[${ts}] ${entry.message}`;
          container.appendChild(div);
        });
        if (!data.logs || data.logs.length === 0) {
          container.innerHTML = '<div class="log-entry">No recent activity.</div>';
        }
        markUpdated('logs');
        resetCountdown();
      })
      .catch(err => {
        showMessage('Error refreshing logs: ' + err.message, 'error');
      })
      .finally(() => {
        setStatus(currentState);
      });
  }

  function runAutoRefresh() {
    refreshStatus();
    refreshLogs();
  }

  function setStatus(state) {
    const pill = document.querySelector('.status-pill');
    const text = pill ? pill.querySelector('[data-status-text]') : null;
    if (!pill || !text) return;
    pill.classList.remove('running', 'idle', 'pending');
    pill.classList.add(state);
    currentState = state;
    if (state === 'running') {
      text.textContent = 'Running';
    } else if (state === 'pending') {
      text.textContent = 'Pending';
    } else {
      text.textContent = 'Idle';
    }
  }

  function setStatusText(text, refreshing = false) {
    const pill = document.querySelector('.status-pill');
    const t = pill ? pill.querySelector('[data-status-text]') : null;
    if (!pill || !t) return;
    pill.classList.toggle('refreshing', refreshing);
    t.textContent = text;
  }

  function showMessage(message, type) {
    const existing = document.querySelector('.dc-queue-message');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = `dc-queue-message dc-queue-message-${type}`;
    div.textContent = message;
    const page = document.querySelector('.dc-queue-page');
    if (page) {
      page.insertBefore(div, page.firstChild);
      setTimeout(() => {
        div.style.opacity = '0';
        div.style.transition = 'opacity 0.3s';
        setTimeout(() => div.remove(), 300);
      }, 4000);
    }
  }

  function startAutoRefresh() {
    stopAutoRefresh();
    countdownSeconds = refreshIntervalMs / 1000;
    updateCountdown(countdownSeconds);
    countdownTimer = setInterval(() => {
      countdownSeconds -= 1;
      if (countdownSeconds <= 0) {
        runAutoRefresh();
        countdownSeconds = refreshIntervalMs / 1000;
      }
      updateCountdown(countdownSeconds);
    }, 1000);
  }

  function stopAutoRefresh() {
    if (countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }
    updateCountdown(null);
  }

  function updateCountdown(seconds) {
    const el = document.querySelector('#dc-auto-refresh-countdown');
    if (!el) return;
    if (seconds === null) {
      el.textContent = '';
      return;
    }
    el.textContent = `Next refresh in ${seconds}s`;
  }

  function markUpdated(kind) {
    const id = kind === 'logs' ? '#dc-logs-updated' : '#dc-status-updated';
    const el = document.querySelector(id);
    if (el) {
      el.textContent = new Date().toLocaleTimeString();
    }
    const inline = document.querySelector('#dc-last-refresh-inline');
    if (inline && kind === 'status') {
      inline.textContent = new Date().toLocaleTimeString();
    }
  }

  function resetCountdown() {
    countdownSeconds = refreshIntervalMs / 1000;
    updateCountdown(countdownSeconds);
  }

})(Drupal, drupalSettings);
