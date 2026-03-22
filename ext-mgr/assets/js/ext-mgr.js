(function () {
  'use strict';

  var init = window.__EXT_MGR_INIT__ || {};
  var apiUrl = init.apiUrl || '/ext-mgr-api.php';
  var apiUrls = Array.isArray(init.apiUrls) ? init.apiUrls.slice() : [apiUrl, '/extensions/sys/ext-mgr-api.php'];
  var extMgrLogsModule = window.ExtMgrLogs || null;
  var tooltipUrl = init.tooltipUrl || '/extensions/sys/content/tooltips.md';
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
  var downloadExtMgrLogsBtn = document.getElementById('download-extmgr-logs-btn');
  var clearExtensionsFolderBtn = document.getElementById('clear-extensions-folder-btn');
  var refreshResourcesBtn = document.getElementById('refresh-resources-btn');
  var refreshServicesBtn = document.getElementById('refresh-services-btn');
  var servicesCoreListEl = document.getElementById('services-core-list');
  var servicesExtmgrListEl = document.getElementById('services-extmgr-list');
  var servicesExtensionsListEl = document.getElementById('services-extensions-list');
  var createBackupBtn = document.getElementById('create-backup-btn');
  var clearCacheBtn = document.getElementById('clear-cache-btn');
  var syncRegistryBtn = document.getElementById('sync-registry-btn');
  var showRegistryBtn = document.getElementById('show-registry-btn');
  var showVariablesBtn = document.getElementById('show-variables-btn');
  var showServicesBtn = document.getElementById('show-services-btn');
  var showApiStatusBtn = document.getElementById('show-api-status-btn');
  var showDatabaseBtn = document.getElementById('show-database-btn');
  var showMoodeApiBtn = document.getElementById('show-moode-api-btn');
  var bootConfigInitBtn = document.getElementById('boot-config-init-btn');
  var bootConfigStatusBtn = document.getElementById('boot-config-status-btn');
  var bootConfigListBtn = document.getElementById('boot-config-list-btn');
  var importExtensionFileEl = document.getElementById('import-extension-file');
  var importExtensionFileNameEl = document.getElementById('import-extension-file-name');
  var importExtensionBtn = document.getElementById('import-extension-btn');
  var importExtensionInstallBtn = document.getElementById('import-extension-install-btn');
  var importWizardNoteEl = document.getElementById('import-wizard-note');
  var wizardReviewJsonEl = document.getElementById('wizard-review-json');
  var wizardScanSummaryEl = document.getElementById('wizard-scan-summary');
  var wizardStepEls = document.querySelectorAll('#import-wizard-stepper [data-step]');
  var wizardNameEl = document.getElementById('wizard-name');
  var wizardVersionEl = document.getElementById('wizard-version');
  var wizardTypeEl = document.getElementById('wizard-type');
  var wizardMenuMEl = document.getElementById('wizard-menu-m');
  var wizardMenuLibraryEl = document.getElementById('wizard-menu-library');
  var wizardMenuSystemEl = document.getElementById('wizard-menu-system');
  var wizardSettingsOnlyEl = document.getElementById('wizard-settings-only');
  var wizardServiceNameEl = document.getElementById('wizard-service-name');
  var wizardDependenciesEl = document.getElementById('wizard-dependencies');
  var wizardAptPackagesEl = document.getElementById('wizard-apt-packages');
  var wizardBootConfigEl = document.getElementById('wizard-boot-config');
  var wizardBootConfigWarningEl = document.getElementById('wizard-bootconfig-warning');
  var listFilterEl = document.getElementById('list-filter');
  var listSortEl = document.getElementById('list-sort');
  var listSearchEl = document.getElementById('list-search');
  var listSummaryEl = document.getElementById('list-summary');
  var guidanceDocEl = document.getElementById('guidance-doc');
  var requirementsDocEl = document.getElementById('requirements-doc');
  var faqDocEl = document.getElementById('faq-doc');
  var menuToggleButtons = document.querySelectorAll('[data-menu-toggle]');
  var submenuToggleButtons = document.querySelectorAll('[data-submenu-toggle]');
  var actionModalState = { el: null, confirmHandler: null, cancelHandler: null };

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
  var importWizardState = {
    sessionId: '',
    extensionId: '',
    scan: null,
    review: null,
    manifest: null,
    info: null
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

  // ═══════════════════════════════════════════════════════════════════════════════
  // CONFIG MODULE - Centralized configuration loaded from Variables API
  // Matches PHP getDefaultSystemVariables() structure in ext-mgr-api.php
  // ═══════════════════════════════════════════════════════════════════════════════
  var Config = (function() {
    var loaded = false;
    var loading = false;
    var loadPromise = null;
    var data = {
      system: {
        paths: {
          extensionsRoot: '/var/www/extensions',
          installedRoot: '/var/www/extensions/installed',
          cacheRoot: '/var/www/extensions/cache',
          backupRoot: '/var/www/extensions/sys/backup',
          registryPath: '/var/www/extensions/sys/registry.json',
          logsRoot: '/var/www/extensions/sys/logs',
          extensionLogsRoot: '/var/www/extensions/sys/logs/extensionslogs',
          extMgrLogsRoot: '/var/www/extensions/sys/logs/ext-mgr logs',
          runtimeRoot: '/var/www/extensions/sys/.ext-mgr',
          variablesPath: '/var/www/extensions/sys/.ext-mgr/variables.json',
          moodeRoot: '/var/www',
          moodeInclude: '/var/www/inc',
          sqliteDb: '/var/local/www/db/moode-sqlite3.db',
          bootConfigHelper: '/var/www/extensions/api/ext-mgr-boot-config.sh',
          bootConfigFragmentsDir: '/boot/firmware/extensions.d',
          bootConfigTarget: '/boot/firmware/config.txt'
        },
        uris: {
          apiEndpoint: '/ext-mgr-api.php',
          apiEndpointAlt: '/extensions/sys/ext-mgr-api.php',
          tooltipsJson: '/extensions/sys/assets/data/ext-mgr-tooltips.json',
          tooltipsMd: '/extensions/sys/content/tooltips.md',
          cssPath: '/extensions/sys/assets/css/ext-mgr.css',
          jsPath: '/extensions/sys/assets/js/ext-mgr.js'
        },
        security: {
          user: 'moode-extmgrusr',
          group: 'moode-extmgr',
          webUser: 'www-data',
          webGroup: 'www-data'
        },
        defaults: {
          stageProfile: 'visible-by-default',
          menuVisibility: { m: true, library: true, system: false },
          settingsCardOnly: false
        },
        features: {
          bootConfig: {
            enabled: true,
            requiresReboot: true,
            marker: '# --- ext-mgr managed block ---'
          }
        }
      },
      extensions: {}
    };
    var callbacks = [];

    function load(callback) {
      if (loaded) {
        if (typeof callback === 'function') callback(data);
        return Promise.resolve(data);
      }
      if (typeof callback === 'function') {
        callbacks.push(callback);
      }
      if (loading) {
        return new Promise(function(resolve) {
          callbacks.push(function(d) { resolve(d); });
        });
      }
      loading = true;
      loadPromise = api({ action: 'variables' })
        .then(function(response) {
          if (response && response.ok && response.data) {
            // Merge response into data, preserving structure
            if (response.data.system) {
              Object.assign(data.system.paths, response.data.system.paths || {});
              Object.assign(data.system.uris, response.data.system.uris || {});
              Object.assign(data.system.security || {}, response.data.system.security || {});
              Object.assign(data.system.defaults || {}, response.data.system.defaults || {});
              // Merge features (boot_config, etc.)
              if (response.data.system.features) {
                data.system.features = data.system.features || {};
                Object.keys(response.data.system.features).forEach(function(featureKey) {
                  data.system.features[featureKey] = Object.assign(
                    data.system.features[featureKey] || {},
                    response.data.system.features[featureKey]
                  );
                });
              }
            }
            if (response.data.extensions) {
              data.extensions = response.data.extensions;
            }
          }
          loaded = true;
          loading = false;
          callbacks.forEach(function(cb) { cb(data); });
          callbacks = [];
          return data;
        })
        .catch(function(err) {
          console.warn('[Config] Failed to load variables, using defaults:', err);
          loaded = true;
          loading = false;
          callbacks.forEach(function(cb) { cb(data); });
          callbacks = [];
          return data;
        });
      return loadPromise;
    }

    function get(keyPath, fallback) {
      var keys = String(keyPath || '').split('.');
      var current = data;
      for (var i = 0; i < keys.length; i++) {
        if (!current || typeof current !== 'object' || !Object.prototype.hasOwnProperty.call(current, keys[i])) {
          return fallback !== undefined ? fallback : null;
        }
        current = current[keys[i]];
      }
      return current !== undefined ? current : (fallback !== undefined ? fallback : null);
    }

    function getPath(key, fallback) {
      return get('system.paths.' + key, fallback);
    }

    function getUri(key, fallback) {
      return get('system.uris.' + key, fallback);
    }

    function getSecurity(key, fallback) {
      return get('system.security.' + key, fallback);
    }

    function getDefault(key, fallback) {
      return get('system.defaults.' + key, fallback);
    }

    function getExtension(extId, keyPath, fallback) {
      var extVars = data.extensions && data.extensions[extId];
      if (!extVars) return fallback !== undefined ? fallback : null;
      if (!keyPath) return extVars;
      var keys = String(keyPath).split('.');
      var current = extVars;
      for (var i = 0; i < keys.length; i++) {
        if (!current || typeof current !== 'object' || !Object.prototype.hasOwnProperty.call(current, keys[i])) {
          return fallback !== undefined ? fallback : null;
        }
        current = current[keys[i]];
      }
      return current !== undefined ? current : (fallback !== undefined ? fallback : null);
    }

    function getAllExtensions() {
      return data.extensions || {};
    }

    function isLoaded() {
      return loaded;
    }

    function getData() {
      return data;
    }

    return {
      load: load,
      get: get,
      getPath: getPath,
      getUri: getUri,
      getSecurity: getSecurity,
      getDefault: getDefault,
      getExtension: getExtension,
      getAllExtensions: getAllExtensions,
      isLoaded: isLoaded,
      getData: getData
    };
  })();

  // ═══════════════════════════════════════════════════════════════════════════════
  // VARIABLES MANAGER UI MODULE - Tab-based variable viewer/editor
  // ═══════════════════════════════════════════════════════════════════════════════
  var VariablesManager = (function() {
    var containerEl = null;
    var currentTab = 'moode';
    var currentExtId = '';
    var editScope = 'extmgr';
    var editExtId = '';

    function init() {
      containerEl = document.getElementById('variables-manager-container');
      if (!containerEl) return;

      bindEvents();
      renderTab('moode');
    }

    function bindEvents() {
      // Tab navigation
      var tabBtns = containerEl.querySelectorAll('[data-var-tab]');
      tabBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
          var tab = btn.getAttribute('data-var-tab');
          switchTab(tab);
        });
      });

      // Extension dropdown on Extensions tab
      var extDropdown = document.getElementById('var-ext-dropdown');
      if (extDropdown) {
        extDropdown.addEventListener('change', function() {
          currentExtId = extDropdown.value;
          renderExtensionVariables();
        });
      }

      // Editor scope selector
      var editScopeSelect = document.getElementById('var-edit-scope');
      if (editScopeSelect) {
        editScopeSelect.addEventListener('change', function() {
          editScope = editScopeSelect.value;
          var extDrop = document.getElementById('var-edit-ext-dropdown');
          if (extDrop) {
            extDrop.style.display = editScope === 'extension' ? '' : 'none';
          }
          renderEditorVariables();
        });
      }

      // Editor extension dropdown
      var editExtDropdown = document.getElementById('var-edit-ext-dropdown');
      if (editExtDropdown) {
        editExtDropdown.addEventListener('change', function() {
          editExtId = editExtDropdown.value;
          renderEditorVariables();
        });
      }

      // Add variable button
      var addVarBtn = document.getElementById('var-add-btn');
      if (addVarBtn) {
        addVarBtn.addEventListener('click', handleAddVariable);
      }
    }

    function switchTab(tab) {
      currentTab = tab;

      // Update tab buttons
      var tabBtns = containerEl.querySelectorAll('[data-var-tab]');
      tabBtns.forEach(function(btn) {
        var t = btn.getAttribute('data-var-tab');
        btn.classList.toggle('is-active', t === tab);
        btn.setAttribute('aria-selected', t === tab ? 'true' : 'false');
      });

      // Update panels
      var panels = containerEl.querySelectorAll('[data-var-panel]');
      panels.forEach(function(panel) {
        var p = panel.getAttribute('data-var-panel');
        panel.classList.toggle('is-active', p === tab);
      });

      renderTab(tab);
    }

    function renderTab(tab) {
      switch (tab) {
        case 'extmgr':
          renderExtMgrVariables();
          break;
        case 'moode':
          renderMoodeVariables();
          break;
        case 'extensions':
          populateExtensionDropdowns();
          renderExtensionVariables();
          break;
        case 'editor':
          populateExtensionDropdowns();
          renderEditorVariables();
          break;
      }
    }

    function populateExtensionDropdowns() {
      var extensions = Config.getAllExtensions() || {};
      var extIds = Object.keys(extensions);

      var dropdowns = [
        document.getElementById('var-ext-dropdown'),
        document.getElementById('var-edit-ext-dropdown')
      ];

      dropdowns.forEach(function(dropdown) {
        if (!dropdown) return;
        var currentVal = dropdown.value;
        dropdown.innerHTML = '<option value="">-- Select extension --</option>';
        extIds.forEach(function(extId) {
          var opt = document.createElement('option');
          opt.value = extId;
          opt.textContent = extId;
          dropdown.appendChild(opt);
        });
        if (currentVal && extIds.indexOf(currentVal) >= 0) {
          dropdown.value = currentVal;
        }
      });
    }

    function renderExtMgrVariables() {
      var contentEl = document.getElementById('var-extmgr-content');
      if (!contentEl) return;

      var systemVars = Config.get('system') || {};
      var html = '<div class="extmgr-var-editor-grid">';
      html += renderVariableTree(systemVars, '', false);
      html += '</div>';
      contentEl.innerHTML = html;
    }

    function renderMoodeVariables() {
      var contentEl = document.getElementById('var-moode-content');
      if (!contentEl) return;

      // moOde variables are typically readonly system info
      // We fetch them via the variables API with scope=moode
      contentEl.innerHTML = '<p class="extmgr-note">Loading moOde variables...</p>';

      api({ action: 'variables', scope: 'moode' })
        .then(function(response) {
          if (response && response.ok && response.variables) {
            var html = '<div class="extmgr-var-editor-grid">';
            html += renderVariableTree(response.variables, '', true);
            html += '</div>';
            contentEl.innerHTML = html;
          } else {
            contentEl.innerHTML = '<p class="extmgr-note">No moOde variables available or not supported.</p>';
          }
        })
        .catch(function() {
          contentEl.innerHTML = '<p class="extmgr-note">Failed to load moOde variables.</p>';
        });
    }

    function renderExtensionVariables() {
      var contentEl = document.getElementById('var-extensions-content');
      if (!contentEl) return;

      if (!currentExtId) {
        contentEl.innerHTML = '<p class="extmgr-note">Select an extension to view its variables.</p>';
        return;
      }

      var extVars = Config.getExtension(currentExtId);
      if (!extVars || Object.keys(extVars).length === 0) {
        contentEl.innerHTML = '<p class="extmgr-note">No variables configured for <strong>' + escapeHtml(currentExtId) + '</strong>.</p>';
        return;
      }

      var html = '<div class="extmgr-var-editor-grid">';
      html += renderVariableTree(extVars, '', true);
      html += '</div>';
      contentEl.innerHTML = html;
    }

    function renderEditorVariables() {
      var contentEl = document.getElementById('var-editor-content');
      if (!contentEl) return;

      var vars = {};
      var scope = 'system';

      if (editScope === 'extmgr') {
        // Edit ext-mgr features (features.* keys)
        var systemVars = Config.get('system') || {};
        vars = systemVars.features || {};
        scope = 'system';
      } else if (editScope === 'extension') {
        if (!editExtId) {
          contentEl.innerHTML = '<p class="extmgr-note">Select an extension to edit its variables.</p>';
          return;
        }
        vars = Config.getExtension(editExtId) || {};
        scope = 'extension';
      }

      if (Object.keys(vars).length === 0) {
        contentEl.innerHTML = '<p class="extmgr-note">No editable variables found. Use the form below to add new variables.</p>';
      } else {
        var html = '<div class="extmgr-var-editor-grid">';
        html += renderVariableTree(vars, editScope === 'extmgr' ? 'features' : '', false, true);
        html += '</div>';
        contentEl.innerHTML = html;

        // Bind delete buttons
        contentEl.querySelectorAll('[data-var-delete]').forEach(function(btn) {
          btn.addEventListener('click', function() {
            var key = btn.getAttribute('data-var-delete');
            handleDeleteVariable(key, scope);
          });
        });
      }
    }

    function renderVariableTree(obj, prefix, isReadOnly, showDelete) {
      var html = '';
      if (!obj || typeof obj !== 'object') return html;

      Object.keys(obj).forEach(function(key) {
        var fullKey = prefix ? prefix + '.' + key : key;
        var value = obj[key];

        if (value && typeof value === 'object' && !Array.isArray(value)) {
          html += '<div class="extmgr-var-group">' +
                  '<span class="extmgr-var-group-label">' + escapeHtml(key.toUpperCase()) + '</span>' +
                  '<div class="extmgr-var-group-content">' +
                  renderVariableTree(value, fullKey, isReadOnly, showDelete) +
                  '</div></div>';
        } else {
          var displayValue = Array.isArray(value) || typeof value === 'object'
            ? JSON.stringify(value)
            : String(value);
          var isProtected = isReadOnly || fullKey.indexOf('paths.') === 0 ||
                           fullKey.indexOf('security.') === 0 ||
                           fullKey.indexOf('uris.') === 0;

          html += '<div class="extmgr-var-row' + (isProtected ? ' is-protected' : '') + '">' +
                  '<span class="extmgr-var-key" title="' + escapeHtml(fullKey) + '">' + escapeHtml(key) + '</span>' +
                  '<span class="extmgr-var-value" title="' + escapeHtml(displayValue) + '">' + escapeHtml(displayValue) + '</span>';
          if (showDelete && !isProtected) {
            html += '<button type="button" class="extmgr-var-delete btn btn-small" data-var-delete="' + escapeHtml(fullKey) + '" title="Delete"><i class="fa-solid fa-trash"></i></button>';
          }
          html += '</div>';
        }
      });

      return html;
    }

    function handleAddVariable() {
      var keyEl = document.getElementById('var-new-key');
      var valueEl = document.getElementById('var-new-value');
      var typeEl = document.getElementById('var-new-type');

      if (!keyEl || !valueEl) return;

      var key = String(keyEl.value || '').trim();
      var rawValue = valueEl.value;
      var valueType = typeEl ? typeEl.value : 'string';

      if (!key) {
        setStatus('Enter a variable key', 'error');
        return;
      }

      // Determine scope and extension_id based on editor settings
      var scope = editScope === 'extension' ? 'extension' : 'system';
      var params = {
        action: 'set_variable',
        scope: scope,
        key: editScope === 'extmgr' ? 'features.' + key : key,
        value: rawValue,
        type: valueType
      };

      if (scope === 'extension' && editExtId) {
        params.extension_id = editExtId;
      }

      setStatus('Saving variable...', null);
      api(params)
        .then(function(response) {
          if (response && response.ok) {
            setStatus('Variable saved: ' + key, 'ok');
            keyEl.value = '';
            valueEl.value = '';
            // Reload config and re-render
            Config.load(function() {
              renderEditorVariables();
            });
          } else {
            setStatus(response.error || 'Failed to save variable', 'error');
          }
        })
        .catch(function(err) {
          setStatus(err.message || 'Failed to save variable', 'error');
        });
    }

    function handleDeleteVariable(key, scope) {
      if (!confirm('Delete variable: ' + key + '?')) return;

      var params = {
        action: 'delete_variable',
        scope: scope,
        key: key
      };

      if (scope === 'extension' && editExtId) {
        params.extension_id = editExtId;
      }

      setStatus('Deleting variable...', null);
      api(params)
        .then(function(response) {
          if (response && response.ok) {
            setStatus('Variable deleted: ' + key, 'ok');
            Config.load(function() {
              renderEditorVariables();
            });
          } else {
            setStatus(response.error || 'Failed to delete variable', 'error');
          }
        })
        .catch(function(err) {
          setStatus(err.message || 'Failed to delete variable', 'error');
        });
    }

    return {
      init: init,
      switchTab: switchTab,
      refresh: function() { renderTab(currentTab); }
    };
  })();

  // ═══════════════════════════════════════════════════════════════════════════════
  // IMPORT WIZARD MODULE - Step navigation for extension import
  // ═══════════════════════════════════════════════════════════════════════════════
  var ImportWizard = (function() {
    var steps = ['upload', 'metadata', 'menu', 'service', 'packages', 'review'];
    var currentStepIndex = 0;
    var wizardEl = null;
    var stepperEl = null;
    var panelsEl = null;
    var completedSteps = {};

    function init() {
      wizardEl = document.getElementById('import-wizard');
      stepperEl = document.getElementById('import-wizard-stepper');
      panelsEl = wizardEl ? wizardEl.querySelector('.extmgr-wizard-panels') : null;

      if (!wizardEl || !stepperEl || !panelsEl) {
        console.warn('[ImportWizard] Elements not found');
        return;
      }

      // Reset wizard state to prevent stale data
      importWizardState.sessionId = '';
      importWizardState.extensionId = '';
      importWizardState.scan = null;
      importWizardState.review = null;
      importWizardState.manifest = null;
      importWizardState.info = null;

      // Clear form fields to prevent cache leakage between imports
      clearWizardForm();

      // Clear file input
      if (importExtensionFileEl) importExtensionFileEl.value = '';
      if (importExtensionFileNameEl) importExtensionFileNameEl.textContent = 'No file chosen';

      // Disable install button until scan completes
      if (importExtensionInstallBtn) {
        importExtensionInstallBtn.disabled = true;
      }

      // Reset all step states on init (clears any cached/stale classes)
      completedSteps = {};
      currentStepIndex = 0;
      var stepBtns = stepperEl.querySelectorAll('.extmgr-wizard-step');
      console.log('[ImportWizard] init: resetting', stepBtns.length, 'steps');
      stepBtns.forEach(function(btn, idx) {
        btn.classList.remove('is-active', 'is-completed');
        // First step starts as active
        if (idx === 0) {
          btn.classList.add('is-active');
        }
      });

      // Also reset panels
      var panels = panelsEl.querySelectorAll('.extmgr-wizard-panel');
      panels.forEach(function(panel, idx) {
        panel.classList.toggle('is-active', idx === 0);
      });

      bindStepperClicks();
      bindNavButtons();
      console.log('[ImportWizard] init complete, step 0 active');
    }

    function bindStepperClicks() {
      var stepBtns = stepperEl.querySelectorAll('.extmgr-wizard-step');
      stepBtns.forEach(function(btn, idx) {
        btn.addEventListener('click', function() {
          // Only allow clicking on completed or current step, or next step if scan done
          if (idx <= currentStepIndex || completedSteps[steps[idx - 1]]) {
            goToStep(idx);
          }
        });
      });
    }

    function bindNavButtons() {
      // Next buttons
      var nextBtns = panelsEl.querySelectorAll('[data-wizard-next]');
      nextBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
          // Require valid session to proceed past upload step
          if (currentStepIndex === 0 && !importWizardState.sessionId) {
            console.log('[ImportWizard] Cannot proceed: no sessionId (upload first)');
            return;
          }
          if (currentStepIndex < steps.length - 1) {
            markStepCompleted(steps[currentStepIndex]);
            goToStep(currentStepIndex + 1);
          }
        });
      });

      // Previous buttons
      var prevBtns = panelsEl.querySelectorAll('[data-wizard-prev]');
      prevBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
          if (currentStepIndex > 0) {
            goToStep(currentStepIndex - 1);
          }
        });
      });
    }

    function goToStep(index) {
      if (index < 0 || index >= steps.length) return;

      currentStepIndex = index;
      var stepName = steps[index];
      console.log('[ImportWizard] goToStep(' + index + ') = ' + stepName);
      console.log('[ImportWizard] completedSteps:', JSON.stringify(completedSteps));

      // Update stepper indicators
      var stepBtns = stepperEl.querySelectorAll('.extmgr-wizard-step');
      stepBtns.forEach(function(btn, idx) {
        var isActive = idx === index;
        var isCompleted = completedSteps[steps[idx]];
        btn.classList.remove('is-active', 'is-completed');
        if (isActive) {
          btn.classList.add('is-active');
        } else if (isCompleted) {
          btn.classList.add('is-completed');
        }
        console.log('[ImportWizard] step[' + idx + ']=' + steps[idx] + ' active=' + isActive + ' completed=' + !!isCompleted + ' classes=' + btn.className);
      });

      // Show/hide panels
      var panels = panelsEl.querySelectorAll('.extmgr-wizard-panel');
      panels.forEach(function(panel) {
        var panelStep = panel.getAttribute('data-panel');
        panel.classList.toggle('is-active', panelStep === stepName);
      });
    }

    function markStepCompleted(stepName) {
      console.log('[ImportWizard] markStepCompleted(' + stepName + ')');
      completedSteps[stepName] = true;
      // Don't add class here - goToStep will handle it
    }

    function getCurrentStep() {
      return steps[currentStepIndex];
    }

    function reset() {
      completedSteps = {};
      goToStep(0);
      var stepBtns = stepperEl.querySelectorAll('.extmgr-wizard-step');
      stepBtns.forEach(function(btn) {
        btn.classList.remove('is-completed');
      });
    }

    // Called after successful scan to enable navigation
    function onScanComplete() {
      markStepCompleted('upload');
      goToStep(1); // Go to metadata
    }

    return {
      init: init,
      goToStep: goToStep,
      getCurrentStep: getCurrentStep,
      markStepCompleted: markStepCompleted,
      onScanComplete: onScanComplete,
      reset: reset
    };
  })();

  // Expose modules for external access
  window.ExtMgrConfig = Config;
  window.ExtMgrVariables = VariablesManager;
  window.ExtMgrImportWizard = ImportWizard;

  // ═══════════════════════════════════════════════════════════════════════════════

  function setStatus(text, kind, options) {
    options = options || {};
    if (!statusEl) {
      return;
    }

    // User preference: suppress success/info toast-style status noise.
    // Exception: force=true shows the message regardless (used for uninstall etc.)
    if (kind === 'ok' && !options.force) {
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
        var status = err && typeof err.status !== 'undefined' ? err.status : null;
        if (status === null || status === 0 || status === 404) {
          return tryAt(idx + 1);
        }
        throw err;
      });
    }

    return tryAt(0);
  }

  function buildApiUrl(params) {
    var url = apiUrl;
    if (params && typeof params === 'object') {
      var qs = new URLSearchParams(params).toString();
      if (qs) {
        url += (url.indexOf('?') === -1 ? '?' : '&') + qs;
      }
    }
    return url;
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
    var urls = [
      tooltipUrl,
      Config.getUri('tooltipsMd', '/extensions/sys/content/tooltips.md'),
      Config.getUri('tooltipsJson', '/extensions/sys/assets/data/ext-mgr-tooltips.json')
    ];
    var deduped = [];
    urls.forEach(function (candidate) {
      var url = String(candidate || '').trim();
      if (!url || deduped.indexOf(url) !== -1) {
        return;
      }
      deduped.push(url);
    });

    function parseTooltipMarkdown(text) {
      var map = {};
      var lines = String(text || '').split(/\r?\n/);
      lines.forEach(function (rawLine) {
        var line = String(rawLine || '').trim();
        if (!line || line.charAt(0) === '#') {
          return;
        }
        if (line.indexOf('- ') === 0) {
          line = line.slice(2).trim();
        }
        var idx = line.indexOf(':');
        if (idx <= 0) {
          return;
        }
        var key = line.slice(0, idx).trim();
        var value = line.slice(idx + 1).trim();
        if (!key) {
          return;
        }
        map[key] = value;
      });
      return map;
    }

    function tryParseTooltipBody(url, bodyText) {
      if (/\.json($|\?)/i.test(url)) {
        try {
          var parsedJson = JSON.parse(bodyText);
          return (parsedJson && typeof parsedJson === 'object' && !Array.isArray(parsedJson)) ? parsedJson : null;
        } catch (e) {
          return null;
        }
      }

      var mdMap = parseTooltipMarkdown(bodyText);
      if (Object.keys(mdMap).length) {
        return mdMap;
      }

      try {
        var fallbackJson = JSON.parse(bodyText);
        return (fallbackJson && typeof fallbackJson === 'object' && !Array.isArray(fallbackJson)) ? fallbackJson : null;
      } catch (e2) {
        return null;
      }
    }

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
          return res.text().then(function (text) {
            return tryParseTooltipBody(url, text);
          }).catch(function () { return null; });
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

  function applyStaticTooltips() {
    applyTip(refreshBtn, 'manager.action.refresh', 'Refresh extension list and status data.');
    applyTip(syncRegistryBtn, 'manager.action.syncRegistry', 'Rebuild registry from installed extension folders.');
    applyTip(systemUpdateBtn, 'manager.action.syncExtensions', 'Run extension-wide synchronization flow.');
    applyTip(importExtensionBtn, 'manager.import.upload', 'Import selected extension zip package.');
    applyTip(document.getElementById('download-template-btn'), 'manager.import.template', 'Download starter template extension package.');
    applyTip(repairBtn, 'manager.troubleshooting.repair', 'Run ext-mgr repair flow for common install issues.');
    applyTip(document.getElementById('open-extmgr-logs-btn'), 'manager.troubleshooting.openLogs', 'Open ext-mgr log viewer.');
    applyTip(downloadExtMgrLogsBtn, 'manager.troubleshooting.downloadLogs', 'Download combined ext-mgr and system log snapshot.');
    applyTip(clearExtensionsFolderBtn, 'manager.troubleshooting.clearExtensionsFolder', 'Gracefully uninstall all extensions and then clear remaining extension folders.');
    applyTip(createBackupBtn, 'manager.maintenance.backup', 'Create a backup snapshot for ext-mgr runtime state.');
    applyTip(clearCacheBtn, 'manager.maintenance.clearCache', 'Clear cached files in ' + Config.getPath('cacheRoot', '/var/www/extensions/cache') + '.');
    applyTip(refreshResourcesBtn, 'manager.system.refreshResources', 'Refresh memory, CPU, and storage metrics.');
    applyTip(refreshServicesBtn, 'manager.system.refreshServices', 'Refresh systemd service status for moOde and extensions.');
  }

  function bindIfPresent(el, eventName, handler) {
    if (!el) {
      return;
    }
    el.addEventListener(eventName, handler);
  }

  function ensureActionModal() {
    if (actionModalState.el) {
      return actionModalState.el;
    }

    var modal = document.createElement('div');
    modal.className = 'extmgr-action-modal';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = '' +
      '<div class="extmgr-action-modal-backdrop" data-action-modal-close="1"></div>' +
      '<div class="extmgr-action-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="extmgr-action-modal-title">' +
      '  <div class="extmgr-action-modal-header">' +
      '    <h3 id="extmgr-action-modal-title" class="extmgr-action-modal-title">Attention</h3>' +
      '    <button type="button" class="btn btn-small" data-action-modal-close="1">Close</button>' +
      '  </div>' +
      '  <div id="extmgr-action-modal-message" class="extmgr-action-modal-message"></div>' +
      '  <div id="extmgr-action-modal-input-wrap" class="extmgr-action-modal-input-wrap">' +
      '    <label for="extmgr-action-modal-input">Type confirmation</label>' +
      '    <input id="extmgr-action-modal-input" type="text" autocomplete="off">' +
      '  </div>' +
      '  <div id="extmgr-action-modal-note" class="extmgr-note"></div>' +
      '  <div class="extmgr-action-modal-actions">' +
      '    <button type="button" id="extmgr-action-modal-cancel" class="btn btn-small">Cancel</button>' +
      '    <button type="button" id="extmgr-action-modal-confirm" class="btn btn-small btn-primary extmgr-destructive">Confirm</button>' +
      '  </div>' +
      '</div>';

    modal.addEventListener('click', function (evt) {
      var target = evt.target;
      if (target && target.getAttribute('data-action-modal-close') === '1') {
        closeActionModal(false);
      }
    });

    document.body.appendChild(modal);
    actionModalState.el = modal;
    return modal;
  }

  function closeActionModal(confirmed) {
    var modal = actionModalState.el;
    var confirmHandler = actionModalState.confirmHandler;
    var cancelHandler = actionModalState.cancelHandler;
    actionModalState.confirmHandler = null;
    actionModalState.cancelHandler = null;

    if (modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    }

    if (confirmed) {
      if (typeof confirmHandler === 'function') {
        confirmHandler();
      }
      return;
    }

    if (typeof cancelHandler === 'function') {
      cancelHandler();
    }
  }

  function openActionModal(options) {
    var modal = ensureActionModal();
    var config = options || {};
    var titleEl = document.getElementById('extmgr-action-modal-title');
    var messageEl = document.getElementById('extmgr-action-modal-message');
    var inputWrapEl = document.getElementById('extmgr-action-modal-input-wrap');
    var inputEl = document.getElementById('extmgr-action-modal-input');
    var noteEl = document.getElementById('extmgr-action-modal-note');
    var cancelBtn = document.getElementById('extmgr-action-modal-cancel');
    var confirmBtn = document.getElementById('extmgr-action-modal-confirm');
    var requiredText = String(config.requiredText || '');

    titleEl.textContent = String(config.title || 'Attention');
    messageEl.textContent = String(config.message || '');
    // Support HTML in note (for scan issues list)
    var noteText = String(config.note || '');
    if (noteText.indexOf('<') === 0) {
      noteEl.innerHTML = noteText;
    } else {
      noteEl.textContent = noteText;
    }
    noteEl.classList.remove('error', 'ok');
    cancelBtn.textContent = String(config.cancelText || 'Cancel');
    confirmBtn.textContent = String(config.confirmText || 'Confirm');

    inputWrapEl.style.display = requiredText ? '' : 'none';
    inputEl.value = '';
    confirmBtn.disabled = !!requiredText;

    function syncConfirmState() {
      if (!requiredText) {
        confirmBtn.disabled = false;
        return;
      }
      confirmBtn.disabled = String(inputEl.value || '') !== requiredText;
    }

    inputEl.oninput = syncConfirmState;
    syncConfirmState();

    actionModalState.confirmHandler = typeof config.onConfirm === 'function' ? config.onConfirm : null;
    actionModalState.cancelHandler = typeof config.onCancel === 'function' ? config.onCancel : null;

    cancelBtn.onclick = function () {
      closeActionModal(false);
    };
    confirmBtn.onclick = function () {
      closeActionModal(true);
    };

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    if (requiredText) {
      inputEl.focus();
    } else {
      confirmBtn.focus();
    }
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

  function firstSentence(text) {
    var value = String(text || '').trim();
    if (!value) {
      return value;
    }
    var idx = value.search(/[.!?]/);
    if (idx === -1) {
      return value;
    }
    return value.slice(0, idx + 1).trim();
  }

  function apiUpload(file) {
    var formData = new FormData();
    formData.append('action', 'import_extension_scan');
    formData.append('package', file);

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
        var status = err && typeof err.status !== 'undefined' ? err.status : null;
        if (status === null || status === 0 || status === 404) {
          return tryAt(idx + 1);
        }
        throw err;
      });
    }

    return tryAt(0);
  }

  function apiInstallFromSession(payload) {
    var params = Object.assign({ action: 'import_extension_install' }, payload || {});
    return api(params);
  }

  function apiAbortImport(sessionId) {
    return api({ action: 'import_extension_abort', session_id: sessionId });
  }

  /**
   * Count and categorize warnings from the scan results.
   * Returns { errors: number, warnings: number, items: Array }
   */
  function countScanIssues(scan) {
    var result = { errors: 0, warnings: 0, items: [] };
    if (!scan) return result;

    // Check violations (severity = violation)
    var violations = scan.violations || [];
    violations.forEach(function(v) {
      result.errors++;
      result.items.push({ severity: 'error', message: v.label || v.path || 'Path violation' });
    });

    // Check warnings from path audit
    var warnings = scan.warnings || [];
    warnings.forEach(function(w) {
      result.warnings++;
      result.items.push({ severity: 'warning', message: w.message || w.label || w.path || 'Warning' });
    });

    // Check code patterns - only count warning and violation severity
    var codePatterns = scan.code_patterns || {};
    var bySeverity = codePatterns.by_severity || {};

    // Violations from code patterns
    (bySeverity.violation || []).forEach(function(p) {
      result.errors++;
      result.items.push({ severity: 'error', message: p.label + ': ' + p.description, file: p.file });
    });

    // Warnings from code patterns
    (bySeverity.warning || []).forEach(function(p) {
      result.warnings++;
      result.items.push({ severity: 'warning', message: p.label + ': ' + p.description, file: p.file });
    });

    return result;
  }

  /**
   * Format scan issues as HTML for the warning dialog.
   */
  function formatScanIssuesHtml(issues) {
    if (!issues.items || issues.items.length === 0) {
      return '';
    }
    var html = '<ul style="text-align:left; margin:10px 0; padding-left:20px; max-height:200px; overflow-y:auto;">';
    issues.items.forEach(function(item) {
      var icon = item.severity === 'error' ? '✗' : '⚠';
      var color = item.severity === 'error' ? '#e74c3c' : '#f39c12';
      var fileInfo = item.file ? ' <small style="opacity:0.7">(' + item.file + ')</small>' : '';
      html += '<li style="color:' + color + '; margin:4px 0;">' + icon + ' ' + item.message + fileInfo + '</li>';
    });
    html += '</ul>';
    return html;
  }

  /**
   * Show warning dialog before proceeding with install.
   * Returns a Promise that resolves to true (proceed) or false (cancel).
   */
  function showImportWarningDialog(issues) {
    return new Promise(function(resolve) {
      var title = issues.errors > 0 ? 'Scan Errors Found' : 'Scan Warnings Found';
      var summaryText = '';
      if (issues.errors > 0) {
        summaryText += issues.errors + ' error' + (issues.errors > 1 ? 's' : '');
      }
      if (issues.warnings > 0) {
        if (summaryText) summaryText += ' and ';
        summaryText += issues.warnings + ' warning' + (issues.warnings > 1 ? 's' : '');
      }

      openActionModal({
        title: title,
        message: 'The code scanner found ' + summaryText + ' that may cause issues:',
        note: formatScanIssuesHtml(issues),
        confirmText: 'Proceed Anyway',
        cancelText: 'Cancel Import',
        onConfirm: function() {
          resolve(true);
        },
        onCancel: function() {
          resolve(false);
        }
      });
    });
  }

  function wizardSetStep(stepName) {
    // Update stepper indicators
    wizardStepEls.forEach(function (el) {
      el.classList.toggle('is-active', el.getAttribute('data-step') === stepName);
    });

    // Update panels visibility
    var panelsContainer = document.querySelector('.extmgr-wizard-panels');
    if (panelsContainer) {
      var panels = panelsContainer.querySelectorAll('.extmgr-wizard-panel');
      panels.forEach(function(panel) {
        panel.classList.toggle('is-active', panel.getAttribute('data-panel') === stepName);
      });
    }

    // Update ImportWizard module state
    if (window.ExtMgrImportWizard) {
      var steps = ['upload', 'metadata', 'menu', 'service', 'packages', 'review'];
      var idx = steps.indexOf(stepName);
      if (idx >= 0) {
        window.ExtMgrImportWizard.goToStep(idx);
      }
    }
  }

  /**
   * Clear wizard form fields to prevent stale data between imports.
   * Called before populating form with new manifest data.
   */
  function clearWizardForm() {
    if (wizardNameEl) wizardNameEl.value = '';
    if (wizardVersionEl) wizardVersionEl.value = '';
    if (wizardTypeEl) wizardTypeEl.value = 'other';
    if (wizardServiceNameEl) wizardServiceNameEl.value = '';
    if (wizardDependenciesEl) wizardDependenciesEl.value = '';
    if (wizardAptPackagesEl) wizardAptPackagesEl.value = '';
    if (wizardBootConfigEl) wizardBootConfigEl.value = '';
    if (wizardBootConfigWarningEl) wizardBootConfigWarningEl.style.display = 'none';
    if (wizardMenuMEl) wizardMenuMEl.checked = false;
    if (wizardMenuLibraryEl) wizardMenuLibraryEl.checked = false;
    if (wizardMenuSystemEl) wizardMenuSystemEl.checked = false;
    if (wizardSettingsOnlyEl) wizardSettingsOnlyEl.checked = false;
    if (wizardReviewJsonEl) wizardReviewJsonEl.textContent = '';
    if (wizardScanSummaryEl) wizardScanSummaryEl.textContent = 'No scan data yet.';
  }

  /**
   * Fully reset the import wizard state.
   * Called before new upload or after abort/cancel.
   * @param {Object} options - Reset options
   * @param {boolean} options.cleanupServer - If true, calls API to clean up staged files
   * @param {boolean} options.silent - If true, don't update status messages
   * @param {boolean} options.preserveFileInput - If true, don't clear the file input
   * @returns {Promise} Resolves when reset is complete
   */
  function resetImportWizard(options) {
    var opts = options || {};
    var cleanupPromise = Promise.resolve();

    // If there's an existing session and we should cleanup server-side
    if (opts.cleanupServer && importWizardState.sessionId) {
      cleanupPromise = apiAbortImport(importWizardState.sessionId).catch(function(err) {
        // Silently ignore cleanup errors (session may already be cleaned)
        console.log('[ImportWizard] cleanup error (ignored):', err);
      });
    }

    return cleanupPromise.then(function() {
      // Reset all state variables
      importWizardState.sessionId = '';
      importWizardState.extensionId = '';
      importWizardState.scan = null;
      importWizardState.review = null;
      importWizardState.manifest = null;
      importWizardState.info = null;

      // Clear form fields
      clearWizardForm();

      // Reset file input unless preserveFileInput is true
      if (!opts.preserveFileInput) {
        if (importExtensionFileEl) {
          importExtensionFileEl.value = '';
        }
        if (importExtensionFileNameEl) {
          importExtensionFileNameEl.textContent = 'No file chosen';
        }
      }

      // Disable install button
      if (importExtensionInstallBtn) {
        importExtensionInstallBtn.disabled = true;
      }

      // Hide success/progress panels
      if (wizardInstallSuccessEl) {
        wizardInstallSuccessEl.style.display = 'none';
      }
      if (wizardInstallProgressEl) {
        wizardInstallProgressEl.style.display = 'none';
      }

      // Restore wizard navigation visibility
      var wizardNav = document.querySelector('.extmgr-wizard-nav');
      if (wizardNav) {
        wizardNav.style.display = '';
      }

      // Reset wizard stepper to first step
      if (window.ExtMgrImportWizard && window.ExtMgrImportWizard.reset) {
        window.ExtMgrImportWizard.reset();
      }

      // Clear any status/note messages unless silent
      if (!opts.silent) {
        setImportWizardNote('', null);
      }

      console.log('[ImportWizard] wizard state fully reset');
    });
  }

  function setWizardFormFromManifest(manifest, scan, review, info) {
    // Clear form first to prevent stale values from previous imports
    clearWizardForm();

    var row = manifest || {};
    var ext = row.ext_mgr || {};
    var menu = ext.menuVisibility || {};
    var service = ext.service || {};
    var install = ext.install || {};
    var infoData = info || {};

    // Name priority: manifest.name (unified) > info.json name (deprecated fallback)
    var extensionName = row.name || infoData.name || '';
    if (wizardNameEl) {
      wizardNameEl.value = extensionName;
    }
    // Version priority: manifest.version (unified) > info.json version (deprecated fallback)
    if (wizardVersionEl) {
      wizardVersionEl.value = row.version || infoData.version || '';
    }
    if (wizardTypeEl) {
      wizardTypeEl.value = ext.type || ((scan || {}).detected_type || 'other');
    }
    if (wizardMenuMEl) {
      wizardMenuMEl.checked = !!menu.m;
    }
    if (wizardMenuLibraryEl) {
      wizardMenuLibraryEl.checked = !!menu.library;
    }
    if (wizardMenuSystemEl) {
      wizardMenuSystemEl.checked = !!menu.system;
    }
    if (wizardSettingsOnlyEl) {
      wizardSettingsOnlyEl.checked = !!ext.settingsCardOnly;
    }
    if (wizardServiceNameEl) {
      // Service name format: extmgr-{name}.service
      // If service exists but no name, generate from extension name
      var defaultServiceName = '';
      var scanHasService = (scan || {}).has_service || false;
      if (extensionName && (service.name || scanHasService)) {
        // Sanitize name for service: lowercase, alphanumeric and dashes only
        var sanitizedName = extensionName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        defaultServiceName = 'extmgr-' + sanitizedName + '.service';
      }
      wizardServiceNameEl.value = service.name || defaultServiceName;
    }
    if (wizardDependenciesEl) {
      wizardDependenciesEl.value = Array.isArray(service.dependencies) ? service.dependencies.join('\n') : '';
    }
    if (wizardAptPackagesEl) {
      var manifestPkgs = Array.isArray(install.packages) ? install.packages : [];
      var scanPkgs = Array.isArray((scan || {}).apt_packages) ? scan.apt_packages : [];
      var reviewPkgs = Array.isArray((review || {}).manifestPackages) ? review.manifestPackages : [];
      wizardAptPackagesEl.value = Array.from(new Set(manifestPkgs.concat(scanPkgs, reviewPkgs))).join('\n');
    }
    // Boot config from manifest ext_mgr.boot_config array
    if (wizardBootConfigEl) {
      var bootConfigLines = Array.isArray(ext.boot_config) ? ext.boot_config : [];
      wizardBootConfigEl.value = bootConfigLines.join('\n');
      // Show/hide reboot warning
      if (wizardBootConfigWarningEl) {
        wizardBootConfigWarningEl.style.display = bootConfigLines.length > 0 ? 'flex' : 'none';
      }
    }
  }

  function getWizardInstallPayload() {
    return {
      session_id: importWizardState.sessionId,
      dry_run: '0',
      name: wizardNameEl ? wizardNameEl.value : '',
      version: wizardVersionEl ? wizardVersionEl.value : '',
      type: wizardTypeEl ? wizardTypeEl.value : '',
      stage_profile: 'visible-by-default',
      menu_m: wizardMenuMEl && wizardMenuMEl.checked ? '1' : '0',
      menu_library: wizardMenuLibraryEl && wizardMenuLibraryEl.checked ? '1' : '0',
      menu_system: wizardMenuSystemEl && wizardMenuSystemEl.checked ? '1' : '0',
      settings_only: wizardSettingsOnlyEl && wizardSettingsOnlyEl.checked ? '1' : '0',
      service_name: wizardServiceNameEl ? wizardServiceNameEl.value : '',
      dependencies: wizardDependenciesEl ? wizardDependenciesEl.value : '',
      apt_packages: wizardAptPackagesEl ? wizardAptPackagesEl.value : '',
      boot_config: wizardBootConfigEl ? wizardBootConfigEl.value : ''
    };
  }

  /**
   * Build HTML for scan issues (violations, warnings, upgrades).
   */
  function buildScanIssuesHtml(scan) {
    var html = '';
    var violations = scan.violations || [];
    var warnings = scan.warnings || [];
    var codePatterns = (scan.code_patterns || {}).by_severity || {};
    var codeViolations = codePatterns.violation || [];
    var codeWarnings = codePatterns.warning || [];
    var upgradeables = codePatterns.upgradeable || [];
    var okPatterns = codePatterns.ok || [];

    // Violations (blocks install)
    if (violations.length > 0 || codeViolations.length > 0) {
      html += '<div class="extmgr-review-section extmgr-review-violations">';
      html += '<h4><i class="fa-solid fa-circle-xmark"></i> Violations <span class="extmgr-badge extmgr-badge-error">' + (violations.length + codeViolations.length) + '</span></h4>';
      html += '<p class="extmgr-review-note">These issues block installation.</p>';
      html += '<ul class="extmgr-review-list">';
      violations.forEach(function(v) {
        html += '<li><i class="fa-solid fa-ban"></i> <strong>Path:</strong> ' + escapeHtml(v.path) + ' <span class="extmgr-tag">' + escapeHtml(v.label) + '</span></li>';
      });
      codeViolations.forEach(function(p) {
        html += '<li><i class="fa-solid fa-code"></i> <strong>' + escapeHtml(p.label) + '</strong> in <code>' + escapeHtml(p.file) + '</code></li>';
      });
      html += '</ul></div>';
    }

    // Warnings (require acknowledgment)
    if (warnings.length > 0 || codeWarnings.length > 0) {
      html += '<div class="extmgr-review-section extmgr-review-warnings">';
      html += '<h4><i class="fa-solid fa-triangle-exclamation"></i> Warnings <span class="extmgr-badge extmgr-badge-warning">' + (warnings.length + codeWarnings.length) + '</span></h4>';
      html += '<p class="extmgr-review-note">Review before proceeding.</p>';
      html += '<ul class="extmgr-review-list">';
      warnings.forEach(function(w) {
        html += '<li><i class="fa-solid fa-route"></i> <strong>Path:</strong> ' + escapeHtml(w.path) + ' <span class="extmgr-tag">' + escapeHtml(w.label) + '</span></li>';
      });
      codeWarnings.forEach(function(p) {
        html += '<li><i class="fa-solid fa-code"></i> <strong>' + escapeHtml(p.label) + '</strong> in <code>' + escapeHtml(p.file) + '</code>';
        if (p.fix) html += '<br><small class="extmgr-fix-hint"><i class="fa-solid fa-lightbulb"></i> ' + escapeHtml(p.fix) + '</small>';
        html += '</li>';
      });
      html += '</ul></div>';
    }

    // Auto-upgrades (informational)
    if (upgradeables.length > 0) {
      html += '<div class="extmgr-review-section extmgr-review-upgrades">';
      html += '<h4><i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Upgrades <span class="extmgr-badge extmgr-badge-info">' + upgradeables.length + '</span></h4>';
      html += '<p class="extmgr-review-note">These will be automatically applied during install.</p>';
      html += '<ul class="extmgr-review-list">';
      upgradeables.forEach(function(p) {
        html += '<li><i class="fa-solid fa-arrow-up"></i> <strong>' + escapeHtml(p.label) + '</strong> in <code>' + escapeHtml(p.file) + '</code>';
        if (p.fix) html += '<br><small class="extmgr-fix-hint"><i class="fa-solid fa-check"></i> ' + escapeHtml(p.fix) + '</small>';
        html += '</li>';
      });
      html += '</ul></div>';
    }

    // OK patterns (verified integrations)
    if (okPatterns.length > 0) {
      html += '<div class="extmgr-review-section extmgr-review-ok">';
      html += '<h4><i class="fa-solid fa-circle-check"></i> Verified <span class="extmgr-badge extmgr-badge-ok">' + okPatterns.length + '</span></h4>';
      html += '<ul class="extmgr-review-list extmgr-review-list-compact">';
      okPatterns.forEach(function(p) {
        html += '<li><i class="fa-solid fa-check"></i> ' + escapeHtml(p.label) + '</li>';
      });
      html += '</ul></div>';
    }

    return html;
  }

  /**
   * Build HTML for install summary (packages, services, scripts).
   */
  function buildInstallSummaryHtml(review, manifest) {
    var html = '<div class="extmgr-review-section extmgr-review-install">';
    html += '<h4><i class="fa-solid fa-box-open"></i> Install Summary</h4>';

    var packages = review.manifestPackages || [];
    var services = review.serviceUnits || [];
    var scripts = review.installScripts || {};
    var bootConfig = ((manifest.ext_mgr || {}).boot_config || []);

    html += '<table class="extmgr-review-table"><tbody>';

    // Packages
    html += '<tr><td><i class="fa-solid fa-cube"></i> APT Packages</td><td>';
    html += packages.length > 0 ? packages.map(escapeHtml).join(', ') : '<span class="extmgr-muted">None</span>';
    html += '</td></tr>';

    // Services
    html += '<tr><td><i class="fa-solid fa-gear"></i> Systemd Services</td><td>';
    html += services.length > 0 ? services.map(escapeHtml).join(', ') : '<span class="extmgr-muted">None</span>';
    html += '</td></tr>';

    // Install scripts
    var scriptList = [];
    if (scripts.install) scriptList.push('install.sh');
    if (scripts.repair) scriptList.push('repair.sh');
    if (scripts.uninstall) scriptList.push('uninstall.sh');
    html += '<tr><td><i class="fa-solid fa-terminal"></i> Scripts</td><td>';
    html += scriptList.length > 0 ? scriptList.join(', ') : '<span class="extmgr-muted">None</span>';
    html += '</td></tr>';

    // Boot config
    html += '<tr><td><i class="fa-solid fa-microchip"></i> Boot Config</td><td>';
    if (bootConfig.length > 0) {
      html += '<span class="extmgr-tag extmgr-tag-warning"><i class="fa-solid fa-rotate"></i> ' + bootConfig.length + ' lines (reboot required)</span>';
    } else {
      html += '<span class="extmgr-muted">None</span>';
    }
    html += '</td></tr>';

    html += '</tbody></table></div>';
    return html;
  }

  function renderWizardReview() {
    if (!wizardReviewJsonEl) {
      return;
    }

    var scan = importWizardState.scan || {};
    var review = importWizardState.review || {};
    var manifest = importWizardState.manifest || {};

    // Build formatted review HTML
    var html = '';

    // Extension info header
    var name = wizardNameEl ? wizardNameEl.value : (manifest.name || importWizardState.extensionId);
    var version = wizardVersionEl ? wizardVersionEl.value : (manifest.version || '');
    var type = wizardTypeEl ? wizardTypeEl.value : ((manifest.ext_mgr || {}).type || 'other');

    html += '<div class="extmgr-review-header">';
    html += '<h4><i class="fa-solid fa-puzzle-piece"></i> ' + escapeHtml(name) + ' <span class="extmgr-version">' + escapeHtml(version) + '</span></h4>';
    html += '<span class="extmgr-type-badge">' + escapeHtml(type) + '</span>';
    html += '</div>';

    // Scan issues
    html += buildScanIssuesHtml(scan);

    // Install summary
    html += buildInstallSummaryHtml(review, manifest);

    // Menu visibility summary
    html += '<div class="extmgr-review-section extmgr-review-menu">';
    html += '<h4><i class="fa-solid fa-bars"></i> Menu Visibility</h4>';
    html += '<div class="extmgr-review-badges">';
    html += (wizardMenuMEl && wizardMenuMEl.checked) ? '<span class="extmgr-badge extmgr-badge-ok">M Menu</span>' : '<span class="extmgr-badge extmgr-badge-muted">M Menu</span>';
    html += (wizardMenuLibraryEl && wizardMenuLibraryEl.checked) ? '<span class="extmgr-badge extmgr-badge-ok">Library</span>' : '<span class="extmgr-badge extmgr-badge-muted">Library</span>';
    html += (wizardMenuSystemEl && wizardMenuSystemEl.checked) ? '<span class="extmgr-badge extmgr-badge-ok">System</span>' : '<span class="extmgr-badge extmgr-badge-muted">System</span>';
    html += '</div></div>';

    // Replace pre content with formatted HTML
    wizardReviewJsonEl.innerHTML = html;
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
    return area === 'header' ? 'Header menu'
      : area === 'library' ? 'Library menu'
      : area === 'm' ? 'Menu'
      : 'M Configuration Tile';
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
      // Create toggle using innerHTML to match exact PHP structure
      var div = document.createElement('div');
      div.className = 'toggle' + (initialVisible ? '' : ' toggle-off');
      div.innerHTML =
        '<label class="toggle-radio" for="' + id + '-off">ON</label>' +
        '<input type="radio" id="' + id + '-on" name="' + id + '" value="On"' + (initialVisible ? ' checked' : '') + '>' +
        '<label class="toggle-radio" for="' + id + '-on">OFF</label>' +
        '<input type="radio" id="' + id + '-off" name="' + id + '" value="Off"' + (!initialVisible ? ' checked' : '') + '>';

      // Attach change listeners to radios - same approach as Extension Manager visibility
      div.querySelectorAll('input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
          if (radio.checked) {
            var isOn = (radio.value === 'On');
            if (isOn) {
              div.classList.remove('toggle-off');
            } else {
              div.classList.add('toggle-off');
            }
            if (typeof onChange === 'function') { onChange(isOn); }
          }
        });
      });

      div.setVisible = function (v) { applyMoodeToggleState(div, v); };
      div.setDisabled = function (d) {
        div.querySelectorAll('input[type="radio"]').forEach(function (r) { r.disabled = d; });
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

    setText(cacheDirPathEl, cache.path || Config.getPath('cacheRoot', '/var/www/extensions/cache'));
    setText(cacheDirUsageEl, asBytes(cache.bytes || 0) + ' in ' + String(cache.fileCount || 0) + ' files');
    setText(backupDirPathEl, backup.path || Config.getPath('backupRoot', '/var/www/extensions/sys/backup'));
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

  function buildServiceStatusBadge(active, sub) {
    var cls = 'extmgr-service-badge';
    var label = active || 'unknown';
    if (active === 'active' && sub === 'running') {
      cls += ' extmgr-service-running';
      label = 'running';
    } else if (active === 'active') {
      cls += ' extmgr-service-active';
    } else if (active === 'inactive') {
      cls += ' extmgr-service-inactive';
    } else if (active === 'failed') {
      cls += ' extmgr-service-failed';
    } else if (active === 'not-found') {
      cls += ' extmgr-service-notfound';
      label = 'not installed';
    }
    return '<span class="' + cls + '">' + escapeHtml(label) + '</span>';
  }

  function buildServiceRow(svc) {
    var name = svc.name || 'unknown';
    var displayName = name.replace('.service', '');
    var badge = buildServiceStatusBadge(svc.active, svc.sub);
    var desc = svc.description ? ' <span class="extmgr-service-desc">' + escapeHtml(svc.description) + '</span>' : '';
    return '<div class="extmgr-service-row">' +
      '<span class="extmgr-service-name">' + escapeHtml(displayName) + '</span>' +
      badge + desc +
      '</div>';
  }

  function renderSystemServices(data) {
    var core = Array.isArray(data.core) ? data.core : [];
    var extMgr = Array.isArray(data.extMgr) ? data.extMgr : [];
    var extensions = Array.isArray(data.extensions) ? data.extensions : [];

    if (servicesCoreListEl) {
      if (core.length === 0) {
        servicesCoreListEl.innerHTML = '<div class="extmgr-note">No core services detected.</div>';
      } else {
        servicesCoreListEl.innerHTML = '<div class="extmgr-services-title">Core moOde Services</div>' +
          core.map(buildServiceRow).join('');
      }
    }

    if (servicesExtmgrListEl) {
      servicesExtmgrListEl.innerHTML = '<div class="extmgr-services-title">Extension Manager Services</div>' +
        extMgr.map(buildServiceRow).join('');
    }

    if (servicesExtensionsListEl) {
      if (extensions.length === 0) {
        servicesExtensionsListEl.innerHTML = '<div class="extmgr-note">No extension services registered.</div>';
      } else {
        servicesExtensionsListEl.innerHTML = '<div class="extmgr-services-title">Extension Services</div>' +
          extensions.map(function (svc) {
            var row = buildServiceRow(svc);
            if (svc.extensionId) {
              row = row.replace('extmgr-service-row">', 'extmgr-service-row" data-ext-id="' + escapeHtml(svc.extensionId) + '">');
            }
            return row;
          }).join('');
      }
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
          ? 'dev'
          : advancedUpdateState.mode === 'release'
            ? 'release'
            : 'main';
      advancedUpdateNoteEl.textContent = payloadWarning
        ? ('Branch discovery warning: ' + payloadWarning + '. Using stored branch list.')
        : ('Modes: release, main, dev, custom URL. Active mode=' + activeModeLabel + '.');
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
      item.menuVisibility = { m: true, library: true, system: false, header: true };
    }
    item.menuVisibility[key] = !!value;
  }

  function visibilityLabel(target, visible) {
    var name = target === 'm' ? 'Menu' : (target === 'library' ? 'Library menu' : (target === 'header' ? 'Header menu' : 'M Configuration Tile'));
    return name + ': ' + (visible ? 'Visible' : 'Hidden');
  }

  function settingsCardLabel(enabled) {
    return 'Configuration Tile: ' + (enabled ? 'Enabled' : 'Disabled');
  }

  function createInlineSwitchControl(labelText, toggleEl) {
    var wrap = document.createElement('div');
    wrap.className = 'extmgr-manager-visibility-row extmgr-manager-visibility-row-inline';

    var label = document.createElement('span');
    label.className = 'extmgr-manager-visibility-label';
    if (labelText === 'Menu' || labelText === 'Configuration Tile' || labelText === 'M Configuration Tile') {
      label.classList.add('extmgr-manager-visibility-label-m');

      var mBadge = document.createElement('span');
      mBadge.className = 'extmgr-mbrand-badge';
      mBadge.setAttribute('aria-hidden', 'true');
      mBadge.textContent = 'm';

      var mText = document.createElement('span');
      mText.className = 'extmgr-mbrand-text';
      mText.textContent = labelText;

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

  // Service status cache, populated by fetchServiceStatuses()
  var serviceStatusCache = {};

  function getExtensionServiceStatus(item) {
    if (!item || !item.id) { return null; }
    var service = item.service || {};
    var serviceName = service.name || '';
    if (!serviceName) { return null; }
    return serviceStatusCache[serviceName] || null;
  }

  function fetchServiceStatuses(callback) {
    api({ action: 'debug_services' })
      .then(function(data) {
        var services = (data && data.data && data.data.services) || [];
        serviceStatusCache = {};
        services.forEach(function(svc) {
          if (svc.name) {
            serviceStatusCache[svc.name] = svc.status || 'unknown';
          }
        });
        if (typeof callback === 'function') { callback(); }
      })
      .catch(function() {
        // Silently fail, statuses will show as unknown
        if (typeof callback === 'function') { callback(); }
      });
  }

  function extensionInfoSummary(item) {
    var info = (item && item.extensionInfo) || {};
    var installMetadata = (item && item.installMetadata) || {};
    var counts = installMetadata.counts || {};
    var service = (item && item.service) || {};
    var version = info.version || item.version || 'unknown';
    var type = info.type || 'unknown';
    var author = info.author || 'unknown';
    var license = info.license || 'unknown';
    var serviceName = service.name || '';
    var bits = [
      '<div class="list-meta-row"><span class="list-meta-label">Version</span><span class="list-meta-value">' + escapeHtml(version) + '</span></div>',
      '<div class="list-meta-row"><span class="list-meta-label">Type</span><span class="list-meta-value">' + escapeHtml(type) + '</span></div>',
      '<div class="list-meta-row"><span class="list-meta-label">Author</span><span class="list-meta-value">' + escapeHtml(author) + '</span></div>',
      '<div class="list-meta-row"><span class="list-meta-label">License</span><span class="list-meta-value">' + escapeHtml(license) + '</span></div>'
    ];
    if (serviceName) {
      bits.push('<div class="list-meta-row"><span class="list-meta-label">Service</span><span class="list-meta-value">' + escapeHtml(serviceName) + '</span></div>');
    }
    if (counts.installedApt) {
      bits.push('<div class="list-meta-row"><span class="list-meta-label">APT Packages</span><span class="list-meta-value">' + escapeHtml(String(counts.installedApt)) + '</span></div>');
    }
    if (counts.installedBundles) {
      bits.push('<div class="list-meta-row"><span class="list-meta-label">Bundled</span><span class="list-meta-value">' + escapeHtml(String(counts.installedBundles)) + '</span></div>');
    }
    return bits.join('');
  }

  function extensionDescription(item) {
    var info = (item && item.extensionInfo) || {};
    return info.description || 'No extension description available.';
  }

  function extensionDetailsSummary(item) {
    var info = (item && item.extensionInfo) || {};
    var manifest = (item && item.manifest) || {};
    var installMetadata = (item && item.installMetadata) || {};
    var counts = installMetadata.counts || {};
    var service = (item && item.service) || {};
    var id = item.id || 'unknown';
    var version = info.version || manifest.version || item.version || 'unknown';
    var templateKitVersion = manifest.template_kit_version || info.template_kit_version || '';
    var type = info.type || 'unknown';
    var author = info.author || manifest.author || 'unknown';
    var license = info.license || manifest.license || 'unknown';
    var serviceName = service.name || '';
    var settingsPage = info.settingsPage || item.entry || ('/' + id + '.php');
    var isCertified = info.certified === true;

    var bits = [
      '<div class="list-sub list-meta">',
      '<div class="list-meta-row"><span class="list-meta-label">Link</span><span class="list-meta-value"><a class="extmgr-item-link" href="' + escapeHtml(settingsPage) + '">' + escapeHtml(settingsPage) + '</a></span></div>',
      '<div class="list-meta-row"><span class="list-meta-label">Extension root</span><span class="list-meta-value">/extensions/installed/' + escapeHtml(id) + '/</span></div>',
      '<div class="list-meta-row"><span class="list-meta-label">ID</span><span class="list-meta-value">' + escapeHtml(id) + '</span></div>'
    ];
    if (isCertified) {
      bits.push('<div class="list-meta-row"><span class="list-meta-label">Certified</span><span class="list-meta-value"><i class="fas fa-certificate" style="color:#c55a11"></i> Yes</span></div>');
    }
    bits.push(
      '<div class="list-meta-row"><span class="list-meta-label">Version</span><span class="list-meta-value">' + escapeHtml(version) + '</span></div>'
    );
    if (templateKitVersion) {
      bits.push('<div class="list-meta-row"><span class="list-meta-label">Template Kit</span><span class="list-meta-value">v' + escapeHtml(templateKitVersion) + '</span></div>');
    }
    bits.push(
      '<div class="list-meta-row"><span class="list-meta-label">Type</span><span class="list-meta-value">' + escapeHtml(type) + '</span></div>',
      '<div class="list-meta-row"><span class="list-meta-label">Author</span><span class="list-meta-value">' + escapeHtml(author) + '</span></div>',
      '<div class="list-meta-row"><span class="list-meta-label">License</span><span class="list-meta-value">' + escapeHtml(license) + '</span></div>'
    );
    if (serviceName) {
      bits.push('<div class="list-meta-row"><span class="list-meta-label">Service</span><span class="list-meta-value">' + escapeHtml(serviceName) + '</span></div>');
    }
    if (counts.installedApt) {
      bits.push('<div class="list-meta-row"><span class="list-meta-label">APT Packages</span><span class="list-meta-value">' + escapeHtml(String(counts.installedApt)) + '</span></div>');
    }
    if (counts.installedBundles) {
      bits.push('<div class="list-meta-row"><span class="list-meta-label">Bundled</span><span class="list-meta-value">' + escapeHtml(String(counts.installedBundles)) + '</span></div>');
    }
    bits.push('</div>');
    return bits.join('');
  }

  function extensionSettingsPage(item) {
    var info = (item && item.extensionInfo) || {};
    return info.settingsPage || item.entry || ('/' + (item.id || '') + '.php');
  }

  function importReviewSummary(review) {
    var r = review || {};
    var counts = r.counts || {};
    var bits = [];
    if (counts.manifestPackages) {
      bits.push('apt=' + String(counts.manifestPackages));
    }
    if (counts.bundledPackageFiles) {
      bits.push('bundles=' + String(counts.bundledPackageFiles));
    }
    if (counts.serviceUnits) {
      bits.push('services=' + String(counts.serviceUnits));
    }
    if (counts.serviceDependencies) {
      bits.push('deps=' + String(counts.serviceDependencies));
    }
    return bits.length ? ' | review: ' + bits.join(', ') : '';
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
      var showHeader = getVisibility(item, 'header');
      var showSettingsCard = getSettingsCardOnly(item);

      // State info
      var stateClass = item.enabled ? 'enabled' : 'disabled';
      var stateLabel = item.enabled ? 'Enabled' : 'Disabled';
      var serviceStatus = getExtensionServiceStatus(item);
      var statusDotClass = 'status-dot-green';
      if (!item.enabled) {
        statusDotClass = 'status-dot-red';
      } else if (serviceStatus === 'failed' || serviceStatus === 'inactive') {
        statusDotClass = 'status-dot-yellow';
      }

      var itemTitle = escapeHtml(item.name || item.id || 'Unnamed extension');
      var description = escapeHtml(extensionDescription(item));
      var settingsPage = extensionSettingsPage(item);
      var info = (item && item.extensionInfo) || {};
      var isCertified = info.certified === true;

      // Collapsible header - always visible
      var header = document.createElement('div');
      header.className = 'list-item-header';

      var headerLeft = document.createElement('div');
      headerLeft.className = 'list-item-header-left';

      var certifiedBadge = isCertified ? '<span class="badge-certified" title="Certified Extension"><i class="fas fa-certificate"></i></span>' : '';
      headerLeft.innerHTML =
        '<div class="list-item-header-title">' +
          '<span class="list-name">' + itemTitle + '</span>' + certifiedBadge +
          '<span class="badge ' + stateClass + '"><span class="status-dot ' + statusDotClass + '"></span>' + stateLabel + '</span>' +
        '</div>' +
        '<div class="list-item-header-desc">' + description + '</div>';

      var headerRight = document.createElement('div');
      headerRight.className = 'list-item-header-right';

      // Enable/Disable toggle in header
      var enableToggle = createMoodeToggle('extmgr-tgl-en-' + item.id, item.enabled, function (newEnabled) {
        var nextEnabled = newEnabled ? '1' : '0';
        enableToggle.setDisabled(true);
        api({ action: 'set_enabled', id: item.id, value: nextEnabled })
          .then(function () {
            item.enabled = newEnabled;
            item.state = newEnabled ? 'active' : 'inactive';
            if (newEnabled) {
              setVisibility(item, 'm', true);
              setVisibility(item, 'library', true);
              setVisibility(item, 'system', false);
            } else {
              setVisibility(item, 'm', false);
              setVisibility(item, 'library', false);
              setVisibility(item, 'system', false);
              item.settingsCardOnly = false;
            }
            setStatus('Extension state updated for ' + (item.name || item.id) + '.', 'ok');
            runRefresh();
          })
          .catch(function (err) {
            enableToggle.setVisible(!newEnabled);
            setStatus(err.message, 'error');
          })
          .finally(function () {
            enableToggle.setDisabled(false);
          });
      });

      // Stop toggle clicks from toggling expansion
      enableToggle.addEventListener('click', function (e) {
        e.stopPropagation();
      });

      var chevron = document.createElement('i');
      chevron.className = 'fas fa-chevron-right list-item-chevron';

      headerRight.appendChild(enableToggle);
      headerRight.appendChild(chevron);
      header.appendChild(headerLeft);
      header.appendChild(headerRight);
      header.addEventListener('click', function () {
        row.classList.toggle('expanded');
      });

      // Collapsible body - hidden by default
      var body = document.createElement('div');
      body.className = 'list-item-body';

      var left = document.createElement('div');
      var statusWarning = '';
      if (item.enabled && (serviceStatus === 'failed' || serviceStatus === 'inactive')) {
        statusWarning = '<div class="list-sub"><span class="status-warning"><i class="fa-solid fa-exclamation-triangle"></i> Service not running</span></div>';
      }

      // Build extension details
      left.innerHTML = statusWarning + extensionDetailsSummary(item);

      var rightWrap = document.createElement('div');
      rightWrap.className = 'item-actions';

      var infoActionGroup = document.createElement('div');
      infoActionGroup.className = 'item-info-actions';

      var toggleGroup = document.createElement('div');
      toggleGroup.className = 'item-toggle-group';

      var actionGroup = document.createElement('div');
      actionGroup.className = 'item-action-group';

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

        var menuHeaderBtn = createMoodeToggle('extmgr-tgl-hdr-' + item.id, showHeader, function (newVisible) {
          menuHeaderBtn.setDisabled(true);
          api({ action: 'set_menu_visibility', id: item.id, menu: 'header', value: newVisible ? '1' : '0' })
            .then(function () {
              setVisibility(item, 'header', newVisible);
              setStatus('Header menu visibility updated for ' + (item.name || item.id) + '.', 'ok');
              runRefresh();
              reloadPageSoon();
            })
            .catch(function (err) {
              menuHeaderBtn.setVisible(!newVisible);
              setStatus(err.message + (err.message.indexOf('Failed to write registry') !== -1 ? ' Check ext-mgr permissions and restart php-fpm.' : ''), 'error');
            })
            .finally(function () { menuHeaderBtn.setDisabled(false); });
        });
        applyTip(menuHeaderBtn, 'extension.menu.header');

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

        var menuMControl = createInlineSwitchControl('Menu', menuMBtn);
        var menuLibraryControl = createInlineSwitchControl('Library menu', menuLibraryBtn);
        var menuHeaderControl = createInlineSwitchControl('Header menu', menuHeaderBtn);
        // Keep settingsCardBtn logic for future use, but hide from UI
        var settingsCardControl = createInlineSwitchControl('Configuration Tile', settingsCardBtn);
        settingsCardControl.style.display = 'none';

      var repairSymlinkBtn = document.createElement('button');
      repairSymlinkBtn.type = 'button';
      repairSymlinkBtn.className = 'btn btn-small btn-primary';
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

      var uninstallBtn = document.createElement('button');
      uninstallBtn.type = 'button';
      uninstallBtn.className = 'btn btn-small btn-primary extmgr-destructive';
      uninstallBtn.textContent = 'Uninstall';
      applyTip(uninstallBtn, 'extension.remove');
      uninstallBtn.addEventListener('click', function () {
        var label = item.name || item.id;
        openActionModal({
          title: 'Uninstall Extension',
          message: 'Uninstall extension "' + label + '"?',
          note: 'This runs uninstall cleanup (including extension uninstall script when present) and permanently removes extension folders, routes and logs.',
          confirmText: 'Uninstall',
          cancelText: 'Cancel',
          onConfirm: function () {
            uninstallBtn.disabled = true;
            api({ action: 'remove_extension', id: item.id })
              .then(function (data) {
                var payload = (data && data.data) || {};
                var uninstall = payload.uninstall || {};
                var scriptState = uninstall.ranExtensionUninstallScript ? 'script: yes' : 'script: no';
                var removedPathsCount = Array.isArray(uninstall.removedPaths) ? uninstall.removedPaths.length : 0;
                var failedPathsCount = Array.isArray(uninstall.failedPaths) ? uninstall.failedPaths.length : 0;
                var removedRegistry = payload.removedFromRegistry !== false;
                var removedInstallDir = payload.removedInstallDir !== false;
                if (!removedRegistry || !removedInstallDir || failedPathsCount > 0) {
                  setStatus('Uninstall incomplete for ' + label + ' (' + scriptState + ', removed paths: ' + removedPathsCount + ', failed paths: ' + failedPathsCount + ').', 'error');
                } else {
                  setStatus('Uninstalled ' + label + ' (' + scriptState + ', removed paths: ' + removedPathsCount + ').', 'ok', { force: true });
                }
                runRefresh();
                reloadPageSoon();
              })
              .catch(function (err) {
                setStatus(err.message, 'error');
              })
              .finally(function () {
                uninstallBtn.disabled = false;
              });
          }
        });
      });

      function applyExtensionActionState(enabled) {
        var disabled = !enabled;
          menuMBtn.setDisabled(disabled);
          menuLibraryBtn.setDisabled(disabled);
          menuHeaderBtn.setDisabled(disabled);
          settingsCardBtn.setDisabled(disabled);
        repairSymlinkBtn.disabled = disabled;

        // Uninstall can stay available for cleanup even when extension is inactive.
        uninstallBtn.disabled = false;

        // Inactive extension: hide action controls from the row to keep state unambiguous.
        menuMControl.style.display = disabled ? 'none' : '';
        menuLibraryControl.style.display = disabled ? 'none' : '';
        menuHeaderControl.style.display = disabled ? 'none' : '';
        repairSymlinkBtn.style.display = disabled ? 'none' : '';

        repairSymlinkBtn.classList.toggle('btn-muted', disabled);

        var reason = disabled ? tip('extension.action.disabled', 'Disabled while extension is inactive. Enable extension first.') : tip('extension.action.ready', '');
        menuMBtn.title = reason;
        menuLibraryBtn.title = reason;
        menuHeaderBtn.title = reason;
        settingsCardBtn.title = reason;
        repairSymlinkBtn.title = reason;
      }

      applyExtensionActionState(item.enabled);

      infoActionGroup.appendChild(repairSymlinkBtn);
      infoActionGroup.appendChild(uninstallBtn);
      if (extMgrLogsModule && typeof extMgrLogsModule.attachExtensionButton === 'function') {
        extMgrLogsModule.attachExtensionButton(item, infoActionGroup);
      }
      left.appendChild(infoActionGroup);

      body.appendChild(left);
      toggleGroup.appendChild(menuMControl);
      toggleGroup.appendChild(menuLibraryControl);
      // TODO: Header menu toggle disabled - dynamic JS radio toggles not triggering change events
      // toggleGroup.appendChild(menuHeaderControl);
      // settingsCardControl kept for future use, but hidden
      rightWrap.appendChild(toggleGroup);
      body.appendChild(rightWrap);
      row.appendChild(header);
      row.appendChild(body);
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
        // Fetch service statuses then render items
        fetchServiceStatuses(function() {
          renderItems(allItems);
        });
        runSystemResources(true);
        runSystemServices(true);
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
        // Fetch service statuses then render items
        fetchServiceStatuses(function() {
          renderItems(allItems);
        });
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

  function runSystemServices(silent) {
    if (!silent) {
      setStatus('Collecting service status...', null);
    }
    return api({ action: 'system_services' })
      .then(function (data) {
        var payload = (data && data.data) || {};
        renderSystemServices(payload);
        if (!silent) {
          setStatus('Service status refreshed.', 'ok');
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
    var confirmed = window.confirm('Clear ' + Config.getPath('cacheRoot', '/var/www/extensions/cache') + ' now? This removes temporary ext-mgr files only.');
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

  function runClearExtensionsFolder() {
    if (!clearExtensionsFolderBtn) {
      return;
    }

    var promptText = tip(
      'manager.troubleshooting.clearExtensionsFolder.prompt',
      'This will remove everything in the extensions and will result in all extensions not functioning. try uninstalling the single extension first. the extension manager will still be working after this action which allows you to reinstall the extensions So, is this your last resort?(type YES).'
    );
    openActionModal({
      title: 'Attention Required',
      message: promptText,
      note: 'Type YES exactly to continue.',
      requiredText: 'YES',
      confirmText: 'Run Last Resort',
      cancelText: 'Cancel',
      onConfirm: function () {
        clearExtensionsFolderBtn.disabled = true;
        setStatus('Running graceful clear for extensions folder...', null);

        api({ action: 'clear_extensions_folder' })
          .then(function (data) {
            var payload = (data && data.data) || {};
            var removedCount = Array.isArray(payload.removedIds) ? payload.removedIds.length : 0;
            var failedCount = Array.isArray(payload.failedIds) ? payload.failedIds.length : 0;
            if (failedCount > 0) {
              setStatus('Clear Extensions Folder finished with warnings. Removed: ' + removedCount + ', failed: ' + failedCount + '.', 'error');
            } else {
              setStatus('Clear Extensions Folder completed. Removed: ' + removedCount + '.', 'ok');
            }
            runRefresh();
            reloadPageSoon();
          })
          .catch(function (err) {
            setStatus(err.message || 'Failed to clear extensions folder.', 'error');
          })
          .finally(function () {
            clearExtensionsFolderBtn.disabled = false;
          });
      },
      onCancel: function () {
        setStatus('Clear Extensions Folder cancelled.', null);
      }
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

  function runRegistrySync(triggerLabel) {
    var source = String(triggerLabel || 'Registry');
    setStatus('Syncing registry and extensions (' + source + ')...', null);
    api({ action: 'registry_sync', prune: '1' })
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
          'Extensions sync complete. total=' + String(summary.total || 0)
            + ' installed=' + String(summary.installed || 0)
            + ' missing=' + String(summary.missing || 0)
            + ' pruned=' + String(summary.pruned || 0)
            + ' discovered=' + String(summary.discovered || 0),
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
  bindIfPresent(refreshServicesBtn, 'click', function () {
    runSystemServices(false);
  });
  bindIfPresent(createBackupBtn, 'click', runCreateBackup);
  bindIfPresent(clearCacheBtn, 'click', runClearCache);
  bindIfPresent(clearExtensionsFolderBtn, 'click', runClearExtensionsFolder);
  bindIfPresent(syncRegistryBtn, 'click', function () {
    runRegistrySync('Registry');
  });

  // Debug buttons
  function showDebugModal(title, data) {
    var json = JSON.stringify(data, null, 2);
    if (maintenanceLogEl) {
      maintenanceLogEl.innerHTML = '<strong>' + title + '</strong><pre style="white-space:pre-wrap;word-break:break-word;max-height:400px;overflow:auto;font-size:0.75rem;background:rgba(0,0,0,0.3);padding:0.5rem;border-radius:4px;margin-top:0.5rem;">' + json.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
    }
    setStatus(title + ' loaded', 'ok');
  }

  bindIfPresent(showRegistryBtn, 'click', function() {
    setStatus('Loading registry...', null);
    fetch(buildApiUrl({ action: 'debug_registry' }))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          showDebugModal('Registry Data', data.data);
        } else {
          setStatus('Failed to load registry: ' + (data.error || 'Unknown error'), 'error');
        }
      })
      .catch(function(err) {
        setStatus('Registry fetch error: ' + err.message, 'error');
      });
  });

  bindIfPresent(showVariablesBtn, 'click', function() {
    setStatus('Loading variables...', null);
    fetch(buildApiUrl({ action: 'debug_variables' }))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          showDebugModal('System Variables', data.data);
        } else {
          setStatus('Failed to load variables: ' + (data.error || 'Unknown error'), 'error');
        }
      })
      .catch(function(err) {
        setStatus('Variables fetch error: ' + err.message, 'error');
      });
  });

  bindIfPresent(showServicesBtn, 'click', function() {
    setStatus('Loading services...', null);
    fetch(buildApiUrl({ action: 'debug_services' }))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          showDebugModal('Running Services', data.data);
        } else {
          setStatus('Failed to load services: ' + (data.error || 'Unknown error'), 'error');
        }
      })
      .catch(function(err) {
        setStatus('Services fetch error: ' + err.message, 'error');
      });
  });

  bindIfPresent(showApiStatusBtn, 'click', function() {
    setStatus('Loading API status...', null);
    fetch(buildApiUrl({ action: 'debug_api' }))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          showDebugModal('API Status', data.data);
        } else {
          setStatus('Failed to load API status: ' + (data.error || 'Unknown error'), 'error');
        }
      })
      .catch(function(err) {
        setStatus('API status fetch error: ' + err.message, 'error');
      });
  });

  bindIfPresent(showDatabaseBtn, 'click', function() {
    setStatus('Testing database permissions...', null);
    fetch(buildApiUrl({ action: 'debug_database' }))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          showDebugModal('Database Test', data.data);
        } else {
          setStatus('Failed to test database: ' + (data.error || 'Unknown error'), 'error');
        }
      })
      .catch(function(err) {
        setStatus('Database test error: ' + err.message, 'error');
      });
  });

  bindIfPresent(showMoodeApiBtn, 'click', function() {
    setStatus('Testing moOde API connectivity...', null);
    fetch(buildApiUrl({ action: 'debug_moode_api' }))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          showDebugModal('moOde API Test', data.data);
        } else {
          setStatus('Failed to test moOde API: ' + (data.error || 'Unknown error'), 'error');
        }
      })
      .catch(function(err) {
        setStatus('moOde API test error: ' + err.message, 'error');
      });
  });

  // Boot Config Management buttons
  bindIfPresent(bootConfigInitBtn, 'click', function() {
    if (!confirm('Initialize boot config system?\n\nThis will add an include directive to config.txt. This is a one-time setup required before extensions can modify boot configuration (SPI, I2C, overlays).')) {
      return;
    }
    setStatus('Initializing boot config system...', null);
    fetch(buildApiUrl({ action: 'boot_config_init' }), { method: 'POST' })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          showDebugModal('Boot Config Initialized', data.data);
          setStatus('Boot config system initialized successfully.', 'ok');
        } else {
          setStatus('Boot config init failed: ' + (data.error || 'Unknown error'), 'error');
          showDebugModal('Boot Config Init Failed', data);
        }
      })
      .catch(function(err) {
        setStatus('Boot config init error: ' + err.message, 'error');
      });
  });

  bindIfPresent(bootConfigStatusBtn, 'click', function() {
    setStatus('Loading boot config status...', null);
    fetch(buildApiUrl({ action: 'boot_config_status' }))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          var statusHtml = '<strong>Boot Config Status</strong><br>';
          statusHtml += '<br>Helper exists: ' + (data.data.helperExists ? '✓' : '✗');
          statusHtml += '<br>Initialized: ' + (data.data.initialized ? '✓ Yes' : '✗ No (run Initialize Boot Config)');
          statusHtml += '<br>Boot root: ' + (data.data.bootRoot || 'unknown');
          statusHtml += '<br>Extensions with boot config: ' + data.data.fragmentCount;
          statusHtml += '<br>Total config lines: ' + data.data.configLines;
          if (maintenanceLogEl) {
            maintenanceLogEl.innerHTML = statusHtml + '<pre style="white-space:pre-wrap;word-break:break-word;max-height:300px;overflow:auto;font-size:0.75rem;background:rgba(0,0,0,0.3);padding:0.5rem;border-radius:4px;margin-top:0.5rem;">' + (data.data.rawOutput || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
          }
          setStatus('Boot config status loaded', 'ok');
        } else {
          setStatus('Failed to load boot config status: ' + (data.error || 'Unknown error'), 'error');
        }
      })
      .catch(function(err) {
        setStatus('Boot config status error: ' + err.message, 'error');
      });
  });

  bindIfPresent(bootConfigListBtn, 'click', function() {
    setStatus('Loading boot config fragments...', null);
    fetch(buildApiUrl({ action: 'boot_config_list' }))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          var fragments = data.data.fragments || [];
          var listHtml = '<strong>Boot Config Fragments</strong><br><br>';
          if (fragments.length === 0) {
            listHtml += 'No extensions have boot configuration.<br>';
          } else {
            listHtml += '<table style="width:100%;font-size:0.85rem;"><tr><th style="text-align:left;">Extension</th><th style="text-align:right;">Lines</th></tr>';
            fragments.forEach(function(f) {
              listHtml += '<tr><td>' + f.extensionId + '</td><td style="text-align:right;">' + f.lineCount + '</td></tr>';
            });
            listHtml += '</table>';
          }
          listHtml += '<br>Total: ' + fragments.length + ' extension(s)';
          if (maintenanceLogEl) {
            maintenanceLogEl.innerHTML = listHtml;
          }
          setStatus('Boot config fragments loaded', 'ok');
        } else {
          setStatus('Failed to load boot config list: ' + (data.error || 'Unknown error'), 'error');
        }
      })
      .catch(function(err) {
        setStatus('Boot config list error: ' + err.message, 'error');
      });
  });

  [
    [managerVisibilityHeaderBtn, 'header'],
    [managerVisibilityLibraryBtn, 'library'],
    [managerVisibilityMBtn, 'm'],
    [managerVisibilitySystemBtn, 'system']
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
    var newFileName = files.length > 0 ? files[0].name : 'No file chosen';
    importExtensionFileNameEl.textContent = newFileName;

    // When a new file is selected, reset wizard state (cleanup existing session if any)
    // This prevents stale data from previous imports
    if (files.length > 0) {
      console.log('[ImportWizard] new file selected, resetting wizard state');
      resetImportWizard({ cleanupServer: true, silent: true, preserveFileInput: true });
    }
  });
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
    setStatus('Uploading package and running scan...', null);
    setImportWizardNote('Uploading package and running scan...', null);
    wizardSetStep('upload');

    apiUpload(file)
      .then(function (data) {
        var payload = (data || {}).data || {};
        importWizardState.sessionId = payload.sessionId || '';
        importWizardState.extensionId = payload.extensionId || '';
        importWizardState.review = payload.review || {};
        importWizardState.scan = payload.scan || {};
        importWizardState.manifest = payload.manifest || {};
        importWizardState.info = payload.info || {};

        setWizardFormFromManifest(importWizardState.manifest, importWizardState.scan, importWizardState.review, importWizardState.info);
        renderWizardReview();

        var violations = Array.isArray((importWizardState.scan || {}).violations) ? importWizardState.scan.violations.length : 0;
        var warnings = Array.isArray((importWizardState.scan || {}).warnings) ? importWizardState.scan.warnings.length : 0;
        var templateUpgrade = (importWizardState.review || {}).templateUpgrade;

        // Check moOde integration from code patterns
        var codePatterns = ((importWizardState.scan || {}).code_patterns || {}).findings || [];
        var usesMoodeHeader = codePatterns.some(function(f) { return f.id === 'uses_moode_header'; });
        var usesMoodeFooter = codePatterns.some(function(f) { return f.id === 'uses_moode_footer'; });
        var hasDynamicHeader = codePatterns.some(function(f) { return f.id === 'has_dynamic_header_control'; });
        var needsHeaderUpgrade = codePatterns.some(function(f) { return f.id === 'hardcoded_navbar_suppress' || f.id === 'hardcoded_header_suppress'; });

        // Check for boot configuration
        var bootConfig = (importWizardState.scan || {}).boot_config || [];
        var requiresReboot = (importWizardState.scan || {}).requires_reboot || false;

        if (wizardScanSummaryEl) {
          var summaryParts = ['Session: ' + importWizardState.sessionId, 'Extension: ' + importWizardState.extensionId, 'violations=' + violations, 'warnings=' + warnings];
          if (templateUpgrade && templateUpgrade.needed) {
            summaryParts.push('template-upgrade=auto');
          }
          // Add moOde integration status
          if (usesMoodeHeader || usesMoodeFooter) {
            var moodeStatus = 'moOde: ' + (usesMoodeHeader ? 'header' : '') + (usesMoodeHeader && usesMoodeFooter ? '+' : '') + (usesMoodeFooter ? 'footer' : '');
            summaryParts.push(moodeStatus);
          }
          if (hasDynamicHeader) {
            summaryParts.push('header-control=dynamic');
          } else if (needsHeaderUpgrade) {
            summaryParts.push('header-control=needs-upgrade');
          }
          // Add boot config status
          if (requiresReboot || bootConfig.length > 0) {
            summaryParts.push('boot-config=' + bootConfig.length + ' lines');
            summaryParts.push('🔄 REBOOT REQUIRED');
          }
          wizardScanSummaryEl.textContent = summaryParts.join(' | ');
        }

        if (importExtensionInstallBtn) {
          console.log('[ImportWizard] enabling install button: sessionId=' + importWizardState.sessionId + ' violations=' + violations);
          importExtensionInstallBtn.disabled = !importWizardState.sessionId || violations > 0;
        }

        wizardSetStep('metadata');
        if (window.ExtMgrImportWizard) {
          window.ExtMgrImportWizard.markStepCompleted('upload');
        }
        var statusNote = 'Scan complete. Review metadata and run install from Step 6.';
        if (templateUpgrade && templateUpgrade.needed) {
          statusNote += ' Template will be auto-upgraded for dynamic header visibility.';
        }
        if (requiresReboot || bootConfig.length > 0) {
          statusNote += ' ⚠️ This extension modifies boot configuration - REBOOT REQUIRED after install.';
        }
        setStatus('Scan complete for ' + (importWizardState.extensionId || 'unknown') + '.', 'ok');
        setImportWizardNote(statusNote, 'ok');
      })
      .catch(function (err) {
        var fullMessage = String((err && err.message) || 'Import wizard failed.');
        setStatus(firstSentence(fullMessage), 'error');
        setImportWizardNote(fullMessage, 'error');
      })
      .finally(function () {
        importExtensionBtn.disabled = false;
      });
  });

  // Only name/version/type trigger review jump
  [wizardNameEl, wizardVersionEl, wizardTypeEl].forEach(function (el) {
    if (!el) {
      return;
    }
    el.addEventListener('change', function () {
      renderWizardReview();
      // Don't jump to review - let user navigate manually
    });
    el.addEventListener('keyup', function () {
      renderWizardReview();
    });
  });

  // Menu/service/package fields update review but don't jump
  [wizardMenuMEl, wizardMenuLibraryEl, wizardMenuSystemEl, wizardSettingsOnlyEl, wizardServiceNameEl, wizardDependenciesEl, wizardAptPackagesEl, wizardBootConfigEl].forEach(function (el) {
    if (!el) {
      return;
    }
    el.addEventListener('change', function () {
      renderWizardReview();
    });
    el.addEventListener('keyup', function () {
      renderWizardReview();
    });
  });

  // Show/hide boot config reboot warning based on content
  if (wizardBootConfigEl && wizardBootConfigWarningEl) {
    wizardBootConfigEl.addEventListener('input', function () {
      var hasContent = wizardBootConfigEl.value.trim().length > 0;
      wizardBootConfigWarningEl.style.display = hasContent ? 'flex' : 'none';
    });
  }

  // Progress bar elements
  var wizardInstallProgressEl = document.getElementById('wizard-install-progress');
  var wizardProgressFillEl = document.getElementById('wizard-progress-fill');
  var wizardProgressStatusEl = document.getElementById('wizard-progress-status');
  var wizardInstallSuccessEl = document.getElementById('wizard-install-success');
  var wizardSuccessMessageEl = document.getElementById('wizard-success-message');

  function showInstallProgress(show) {
    if (wizardInstallProgressEl) {
      wizardInstallProgressEl.style.display = show ? 'block' : 'none';
    }
    if (wizardInstallSuccessEl) {
      wizardInstallSuccessEl.style.display = 'none';
    }
  }

  function updateInstallProgress(percent, status) {
    if (wizardProgressFillEl) {
      wizardProgressFillEl.style.width = percent + '%';
    }
    if (wizardProgressStatusEl) {
      wizardProgressStatusEl.textContent = status;
    }
  }

  function showInstallSuccess(extensionId, message) {
    showInstallProgress(false);
    if (wizardInstallSuccessEl) {
      wizardInstallSuccessEl.style.display = 'block';
    }
    if (wizardSuccessMessageEl) {
      wizardSuccessMessageEl.innerHTML = message || ('Extension <strong>' + extensionId + '</strong> has been successfully installed and is ready to use.');
    }
    // Hide the wizard navigation (Back/Install buttons) after success
    var wizardNav = document.querySelector('.extmgr-wizard-nav');
    if (wizardNav) {
      wizardNav.style.display = 'none';
    }
  }

  function animateInstallProgress(stages, onComplete) {
    var currentStage = 0;
    var currentPercent = 0;

    function animateToTarget(target, status, callback) {
      updateInstallProgress(currentPercent, status);
      var step = function() {
        if (currentPercent < target) {
          currentPercent = Math.min(currentPercent + 2, target);
          updateInstallProgress(currentPercent, status);
          requestAnimationFrame(step);
        } else if (callback) {
          callback();
        }
      };
      step();
    }

    function nextStage() {
      if (currentStage >= stages.length) {
        if (onComplete) onComplete();
        return;
      }
      var stage = stages[currentStage];
      currentStage++;
      animateToTarget(stage.percent, stage.status, function() {
        setTimeout(nextStage, stage.delay || 200);
      });
    }

    nextStage();
  }

  console.log('[ImportWizard] binding install button, element found:', !!importExtensionInstallBtn);
  if (importExtensionInstallBtn) {
    console.log('[ImportWizard] button id:', importExtensionInstallBtn.id, 'disabled:', importExtensionInstallBtn.disabled);
  }

  /**
   * Execute the actual install process (dry-run + real install).
   * Called after any warning dialogs have been confirmed.
   */
  function executeInstall() {
    importExtensionInstallBtn.disabled = true;
    setStatus('Installing from staged review session...', null);
    wizardSetStep('review');

    // Show progress bar
    showInstallProgress(true);

    // Phase 1: Dry-run validation
    var dryRunStages = [
      { percent: 10, status: 'Running dry-run validation...', delay: 300 },
      { percent: 25, status: 'Checking package structure...', delay: 200 },
      { percent: 40, status: 'Validating dependencies...', delay: 300 }
    ];

    // Phase 2: Real install (after dry-run succeeds)
    var installStages = [
      { percent: 50, status: 'Dry-run passed! Installing...', delay: 300 },
      { percent: 60, status: 'Creating extension directory...', delay: 200 },
      { percent: 70, status: 'Extracting files...', delay: 400 },
      { percent: 80, status: 'Setting permissions...', delay: 200 },
      { percent: 90, status: 'Running install script...', delay: 300 },
      { percent: 95, status: 'Updating registry...', delay: 200 }
    ];

    // Start dry-run progress animation
    animateInstallProgress(dryRunStages, function() {
      // Animation done, wait for dry-run API response
    });

    var installPayload = getWizardInstallPayload();

    // Step 1: Run dry-run first
    var dryRunPayload = Object.assign({}, installPayload, { dry_run: '1' });
    console.log('[ImportWizard] calling dry-run with payload:', JSON.stringify(dryRunPayload));

    apiInstallFromSession(dryRunPayload)
      .then(function (dryRunData) {
        console.log('[ImportWizard] dry-run success:', JSON.stringify(dryRunData));

        // Dry-run passed, now run real install
        updateInstallProgress(45, 'Dry-run validation passed!');

        // Animate through install stages
        setTimeout(function() {
          animateInstallProgress(installStages, function() {
            // Animation done, wait for real install API response
          });

          // Step 2: Run real install
          var realPayload = Object.assign({}, installPayload, { dry_run: '0' });
          console.log('[ImportWizard] calling real install with payload:', JSON.stringify(realPayload));

          apiInstallFromSession(realPayload)
            .then(function (data) {
              console.log('[ImportWizard] install success:', JSON.stringify(data));
              var payload = (data || {}).data || {};
              var importedId = payload.extensionId || importWizardState.extensionId || 'unknown';

              // Check for skipped packages
              var packageStatus = payload.packageStatus || {};
              var skipped = packageStatus.skipped || [];
              var skippedWarning = '';
              if (skipped.length > 0) {
                skippedWarning = '<br><span style="color: #c55a11;">⚠ Some packages could not be installed: ' + skipped.join(', ') +
                  '</span><br><small style="opacity:0.7;">The extension may still work if these are optional or already available.</small>';
              }

              // Check for boot config changes requiring reboot
              var bootConfigStatus = payload.bootConfigStatus || {};
              var rebootNotice = '';
              if (bootConfigStatus.requiresReboot) {
                var bootLines = bootConfigStatus.lines || [];
                rebootNotice = '<br><span style="color: #c55a11; font-weight: bold;">🔄 REBOOT REQUIRED</span>' +
                  '<br><small style="opacity:0.7;">Boot configuration added (' + bootLines.length + ' lines). ' +
                  'Please reboot your device for hardware settings to take effect.</small>';
              }

              // Complete progress then show success
              updateInstallProgress(100, 'Installation complete!');
              setTimeout(function() {
                var summaryHtml = importReviewSummary(payload.review || {});
                showInstallSuccess(importedId,
                  'Extension <strong>' + importedId + '</strong> has been successfully installed.' +
                  rebootNotice +
                  skippedWarning +
                  (summaryHtml ? '<br><small style="opacity:0.7">' + summaryHtml.replace(/<br>/g, ' | ') + '</small>' : '')
                );
                setStatus('Extension imported: ' + importedId + (bootConfigStatus.requiresReboot ? ' (reboot required)' : ''), 'ok');
                setImportWizardNote('Extension imported: ' + importedId + summaryHtml, 'ok');
              }, 400);

              importWizardState.sessionId = '';
              if (importExtensionInstallBtn) {
                importExtensionInstallBtn.disabled = true;
              }
              runRefresh();
            })
            .catch(function (err) {
              console.error('[ImportWizard] real install failed:', err);
              var fullMessage = String((err && err.message) || 'Install failed after dry-run.');
              showInstallProgress(false);
              setStatus(firstSentence(fullMessage), 'error');
              setImportWizardNote(fullMessage, 'error');
              if (importExtensionInstallBtn && importWizardState.sessionId) {
                importExtensionInstallBtn.disabled = false;
              }
            });
        }, 500);
      })
      .catch(function (err) {
        console.error('[ImportWizard] dry-run failed:', err);
          var fullMessage = String((err && err.message) || 'Dry-run validation failed.');
          showInstallProgress(false);
          setStatus('Dry-run failed: ' + firstSentence(fullMessage), 'error');
          setImportWizardNote('Dry-run validation failed: ' + fullMessage, 'error');
          if (importExtensionInstallBtn && importWizardState.sessionId) {
            importExtensionInstallBtn.disabled = false;
          }
        });
  }

  /**
   * Abort the import and clean up staged files.
   */
  function abortImport() {
    if (!importWizardState.sessionId) return Promise.resolve();

    setStatus('Cancelling import...', null);
    return resetImportWizard({ cleanupServer: true }).then(function() {
      setStatus('Import cancelled. Staged files cleaned up.', 'ok');
      setImportWizardNote('Import cancelled. You can upload a new package.', null);
    }).catch(function(err) {
      console.error('[ImportWizard] abort failed:', err);
      setStatus('Abort failed: ' + String(err && err.message || err), 'error');
    });
  }

  // Use direct addEventListener as fallback in case bindIfPresent has issues
  if (importExtensionInstallBtn) {
    importExtensionInstallBtn.addEventListener('click', function (e) {
      console.log('[ImportWizard] install button clicked (direct), sessionId=' + importWizardState.sessionId);
      e.preventDefault();
      e.stopPropagation();

      if (!importWizardState.sessionId) {
        setStatus('Upload + scan first to create a staged session.', 'error');
        setImportWizardNote('Upload + scan first to create a staged session.', 'error');
        return;
      }

      // Check for scan warnings/errors before proceeding
      var issues = countScanIssues(importWizardState.scan);
      console.log('[ImportWizard] scan issues:', JSON.stringify(issues));

      if (issues.errors > 0 || issues.warnings > 0) {
        // Show warning dialog and wait for user decision
        showImportWarningDialog(issues).then(function(proceed) {
          if (proceed) {
            console.log('[ImportWizard] user chose to proceed despite warnings');
            executeInstall();
          } else {
            console.log('[ImportWizard] user cancelled import');
            abortImport();
          }
        });
      } else {
        // No issues, proceed directly
        executeInstall();
      }
    });
  } else {
    console.error('[ImportWizard] install button element NOT FOUND!');
  }

  // Keep bindIfPresent as backup
  bindIfPresent(importExtensionInstallBtn, 'click', function () {
    console.log('[ImportWizard] install button clicked (bindIfPresent), sessionId=' + importWizardState.sessionId);
    // Handler already attached above, this is just for logging
  });

  // Wizard close/done button handler
  var wizardCloseBtn = document.getElementById('wizard-close-btn');
  bindIfPresent(wizardCloseBtn, 'click', function () {
    // Full reset including server cleanup if there's a session
    resetImportWizard({ cleanupServer: true });
  });

  bindIfPresent(systemUpdateBtn, 'click', function () {
    runRegistrySync('Extensions');
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
      managerButtonId: 'open-extmgr-logs-btn',
      managerDownloadButtonId: 'download-extmgr-logs-btn'
    });
  }

  // Load Config first, then initialize tooltips and status
  Config.load()
    .then(function() {
      console.log('[ext-mgr] Config loaded');
      VariablesManager.init();
      ImportWizard.init();
    })
    .catch(function(err) {
      console.warn('[ext-mgr] Config load error:', err);
    })
    .finally(function() {
      loadTooltipSnippets()
        .finally(function () {
          applyStaticTooltips();
          loadStatusAndList(true).then(function () {
            setRunUpdateButtonState();
            clearStatus();
          });
        });
    });
})();
