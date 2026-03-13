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

  var metaVersionEl = document.getElementById('meta-version');
  var metaCreatorEl = document.getElementById('meta-creator');
  var metaLicenseEl = document.getElementById('meta-license');
  var updateNoteEl = document.getElementById('update-note');
  var advancedTrackEl = document.getElementById('advanced-track');
  var advancedChannelEl = document.getElementById('advanced-channel');
  var advancedBranchEl = document.getElementById('advanced-branch');
  var advancedSourceLinkEl = document.getElementById('advanced-source-link');
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
  var listFilterEl = document.getElementById('list-filter');
  var listSortEl = document.getElementById('list-sort');
  var listSearchEl = document.getElementById('list-search');
  var listSummaryEl = document.getElementById('list-summary');

  var allItems = [];
  var PREF_PREFIX = 'extmgr.list.';
  var latestCheckState = {
    hasUpdate: false,
    candidateVersion: null,
    warning: null,
    integrity: null
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
  }

  function providerStatusFromPolicy(policy) {
    var p = policy || {};
    return {
      provider: p.provider || 'github',
      repository: p.repository || 'rubatron/Moode-Extensions-Manager',
      updateTrack: p.updateTrack || 'channel',
      channel: p.channel || 'stable',
      branch: p.branch || 'main',
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
    advancedSourceLinkEl.href = resolveUrl || '#';
    advancedSourceLinkEl.setAttribute('data-source-url', resolveUrl || '');
    advancedSourceLinkEl.setAttribute('data-raw-base-url', rawBase || '');
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

  function ensureBranchOption(value) {
    if (!advancedBranchEl || !value) {
      return;
    }
    var exists = Array.prototype.some.call(advancedBranchEl.options, function (opt) { return opt.value === value; });
    if (!exists) {
      var option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      advancedBranchEl.appendChild(option);
    }
  }

  function renderAdvancedUpdateControls(providerStatus, payloadWarning, candidate) {
    if (!advancedTrackEl || !advancedChannelEl || !advancedBranchEl) {
      return;
    }

    var track = (providerStatus && providerStatus.updateTrack) || 'channel';
    var channel = (providerStatus && providerStatus.channel) || 'stable';
    var branch = (providerStatus && providerStatus.branch) || 'main';
    var branches = ['main', 'dev'];

    advancedBranchEl.innerHTML = '';
    branches.forEach(function (name) {
      if (!name) {
        return;
      }
      var option = document.createElement('option');
      option.value = String(name);
      option.textContent = String(name);
      advancedBranchEl.appendChild(option);
    });

    ensureBranchOption('main');
    ensureBranchOption('dev');
    ensureBranchOption(branch);

    advancedTrackEl.value = track;
    advancedChannelEl.value = channel;
    advancedBranchEl.value = branch;
    advancedBranchEl.disabled = track !== 'branch';
    renderAdvancedSource(providerStatus || {}, candidate || null);

    if (advancedUpdateNoteEl) {
      advancedUpdateNoteEl.textContent = payloadWarning
        ? ('Branch discovery warning: ' + payloadWarning + '. Using stored branch list.')
        : ('Branch mode limited to main/dev. Active track=' + track + '.');
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

  function loadStatusAndList() {
    setStatus('Loading manager status...', null);
    return api({ action: 'status' })
      .then(function (data) {
        renderMeta(data.data.meta || {});
        renderHealth(data.data.health || {});
        renderAdvancedUpdateControls(providerStatusFromPolicy(data.data.releasePolicy || {}), null, null);
        allItems = (data.data && data.data.extensions) || [];
        renderItems(allItems);
        setStatus('Loaded manager status and ' + allItems.length + ' extension(s).', 'ok');
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
  bindIfPresent(systemUpdateBtn, 'click', function () {
    setStatus('Opening System Settings update hook...', null);
    api({ action: 'system_update_hook' })
      .then(function (data) {
        var hook = (data.data && data.data.hook) || {};
        var desc = hook.description || 'System Settings hook placeholder.';
        setStatus(desc, 'ok');
      })
      .catch(function (err) {
        setStatus(err.message, 'error');
      });
  });

  bindIfPresent(advancedTrackEl, 'change', function () {
    if (!advancedBranchEl) {
      return;
    }
    advancedBranchEl.disabled = advancedTrackEl.value !== 'branch';
    renderAdvancedSource({
      provider: 'github',
      repository: 'rubatron/Moode-Extensions-Manager',
      updateTrack: advancedTrackEl.value,
      channel: (advancedChannelEl && advancedChannelEl.value) || 'stable',
      branch: (advancedBranchEl && advancedBranchEl.value) || 'main'
    }, null);
  });

  bindIfPresent(advancedChannelEl, 'change', function () {
    renderAdvancedSource({
      provider: 'github',
      repository: 'rubatron/Moode-Extensions-Manager',
      updateTrack: (advancedTrackEl && advancedTrackEl.value) || 'channel',
      channel: advancedChannelEl.value || 'stable',
      branch: (advancedBranchEl && advancedBranchEl.value) || 'main'
    }, null);
  });

  bindIfPresent(advancedBranchEl, 'change', function () {
    renderAdvancedSource({
      provider: 'github',
      repository: 'rubatron/Moode-Extensions-Manager',
      updateTrack: (advancedTrackEl && advancedTrackEl.value) || 'channel',
      channel: (advancedChannelEl && advancedChannelEl.value) || 'stable',
      branch: advancedBranchEl.value || 'main'
    }, null);
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
    var track = (advancedTrackEl && advancedTrackEl.value) || 'channel';
    var channel = (advancedChannelEl && advancedChannelEl.value) || 'stable';
    var branch = (advancedBranchEl && advancedBranchEl.value) || 'main';

    if (saveAdvancedUpdateBtn) {
      saveAdvancedUpdateBtn.disabled = true;
    }

    setStatus('Saving advanced update settings...', null);
    api({ action: 'set_update_advanced', track: track, channel: channel, branch: branch })
      .then(function (data) {
        var policy = (data && data.data && data.data.releasePolicy) || null;
        renderAdvancedUpdateControls({
          provider: policy && policy.provider,
          repository: policy && policy.repository,
          updateTrack: policy && policy.updateTrack,
          channel: policy && policy.channel,
          branch: policy && policy.branch,
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

  document.querySelectorAll('.extmgr-collapse').forEach(function (detailsEl) {
    var summary = detailsEl.querySelector('summary');
    if (!summary) {
      return;
    }
    summary.addEventListener('click', function (evt) {
      evt.preventDefault();
      detailsEl.open = !detailsEl.open;
    });
  });

  loadStatusAndList().then(function () {
    setRunUpdateButtonState();
    setStatus('Ready. Click Check Update when needed.', 'ok');
  });
})();
