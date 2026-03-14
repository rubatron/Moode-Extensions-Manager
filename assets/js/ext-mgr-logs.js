(function (window, document) {
  'use strict';

  var DEFAULT_LINES = 120;
  var state = {
    apiUrls: ['/ext-mgr-api.php', '/extensions/sys/ext-mgr-api.php'],
    statusHandler: null,
    modalEl: null,
    currentId: '',
    currentLabel: '',
    currentLogs: [],
    currentKey: ''
  };

  function uniqUrls(urls) {
    var deduped = [];
    (Array.isArray(urls) ? urls : []).forEach(function (candidate) {
      var url = String(candidate || '').trim();
      if (!url || deduped.indexOf(url) !== -1) {
        return;
      }
      deduped.push(url);
    });
    if (deduped.length === 0) {
      deduped.push('/ext-mgr-api.php');
    }
    return deduped;
  }

  function setStatus(text, kind) {
    if (typeof state.statusHandler === 'function') {
      state.statusHandler(text, kind || null);
    }
  }

  function buildBody(params) {
    var form = new URLSearchParams();
    Object.keys(params || {}).forEach(function (key) {
      form.append(key, String(params[key]));
    });
    return form.toString();
  }

  function postApi(params) {
    var urls = uniqUrls(state.apiUrls);
    var body = buildBody(params);
    var lastError = null;

    function tryAt(index) {
      if (index >= urls.length) {
        return Promise.reject(lastError || new Error('API request failed'));
      }

      return fetch(urls[index], {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body
      }).then(function (res) {
        return res.json().catch(function () {
          return { ok: false, error: 'Invalid JSON response from API endpoint.' };
        }).then(function (data) {
          if (res.ok && data && data.ok) {
            return data;
          }

          var err = new Error((data && data.error) || ('Request failed (' + res.status + ')'));
          err.status = res.status;
          lastError = err;
          if (res.status === 404 || res.status === 0) {
            return tryAt(index + 1);
          }
          throw err;
        });
      }).catch(function (err) {
        lastError = err;
        return tryAt(index + 1);
      });
    }

    return tryAt(0);
  }

  function bestApiBase() {
    var urls = uniqUrls(state.apiUrls);
    return urls[0];
  }

  function buildDownloadUrl(extensionId, key) {
    var base = bestApiBase();
    var query = new URLSearchParams({
      action: 'download_extension_log',
      id: extensionId,
      key: key
    }).toString();
    return base + '?' + query;
  }

  function ensureModal() {
    if (state.modalEl) {
      return state.modalEl;
    }

    var modal = document.createElement('div');
    modal.className = 'extmgr-log-modal';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = '' +
      '<div class="extmgr-log-modal-backdrop" data-log-close="1"></div>' +
      '<div class="extmgr-log-modal-dialog" role="dialog" aria-modal="true" aria-label="Extension logs">' +
      '  <div class="extmgr-log-modal-header">' +
      '    <h3 class="extmgr-log-modal-title">Logs</h3>' +
      '    <button type="button" class="btn btn-small" data-log-close="1">Close</button>' +
      '  </div>' +
      '  <div class="extmgr-log-modal-toolbar">' +
      '    <label for="extmgr-log-file-select">Log file</label>' +
      '    <select id="extmgr-log-file-select"></select>' +
      '    <button type="button" class="btn btn-small" id="extmgr-log-refresh-btn">Refresh</button>' +
      '    <button type="button" class="btn btn-small" id="extmgr-log-analyze-btn">Analyze</button>' +
      '    <button type="button" class="btn btn-small" id="extmgr-log-open-btn">Open Log File</button>' +
      '  </div>' +
      '  <div id="extmgr-log-file-meta" class="extmgr-log-file-meta">-</div>' +
      '  <pre id="extmgr-log-analysis" class="extmgr-log-analysis">Analysis not loaded.</pre>' +
      '  <pre id="extmgr-log-content" class="extmgr-log-content">Select a log file to load content.</pre>' +
      '</div>';

    modal.addEventListener('click', function (evt) {
      var target = evt.target;
      if (target && target.getAttribute('data-log-close') === '1') {
        closeModal();
      }
    });

    document.body.appendChild(modal);
    state.modalEl = modal;
    return modal;
  }

  function closeModal() {
    if (!state.modalEl) {
      return;
    }
    state.modalEl.classList.remove('is-open');
    state.modalEl.setAttribute('aria-hidden', 'true');
  }

  function openModal() {
    var modal = ensureModal();
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function byId(id) {
    return document.getElementById(id);
  }

  function currentLog() {
    var found = null;
    state.currentLogs.forEach(function (row) {
      if (row && row.key === state.currentKey) {
        found = row;
      }
    });
    return found;
  }

  function renderMeta(logRow) {
    var el = byId('extmgr-log-file-meta');
    if (!el) {
      return;
    }
    if (!logRow) {
      el.textContent = '-';
      return;
    }
    var sizeLabel = typeof logRow.sizeBytes === 'number' ? (logRow.sizeBytes + ' bytes') : 'size unknown';
    var updated = logRow.updatedAt || 'unknown time';
    var source = logRow.source || 'runtime';
    el.textContent = 'Source: ' + source + ' | Updated: ' + updated + ' | Size: ' + sizeLabel;
  }

  function renderAnalysisText(text) {
    var el = byId('extmgr-log-analysis');
    if (!el) {
      return;
    }
    el.textContent = String(text || 'Analysis not loaded.');
  }

  function formatRows(prefix, rows, limit) {
    var list = Array.isArray(rows) ? rows.slice(0, limit || 8) : [];
    if (!list.length) {
      return prefix + ': none';
    }
    return prefix + ': ' + list.map(function (row) {
      if (!row || typeof row !== 'object') {
        return 'n/a';
      }
      if (Object.prototype.hasOwnProperty.call(row, 'signature')) {
        return String(row.signature || 'n/a') + ' (' + String(row.count || 0) + ')';
      }
      if (Object.prototype.hasOwnProperty.call(row, 'id')) {
        return String(row.id || 'n/a') + ' (' + String(row.errorRatePct || 0) + '%, e=' + String(row.errorLines || 0) + ', s=' + String(row.systemLines || 0) + ')';
      }
      return 'n/a';
    }).join(' | ');
  }

  function loadAnalysis() {
    if (!state.currentId) {
      renderAnalysisText('Analysis unavailable: no target selected.');
      return Promise.resolve();
    }

    renderAnalysisText('Running analysis...');

    return postApi({ action: 'analyze_logs', id: state.currentId }).then(function (res) {
      var data = (res && res.data) || {};
      var text = [];

      if (data.scope === 'single' && data.target) {
        var target = data.target;
        text.push('Target: ' + String(target.id || state.currentId));
        text.push('Error rate: ' + String(target.errorRatePct || 0) + '% (errors=' + String(target.errorLines || 0) + ', system=' + String(target.systemLines || 0) + ', total=' + String(target.totalLines || 0) + ')');
        text.push(formatRows('Top errors', target.topErrors, 8));
        var events = Array.isArray(target.restartEvents) ? target.restartEvents.slice(0, 6) : [];
        text.push(events.length ? ('Last restart events: ' + events.map(function (e) {
          return '[' + String(e.at || 'n/a') + '] ' + String(e.message || '');
        }).join(' | ')) : 'Last restart events: none');
      } else {
        text.push('Scope: global (' + String(data.extensionCount || 0) + ' extensions)');
        text.push(formatRows('Highest error rates', data.perExtension, 8));
        text.push(formatRows('Top errors', data.topErrors, 10));
        var globalEvents = Array.isArray(data.restartEvents) ? data.restartEvents.slice(0, 8) : [];
        text.push(globalEvents.length ? ('Recent restart events: ' + globalEvents.map(function (e) {
          return String(e.id || 'n/a') + ' [' + String(e.at || 'n/a') + '] ' + String(e.message || '');
        }).join(' | ')) : 'Recent restart events: none');
      }

      renderAnalysisText(text.join('\n'));
    }).catch(function (err) {
      renderAnalysisText('Analysis failed: ' + String((err && err.message) || 'unknown error'));
      setStatus(String((err && err.message) || 'Analysis failed'), 'error');
    });
  }

  function loadLogContent() {
    var pre = byId('extmgr-log-content');
    var logRow = currentLog();
    renderMeta(logRow);
    if (!pre || !logRow) {
      return;
    }

    pre.textContent = 'Loading log...';

    return postApi({
      action: 'read_extension_log',
      id: state.currentId,
      key: state.currentKey,
      lines: String(DEFAULT_LINES)
    }).then(function (res) {
      var payload = (res && res.data) || {};
      pre.textContent = String(payload.content || 'No log content available.');
      renderMeta(payload.log || logRow);
    }).catch(function (err) {
      pre.textContent = err.message || 'Failed to load log.';
      setStatus(pre.textContent, 'error');
    });
  }

  function renderPicker() {
    var select = byId('extmgr-log-file-select');
    var title = state.modalEl ? state.modalEl.querySelector('.extmgr-log-modal-title') : null;
    if (!select) {
      return;
    }

    select.innerHTML = '';
    state.currentLogs.forEach(function (logRow) {
      var opt = document.createElement('option');
      opt.value = logRow.key;
      opt.textContent = logRow.label;
      select.appendChild(opt);
    });

    if (!state.currentLogs.length) {
      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'No logs found';
      select.appendChild(placeholder);
      state.currentKey = '';
    } else if (!state.currentKey) {
      state.currentKey = state.currentLogs[0].key;
      select.value = state.currentKey;
    } else {
      select.value = state.currentKey;
    }

    if (title) {
      title.textContent = 'Logs: ' + state.currentLabel;
    }

    loadLogContent();
  }

  function wireModalActions() {
    var select = byId('extmgr-log-file-select');
    var refreshBtn = byId('extmgr-log-refresh-btn');
    var analyzeBtn = byId('extmgr-log-analyze-btn');
    var openBtn = byId('extmgr-log-open-btn');

    if (select && !select._extmgrBound) {
      select.addEventListener('change', function () {
        state.currentKey = select.value || '';
        loadLogContent();
      });
      select._extmgrBound = true;
    }

    if (refreshBtn && !refreshBtn._extmgrBound) {
      refreshBtn.addEventListener('click', function () {
        loadLogList(state.currentId, state.currentLabel);
      });
      refreshBtn._extmgrBound = true;
    }

    if (analyzeBtn && !analyzeBtn._extmgrBound) {
      analyzeBtn.addEventListener('click', function () {
        loadAnalysis();
      });
      analyzeBtn._extmgrBound = true;
    }

    if (openBtn && !openBtn._extmgrBound) {
      openBtn.addEventListener('click', function () {
        if (!state.currentId || !state.currentKey) {
          return;
        }
        window.open(buildDownloadUrl(state.currentId, state.currentKey), '_blank', 'noopener');
      });
      openBtn._extmgrBound = true;
    }
  }

  function loadLogList(extensionId, label) {
    state.currentId = extensionId;
    state.currentLabel = label || extensionId;
    state.currentLogs = [];
    state.currentKey = '';

    openModal();
    wireModalActions();

    var pre = byId('extmgr-log-content');
    if (pre) {
      pre.textContent = 'Loading available logs...';
    }
    renderAnalysisText('Analysis not loaded. Click Analyze to refresh metrics.');

    return postApi({ action: 'list_extension_logs', id: extensionId }).then(function (res) {
      var payload = (res && res.data) || {};
      state.currentLogs = Array.isArray(payload.logs) ? payload.logs : [];
      state.currentKey = state.currentLogs.length ? state.currentLogs[0].key : '';
      renderPicker();
      return loadAnalysis();
    }).catch(function (err) {
      if (pre) {
        pre.textContent = err.message || 'Failed to load logs.';
      }
      setStatus(err.message || 'Failed to load logs.', 'error');
    });
  }

  function makeButton(text, className) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = className || 'btn btn-small';
    btn.textContent = text;
    return btn;
  }

  function attachExtensionButton(item, container) {
    if (!item || !container || !item.id) {
      return null;
    }

    var btn = makeButton('Open Logs', 'btn btn-small');
    btn.classList.add('extmgr-log-open-btn');
    btn.addEventListener('click', function () {
      var label = item.name || item.id;
      loadLogList(String(item.id), label);
    });

    container.appendChild(btn);
    return btn;
  }

  function bindManagerButton(buttonId) {
    var id = String(buttonId || 'open-extmgr-logs-btn');
    var btn = document.getElementById(id);
    if (!btn || btn._extmgrBound) {
      return;
    }

    btn.addEventListener('click', function () {
      loadLogList('ext-mgr', 'ext-mgr');
    });
    btn._extmgrBound = true;
  }

  function init(options) {
    var initConfig = options || {};
    state.apiUrls = uniqUrls(initConfig.apiUrls || state.apiUrls);
    state.statusHandler = typeof initConfig.statusHandler === 'function' ? initConfig.statusHandler : null;

    if (!document.body) {
      document.addEventListener('DOMContentLoaded', function () {
        ensureModal();
      }, { once: true });
    } else {
      ensureModal();
    }

    bindManagerButton(initConfig.managerButtonId || 'open-extmgr-logs-btn');
  }

  window.ExtMgrLogs = {
    init: init,
    attachExtensionButton: attachExtensionButton,
    openFor: loadLogList,
    bindManagerButton: bindManagerButton
  };
})(window, document);
