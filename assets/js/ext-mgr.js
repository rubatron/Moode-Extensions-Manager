(function () {
  'use strict';

  var init = window.__EXT_MGR_INIT__ || {};
  var apiUrl = init.apiUrl || '/ext-mgr-api.php';
  var apiUrls = Array.isArray(init.apiUrls) ? init.apiUrls.slice() : [apiUrl, '/extensions/sys/ext-mgr-api.php'];
  var extMgrLogsModule = window.ExtMgrLogs || null;
  var tooltipUrl = init.tooltipUrl || '/extensions/sys/assets/data/ext-mgr-tooltips.json';
  var tooltipMap = {};

  var statusEl = document.getElementById('status');
  var listEl = document.getElementById('list');
  var apiServiceEl = document.getElementById('api-service');
  var registryHealthEl = document.getElementById('registry-health');
  var extensionCountEl = document.getElementById('extension-count');
  var activeCountEl = document.getElementById('active-count');
  var inactiveCountEl = document.getElementById('inactive-count');
  var mVisibleCountEl = document.getElementById('m-visible-count');
  var libraryVisibleCountEl = document.getElementById('library-visible-count');
  var systemVisibleCountEl = document.getElementById('system-visible-count');
  var settingsCardCountEl = document.getElementById('settings-card-count');
  var serviceMemPctEl = document.getElementById('service-mem-pct');
  var resourceCpuUsageEl = document.getElementById('resource-cpu-usage');
  var resourceLoadAvgEl = document.getElementById('resource-load-avg');
  var resourceMemoryUsedEl = document.getElementById('resource-memory-used');
  var resourceMemoryAvailableEl = document.getElementById('resource-memory-available');
  var resourceDiskRootEl = document.getElementById('resource-disk-root');
  var resourceDiskExtensionsEl = document.getElementById('resource-disk-extensions');
  var resourceExtmgrMemEl = document.getElementById('resource-extmgr-mem');
  var resourceExtensionsMemEl = document.getElementById('resource-extensions-mem');
  var resourceExtensionsStorageEl = document.getElementById('resource-extensions-storage');
  var resourceExtensionTopEl = document.getElementById('resource-extension-top');
  var resourceRequirementsNoteEl = document.getElementById('resource-requirements-note');
  var cacheDirPathEl = document.getElementById('cache-dir-path');
  var cacheDirUsageEl = document.getElementById('cache-dir-usage');
  var backupDirPathEl = document.getElementById('backup-dir-path');
  var backupDirCountEl = document.getElementById('backup-dir-count');
  var backupLatestEl = document.getElementById('backup-latest');
  var maintenanceStorageNoteEl = document.getElementById('maintenance-storage-note');
  var managerVisibilityHeaderBtn = document.getElementById('manager-visibility-header-btn');
  var managerVisibilityLibraryBtn = document.getElementById('manager-visibility-library-btn');
  var managerVisibilityMBtn = document.getElementById('manager-visibility-m-btn');
  var managerVisibilitySystemBtn = document.getElementById('manager-visibility-system-btn');
  var managerVisibilityHeaderStateEl = document.getElementById('manager-visibility-header-state');
  var managerVisibilityLibraryStateEl = document.getElementById('manager-visibility-library-state');
  var managerVisibilityMStateEl = document.getElementById('manager-visibility-m-state');
  var managerVisibilitySystemStateEl = document.getElementById('manager-visibility-system-state');
  var managerVisibilityNoteEl = document.getElementById('manager-visibility-note');

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
  var refreshResourcesBtn = document.getElementById('refresh-resources-btn');
  var createBackupBtn = document.getElementById('create-backup-btn');
  var clearCacheBtn = document.getElementById('clear-cache-btn');
  var syncRegistryBtn = document.getElementById('sync-registry-btn');
  var importExtensionFileEl = document.getElementById('import-extension-file');
  var importExtensionFileNameEl = document.getElementById('import-extension-file-name');
  var importExtensionDryRunEl = document.getElementById('import-extension-dry-run');
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
  var managerVisibilityState = {
    header: true,
    library: true,
    m: true,
    system: true
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
    if (!statusEl) {
      return;
    }

    // User preference: suppress success/info toast-style status noise.
    if (kind === 'ok') {
      statusEl.textContent = '';
      statusEl.classList.remove('error', 'ok');
      return;
    }

    statusEl.textContent = text;
    statusEl.classList.remove('error', 'ok');
    if (kind) {
      statusEl.classList.add(kind);
    }
  }

  function api(params) {
    var deduped = [];
    apiUrls.forEach(function (candidate) {
      var url = String(candidate || '').trim();
      if (!url || deduped.indexOf(url) !== -1) {
        return;
      }
      deduped.push(url);
    });
    if (deduped.length === 0) {
      deduped.push(apiUrl);
    }

    var body = new URLSearchParams(params).toString();
    var lastErr = null;

    function tryAt(idx) {
      if (idx >= deduped.length) {
        throw (lastErr || new Error('Request failed'));
      }

      return fetch(deduped[idx], {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body
      }).then(function (res) {
        return res.json().catch(function () {
          return { ok: false, error: 'Invalid JSON response from API endpoint.' };
        }).then(function (data) {
          if (res.ok && data.ok) {
            return data;
          }

          var err = new Error((data && data.error) || ('Request failed (' + res.status + ')'));
          err.status = res.status;
          lastErr = err;

          if (res.status === 404 || res.status === 0) {
            return tryAt(idx + 1);
          }
          throw err;
        });
      }).catch(function (err) {
        lastErr = err;
        return tryAt(idx + 1);
      });
    }

    return tryAt(0);
  }

  function tip(key, fallback) {
    if (tooltipMap && Object.prototype.hasOwnProperty.call(tooltipMap, key)) {
      return String(tooltipMap[key] || '');
    }
    return String(fallback || '');
  }

  function applyTip(el, key, fallback) {
    if (!el) {
      return;
    }
    var text = tip(key, fallback);
    if (text) {
      el.title = text;
    } else {
      el.removeAttribute('title');
    }
  }

  function loadTooltipSnippets() {
    var urls = [tooltipUrl, '/extensions/sys/assets/data/ext-mgr-tooltips.json'];
    var deduped = [];
    urls.forEach(function (candidate) {
      var url = String(candidate || '').trim();
      if (!url || deduped.indexOf(url) !== -1) {
        return;
      }
      deduped.push(url);
    });

    var idx = 0;
    function next() {
      if (idx >= deduped.length) {
        return Promise.resolve({});
      }
      var url = deduped[idx];
      idx += 1;
      return fetch(url, { cache: 'no-store' })
        .then(function (res) {
          if (!res.ok) {
            return next();
          }
          return res.json().catch(function () { return {}; });
        })
        .then(function (data) {
          if (data && typeof data === 'object' && !Array.isArray(data)) {
            tooltipMap = data;
            return data;
          }
          return next();
        })
        .catch(function () {
          return next();
        });
    }

    return next();
  }

  function bindIfPresent(el, eventName, handler) {
    if (!el) {
      return;
    }
    el.addEventListener(eventName, handler);
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

  function apiUpload(file, dryRun) {
    var formData = new FormData();
    formData.append('action', 'import_extension_upload');
    formData.append('package', file);
    formData.append('dry_run', dryRun ? '1' : '0');

    var deduped = [];
    apiUrls.forEach(function (candidate) {
      var url = String(candidate || '').trim();
      if (!url || deduped.indexOf(url) !== -1) {
        return;
      }
      deduped.push(url);
    });
    if (deduped.length === 0) {
      deduped.push(apiUrl);
    }

    var lastErr = null;
    function tryAt(idx) {
      if (idx >= deduped.length) {
        throw (lastErr || new Error('Upload failed'));
      }
      return fetch(deduped[idx], {
        method: 'POST',
        body: formData
      }).then(function (res) {
        return res.json().catch(function () {
          return { ok: false, error: 'Invalid JSON response from API endpoint.' };
        }).then(function (data) {
          if (res.ok && data.ok) {
            return data;
          }
          var err = new Error((data && data.error) || 'Upload failed');
          err.status = res.status;
          lastErr = err;
          if (res.status === 404 || res.status === 0) {
            return tryAt(idx + 1);
          }
          throw err;
        });
      }).catch(function (err) {
        lastErr = err;
        return tryAt(idx + 1);
      });
    }

    return tryAt(0);
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

  function asMiB(value) {
    if (typeof value !== 'number' || !isFinite(value)) {
      return 'n/a';
    }
    return value.toFixed(2) + ' MiB';
  }

  function asPercent(value) {
    if (typeof value !== 'number' || !isFinite(value)) {
      return 'n/a';
    }
    return value.toFixed(2) + '%';
  }

  function asBytes(value) {
    if (typeof value !== 'number' || !isFinite(value) || value < 0) {
      return 'n/a';
    }
    var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    var size = value;
    var idx = 0;
    while (size >= 1024 && idx < units.length - 1) {
      size /= 1024;
      idx += 1;
    }
    return size.toFixed(idx === 0 ? 0 : 2) + ' ' + units[idx];
  }

  function managerVisibilityAreaName(area) {
    return area === 'header' ? 'Header tab'
      : area === 'library' ? 'Library menu'
      : area === 'm' ? 'M menu'
      : 'System context';
  }

  function managerVisibilityLabel(area, visible) {
    return managerVisibilityAreaName(area) + ': ' + (visible ? 'Visible' : 'Hidden');
  }

    function applyMoodeToggleState(toggleEl, visible) {
      if (!toggleEl) { return; }
      if (visible) {
        toggleEl.classList.remove('toggle-off');
      } else {
        toggleEl.classList.add('toggle-off');
      }
      var onRadio = toggleEl.querySelector('input[value="On"]');
      var offRadio = toggleEl.querySelector('input[value="Off"]');
      if (onRadio) { onRadio.checked = !!visible; }
      if (offRadio) { offRadio.checked = !visible; }
    }

    function createMoodeToggle(id, initialVisible, onChange) {
      var div = document.createElement('div');
      div.className = 'toggle' + (initialVisible ? '' : ' toggle-off');

      var onLabel = document.createElement('label');
      onLabel.className = 'toggle-radio';
      onLabel.setAttribute('for', id + '-off');
      onLabel.textContent = 'ON';

      var onRadio = document.createElement('input');
      onRadio.type = 'radio';
      onRadio.name = id;
      onRadio.id = id + '-on';
      onRadio.value = 'On';
      onRadio.checked = !!initialVisible;

      var offLabel = document.createElement('label');
      offLabel.className = 'toggle-radio';
      offLabel.setAttribute('for', id + '-on');
      offLabel.textContent = 'OFF';

      var offRadio = document.createElement('input');
      offRadio.type = 'radio';
      offRadio.name = id;
      offRadio.id = id + '-off';
      offRadio.value = 'Off';
      offRadio.checked = !initialVisible;

      onRadio.addEventListener('change', function () {
        if (onRadio.checked) {
          div.classList.remove('toggle-off');
          if (typeof onChange === 'function') { onChange(true); }
        }
      });
      offRadio.addEventListener('change', function () {
        if (offRadio.checked) {
          div.classList.add('toggle-off');
          if (typeof onChange === 'function') { onChange(false); }
        }
      });

      div.appendChild(onLabel);
      div.appendChild(onRadio);
      div.appendChild(offLabel);
      div.appendChild(offRadio);

      div.setVisible = function (v) { applyMoodeToggleState(div, v); };
      div.setDisabled = function (d) {
        onRadio.disabled = d;
        offRadio.disabled = d;
        div.style.opacity = d ? '0.55' : '';
        div.style.pointerEvents = d ? 'none' : '';
      };
      return div;
    }

    function applyManagerVisibilityButtonState(toggleEl, stateEl, area, visible) {
      if (!toggleEl) { return; }
      applyMoodeToggleState(toggleEl, visible);
      toggleEl.title = managerVisibilityLabel(area, visible);
      if (stateEl) {
        stateEl.textContent = visible ? 'Visible' : 'Hidden';
      }
    }

  function renderManagerVisibility(visibility) {
    var v = visibility || {};
    managerVisibilityState.header = v.header !== false;
    managerVisibilityState.library = v.library !== false;
    managerVisibilityState.m = v.m !== false;
    managerVisibilityState.system = v.system !== false;

    applyManagerVisibilityButtonState(managerVisibilityHeaderBtn, managerVisibilityHeaderStateEl, 'header', managerVisibilityState.header);
    applyManagerVisibilityButtonState(managerVisibilityLibraryBtn, managerVisibilityLibraryStateEl, 'library', managerVisibilityState.library);
    applyManagerVisibilityButtonState(managerVisibilityMBtn, managerVisibilityMStateEl, 'm', managerVisibilityState.m);
    applyManagerVisibilityButtonState(managerVisibilitySystemBtn, managerVisibilitySystemStateEl, 'system', managerVisibilityState.system);

    applyTip(managerVisibilityHeaderBtn, 'manager.visibility.header');
    applyTip(managerVisibilityLibraryBtn, 'manager.visibility.library');
    applyTip(managerVisibilityMBtn, 'manager.visibility.m');
    applyTip(managerVisibilitySystemBtn, 'manager.visibility.system');
  }

  function renderMaintenanceStatus(maintenance) {
    var m = maintenance || {};
    var cache = m.cache || {};
    var backup = m.backup || {};

    setText(cacheDirPathEl, cache.path || '/var/www/extensions/cache');
    setText(cacheDirUsageEl, asBytes(cache.bytes || 0) + ' in ' + String(cache.fileCount || 0) + ' files');
    setText(backupDirPathEl, backup.path || '/var/www/extensions/sys/backup');
    setText(backupDirCountEl, String(backup.snapshotCount || 0));
    setText(backupLatestEl, backup.latest || 'none');
  }

  function renderSystemResources(resources) {
    var r = resources || {};
    var cpu = r.cpu || {};
    var load = r.load || {};
    var memory = r.memory || {};
    var disk = r.disk || {};
    var extmgr = r.extMgr || {};
    var extensions = r.extensions || {};
    var runtime = extensions.runtimeMemory || {};
    var top = Array.isArray(runtime.topConsumers) ? runtime.topConsumers : [];

    setText(resourceCpuUsageEl, asPercent(cpu.usagePct));
    setText(resourceLoadAvgEl, (typeof load.one === 'number' ? load.one.toFixed(2) : 'n/a') + ' / ' + (typeof load.five === 'number' ? load.five.toFixed(2) : 'n/a') + ' / ' + (typeof load.fifteen === 'number' ? load.fifteen.toFixed(2) : 'n/a'));
    setText(resourceMemoryUsedEl, asMiB(memory.usedMiB) + ' (' + asPercent(memory.usedPct) + ')');
    setText(resourceMemoryAvailableEl, asMiB(memory.availableMiB));

    var rootDisk = disk.root || {};
    var extDisk = disk.extensions || {};
    setText(resourceDiskRootEl, asBytes(rootDisk.usedBytes) + ' / ' + asBytes(rootDisk.totalBytes) + ' (' + asPercent(rootDisk.usedPct) + ')');
    setText(resourceDiskExtensionsEl, asBytes(extDisk.usedBytes) + ' / ' + asBytes(extDisk.totalBytes) + ' (' + asPercent(extDisk.usedPct) + ')');

    setText(resourceExtmgrMemEl, asMiB(extmgr.memoryMiB) + ' (' + asPercent(extmgr.memoryPctOfSystem) + ')');
    setText(resourceExtensionsMemEl, asMiB(runtime.totalMiB) + ' via ' + String(runtime.method || 'n/a'));

    var storage = extensions.storage || {};
    setText(resourceExtensionsStorageEl, asBytes(storage.totalBytes) + ' across ' + String(storage.extensionCount || 0) + ' extension(s)');

    if (resourceExtensionTopEl) {
      if (top.length === 0) {
        resourceExtensionTopEl.textContent = 'Top extension memory consumers: no runtime processes detected.';
      } else {
        resourceExtensionTopEl.textContent = 'Top extension memory consumers: ' + top.map(function (row) {
          return String(row.id || 'unknown') + ' (' + asMiB(row.memoryMiB) + ')';
        }).join(', ');
      }
    }

    if (resourceRequirementsNoteEl) {
      var req = runtime.requirements || [];
      resourceRequirementsNoteEl.textContent = req.length
        ? ('Requirements for accurate per-extension memory: ' + req.join(' | '))
        : 'Runtime process matching is active.';
    }
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
    renderManagerVisibility(meta.managerVisibility || {});
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
    setText(systemVisibleCountEl, String(health.systemVisibleCount || 0));
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
    var sourceUrl = resolveUrl || rawBase || '';
    advancedSourceLinkEl.setAttribute('data-source-url', sourceUrl);
    advancedSourceLinkEl.setAttribute('data-raw-base-url', rawBase || '');

    if (openAdvancedSourceBtn) {
      openAdvancedSourceBtn.href = sourceUrl || '#';
      openAdvancedSourceBtn.setAttribute('aria-disabled', sourceUrl ? 'false' : 'true');
      openAdvancedSourceBtn.tabIndex = sourceUrl ? 0 : -1;
    }
  }

  function fallbackCopyText(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', 'readonly');
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();

    var copied = false;
    try {
      copied = document.execCommand('copy');
    } catch (e) {
      copied = false;
    }

    document.body.removeChild(ta);
    return copied;
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
    if ((status.updateTrack || '') === 'channel') {
      return 'release';
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
    } else if (advancedUpdateState.mode === 'release') {
      previewStatus = mergeWithCurrentProviderStatus({
        provider: (providerStatus && providerStatus.provider) || currentProviderStatus.provider || 'github',
        repository: (providerStatus && providerStatus.repository) || currentProviderStatus.repository,
        updateTrack: 'channel',
        channel: 'stable',
        branch: 'main',
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
        : advancedUpdateState.mode === 'dev'
          ? 'dev branch'
          : advancedUpdateState.mode === 'release'
            ? 'release'
            : 'main';
      advancedUpdateNoteEl.textContent = payloadWarning
        ? ('Branch discovery warning: ' + payloadWarning + '. Using stored branch list.')
        : ('Modes: release, main, dev branch, custom URL. Active mode=' + activeModeLabel + '.');
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
    if (!Object.prototype.hasOwnProperty.call(visibility, key)) {
      return true;
    }
    return !!visibility[key];
  }

  function setVisibility(item, key, value) {
    if (!item.menuVisibility || typeof item.menuVisibility !== 'object') {
      item.menuVisibility = { m: true, library: true, system: false };
    }
    item.menuVisibility[key] = !!value;
  }

  function visibilityLabel(target, visible) {
    var name = target === 'm' ? 'M menu' : (target === 'library' ? 'Library menu' : 'System context');
    return name + ': ' + (visible ? 'Visible' : 'Hidden');
  }

  function settingsCardLabel(enabled) {
    return 'Settings Card: ' + (enabled ? 'Enabled' : 'Disabled');
  }

  function applyVisibilityButtonState(button, target, visible, stateEl) {
  function createInlineSwitchControl(labelText, toggleEl) {
    var wrap = document.createElement('div');
    wrap.className = 'extmgr-manager-visibility-row extmgr-manager-visibility-row-inline';

    var label = document.createElement('span');
    label.className = 'extmgr-manager-visibility-label';
    if (labelText === 'M menu') {
      label.classList.add('extmgr-manager-visibility-label-m');

      var mBadge = document.createElement('span');
      mBadge.className = 'extmgr-mbrand-badge';
      mBadge.setAttribute('aria-hidden', 'true');
      mBadge.textContent = 'm';

      var mText = document.createElement('span');
      mText.className = 'extmgr-mbrand-text';
      mText.textContent = 'M menu';

      label.appendChild(mBadge);
      label.appendChild(mText);
    } else {
      label.textContent = labelText;
    }

    var control = document.createElement('div');
    control.className = 'extmgr-manager-visibility-control';

    control.appendChild(toggleEl);
    wrap.appendChild(label);
    wrap.appendChild(control);
    return wrap;
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
        var scoreA = (getVisibility(a, 'm') ? 1 : 0) + (getVisibility(a, 'library') ? 1 : 0) + (getSettingsCardOnly(a) ? 1 : 0);
        var scoreB = (getVisibility(b, 'm') ? 1 : 0) + (getVisibility(b, 'library') ? 1 : 0) + (getSettingsCardOnly(b) ? 1 : 0);
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
      var showSettingsCard = getSettingsCardOnly(item);

      var left = document.createElement('div');
      var stateClass = item.enabled ? 'active' : 'inactive';
      var stateLabel = item.enabled ? 'active' : 'inactive';
      left.innerHTML =
        '<div class="list-top"><div class="list-name">' + escapeHtml(item.name || item.id || 'Unnamed extension') + '</div><span class="badge ' + stateClass + '">' + stateLabel + '</span></div>' +
        '<div class="list-sub">' + escapeHtml(item.path || '#') + '</div>' +
        '<div class="list-sub">Placement: ' + escapeHtml(visibilityLabel('m', showInM)) + ' | ' + escapeHtml(visibilityLabel('library', showInLibrary)) + ' | ' + escapeHtml(settingsCardLabel(showSettingsCard)) + '</div>' +
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

      var toggleGroup = document.createElement('div');
      toggleGroup.className = 'item-toggle-group';

      var actionGroup = document.createElement('div');
      actionGroup.className = 'item-action-group';

      var enableBtn = document.createElement('button');
      enableBtn.type = 'button';
      enableBtn.className = 'btn btn-small' + (item.enabled ? '' : ' btn-primary');
      enableBtn.textContent = item.enabled ? 'Disable' : 'Enable';
      applyTip(enableBtn, item.enabled ? 'extension.disable' : 'extension.enable');
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
            item.enabled = (nextEnabled === '1');
            item.state = item.enabled ? 'active' : 'inactive';
            if (item.enabled) {
              setVisibility(item, 'm', true);
              setVisibility(item, 'library', true);
              setVisibility(item, 'system', false);
            } else {
              setVisibility(item, 'm', false);
              setVisibility(item, 'library', false);
              setVisibility(item, 'system', false);
              item.settingsCardOnly = false;
            }
            enableBtn.textContent = item.enabled ? 'Disable' : 'Enable';
            enableBtn.className = 'btn btn-small' + (item.enabled ? '' : ' btn-primary');
            applyTip(enableBtn, item.enabled ? 'extension.disable' : 'extension.enable');

            menuMBtn.setVisible(getVisibility(item, 'm'));
            menuLibraryBtn.setVisible(getVisibility(item, 'library'));
            settingsCardBtn.setVisible(getSettingsCardOnly(item));
            applyExtensionActionState(item.enabled);
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

        var menuMBtn = createMoodeToggle('extmgr-tgl-m-' + item.id, showInM, function (newVisible) {
          menuMBtn.setDisabled(true);
          api({ action: 'set_menu_visibility', id: item.id, menu: 'm', value: newVisible ? '1' : '0' })
            .then(function () {
              setVisibility(item, 'm', newVisible);
              setStatus('M menu visibility updated for ' + (item.name || item.id) + '.', 'ok');
              runRefresh();
              reloadPageSoon();
            })
            .catch(function (err) {
              menuMBtn.setVisible(!newVisible);
              setStatus(err.message + (err.message.indexOf('Failed to write registry') !== -1 ? ' Check ext-mgr permissions and restart php-fpm.' : ''), 'error');
            })
            .finally(function () { menuMBtn.setDisabled(false); });
        });
        applyTip(menuMBtn, 'extension.menu.m');

        var menuLibraryBtn = createMoodeToggle('extmgr-tgl-lib-' + item.id, showInLibrary, function (newVisible) {
          menuLibraryBtn.setDisabled(true);
          api({ action: 'set_menu_visibility', id: item.id, menu: 'library', value: newVisible ? '1' : '0' })
            .then(function () {
              setVisibility(item, 'library', newVisible);
              setStatus('Library visibility updated for ' + (item.name || item.id) + '.', 'ok');
              runRefresh();
              reloadPageSoon();
            })
            .catch(function (err) {
              menuLibraryBtn.setVisible(!newVisible);
              setStatus(err.message + (err.message.indexOf('Failed to write registry') !== -1 ? ' Check ext-mgr permissions and restart php-fpm.' : ''), 'error');
            })
            .finally(function () { menuLibraryBtn.setDisabled(false); });
        });
        applyTip(menuLibraryBtn, 'extension.menu.library');

        var settingsCardBtn = createMoodeToggle('extmgr-tgl-sc-' + item.id, getSettingsCardOnly(item), function (newEnabled) {
          settingsCardBtn.setDisabled(true);
          api({ action: 'set_settings_card_only', id: item.id, value: newEnabled ? '1' : '0' })
            .then(function () {
              item.settingsCardOnly = newEnabled;
              setStatus('Settings-card mode updated for ' + (item.name || item.id) + '.', 'ok');
              runRefresh();
              reloadPageSoon();
            })
            .catch(function (err) {
              settingsCardBtn.setVisible(!newEnabled);
              setStatus(err.message + (err.message.indexOf('Failed to write registry') !== -1 ? ' Check ext-mgr permissions and restart php-fpm.' : ''), 'error');
            })
            .finally(function () { settingsCardBtn.setDisabled(false); });
        });
        applyTip(settingsCardBtn, 'extension.settingsCard');

        var menuMControl = createInlineSwitchControl('M menu', menuMBtn);
        var menuLibraryControl = createInlineSwitchControl('Library menu', menuLibraryBtn);
        var settingsCardControl = createInlineSwitchControl('Settings card', settingsCardBtn);

      var repairSymlinkBtn = document.createElement('button');
      repairSymlinkBtn.type = 'button';
      repairSymlinkBtn.className = 'btn btn-small extmgr-destructive';
      repairSymlinkBtn.textContent = 'Repair Symlink';
      applyTip(repairSymlinkBtn, 'extension.repair');
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

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'btn btn-small extmgr-destructive';
      removeBtn.textContent = 'Remove Extension';
      applyTip(removeBtn, 'extension.remove');
      removeBtn.addEventListener('click', function () {
        var label = item.name || item.id;
        var ok = window.confirm('Remove extension "' + label + '"?\n\nThis removes its installed files and route. A backup is kept in ext-mgr backup/removed-extensions.');
        if (!ok) {
          return;
        }

        removeBtn.disabled = true;
        api({ action: 'remove_extension', id: item.id })
          .then(function (data) {
            var payload = (data && data.data) || {};
            setStatus('Removed ' + label + '. Backup: ' + (payload.backupPath || 'n/a'), 'ok');
            runRefresh();
            reloadPageSoon();
          })
          .catch(function (err) {
            setStatus(err.message, 'error');
          })
          .finally(function () {
            removeBtn.disabled = false;
          });
      });

      function applyExtensionActionState(enabled) {
        var disabled = !enabled;
          menuMBtn.setDisabled(disabled);
          menuLibraryBtn.setDisabled(disabled);
          settingsCardBtn.setDisabled(disabled);
        repairSymlinkBtn.disabled = disabled;

        // Remove can stay available for cleanup even when extension is inactive.
        removeBtn.disabled = false;

        // Inactive extension: hide action controls from the row to keep state unambiguous.
        menuMControl.style.display = disabled ? 'none' : '';
        menuLibraryControl.style.display = disabled ? 'none' : '';
        settingsCardControl.style.display = disabled ? 'none' : '';
        repairSymlinkBtn.style.display = disabled ? 'none' : '';

        repairSymlinkBtn.classList.toggle('btn-muted', disabled);

        var reason = disabled ? tip('extension.action.disabled', 'Disabled while extension is inactive. Enable extension first.') : tip('extension.action.ready', '');
        menuMBtn.title = reason;
        menuLibraryBtn.title = reason;
        settingsCardBtn.title = reason;
        repairSymlinkBtn.title = reason;
      }

      applyExtensionActionState(item.enabled);

      row.appendChild(left);
      rightWrap.appendChild(enableBtn);
      toggleGroup.appendChild(menuMControl);
      toggleGroup.appendChild(menuLibraryControl);
      toggleGroup.appendChild(settingsCardControl);
      actionGroup.appendChild(repairSymlinkBtn);
      actionGroup.appendChild(removeBtn);
      if (extMgrLogsModule && typeof extMgrLogsModule.attachExtensionButton === 'function') {
        extMgrLogsModule.attachExtensionButton(item, actionGroup);
      }
      rightWrap.appendChild(toggleGroup);
      rightWrap.appendChild(actionGroup);
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
        renderMaintenanceStatus(data.data.maintenance || {});
        allItems = (data.data && data.data.extensions) || [];
        renderItems(allItems);
        runSystemResources(true);
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
        renderMaintenanceStatus(data.data.maintenance || {});
        allItems = (data.data && data.data.extensions) || [];
        renderItems(allItems);
        runSystemResources(true);
        setStatus('Refresh complete.', 'ok');
      })
      .catch(function (err) {
        setStatus(err.message, 'error');
      });
  }

  function reloadPageSoon() {
    window.setTimeout(function () {
      window.location.reload();
    }, 220);
  }

  function runSystemResources(silent) {
    if (!silent) {
      setStatus('Collecting system resources...', null);
    }
    return api({ action: 'system_resources' })
      .then(function (data) {
        var payload = (data && data.data) || {};
        renderSystemResources(payload.resources || {});
        renderMaintenanceStatus(payload.maintenance || {});
        if (!silent) {
          setStatus('System resources refreshed.', 'ok');
        }
      })
      .catch(function (err) {
        if (!silent) {
          setStatus(err.message, 'error');
        }
      });
  }

  function runCreateBackup() {
    if (!createBackupBtn) {
      return;
    }
    createBackupBtn.disabled = true;
    setStatus('Creating ext-mgr backup snapshot...', null);
    api({ action: 'create_backup_snapshot' })
      .then(function (data) {
        var payload = (data && data.data) || {};
        if (maintenanceStorageNoteEl) {
          maintenanceStorageNoteEl.textContent = 'Backup created: ' + String(payload.snapshotPath || 'n/a') + ' (' + String(payload.copiedItems || 0) + ' item(s)).';
          maintenanceStorageNoteEl.classList.remove('error');
          maintenanceStorageNoteEl.classList.add('ok');
        }
        runSystemResources(true);
        setStatus('Backup snapshot created.', 'ok');
      })
      .catch(function (err) {
        if (maintenanceStorageNoteEl) {
          maintenanceStorageNoteEl.textContent = err.message;
          maintenanceStorageNoteEl.classList.remove('ok');
          maintenanceStorageNoteEl.classList.add('error');
        }
        setStatus(err.message, 'error');
      })
      .finally(function () {
        createBackupBtn.disabled = false;
      });
  }

  function runClearCache() {
    if (!clearCacheBtn) {
      return;
    }
    var confirmed = window.confirm('Clear /var/www/extensions/cache now? This removes temporary ext-mgr files only.');
    if (!confirmed) {
      return;
    }

    clearCacheBtn.disabled = true;
    setStatus('Clearing cache folder...', null);
    api({ action: 'clear_cache' })
      .then(function (data) {
        var payload = (data && data.data) || {};
        if (maintenanceStorageNoteEl) {
          maintenanceStorageNoteEl.textContent = 'Cache cleared: removed ' + String(payload.removedEntries || 0) + ' item(s), freed ' + asBytes(payload.freedBytes || 0) + '.';
          maintenanceStorageNoteEl.classList.remove('error');
          maintenanceStorageNoteEl.classList.add('ok');
        }
        runSystemResources(true);
        setStatus('Cache folder cleared.', 'ok');
      })
      .catch(function (err) {
        if (maintenanceStorageNoteEl) {
          maintenanceStorageNoteEl.textContent = err.message;
          maintenanceStorageNoteEl.classList.remove('ok');
          maintenanceStorageNoteEl.classList.add('error');
        }
        setStatus(err.message, 'error');
      })
      .finally(function () {
        clearCacheBtn.disabled = false;
      });
  }

  function setManagerVisibility(area, visible, button) {
    if (!button) {
      return;
    }
    var radios = button.querySelectorAll('input[type="radio"]');
    radios.forEach(function (r) { r.disabled = true; });
    button.style.pointerEvents = 'none';
    api({ action: 'set_manager_visibility', area: area, value: visible ? '1' : '0' })
      .then(function (data) {
        var payload = (data && data.data) || {};
        renderManagerVisibility(payload.visibility || {});
        if (managerVisibilityNoteEl) {
          managerVisibilityNoteEl.textContent = 'Manager visibility updated for ' + managerVisibilityAreaName(area) + '.';
          managerVisibilityNoteEl.classList.remove('error');
          managerVisibilityNoteEl.classList.add('ok');
        }
        setStatus('Manager visibility updated.', 'ok');
        reloadPageSoon();
      })
      .catch(function (err) {
        if (managerVisibilityNoteEl) {
          managerVisibilityNoteEl.textContent = err.message;
          managerVisibilityNoteEl.classList.remove('ok');
          managerVisibilityNoteEl.classList.add('error');
        }
        setStatus(err.message, 'error');
      })
      .finally(function () {
        radios.forEach(function (r) { r.disabled = false; });
        button.style.pointerEvents = '';
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
  bindIfPresent(refreshResourcesBtn, 'click', function () {
    runSystemResources(false);
  });
  bindIfPresent(createBackupBtn, 'click', runCreateBackup);
  bindIfPresent(clearCacheBtn, 'click', runClearCache);
  bindIfPresent(syncRegistryBtn, 'click', runRegistrySync);
  [
    [managerVisibilityHeaderBtn, 'header'],
    [managerVisibilityLibraryBtn, 'library'],
    [managerVisibilityMBtn, 'm'],
    [managerVisibilitySystemBtn, 'system'],
  ].forEach(function (e) {
    var toggleEl = e[0], area = e[1];
    if (!toggleEl) { return; }
    toggleEl.querySelectorAll('input[type="radio"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (radio.checked) { setManagerVisibility(area, radio.value === 'On', toggleEl); }
      });
    });
  });
  bindIfPresent(importExtensionFileEl, 'change', function () {
    if (!importExtensionFileNameEl) {
      return;
    }
    var files = importExtensionFileEl.files || [];
    importExtensionFileNameEl.textContent = files.length > 0 ? files[0].name : 'No file chosen';
  });
  bindIfPresent(importExtensionBtn, 'click', function () {
    var files = (importExtensionFileEl && importExtensionFileEl.files) || null;
    var dryRun = !!(importExtensionDryRunEl && importExtensionDryRunEl.checked);
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
    setStatus(dryRun ? 'Uploading and validating extension package (dry-run)...' : 'Uploading and importing extension package...', null);
    setImportWizardNote(dryRun ? 'Uploading and validating extension package (dry-run)...' : 'Uploading and importing extension package...', null);

    apiUpload(file, dryRun)
      .then(function (data) {
        var importedId = ((data || {}).data || {}).extensionId || 'unknown';
        var outcome = ((data || {}).data || {}).dryRun ? 'Dry-run validated' : 'Extension imported';
        setStatus(outcome + ': ' + importedId, 'ok');
        setImportWizardNote(outcome + ': ' + importedId, 'ok');
        if (importExtensionFileEl) {
          importExtensionFileEl.value = '';
        }
        if (importExtensionFileNameEl) {
          importExtensionFileNameEl.textContent = 'No file chosen';
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
      var previewOverride;
      if (nextMode === 'custom') {
        previewOverride = {
          provider: 'custom',
          repository: '',
          updateTrack: 'custom',
          channel: 'stable',
          branch: 'main',
          customBaseUrl: advancedCustomUrlEl ? advancedCustomUrlEl.value : ''
        };
      } else if (nextMode === 'dev') {
        previewOverride = {
          provider: currentProviderStatus.provider || 'github',
          repository: currentProviderStatus.repository,
          updateTrack: 'branch',
          channel: 'dev',
          branch: 'dev',
          customBaseUrl: ''
        };
      } else if (nextMode === 'release') {
        previewOverride = {
          provider: currentProviderStatus.provider || 'github',
          repository: currentProviderStatus.repository,
          updateTrack: 'channel',
          channel: 'stable',
          branch: 'main',
          customBaseUrl: ''
        };
      } else {
        previewOverride = {
          provider: currentProviderStatus.provider || 'github',
          repository: currentProviderStatus.repository,
          updateTrack: 'branch',
          channel: 'stable',
          branch: 'main',
          customBaseUrl: ''
        };
      }
      renderAdvancedSource(mergeWithCurrentProviderStatus(previewOverride), null);
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
          if (fallbackCopyText(text)) {
            setStatus('Advanced source link copied.', 'ok');
            return;
          }
          setStatus('Copy failed. Select and copy manually.', 'error');
        });
      return;
    }

    if (fallbackCopyText(text)) {
      setStatus('Advanced source link copied.', 'ok');
      return;
    }

    setStatus('Clipboard API unavailable. Select and copy the source link manually.', 'error');
  });

  bindIfPresent(openAdvancedSourceBtn, 'click', function (e) {
    if (!advancedSourceLinkEl) {
      return;
    }

    var sourceUrl = advancedSourceLinkEl.getAttribute('data-source-url') || '';
    if (!sourceUrl) {
      e.preventDefault();
      setStatus('No source URL available to open.', 'error');
      return;
    }

    e.preventDefault();
    window.open(sourceUrl, '_blank', 'noopener');
  });

  bindIfPresent(saveAdvancedUpdateBtn, 'click', function () {
    var mode = advancedUpdateState.mode || 'main';
    var track, channel, branch;
    if (mode === 'custom') {
      track = 'custom'; channel = 'stable'; branch = 'main';
    } else if (mode === 'dev') {
      track = 'branch'; channel = 'dev'; branch = 'dev';
    } else if (mode === 'release') {
      track = 'channel'; channel = 'stable'; branch = 'main';
    } else {
      track = 'branch'; channel = 'stable'; branch = 'main';
    }
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
      if (section.id) {
        writePref('section.open.' + section.id, isOpen ? '1' : '0');
      }
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
      if (submenu.id) {
        writePref('submenu.open.' + submenu.id, isOpen ? '1' : '0');
      }
    });
  });

  document.querySelectorAll('.extmgr-section').forEach(function (section) {
    if (section.id) {
      var storedSection = readPref('section.open.' + section.id, '');
      if (storedSection === '1' || storedSection === '0') {
        section.classList.toggle('is-open', storedSection === '1');
      }
    }
    var sectionToggle = section.querySelector('[data-menu-toggle]');
    if (!sectionToggle) {
      return;
    }
    sectionToggle.setAttribute('aria-expanded', section.classList.contains('is-open') ? 'true' : 'false');
  });

  document.querySelectorAll('.extmgr-submenu').forEach(function (submenu) {
    if (submenu.id) {
      var storedSubmenu = readPref('submenu.open.' + submenu.id, '');
      if (storedSubmenu === '1' || storedSubmenu === '0') {
        submenu.classList.toggle('is-open', storedSubmenu === '1');
      }
    }
    var submenuToggle = submenu.querySelector('[data-submenu-toggle]');
    if (!submenuToggle) {
      return;
    }
    submenuToggle.setAttribute('aria-expanded', submenu.classList.contains('is-open') ? 'true' : 'false');
  });

  if (extMgrLogsModule && typeof extMgrLogsModule.init === 'function') {
    extMgrLogsModule.init({
      apiUrls: apiUrls,
      statusHandler: setStatus,
      managerButtonId: 'open-extmgr-logs-btn'
    });
  }

  loadTooltipSnippets()
    .finally(function () {
      loadStatusAndList(true).then(function () {
        setRunUpdateButtonState();
        clearStatus();
      });
    });
})();
