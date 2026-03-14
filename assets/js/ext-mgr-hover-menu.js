(function () {
  'use strict';

  if (window.__extMgrHoverMenuInit) {
    return;
  }
  window.__extMgrHoverMenuInit = true;

  var PAYLOAD_CACHE = null;
  var PAYLOAD_CACHE_AT = 0;
  var PAYLOAD_CACHE_TTL_MS = 10000;
  var API_URLS = ['/ext-mgr-api.php', '/extensions/sys/ext-mgr-api.php'];
  var LAST_LIBRARY_SIG = '';
  var LAST_MMENU_SIG = '';
  var LAST_SYSTEM_SIG = '';
  var LAST_CONFIGURE_SIG = '';
  var LAST_HEADER_SIG = '';

  function esc(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function normalizePath(url) {
    try {
      return new URL(url, window.location.origin).pathname;
    } catch (e) {
      return String(url || '');
    }
  }

  function sortPinnedFirst(items) {
    var pinned = [];
    var rest = [];
    var i;

    for (i = 0; i < items.length; i += 1) {
      if (items[i] && items[i].pinned) {
        pinned.push(items[i]);
      } else {
        rest.push(items[i]);
      }
    }

    return pinned.concat(rest);
  }

  function normalizeIconClass(value, fallback) {
    var raw = String(value || '').trim();
    if (!raw || raw.indexOf('fa-') === -1 || /[^a-z0-9\-\s]/i.test(raw)) {
      return fallback || 'fa-solid fa-sharp fa-puzzle-piece';
    }
    return raw;
  }

  function extensionIcon(item, fallback) {
    var row = item || {};
    var info = row.extensionInfo || {};
    return normalizeIconClass(info.iconClass || row.iconClass, fallback || 'fa-solid fa-sharp fa-globe');
  }

  function toBool(value, fallback) {
    if (typeof value === 'boolean') {
      return value;
    }
    return !!fallback;
  }

  function fetchApiListWithFallback() {
    var urls = API_URLS.slice();
    var index = 0;

    function next() {
      if (index >= urls.length) {
        return Promise.resolve({ ok: false, data: { extensions: [], meta: {} } });
      }

      var url = urls[index];
      index += 1;

      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: 'action=list'
      })
        .then(function (res) {
          return res.json().catch(function () {
            return { ok: false };
          }).then(function (data) {
            if (res.ok && data && data.ok) {
              return data;
            }
              if (!res.ok || !data || !data.ok || res.status === 404 || res.status === 0) {
              return next();
            }
            return data || { ok: false };
          });
        })
        .catch(function () {
          return next();
        });
    }

    return next();
  }

  function fetchState() {
    var now = Date.now();
    if (PAYLOAD_CACHE && (now - PAYLOAD_CACHE_AT) < PAYLOAD_CACHE_TTL_MS) {
      return Promise.resolve(PAYLOAD_CACHE);
    }

    return fetchApiListWithFallback().then(function (payload) {
      var data = payload && payload.data ? payload.data : { extensions: [], meta: {} };
      PAYLOAD_CACHE = data;
      PAYLOAD_CACHE_AT = Date.now();
      return data;
    });
  }

  function renderList(host, items) {
    if (!host) {
      return;
    }

    var ordered = sortPinnedFirst(items || []);
    var currentPath = normalizePath(window.location.pathname || '');
    var html = '';
    var i;

    for (i = 0; i < ordered.length; i += 1) {
      var item = ordered[i] || {};
      var id = String(item.id || '');
      var name = String(item.name || id);
      var entry = item.menuEntry || item.entry || ('/' + id + '.php');

      if (!item.enabled || !item.menuVisibility || !item.menuVisibility.library) {
        continue;
      }

      html += '<a class="btn extmgr-hover-item' + (normalizePath(entry) === currentPath ? ' active' : '') + '" href="' + esc(entry) + '">';
      html += '<i class="' + esc(extensionIcon(item, 'fa-solid fa-sharp fa-globe')) + '" style="margin-right:.5em;"></i>' + esc(name);
      html += '</a>';
    }

    host.innerHTML = html || '<span style="display:block;padding:8px 12px 8px 2.1em;color:#aaa;">No visible extensions</span>';
  }

  function applyManagerVisibility(meta, refs) {
    var managerVisibility = (meta && meta.managerVisibility) || {};
    var showHeader = toBool(managerVisibility.header, true);
    var showLibrary = toBool(managerVisibility.library, true);
    var showSystem = toBool(managerVisibility.system, true);

    var headerBtn = document.getElementById('ext-mgr-btn');
    if (headerBtn) {
      headerBtn.style.display = showHeader ? '' : 'none';
    }

    if (refs && refs.wrap) {
      refs.wrap.style.display = showLibrary ? '' : 'none';
    }

    var managerLinks = document.querySelectorAll(
      '#sys-cmds a[href="/ext-mgr.php"], #sys-cmds a[href="ext-mgr.php"], #sys-cmds a[href="/extensions-manager.php"], #sys-cmds a[href="extensions-manager.php"], #configure-modal a[href="/ext-mgr.php"], #configure-modal a[href="ext-mgr.php"], #configure-modal a[href="/extensions-manager.php"], #configure-modal a[href="extensions-manager.php"]'
    );
    var i;
    for (i = 0; i < managerLinks.length; i += 1) {
      managerLinks[i].style.display = showSystem ? '' : 'none';
    }
  }

  function renderHeaderManagerButton(meta) {
    var managerVisibility = (meta && meta.managerVisibility) || {};
    var showHeader = toBool(managerVisibility.header, true);
    var sig = String(showHeader);
    if (sig === LAST_HEADER_SIG) {
      return;
    }
    LAST_HEADER_SIG = sig;

    var tabs = document.getElementById('config-tabs');
    if (!tabs) {
      return;
    }

    var existing = document.getElementById('ext-mgr-btn');
    if (!showHeader) {
      if (existing) {
        existing.style.display = 'none';
      }
      return;
    }

    if (existing) {
      existing.href = '/ext-mgr.php';
      existing.style.display = '';
      return;
    }

    var btn = document.createElement('a');
    btn.id = 'ext-mgr-btn';
    btn.className = 'btn extmgr-header-entry';
    btn.href = '/ext-mgr.php';
    btn.innerHTML = '<span>Extensions</span><i class="fa-solid fa-sharp fa-puzzle-piece"></i>';

    var marker = document.getElementById('per-config-btn');
    if (marker && marker.parentNode === tabs && marker.nextSibling) {
      tabs.insertBefore(btn, marker.nextSibling);
      return;
    }

    tabs.appendChild(btn);
  }

  function findLibraryMenuContainer() {
    return document.querySelector('#viewswitch .dropdown-menu, .viewswitch .dropdown-menu, ul.dropdown-menu.context-menu');
  }

  function removeExistingLibraryInjected(container) {
    if (!container) {
      return;
    }
    var existing = container.querySelectorAll('.extmgr-library-divider, .extmgr-library-entry, .extmgr-library-header');
    var i;
    for (i = 0; i < existing.length; i += 1) {
      if (existing[i] && existing[i].parentNode) {
        existing[i].parentNode.removeChild(existing[i]);
      }
    }
  }

  function isManagerEntry(item) {
    var row = item || {};
    var id = String(row.id || '').toLowerCase();
    var entry = row.menuEntry || row.entry || ('/' + String(row.id || '') + '.php');
    var path = normalizePath(entry).toLowerCase();
    return id === 'ext-mgr' || path === '/ext-mgr.php';
  }

  function hasExistingManagerLink(container) {
    if (!container || !container.querySelector) {
      return false;
    }
    return !!container.querySelector(
      'a[href="/ext-mgr.php"], a[href="ext-mgr.php"], a[href="/extensions-manager.php"], a[href="extensions-manager.php"], button[href="/ext-mgr.php"], button[href="ext-mgr.php"], button[href="/extensions-manager.php"], button[href="extensions-manager.php"]'
    );
  }

  function applyLibraryManagerLinkVisibility(container, visible) {
    if (!container || !container.querySelectorAll) {
      return;
    }

    var links = container.querySelectorAll(
      'a[href="/ext-mgr.php"], a[href="ext-mgr.php"], a[href="/extensions-manager.php"], a[href="extensions-manager.php"], button[href="/ext-mgr.php"], button[href="ext-mgr.php"], button[href="/extensions-manager.php"], button[href="extensions-manager.php"]'
    );
    var i;
    for (i = 0; i < links.length; i += 1) {
      links[i].style.display = visible ? '' : 'none';
    }
  }

  function renderLibraryMenu(items, meta) {
    var container = findLibraryMenuContainer();
    if (!container) {
      return;
    }

    var managerVisibility = (meta && meta.managerVisibility) || {};
    var showManagerInLibrary = managerVisibility.library !== false;
    applyLibraryManagerLinkVisibility(container, showManagerInLibrary);

    var visibleItems = [];
    var i;
    for (i = 0; i < items.length; i += 1) {
      var candidate = items[i] || {};
      if (!candidate.enabled || !candidate.menuVisibility || !candidate.menuVisibility.library) {
        continue;
      }
      if (isManagerEntry(candidate)) {
        continue;
      }
      visibleItems.push(candidate);
    }

    var sig = String(showManagerInLibrary) + '|' + visibleItems.map(function (row) {
      return String(row.id || '');
    }).join(',');
    if (sig === LAST_LIBRARY_SIG) {
      return;
    }
    LAST_LIBRARY_SIG = sig;

    removeExistingLibraryInjected(container);

    if (!showManagerInLibrary && visibleItems.length === 0) {
      return;
    }

    var divider = document.createElement('div');
    divider.className = 'extmgr-library-divider';
    divider.setAttribute('aria-hidden', 'true');
    divider.style.borderTop = '1px solid rgba(128,128,128,.2)';
    divider.style.margin = '6px 0 4px';
    container.appendChild(divider);

    var header = document.createElement('div');
    header.className = 'extmgr-library-header';
    header.style.fontSize = '0.78em';
    header.style.opacity = '0.72';
    header.style.padding = '4px 12px 2px';
    header.textContent = 'Extensions';
    container.appendChild(header);

    if (showManagerInLibrary && !hasExistingManagerLink(container)) {
      var managerBtn = document.createElement('a');
      managerBtn.className = 'btn extmgr-library-entry';
      managerBtn.setAttribute('aria-label', 'Extensions Manager');
      managerBtn.href = '/ext-mgr.php';
      managerBtn.style.fontSize = '0.92em';
      managerBtn.style.opacity = '0.95';
      managerBtn.style.borderColor = 'transparent';
      managerBtn.innerHTML = '<i class="fa-solid fa-sharp fa-puzzle-piece"></i> Extensions Manager';
      container.appendChild(managerBtn);
    }

    for (i = 0; i < visibleItems.length; i += 1) {
      var item = visibleItems[i] || {};
      var id = String(item.id || '');
      var name = String(item.name || id || 'Extension');
      var entry = item.menuEntry || item.entry || ('/' + id + '.php');

      var extBtn = document.createElement('a');
      extBtn.className = 'btn extmgr-library-entry';
      extBtn.setAttribute('aria-label', name);
      extBtn.href = entry;
      extBtn.style.fontSize = '0.92em';
      extBtn.style.opacity = '0.92';
      extBtn.style.borderColor = 'transparent';
      extBtn.innerHTML = '<i class="' + esc(extensionIcon(item, 'fa-solid fa-sharp fa-globe')) + '"></i> ' + esc(name);
      container.appendChild(extBtn);
    }
  }

  function removeExistingMMenuInjected(container) {
    var existing = container.querySelectorAll('.extmgr-mmenu-divider, .extmgr-mmenu-header, .extmgr-mmenu-entry');
    var i;
    for (i = 0; i < existing.length; i += 1) {
      if (existing[i] && existing[i].parentNode) {
        existing[i].parentNode.removeChild(existing[i]);
      }
    }
  }

  function findMMenuContainer() {
    var selectors = [
      'ul[aria-labelledby="menu-settings"]',
      '#menu-settings ~ ul.dropdown-menu',
      '#menu-settings ~ ul'
    ];
    var i;

    for (i = 0; i < selectors.length; i += 1) {
      var hit = document.querySelector(selectors[i]);
      if (hit) {
        return hit;
      }
    }

    return null;
  }

  function appendMMenuEntry(container, entryHref, label, iconClass, useListItem) {
    if (useListItem) {
      var li = document.createElement('li');
      li.className = 'extmgr-mmenu-entry';
      li.style.listStyle = 'none';

      var a = document.createElement('a');
      a.href = entryHref;
      a.className = 'extmgr-mmenu-link';
      a.style.display = 'block';
      a.style.padding = '8px 12px';
      a.style.textDecoration = 'none';
      a.style.color = 'inherit';
      a.style.lineHeight = '1.25';
      a.innerHTML = '<i class="' + esc(iconClass || 'fa-solid fa-sharp fa-puzzle-piece') + '" style="margin-right:.5em;"></i>' + esc(label);
      li.appendChild(a);
      container.appendChild(li);
      return;
    }

    var link = document.createElement('a');
    link.href = entryHref;
    link.className = 'extmgr-mmenu-entry';
    link.style.display = 'block';
    link.style.padding = '0.7em 1em';
    link.style.textDecoration = 'none';
    link.style.color = 'inherit';
    link.innerHTML = '<i class="' + esc(iconClass || 'fa-solid fa-sharp fa-puzzle-piece') + '" style="margin-right:.5em;"></i>' + esc(label);
    container.appendChild(link);
  }

  function renderMMenu(items, meta) {
    var container = findMMenuContainer();
    if (!container) {
      return;
    }
    if (container.closest && container.closest('#configure-modal')) {
      return;
    }

    var managerVisibility = (meta && meta.managerVisibility) || {};
    var showManagerInM = toBool(managerVisibility.m, true);
    var visible = [];
    var i;

    for (i = 0; i < items.length; i += 1) {
      var item = items[i] || {};
      if (!item.enabled || !item.menuVisibility || !item.menuVisibility.m) {
        continue;
      }
      visible.push(item);
    }

    if (!showManagerInM && visible.length === 0) {
      if (LAST_MMENU_SIG !== 'empty') {
        removeExistingMMenuInjected(container);
        LAST_MMENU_SIG = 'empty';
      }
      return;
    }

    var nextSig = String(showManagerInM) + '|' + visible.map(function (row) {
      return String(row.id || '');
    }).join(',');
    if (nextSig === LAST_MMENU_SIG) {
      return;
    }
    LAST_MMENU_SIG = nextSig;
    removeExistingMMenuInjected(container);

    var useListItem = container.tagName === 'UL' || container.querySelector('li') !== null;
    if (useListItem) {
      var dividerLi = document.createElement('li');
      dividerLi.className = 'extmgr-mmenu-divider';
      dividerLi.style.listStyle = 'none';
      dividerLi.style.borderTop = '1px solid rgba(128,128,128,.25)';
      dividerLi.style.margin = '6px 0';
      container.appendChild(dividerLi);

      var headerLi = document.createElement('li');
      headerLi.className = 'extmgr-mmenu-header';
      headerLi.style.listStyle = 'none';
      headerLi.style.padding = '4px 12px';
      headerLi.style.fontSize = '0.8em';
      headerLi.style.opacity = '0.8';
      headerLi.textContent = 'Extensions';
      container.appendChild(headerLi);
    } else {
      var divider = document.createElement('div');
      divider.className = 'extmgr-mmenu-divider';
      divider.style.borderTop = '1px solid rgba(128,128,128,.25)';
      divider.style.margin = '6px 0';
      container.appendChild(divider);

      var header = document.createElement('div');
      header.className = 'extmgr-mmenu-header';
      header.style.padding = '4px 12px';
      header.style.fontSize = '0.8em';
      header.style.opacity = '0.8';
      header.textContent = 'Extensions';
      container.appendChild(header);
    }

    if (showManagerInM) {
      appendMMenuEntry(container, '/ext-mgr.php', 'Extensions Manager', 'fa-solid fa-sharp fa-puzzle-piece', useListItem);
    }

    for (i = 0; i < visible.length; i += 1) {
      var ext = visible[i] || {};
      var id = String(ext.id || '');
      var name = String(ext.name || id || 'Extension');
      var entry = ext.menuEntry || ext.entry || ('/' + id + '.php');
      appendMMenuEntry(container, entry, name, extensionIcon(ext, 'fa-solid fa-sharp fa-globe'), useListItem);
    }
  }

  function removeExistingSystemMenuInjected(container) {
    var existing = container.querySelectorAll('.extmgr-system-divider, .extmgr-system-header, .extmgr-system-entry');
    var i;
    for (i = 0; i < existing.length; i += 1) {
      if (existing[i] && existing[i].parentNode) {
        existing[i].parentNode.removeChild(existing[i]);
      }
    }
  }

  function findSystemMenuContainer() {
    return document.querySelector(
      '#sys-cmds .dropdown-menu, #sys-cmds ul, #context-menu ul, .modal #context-menu ul, .context-menu ul, ul[aria-labelledby="sys-cmds"]'
    );
  }

  function renderSystemMenu() {
    var container = findSystemMenuContainer();
    if (container) {
      removeExistingSystemMenuInjected(container);
    }
    LAST_SYSTEM_SIG = 'disabled';
  }

  function findConfigureTileList() {
    return document.querySelector('#configure-modal #configure ul');
  }

  function removeExistingConfigureTile(list) {
    if (!list) {
      return;
    }
    var existing = list.querySelectorAll('.extmgr-configure-entry');
    var i;
    for (i = 0; i < existing.length; i += 1) {
      if (existing[i] && existing[i].parentNode) {
        existing[i].parentNode.removeChild(existing[i]);
      }
    }
  }

  function appendConfigureEntry(list, entry) {
    var li = document.createElement('li');
    li.className = 'extmgr-configure-entry';

    var link = document.createElement('a');
    link.className = 'btn btn-medium btn-primary config-btn';
    link.href = entry.href || '#';
    link.setAttribute('data-extmgr-entry', entry.id || '');
    link.innerHTML = '<i class="' + esc(entry.iconClass || 'fa-solid fa-sharp fa-puzzle-piece') + '"></i><br>' + esc(entry.label || 'Extension');
    if (entry.title) {
      link.title = entry.title;
    }

    li.appendChild(link);
    list.appendChild(li);
  }

  function renderConfigureTile(items, meta) {
    var list = findConfigureTileList();
    if (!list) {
      return;
    }

    var managerVisible = !!(meta && meta.managerVisibility && meta.managerVisibility.system);
    var settingsCardItems = (items || []).filter(function (item) {
      return item && item.enabled && item.settingsCardOnly && (item.menuEntry || item.entry);
    });
    var sig = String(managerVisible) + '|' + settingsCardItems.map(function (item) {
      return [item.id || '', item.menuEntry || item.entry || ''].join(':');
    }).join('|');

    if (sig === LAST_CONFIGURE_SIG) {
      return;
    }
    LAST_CONFIGURE_SIG = sig;

    removeExistingConfigureTile(list);

    if (managerVisible) {
      appendConfigureEntry(list, {
        id: '__manager__',
        href: '/ext-mgr.php',
        iconClass: 'fa-solid fa-sharp fa-puzzle-piece',
        label: 'Extensions',
        title: 'Open Extensions manager'
      });
    }

    settingsCardItems.forEach(function (item) {
      appendConfigureEntry(list, {
        id: item.id,
        href: item.menuEntry || item.entry,
        iconClass: extensionIcon(item, 'fa-solid fa-sharp fa-globe'),
        label: item.name || item.id,
        title: 'Open ' + (item.name || item.id)
      });
    });
  }

  function ensureHostElements() {
    var wrap = document.querySelector('.extmgr-hover-menu');
    var panel = document.getElementById('extmgr-hover-panel');
    var listHost = document.getElementById('extmgr-hover-list');

    if (wrap && panel && listHost) {
      var wrapInLibraryMenu = !!wrap.closest('#viewswitch, .viewswitch, .dropdown-menu');
      if (wrapInLibraryMenu) {
        return { wrap: wrap, panel: panel, listHost: listHost };
      }

      if (wrap.parentNode) {
        wrap.parentNode.removeChild(wrap);
      }
    }

    return null;
  }

  function loadExtensions(host) {
    fetchState().then(function (payload) {
      var items = payload.extensions || [];
      renderList(host, items);
      renderLibraryMenu(items, payload.meta || {});
      renderMMenu(items, payload.meta || {});
      renderSystemMenu();
      renderConfigureTile(items, payload.meta || {});
      renderHeaderManagerButton(payload.meta || {});
    });
  }

  function observeMMenu() {
    if (!window.MutationObserver) {
      return;
    }

    var timer = null;
    var observer = new MutationObserver(function () {
      if (timer) {
        window.clearTimeout(timer);
      }
      timer = window.setTimeout(function () {
        fetchState().then(function (payload) {
          var items = payload.extensions || [];
          renderLibraryMenu(items, payload.meta || {});
          renderMMenu(items, payload.meta || {});
          renderSystemMenu();
          renderConfigureTile(items, payload.meta || {});
          renderHeaderManagerButton(payload.meta || {});
        });
      }, 120);
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var refs = ensureHostElements();

    if (refs) {
      var wrap = refs.wrap;
      var panel = refs.panel;
      var listHost = refs.listHost;

      wrap.addEventListener('mouseenter', function () {
        panel.style.display = 'block';
        loadExtensions(listHost);
      });

      wrap.addEventListener('mouseleave', function () {
        panel.style.display = 'none';
      });

      wrap.addEventListener('click', function (e) {
        var target = e.target;
        var isExtensionsBtn = target && target.closest && target.closest('.extensions-manager-btn');
        if (!isExtensionsBtn) {
          return;
        }

        e.preventDefault();
        if (panel.style.display === 'block') {
          panel.style.display = 'none';
        } else {
          panel.style.display = 'block';
          loadExtensions(listHost);
        }
      });
    }

    fetchState().then(function (payload) {
      var items = payload.extensions || [];
      renderLibraryMenu(items, payload.meta || {});
      renderMMenu(items, payload.meta || {});
      renderSystemMenu();
      renderConfigureTile(items, payload.meta || {});
      renderHeaderManagerButton(payload.meta || {});
      applyManagerVisibility(payload.meta || {}, refs);
    });
    observeMMenu();
  });
})();
