(function () {
  'use strict';

  var init = window.__EXT_MGR_INIT__ || {};
  var apiUrl = init.apiUrl || '/ext-mgr-api.php';

  var statusEl = document.getElementById('status');
  var listEl = document.getElementById('list');
  var apiServiceEl = document.getElementById('api-service');
  var registryHealthEl = document.getElementById('registry-health');
  var extensionCountEl = document.getElementById('extension-count');
  var activeCountEl = document.getElementById('active-count');
  var inactiveCountEl = document.getElementById('inactive-count');
  var mVisibleCountEl = document.getElementById('m-visible-count');
  var libraryVisibleCountEl = document.getElementById('library-visible-count');
  var settingsCardCountEl = document.getElementById('settings-card-count');
  var serviceMemPctEl = document.getElementById('service-mem-pct');

  var metaVersionEl = document.getElementById('meta-version');
  var metaCreatorEl = document.getElementById('meta-creator');
  var metaLicenseEl = document.getElementById('meta-license');
  var updateNoteEl = document.getElementById('update-note');
  var advancedModeButtons = document.querySelectorAll('[data-advanced-mode]');
  var advancedCustomWrapEl = document.getElementById('advanced-custom-wrap');
  var advancedCustomUrlEl = document.getElementById('advanced-custom-url');
  var advancedSourceLinkEl = document.getElementById('advanced-source-link');
  var openAdvancedSourceBtn = document.getElementById('open-advanced-source-btn');
  var copyAdvancedSourceBtn = document.getElementById('copy-advanced-source-btn');
  var saveAdvancedUpdateBtn = document.getElementById('save-advanced-update-btn');
  var advancedUpdateNoteEl = document.getElementById('advanced-update-note');
  var maintenanceLogEl = document.getElementById('maintenance-log');

  var refreshBtn = document.getElementById('refresh-btn');
  var checkUpdateBtn = document.getElementById('check-update-btn');
  var runUpdateBtn = document.getElementById('run-update-btn');
  var systemUpdateBtn = document.getElementById('system-update-btn');
  var repairBtn = document.getElementById('repair-btn');
  var syncRegistryBtn = document.getElementById('sync-registry-btn');
  var importExtensionFileEl = document.getElementById('import-extension-file');
  var importExtensionBtn = document.getElementById('import-extension-btn');
  var importWizardNoteEl = document.getElementById('import-wizard-note');
  var listFilterEl = document.getElementById('list-filter');
  var listSortEl = document.getElementById('list-sort');
  var listSearchEl = document.getElementById('list-search');
  var listSummaryEl = document.getElementById('list-summary');
  var guidanceDocEl = document.getElementById('guidance-doc');
  var requirementsDocEl = document.getElementById('requirements-doc');
  var faqDocEl = document.getElementById('faq-doc');
  var menuToggleButtons = document.querySelectorAll('[data-menu-toggle]');
  var submenuToggleButtons = document.querySelectorAll('[data-submenu-toggle]');

  var allItems = [];
  var PREF_PREFIX = 'extmgr.list.';
  var latestCheckState = {
    hasUpdate: false,
    candidateVersion: null,
    warning: null,
    integrity: null
  };
  var advancedUpdateState = {
    mode: 'main',
    customUrl: ''
  };
  var currentProviderStatus = {
    provider: 'github',
    repository: '',
    updateTrack: 'branch',
    channel: 'stable',
    branch: 'main',
    customBaseUrl: '',
    availableBranches: ['main', 'dev'],
    signatureVerification: 'planned',
    checksumAlgorithm: 'sha256',
    integrityManifestPath: 'ext-mgr.integrity.json'
  };

  function setStatus(text, kind) {
    statusEl.textContent = text;
    statusEl.classList.remove('error', 'ok');
    if (kind) {
      statusEl.classList.add(kind);
    }
  }

  function api(params) {
    return fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: new URLSearchParams(params).toString()
    }).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok || !data.ok) {
          throw new Error((data && data.error) || 'Request failed');
        }
        return data;
      });
    });
  }

  function bindIfPresent(el, eventName, handler) {
    if (!el) {
      return;
    }
    el.addEventListener(eventName, handler);
  }

  function initConfigureModalFix() {
    function bindWithJquery($) {
      if (!$ || !$.fn || !$.fn.modal) {
        return false;
      }

      $(document)
        .off('click.extMgrConfigureModalFix')
        .on(
          'click.extMgrConfigureModalFix',
          'a[href="#configure-modal"], a[href*="configure-modal"], [data-target="#configure-modal"]',
          function (e) {
            var $modal = $('#configure-modal');
            if (!$modal.length) {
              return;
            }

            e.preventDefault();
            e.stopPropagation();
            $modal.removeClass('hide').modal('show');
          }
        );

      if (window.location.hash === '#configure-modal' || window.location.hash.indexOf('configure-modal') !== -1) {
        window.setTimeout(function () {
          var $modal = $('#configure-modal');
          if ($modal.length) {
            $modal.removeClass('hide').modal('show');
          }
        }, 0);
      }

      return true;
    }

    if (bindWithJquery(window.jQuery || window.$)) {
      return;
    }

    document.addEventListener('DOMContentLoaded', function onReady() {
      bindWithJquery(window.jQuery || window.$);
    }, { once: true });
  }

  function setImportWizardNote(text, kind) {
    if (!importWizardNoteEl) {
      return;
    }
    importWizardNoteEl.textContent = text;
    importWizardNoteEl.classList.remove('error', 'ok');
    if (kind) {
      importWizardNoteEl.classList.add(kind);
    }
  }

  function apiUpload(file) {
    var formData = new FormData();
    formData.append('action', 'import_extension_upload');
    formData.append('package', file);

    return fetch(apiUrl, {
      method: 'POST',
      body: formData
    }).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok || !data.ok) {
          throw new Error((data && data.error) || 'Upload failed');
        }
        return data;
      });
    });
  }

  function readPref(key, fallback) {
    try {
      var value = window.localStorage.getItem(PREF_PREFIX + key);
      return value === null ? fallback : value;
    } catch (e) {
      return fallback;
    }
  }

  function writePref(key, value) {
    try {
      window.localStorage.setItem(PREF_PREFIX + key, String(value));
    } catch (e) {
      // Ignore localStorage restrictions.
    }
  }

  function setText(el, value) {
    if (!el) {
      return;
    }
    el.textContent = value;
  }

  function renderMeta(meta) {
    if (!meta) {
      return;
    }

    setText(metaVersionEl, meta.version || 'n/a');
    setText(metaCreatorEl, meta.creator || 'n/a');
    setText(metaLicenseEl, meta.license || 'n/a');

    var maintenance = meta.maintenance || {};
    var lastRun = maintenance.lastRunAt || 'never';
    var lastAction = maintenance.lastAction || 'none';
    var lastResult = maintenance.lastResult || 'n/a';
    var src = meta.versionSources || {};
    var srcVersion = src.currentVersionFile || 'n/a';
    var srcRelease = src.releasePolicyFile || 'n/a';
    setText(maintenanceLogEl, 'lastAction=' + lastAction + '\nlastResult=' + lastResult + '\nlastRunAt=' + lastRun + '\nversionSource=' + srcVersion + '\nreleaseSource=' + srcRelease);
  }

  function renderHealth(health) {
    if (!health) {
      return;
    }
    setText(apiServiceEl, health.apiService || 'unknown');
    setText(registryHealthEl, health.registry || 'unknown');
    setText(extensionCountEl, String(health.extensionCount || 0));
    setText(activeCountEl, String(health.activeCount || 0));
    setText(inactiveCountEl, String(health.inactiveCount || 0));
    setText(mVisibleCountEl, String(health.mVisibleCount || 0));
    setText(libraryVisibleCountEl, String(health.libraryVisibleCount || 0));
    setText(settingsCardCountEl, String(health.settingsCardCount || 0));
    if (serviceMemPctEl) {
      var memPct = health.serviceMemoryPctOfSystem;
      if (typeof memPct === 'number') {
        serviceMemPctEl.textContent = memPct.toFixed(4) + '%';
      } else {
        serviceMemPctEl.textContent = 'n/a';
      }
    }
  }

  function providerStatusFromPolicy(policy) {
    var p = policy || {};
    return {
      provider: p.provider || 'github',
      repository: p.repository || '',
      updateTrack: p.updateTrack || 'branch',
      channel: p.channel || 'stable',
      branch: p.branch || 'main',
      customBaseUrl: p.customBaseUrl || '',
      availableBranches: ['main', 'dev'],
      signatureVerification: p.signatureVerification || 'planned',
      checksumAlgorithm: p.checksumAlgorithm || 'sha256',
      integrityManifestPath: p.integrityManifestPath || 'ext-mgr.integrity.json'
    };
  }

  function parseRepository(repository) {
    var repo = String(repository || '').trim();
    var parts = repo.split('/');
    if (parts.length !== 2 || !parts[0] || !parts[1]) {
      return null;
    }
    return { owner: parts[0], name: parts[1] };
  }

  function buildResolveSourceUrl(providerStatus) {
    var status = providerStatus || {};
    if ((status.updateTrack || 'branch') === 'custom') {
      return String(status.customBaseUrl || '').trim();
    }

    if ((status.provider || 'github') !== 'github') {
      return '';
    }

    var repoParts = parseRepository(status.repository || '');
    if (!repoParts) {
      return '';
    }

    var base = 'https://api.github.com/repos/' + encodeURIComponent(repoParts.owner) + '/' + encodeURIComponent(repoParts.name);
    var track = status.updateTrack || 'channel';
    if (track === 'branch') {
      return base + '/branches/' + encodeURIComponent(status.branch || 'main');
    }

    var channel = status.channel || 'stable';
    if (channel === 'stable') {
      return base + '/releases/latest';
    }
    return base + '/releases?per_page=30';
  }

  function buildRawManagedBaseUrl(providerStatus, candidate) {
    var status = providerStatus || {};
    if ((status.updateTrack || 'branch') === 'custom') {
      var customBase = String((candidate && candidate.baseUrl) || status.customBaseUrl || '').trim();
      if (!customBase) {
        return '';
      }
      return customBase.replace(/\/+$/, '') + '/';
    }

    if ((status.provider || 'github') !== 'github') {
      return '';
    }

    var repoParts = parseRepository(status.repository || '');
    if (!repoParts) {
      return '';
    }

    var ref = (candidate && (candidate.ref || candidate.tag)) || (status.updateTrack === 'branch' ? status.branch : 'main');
    if (!ref) {
      return '';
    }

    return 'https://raw.githubusercontent.com/' + encodeURIComponent(repoParts.owner) + '/' + encodeURIComponent(repoParts.name) + '/' + encodeURIComponent(ref) + '/';
  }

  function renderAdvancedSource(providerStatus, candidate) {
    if (!advancedSourceLinkEl) {
      return;
    }

    var resolveUrl = buildResolveSourceUrl(providerStatus);
    var rawBase = buildRawManagedBaseUrl(providerStatus, candidate);
    var display = resolveUrl || '-';
    if (rawBase) {
      display += ' | raw base: ' + rawBase;
    }

    advancedSourceLinkEl.textContent = display;
    advancedSourceLinkEl.setAttribute('data-source-url', resolveUrl || '');
    advancedSourceLinkEl.setAttribute('data-raw-base-url', rawBase || '');

    if (openAdvancedSourceBtn) {
      openAdvancedSourceBtn.href = resolveUrl || '#';
      openAdvancedSourceBtn.setAttribute('aria-disabled', resolveUrl ? 'false' : 'true');
      openAdvancedSourceBtn.tabIndex = resolveUrl ? 0 : -1;
    }
  }

  function buildIntegrityText(integrity, providerStatus) {
    var mode = (providerStatus && providerStatus.signatureVerification) || (integrity && integrity.mode) || 'n/a';
    var algorithm = (providerStatus && providerStatus.checksumAlgorithm) || (integrity && integrity.algorithm) || 'n/a';
    var manifestPath = (providerStatus && providerStatus.integrityManifestPath) || (integrity && integrity.manifestPath) || 'n/a';
    var status = (integrity && integrity.status) || 'not-run';

    if (integrity && integrity.status === 'verified' && integrity.details) {
      return 'integrity=' + status + ' mode=' + mode + ' alg=' + algorithm + ' manifest=' + manifestPath + ' matched=' + String(integrity.details.matched || 0);
    }
    if (integrity && integrity.status === 'degraded') {
      return 'integrity=' + status + ' mode=' + mode + ' alg=' + algorithm + ' manifest=' + manifestPath + (integrity.warning ? ' warning=' + integrity.warning : '');
    }
    return 'integrity=' + status + ' mode=' + mode + ' alg=' + algorithm + ' manifest=' + manifestPath;
  }

  function renderUpdateStatus(meta, hasUpdate, candidate, warning, providerStatus, integrity) {
    if (!updateNoteEl) {
      return;
    }
    var currentVersion = (meta && meta.version) || 'n/a';
    var latestVersion = (meta && meta.latestVersion) || 'n/a';
    var integration = (meta && meta.updateIntegration) || {};
    var provider = integration.provider || 'n/a';
    var verification = integration.signatureVerification || 'n/a';
    var candidateVersion = candidate && candidate.version ? candidate.version : 'n/a';
    var candidateSource = candidate && candidate.source ? candidate.source : 'n/a';
    var channel = integration.channel || 'n/a';
    var track = (providerStatus && providerStatus.updateTrack) || 'channel';
    var branch = (providerStatus && providerStatus.branch) || 'n/a';
    var integrityText = buildIntegrityText(integrity, providerStatus);
    if (hasUpdate) {
      updateNoteEl.textContent = 'Update available: ' + currentVersion + ' -> ' + latestVersion + ' | candidate=' + candidateVersion + ' | source=' + candidateSource + ' | track=' + track + ' | channel=' + channel + ' | branch=' + branch + ' | provider=' + provider + ' | signature=' + verification + ' | ' + integrityText;
      return;
    }

    if (warning) {
      updateNoteEl.textContent = 'Update check warning: ' + warning + ' | current=' + currentVersion + ' | latest=' + latestVersion + ' | track=' + track + ' | channel=' + channel + ' | branch=' + branch + ' | ' + integrityText;
      return;
    }

    updateNoteEl.textContent = 'No update pending. Current version: ' + currentVersion + '. track=' + track + ' channel=' + channel + ' branch=' + branch + ' provider=' + provider + ' signature=' + verification + ' | ' + integrityText;
  }

  function getAdvancedModeFromStatus(providerStatus) {
    var status = providerStatus || {};
    if ((status.updateTrack || '') === 'custom') {
      return 'custom';
    }
    if ((status.branch || 'main') === 'dev') {
      return 'dev';
    }
    return 'main';
  }

  function renderAdvancedModeButtons() {
    advancedModeButtons.forEach(function (btn) {
      var mode = btn.getAttribute('data-advanced-mode');
      btn.classList.toggle('is-active', mode === advancedUpdateState.mode);
      btn.setAttribute('aria-pressed', mode === advancedUpdateState.mode ? 'true' : 'false');
    });

    if (advancedCustomWrapEl) {
      advancedCustomWrapEl.classList.toggle('is-visible', advancedUpdateState.mode === 'custom');
    }

    if (advancedCustomUrlEl) {
      advancedCustomUrlEl.disabled = advancedUpdateState.mode !== 'custom';
    }
  }

  function mergeWithCurrentProviderStatus(overrides) {
    var next = {};
    var base = currentProviderStatus || {};
    var patch = overrides || {};

    Object.keys(base).forEach(function (key) {
      next[key] = base[key];
    });
    Object.keys(patch).forEach(function (key) {
      next[key] = patch[key];
    });

    return next;
  }

  function renderAdvancedUpdateControls(providerStatus, payloadWarning, candidate) {
    if (!advancedModeButtons || advancedModeButtons.length === 0) {
      return;
    }

    currentProviderStatus = providerStatusFromPolicy(providerStatus || {});

    advancedUpdateState.mode = getAdvancedModeFromStatus(providerStatus);
    advancedUpdateState.customUrl = String((providerStatus && providerStatus.customBaseUrl) || '').trim();
    if (advancedCustomUrlEl) {
      advancedCustomUrlEl.value = advancedUpdateState.customUrl;
    }

    renderAdvancedModeButtons();
    var previewStatus = providerStatus || {};
    if (advancedUpdateState.mode === 'custom') {
      previewStatus = mergeWithCurrentProviderStatus({
        provider: 'custom',
        repository: '',
        updateTrack: 'custom',
        channel: 'stable',
        branch: 'main',
        customBaseUrl: advancedUpdateState.customUrl
      });
    } else if (advancedUpdateState.mode === 'dev') {
      previewStatus = mergeWithCurrentProviderStatus({
        provider: (providerStatus && providerStatus.provider) || currentProviderStatus.provider || 'github',
        repository: (providerStatus && providerStatus.repository) || currentProviderStatus.repository,
        updateTrack: 'branch',
        channel: 'dev',
        branch: 'dev',
        customBaseUrl: ''
      });
    } else {
      previewStatus = mergeWithCurrentProviderStatus({
        provider: (providerStatus && providerStatus.provider) || currentProviderStatus.provider || 'github',
        repository: (providerStatus && providerStatus.repository) || currentProviderStatus.repository,
        updateTrack: 'branch',
        channel: 'stable',
        branch: 'main',
        customBaseUrl: ''
      });
    }
    renderAdvancedSource(previewStatus, candidate || null);

    if (advancedUpdateNoteEl) {
      var activeModeLabel = advancedUpdateState.mode === 'custom'
        ? 'custom URL'
        : (advancedUpdateState.mode === 'dev' ? 'dev branch' : 'main');
      advancedUpdateNoteEl.textContent = payloadWarning
        ? ('Branch discovery warning: ' + payloadWarning + '. Using stored branch list.')
        : ('Modes: main, dev branch, custom URL. Active mode=' + activeModeLabel + '.');
    }
  }

  function setRunUpdateButtonState() {
    if (!runUpdateBtn) {
      return;
    }
    runUpdateBtn.disabled = !latestCheckState.hasUpdate;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function markdownToHtml(markdown) {
    var lines = String(markdown || '').split(/\r?\n/);
    var html = [];
    var inList = false;

    function closeList() {
      if (inList) {
        html.push('</ul>');
        inList = false;
      }
    }

    lines.forEach(function (rawLine) {
      var line = rawLine.trim();
      if (!line) {
        closeList();
        return;
      }

      if (line.indexOf('## ') === 0) {
        closeList();
        html.push('<h4>' + escapeHtml(line.slice(3)) + '</h4>');
        return;
      }

      if (line.indexOf('# ') === 0) {
        closeList();
        html.push('<h4>' + escapeHtml(line.slice(2)) + '</h4>');
        return;
      }

      if (line.indexOf('- ') === 0) {
        if (!inList) {
          html.push('<ul>');
          inList = true;
        }
        html.push('<li>' + escapeHtml(line.slice(2)) + '</li>');
        return;
      }

      closeList();
      html.push('<p>' + escapeHtml(line) + '</p>');
    });

    closeList();
    return html.join('');
  }

  function renderGuidanceDocs(guidance) {
    if (!guidance || typeof guidance !== 'object') {
      return;
    }

    if (guidanceDocEl) {
      guidanceDocEl.innerHTML = markdownToHtml(guidance.guidanceMarkdown || 'No guidance loaded.');
    }
    if (requirementsDocEl) {
      requirementsDocEl.innerHTML = markdownToHtml(guidance.requirementsMarkdown || 'No requirements loaded.');
    }
    if (faqDocEl) {
      faqDocEl.innerHTML = markdownToHtml(guidance.faqMarkdown || 'No FAQ loaded.');
    }
  }

  function getVisibility(item, key) {
    var visibility = item && item.menuVisibility;
    if (!visibility || typeof visibility !== 'object') {
      return true;
    }
    return !!visibility[key];
  }

  function setVisibility(item, key, value) {
    if (!item.menuVisibility || typeof item.menuVisibility !== 'object') {
      item.menuVisibility = { m: true, library: true };
    }
    item.menuVisibility[key] = !!value;
  }

  function visibilityLabel(target, visible) {
    var name = target === 'm' ? 'M menu' : 'Library menu';
    return name + ': ' + (visible ? 'Visible' : 'Hidden');
  }

  function settingsCardLabel(enabled) {
    return 'Settings Card: ' + (enabled ? 'Enabled' : 'Disabled');
  }

  function applyVisibilityButtonState(button, target, visible) {
    if (!button) {
      return;
    }
    button.classList.add('visibility-toggle');
    button.classList.remove('is-on', 'is-off');
    button.classList.add(visible ? 'is-on' : 'is-off');
    button.textContent = visibilityLabel(target, visible);
  }

  function applySettingsCardButtonState(button, enabled) {
    if (!button) {
      return;
    }
    button.classList.add('visibility-toggle');
    button.classList.remove('is-on', 'is-off');
    button.classList.add(enabled ? 'is-on' : 'is-off');
    button.textContent = settingsCardLabel(enabled);
  }

  function getSettingsCardOnly(item) {
    return !!(item && item.settingsCardOnly);
  }

  function extensionInfoSummary(item) {
    var info = (item && item.extensionInfo) || {};
    var version = info.version || item.version || 'unknown';
    var author = info.author || 'unknown';
    var license = info.license || 'unknown';
    return 'Version: ' + version + ' | Author: ' + author + ' | License: ' + license;
  }

  function extensionDescription(item) {
    var info = (item && item.extensionInfo) || {};
    return info.description || 'No extension description available.';
  }

  function extensionSettingsPage(item) {
    var info = (item && item.extensionInfo) || {};
    return info.settingsPage || item.entry || ('/' + (item.id || '') + '.php');
  }

  function applyListControls(items) {
    var result = Array.isArray(items) ? items.slice() : [];
    var filter = (listFilterEl && listFilterEl.value) || 'all';
    var sort = (listSortEl && listSortEl.value) || 'name';
    var search = ((listSearchEl && listSearchEl.value) || '').trim().toLowerCase();

    if (filter === 'active') {
      result = result.filter(function (item) { return !!item.enabled; });
    } else if (filter === 'inactive') {
      result = result.filter(function (item) { return !item.enabled; });
    }

    if (search) {
      result = result.filter(function (item) {
        var info = item.extensionInfo || {};
        var hay = ((item.name || '') + ' ' + (item.id || '') + ' ' + (item.path || '') + ' ' + (info.description || '') + ' ' + (info.author || '')).toLowerCase();
        return hay.indexOf(search) !== -1;
      });
    }

    if (sort === 'state') {
      result.sort(function (a, b) {
        if (!!a.enabled === !!b.enabled) {
          return String(a.name || a.id).localeCompare(String(b.name || b.id));
        }
        return a.enabled ? -1 : 1;
      });
    } else if (sort === 'visibility') {
      result.sort(function (a, b) {
        var scoreA = (getVisibility(a, 'm') ? 1 : 0) + (getVisibility(a, 'library') ? 1 : 0);
        var scoreB = (getVisibility(b, 'm') ? 1 : 0) + (getVisibility(b, 'library') ? 1 : 0);
        if (scoreA === scoreB) {
          return String(a.name || a.id).localeCompare(String(b.name || b.id));
        }
        return scoreB - scoreA;
      });
    } else {
      result.sort(function (a, b) {
        return String(a.name || a.id).localeCompare(String(b.name || b.id));
      });
    }

    return result;
  }

  function renderSummary(visibleCount, totalCount) {
    if (!listSummaryEl) {
      return;
    }
    listSummaryEl.textContent = 'Showing ' + visibleCount + ' of ' + totalCount + ' extensions.';
  }

  function renderItems(items) {
    listEl.innerHTML = '';

    var filtered = applyListControls(items);
    renderSummary(filtered.length, (items || []).length);

    if (!filtered.length) {
      listEl.textContent = 'No extensions match the current filter.';
      return;
    }

    filtered.forEach(function (item) {
      var row = document.createElement('div');
      row.className = 'list-item';

      var showInM = getVisibility(item, 'm');
      var showInLibrary = getVisibility(item, 'library');

      var left = document.createElement('div');
      var stateClass = item.enabled ? 'active' : 'inactive';
      var stateLabel = item.enabled ? 'active' : 'inactive';
      left.innerHTML =
        '<div class="list-top"><div class="list-name">' + escapeHtml(item.name || item.id || 'Unnamed extension') + '</div><span class="badge ' + stateClass + '">' + stateLabel + '</span></div>' +
        '<div class="list-sub">' + escapeHtml(item.path || '#') + '</div>' +
        '<div class="list-sub">Visibility: ' + escapeHtml(visibilityLabel('m', showInM)) + ' | ' + escapeHtml(visibilityLabel('library', showInLibrary)) + '</div>' +
        '<div class="list-sub">' + escapeHtml(extensionInfoSummary(item)) + '</div>' +
        '<div class="list-sub">' + escapeHtml(extensionDescription(item)) + '</div>';

      if (getSettingsCardOnly(item)) {
        left.innerHTML +=
          '<div class="extmgr-subcard">' +
          '<div class="extmgr-subcard-title">Settings Card Mode</div>' +
          '<div class="extmgr-subcard-body">This extension is handled as a settings-only card in ext-mgr.</div>' +
          '<a class="btn btn-small" href="' + escapeHtml(extensionSettingsPage(item)) + '">Open Settings Page</a>' +
          '</div>';
      }

      var rightWrap = document.createElement('div');
      rightWrap.className = 'item-actions';

      var enableBtn = document.createElement('button');
      enableBtn.type = 'button';
      enableBtn.className = 'btn btn-small';
      enableBtn.textContent = item.enabled ? 'Disable' : 'Enable';
      if (!item.enabled) {
        enableBtn.className += ' btn-primary';
      }
      enableBtn.addEventListener('click', function () {
        if (item.enabled) {
          var ok = window.confirm('Disable ' + (item.name || item.id) + '? This can hide it from menu integrations.');
          if (!ok) {
            return;
          }
        }

        var nextEnabled = item.enabled ? '0' : '1';
        enableBtn.disabled = true;
        api({ action: 'set_enabled', id: item.id, value: nextEnabled })
          .then(function () {
            setStatus('Extension state updated for ' + (item.name || item.id) + '.', 'ok');
            runRefresh();
          })
          .catch(function (err) {
            setStatus(err.message, 'error');
          })
          .finally(function () {
            enableBtn.disabled = false;
          });
      });

      var menuMBtn = document.createElement('button');
      menuMBtn.type = 'button';
      menuMBtn.className = 'btn btn-small';
      applyVisibilityButtonState(menuMBtn, 'm', showInM);
      menuMBtn.addEventListener('click', function () {
        var next = getVisibility(item, 'm') ? '0' : '1';
        menuMBtn.disabled = true;
        api({ action: 'set_menu_visibility', id: item.id, menu: 'm', value: next })
          .then(function () {
            setVisibility(item, 'm', next === '1');
            applyVisibilityButtonState(menuMBtn, 'm', getVisibility(item, 'm'));
            setStatus('M menu visibility updated for ' + (item.name || item.id) + '.', 'ok');
            runRefresh();
          })
          .catch(function (err) {
            setStatus(err.message + (err.message.indexOf('Failed to write registry') !== -1 ? ' Check ext-mgr permissions and restart php-fpm.' : ''), 'error');
          })
          .finally(function () {
            menuMBtn.disabled = false;
          });
      });

      var menuLibraryBtn = document.createElement('button');
      menuLibraryBtn.type = 'button';
      menuLibraryBtn.className = 'btn btn-small';
      applyVisibilityButtonState(menuLibraryBtn, 'library', showInLibrary);
      menuLibraryBtn.addEventListener('click', function () {
        var next = getVisibility(item, 'library') ? '0' : '1';
        menuLibraryBtn.disabled = true;
        api({ action: 'set_menu_visibility', id: item.id, menu: 'library', value: next })
          .then(function () {
            setVisibility(item, 'library', next === '1');
            applyVisibilityButtonState(menuLibraryBtn, 'library', getVisibility(item, 'library'));
            setStatus('Library visibility updated for ' + (item.name || item.id) + '.', 'ok');
            runRefresh();
          })
          .catch(function (err) {
            setStatus(err.message + (err.message.indexOf('Failed to write registry') !== -1 ? ' Check ext-mgr permissions and restart php-fpm.' : ''), 'error');
          })
          .finally(function () {
            menuLibraryBtn.disabled = false;
          });
      });

      var settingsCardBtn = document.createElement('button');
      settingsCardBtn.type = 'button';
      settingsCardBtn.className = 'btn btn-small';
      applySettingsCardButtonState(settingsCardBtn, getSettingsCardOnly(item));
      settingsCardBtn.addEventListener('click', function () {
        var next = getSettingsCardOnly(item) ? '0' : '1';
        settingsCardBtn.disabled = true;
        api({ action: 'set_settings_card_only', id: item.id, value: next })
          .then(function () {
            item.settingsCardOnly = next === '1';
            applySettingsCardButtonState(settingsCardBtn, getSettingsCardOnly(item));
            setStatus('Settings-card mode updated for ' + (item.name || item.id) + '.', 'ok');
            runRefresh();
          })
          .catch(function (err) {
            setStatus(err.message + (err.message.indexOf('Failed to write registry') !== -1 ? ' Check ext-mgr permissions and restart php-fpm.' : ''), 'error');
          })
          .finally(function () {
            settingsCardBtn.disabled = false;
          });
      });

      var repairSymlinkBtn = document.createElement('button');
      repairSymlinkBtn.type = 'button';
      repairSymlinkBtn.className = 'btn btn-small btn-danger';
      repairSymlinkBtn.textContent = 'Repair Symlink';
      repairSymlinkBtn.addEventListener('click', function () {
        repairSymlinkBtn.disabled = true;
        api({ action: 'repair_symlink', id: item.id })
          .then(function (data) {
            var payload = (data && data.data) || {};
            setStatus('Symlink repaired for ' + (item.name || item.id) + ': ' + (payload.linkPath || 'n/a') + ' -> ' + (payload.targetPath || 'n/a'), 'ok');
            runRefresh();
          })
          .catch(function (err) {
            setStatus(err.message, 'error');
          })
          .finally(function () {
            repairSymlinkBtn.disabled = false;
          });
      });

      row.appendChild(left);
      rightWrap.appendChild(enableBtn);
      rightWrap.appendChild(menuMBtn);
      rightWrap.appendChild(menuLibraryBtn);
      rightWrap.appendChild(settingsCardBtn);
      rightWrap.appendChild(repairSymlinkBtn);
      row.appendChild(rightWrap);
      listEl.appendChild(row);
    });
  }

  function clearStatus() {
    if (!statusEl) {
      return;
    }
    statusEl.textContent = '';
    statusEl.classList.remove('error', 'ok');
  }

  function loadStatusAndList(silent) {
    if (!silent) {
      setStatus('Loading manager status...', null);
    }
    return api({ action: 'status' })
      .then(function (data) {
        renderMeta(data.data.meta || {});
        renderHealth(data.data.health || {});
        renderGuidanceDocs(data.data.guidance || {});
        renderAdvancedUpdateControls(providerStatusFromPolicy(data.data.releasePolicy || {}), null, null);
        allItems = (data.data && data.data.extensions) || [];
        renderItems(allItems);
        if (!silent) {
          setStatus('Loaded manager status and ' + allItems.length + ' extension(s).', 'ok');
        }
      })
      .catch(function (err) {
        setStatus(err.message, 'error');
      });
  }

  function runRefresh() {
    setStatus('Refreshing...');
    api({ action: 'refresh' })
      .then(function (data) {
        renderMeta(data.data.meta || {});
        renderHealth(data.data.health || {});
        renderGuidanceDocs(data.data.guidance || {});
        renderAdvancedUpdateControls(providerStatusFromPolicy(data.data.releasePolicy || {}), null, null);
        allItems = (data.data && data.data.extensions) || [];
        renderItems(allItems);
        setStatus('Refresh complete.', 'ok');
      })
      .catch(function (err) {
        setStatus(err.message, 'error');
      });
  }

  function runCheckUpdate() {
    setStatus('Checking update availability...');
    api({ action: 'check_update' })
      .then(function (data) {
        var payload = data.data || {};
        var hasUpdate = !!payload.hasUpdate;
        latestCheckState.hasUpdate = hasUpdate;
        latestCheckState.candidateVersion = payload.candidate && payload.candidate.version ? payload.candidate.version : null;
        latestCheckState.warning = payload.warning || null;
        latestCheckState.integrity = null;

        renderMeta(payload.meta || {});
        renderUpdateStatus(payload.meta || {}, hasUpdate, payload.candidate || null, payload.warning || null, payload.providerStatus || null, null);
        renderAdvancedUpdateControls(payload.providerStatus || null, payload.branchWarning || null, payload.candidate || null);
        setRunUpdateButtonState();

        if (hasUpdate) {
          setStatus('Update check completed. New version available.', 'ok');
        } else if (payload.warning) {
          setStatus('Update check completed with provider warning.', 'error');
        } else {
          setStatus('Update check completed. Already up to date.', 'ok');
        }
      })
      .catch(function (err) {
        latestCheckState.hasUpdate = false;
        setRunUpdateButtonState();
        setStatus(err.message, 'error');
      });
  }

  function runUpdate() {
    setStatus('Running update...');
    api({ action: 'run_update' })
      .then(function (data) {
        var meta = data.data.meta || {};
        renderMeta(meta);
        latestCheckState.hasUpdate = false;
        latestCheckState.warning = null;
        latestCheckState.candidateVersion = null;
        latestCheckState.integrity = data.data.integrity || null;
        setRunUpdateButtonState();
        renderUpdateStatus(meta, false, data.data.candidate || null, null, null, latestCheckState.integrity);
        if (data.data.updated) {
          setStatus('Update applied successfully.', 'ok');
        } else {
          setStatus('No update applied. Already on latest version.', 'ok');
        }
      })
      .catch(function (err) {
        latestCheckState.hasUpdate = true;
        setRunUpdateButtonState();
        setStatus(err.message, 'error');
      });
  }

  function runRepair() {
    setStatus('Running repair...');
    api({ action: 'repair' })
      .then(function (data) {
        renderMeta(data.data.meta || {});
        renderGuidanceDocs(data.data.guidance || {});
        allItems = data.data.extensions || [];
        renderItems(allItems);
        setStatus('Repair completed successfully.', 'ok');
      })
      .catch(function (err) {
        setStatus(err.message, 'error');
      });
  }

  function runRegistrySync() {
    setStatus('Syncing registry with installed extensions...');
    api({ action: 'registry_sync' })
      .then(function (data) {
        var payload = data.data || {};
        var summary = payload.summary || {};
        var state = payload.state || {};

        renderMeta(state.meta || {});
        renderHealth(state.health || {});
        renderGuidanceDocs(state.guidance || {});
        allItems = state.extensions || [];
        renderItems(allItems);

        setStatus(
          'Registry sync complete. total=' + String(summary.total || 0)
            + ' installed=' + String(summary.installed || 0)
            + ' missing=' + String(summary.missing || 0),
          'ok'
        );
      })
      .catch(function (err) {
        setStatus(err.message, 'error');
      });
  }

  if (listFilterEl) {
    listFilterEl.value = readPref('filter', listFilterEl.value || 'all');
    if (!listFilterEl.value) {
      listFilterEl.value = 'all';
    }
  }
  if (listSortEl) {
    listSortEl.value = readPref('sort', listSortEl.value || 'name');
    if (!listSortEl.value) {
      listSortEl.value = 'name';
    }
  }
  if (listSearchEl) {
    listSearchEl.value = readPref('search', '');
  }

  bindIfPresent(refreshBtn, 'click', runRefresh);
  bindIfPresent(checkUpdateBtn, 'click', runCheckUpdate);
  bindIfPresent(runUpdateBtn, 'click', runUpdate);
  bindIfPresent(repairBtn, 'click', runRepair);
  bindIfPresent(syncRegistryBtn, 'click', runRegistrySync);
  bindIfPresent(importExtensionBtn, 'click', function () {
    var files = (importExtensionFileEl && importExtensionFileEl.files) || null;
    if (!files || files.length === 0) {
      setStatus('Select a .zip package first.', 'error');
      setImportWizardNote('Select a .zip package first.', 'error');
      return;
    }

    var file = files[0];
    var fileName = String(file.name || '').toLowerCase();
    if (fileName.slice(-4) !== '.zip') {
      setStatus('Only .zip extension packages are supported.', 'error');
      setImportWizardNote('Only .zip extension packages are supported.', 'error');
      return;
    }

    importExtensionBtn.disabled = true;
    setStatus('Uploading and importing extension package...', null);
    setImportWizardNote('Uploading and importing extension package...', null);

    apiUpload(file)
      .then(function (data) {
        var importedId = ((data || {}).data || {}).extensionId || 'unknown';
        setStatus('Extension imported: ' + importedId, 'ok');
        setImportWizardNote('Extension imported: ' + importedId, 'ok');
        if (importExtensionFileEl) {
          importExtensionFileEl.value = '';
        }
        runRefresh();
      })
      .catch(function (err) {
        setStatus(err.message, 'error');
        setImportWizardNote(err.message, 'error');
      })
      .finally(function () {
        importExtensionBtn.disabled = false;
      });
  });
  bindIfPresent(systemUpdateBtn, 'click', function () {
    setStatus('Syncing extensions metadata...', null);
    api({ action: 'system_update_hook' })
      .then(function (data) {
        var hook = (data.data && data.data.hook) || {};
        var desc = hook.description || 'Extensions sync hook placeholder.';
        setStatus(desc, 'ok');
      })
      .catch(function (err) {
        setStatus(err.message, 'error');
      });
  });

  advancedModeButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var nextMode = btn.getAttribute('data-advanced-mode') || 'main';
      advancedUpdateState.mode = nextMode;
      renderAdvancedModeButtons();
      renderAdvancedSource(mergeWithCurrentProviderStatus({
        provider: currentProviderStatus.provider || 'github',
        repository: currentProviderStatus.repository,
        updateTrack: nextMode === 'custom' ? 'custom' : 'branch',
        channel: nextMode === 'dev' ? 'dev' : 'stable',
        branch: nextMode === 'dev' ? 'dev' : 'main',
        customBaseUrl: advancedCustomUrlEl ? advancedCustomUrlEl.value : ''
      }), null);
    });
  });

  bindIfPresent(advancedCustomUrlEl, 'input', function () {
    advancedUpdateState.customUrl = String(advancedCustomUrlEl.value || '').trim();
    if (advancedUpdateState.mode !== 'custom') {
      return;
    }
    renderAdvancedSource(mergeWithCurrentProviderStatus({
      provider: 'custom',
      repository: '',
      updateTrack: 'custom',
      channel: 'stable',
      branch: 'main',
      customBaseUrl: advancedUpdateState.customUrl
    }), null);
  });

  bindIfPresent(copyAdvancedSourceBtn, 'click', function () {
    if (!advancedSourceLinkEl) {
      return;
    }

    var resolveUrl = advancedSourceLinkEl.getAttribute('data-source-url') || '';
    var rawBase = advancedSourceLinkEl.getAttribute('data-raw-base-url') || '';
    var text = resolveUrl;
    if (rawBase) {
      text += '\nraw base: ' + rawBase;
    }
    if (!text) {
      return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text)
        .then(function () {
          setStatus('Advanced source link copied.', 'ok');
        })
        .catch(function () {
          setStatus('Copy failed. Select and copy manually.', 'error');
        });
      return;
    }

    setStatus('Clipboard API unavailable. Select and copy the source link manually.', 'error');
  });

  bindIfPresent(saveAdvancedUpdateBtn, 'click', function () {
    var mode = advancedUpdateState.mode || 'main';
    var track = mode === 'custom' ? 'custom' : 'branch';
    var channel = mode === 'dev' ? 'dev' : 'stable';
    var branch = mode === 'dev' ? 'dev' : 'main';
    var customUrl = advancedCustomUrlEl ? String(advancedCustomUrlEl.value || '').trim() : '';

    if (mode === 'custom' && !customUrl) {
      setStatus('Enter a custom URL before saving custom mode.', 'error');
      return;
    }

    if (saveAdvancedUpdateBtn) {
      saveAdvancedUpdateBtn.disabled = true;
    }

    setStatus('Saving advanced update settings...', null);
    api({ action: 'set_update_advanced', track: track, channel: channel, branch: branch, custom_url: customUrl })
      .then(function (data) {
        var policy = (data && data.data && data.data.releasePolicy) || null;
        renderAdvancedUpdateControls({
          provider: policy && policy.provider,
          repository: policy && policy.repository,
          updateTrack: policy && policy.updateTrack,
          channel: policy && policy.channel,
          branch: policy && policy.branch,
          customBaseUrl: policy && policy.customBaseUrl,
          availableBranches: policy && policy.availableBranches
        }, null, null);
        setStatus('Advanced update settings saved.', 'ok');
        runCheckUpdate();
      })
      .catch(function (err) {
        setStatus(err.message, 'error');
      })
      .finally(function () {
        if (saveAdvancedUpdateBtn) {
          saveAdvancedUpdateBtn.disabled = false;
        }
      });
  });

  bindIfPresent(listFilterEl, 'change', function () {
    writePref('filter', listFilterEl.value);
    renderItems(allItems);
  });
  bindIfPresent(listSortEl, 'change', function () {
    writePref('sort', listSortEl.value);
    renderItems(allItems);
  });
  bindIfPresent(listSearchEl, 'input', function () {
    writePref('search', listSearchEl.value);
    renderItems(allItems);
  });

  menuToggleButtons.forEach(function (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      var section = toggleBtn.closest('.extmgr-section');
      if (!section) {
        return;
      }
      var isOpen = section.classList.toggle('is-open');
      toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  });

  submenuToggleButtons.forEach(function (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      var submenu = toggleBtn.closest('.extmgr-submenu');
      if (!submenu) {
        return;
      }
      var isOpen = submenu.classList.toggle('is-open');
      toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  });

  document.querySelectorAll('.extmgr-section').forEach(function (section) {
    var sectionToggle = section.querySelector('[data-menu-toggle]');
    if (!sectionToggle) {
      return;
    }
    sectionToggle.setAttribute('aria-expanded', section.classList.contains('is-open') ? 'true' : 'false');
  });

  document.querySelectorAll('.extmgr-submenu').forEach(function (submenu) {
    var submenuToggle = submenu.querySelector('[data-submenu-toggle]');
    if (!submenuToggle) {
      return;
    }
    submenuToggle.setAttribute('aria-expanded', submenu.classList.contains('is-open') ? 'true' : 'false');
  });

  initConfigureModalFix();

  loadStatusAndList(true).then(function () {
    setRunUpdateButtonState();
    clearStatus();
  });
})();
