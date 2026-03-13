(function () {
  'use strict';

  if (window.__extMgrHoverMenuInit) {
    return;
  }
  window.__extMgrHoverMenuInit = true;

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

  var PAYLOAD_CACHE = null;
  var PAYLOAD_CACHE_AT = 0;
  var PAYLOAD_CACHE_TTL_MS = 10000;

  function toBool(value, fallback) {
    if (typeof value === 'boolean') {
      return value;
    }
    return !!fallback;
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

    var systemLinks = document.querySelectorAll(
      '#context-menu a[href="/ext-mgr.php"], #context-menu a[href="ext-mgr.php"], #sys-cmds a[href="/ext-mgr.php"], #sys-cmds a[href="ext-mgr.php"], #configure-modal a[href="/ext-mgr.php"], #configure-modal a[href="ext-mgr.php"]'
    );
    var i;
    for (i = 0; i < systemLinks.length; i += 1) {
      systemLinks[i].style.display = showSystem ? '' : 'none';
    }
  }

  function fetchState() {
    var now = Date.now();
    if (PAYLOAD_CACHE && (now - PAYLOAD_CACHE_AT) < PAYLOAD_CACHE_TTL_MS) {
      return Promise.resolve(PAYLOAD_CACHE);
    }

    return fetch('/ext-mgr-api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body: 'action=list'
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        PAYLOAD_CACHE = (data && data.data) || {};
        if (!Array.isArray(PAYLOAD_CACHE.extensions)) {
          PAYLOAD_CACHE.extensions = [];
        }
        PAYLOAD_CACHE_AT = Date.now();
        return PAYLOAD_CACHE;
      })
      .catch(function () {
        return { extensions: [], meta: {} };
      });
  }

  function renderList(host, items) {
    if (!host) {
      return;
    }

    if (!Array.isArray(items) || items.length === 0) {
      host.innerHTML = '<span style="display:block;padding:8px 12px 8px 2.1em;color:#aaa;">No extensions found</span>';
      return;
    }

    var currentPath = window.location.pathname;
    var ordered = sortPinnedFirst(items);
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
      html += '<i class="fa-solid fa-sharp fa-globe" style="margin-right:.5em;"></i>' + esc(name);
      html += '</a>';
    }

    host.innerHTML = html || '<span style="display:block;padding:8px 12px 8px 2.1em;color:#aaa;">No visible extensions</span>';
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
      '#context-menu ul',
      '#sys-cmds ul',
      '#context-menu .dropdown-menu',
      '#configure-modal ul',
      '#configure-modal .dropdown-menu',
      'ul#context-menu',
      '.context-menu ul',
      '.context-menu .dropdown-menu'
    ];
    var i;

    for (i = 0; i < selectors.length; i += 1) {
      var hit = document.querySelector(selectors[i]);
      if (hit) {
        return hit;
      }
    }

    var configureAnchor = document.querySelector('a[href*="configure.php"], a[href*="#/configure"], a[href="#configure-modal"], [data-target="#configure-modal"]');
    if (!configureAnchor) {
      return null;
    }

    var candidate = configureAnchor.closest('ul, .dropdown-menu, .context-menu, .menu, .modal-body, .modal-content, .menu-list');
    return candidate || configureAnchor.parentNode;
  }

  function appendMMenuEntry(container, entryHref, label, useListItem) {
    if (useListItem) {
      var li = document.createElement('li');
      li.className = 'extmgr-mmenu-entry';
      li.style.listStyle = 'none';

      var a = document.createElement('a');
      a.href = entryHref;
      a.className = 'btn btn-large';
      a.innerHTML = '<i class="fa-solid fa-sharp fa-puzzle-piece"></i><br>' + esc(label);
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
    link.innerHTML = '<i class="fa-solid fa-sharp fa-puzzle-piece" style="margin-right:.5em;"></i>' + esc(label);
    container.appendChild(link);
  }

  function renderMMenu(items) {
    var container = findMMenuContainer();
    if (!container) {
      return;
    }

    removeExistingMMenuInjected(container);

    var visible = [];
    var i;
    for (i = 0; i < items.length; i += 1) {
      var item = items[i] || {};
      if (!item.enabled || !item.menuVisibility || !item.menuVisibility.m) {
        continue;
      }
      visible.push(item);
    }

    if (visible.length === 0) {
      return;
    }

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

    for (i = 0; i < visible.length; i += 1) {
      var ext = visible[i] || {};
      var id = String(ext.id || '');
      var name = String(ext.name || id || 'Extension');
      var entry = ext.menuEntry || ext.entry || ('/' + id + '.php');
      appendMMenuEntry(container, entry, name, useListItem);
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
      '#context-menu ul, #sys-cmds ul, #configure-modal ul.dropdown-menu, .modal #context-menu ul, .context-menu ul, #configure-modal .modal-body ul'
    );
  }

  function renderSystemMenu(items) {
    var container = findSystemMenuContainer();
    if (!container) {
      return;
    }

    removeExistingSystemMenuInjected(container);

    var visible = [];
    var i;
    for (i = 0; i < items.length; i += 1) {
      var item = items[i] || {};
      if (!item.enabled || !item.menuVisibility || !item.menuVisibility.system) {
        continue;
      }
      visible.push(item);
    }

    if (visible.length === 0) {
      return;
    }

    var divider = document.createElement('li');
    divider.className = 'extmgr-system-divider';
    divider.style.listStyle = 'none';
    divider.style.borderTop = '1px solid rgba(128,128,128,.25)';
    divider.style.margin = '6px 0';
    container.appendChild(divider);

    var header = document.createElement('li');
    header.className = 'extmgr-system-header';
    header.style.listStyle = 'none';
    header.style.padding = '4px 12px';
    header.style.fontSize = '0.8em';
    header.style.opacity = '0.8';
    header.textContent = 'Extensions';
    container.appendChild(header);

    for (i = 0; i < visible.length; i += 1) {
      var ext = visible[i] || {};
      var id = String(ext.id || '');
      var name = String(ext.name || id || 'Extension');
      var entry = ext.menuEntry || ext.entry || ('/' + id + '.php');

      var li = document.createElement('li');
      li.className = 'extmgr-system-entry';
      li.style.listStyle = 'none';

      var link = document.createElement('a');
      link.href = entry;
      link.className = 'btn btn-large';
      link.innerHTML = '<i class="fa-solid fa-sharp fa-puzzle-piece"></i><br>' + esc(name);
      li.appendChild(link);
      container.appendChild(li);
    }
  }

  function ensureHostElements() {
    var wrap = document.querySelector('.extmgr-hover-menu');
    var panel = document.getElementById('extmgr-hover-panel');
    var listHost = document.getElementById('extmgr-hover-list');

    var folderBtn = document.querySelector(
      '#viewswitch .folder-view-btn, .viewswitch .folder-view-btn, .dropdown-menu .folder-view-btn, button.folder-view-btn, a.folder-view-btn'
    );

    if (wrap && panel && listHost) {
      var wrapInLibraryMenu = !!wrap.closest('#viewswitch, .viewswitch, .dropdown-menu');
      if (wrapInLibraryMenu) {
        return { wrap: wrap, panel: panel, listHost: listHost };
      }

      // Remove stale misplaced wrapper (older installer patch could inject it in playback area).
      if (wrap.parentNode) {
        wrap.parentNode.removeChild(wrap);
      }
      wrap = null;
      panel = null;
      listHost = null;
    }

    // Fallback for moOde template variants where installer markers were not injected.
    if (!folderBtn || !folderBtn.parentNode) {
      return null;
    }

    wrap = document.createElement('span');
    wrap.className = 'extmgr-hover-menu';
    wrap.style.position = 'relative';
    wrap.style.display = 'block';
    wrap.style.width = '100%';

    var extBtn = document.createElement('button');
    extBtn.setAttribute('aria-label', 'Extensions');
    extBtn.className = 'btn extensions-manager-btn menu-separator';
    extBtn.style.width = '100%';
    extBtn.type = 'button';
    extBtn.innerHTML = '<i class="fa-solid fa-sharp fa-puzzle-piece"></i> Extensions';
    extBtn.addEventListener('click', function () {
      window.location.href = '/ext-mgr.php';
    });

    panel = document.createElement('div');
    panel.id = 'extmgr-hover-panel';
    panel.style.display = 'none';
    panel.style.position = 'static';
    panel.style.minWidth = '0';
    panel.style.zIndex = 'auto';
    panel.style.background = 'transparent';
    panel.style.border = 'none';
    panel.style.boxShadow = 'none';
    panel.style.padding = '0 0 4px 0';
    panel.style.borderRadius = '0';

    listHost = document.createElement('div');
    listHost.id = 'extmgr-hover-list';
    panel.appendChild(listHost);

    wrap.appendChild(extBtn);
    wrap.appendChild(panel);
    folderBtn.parentNode.insertBefore(wrap, folderBtn);

    return { wrap: wrap, panel: panel, listHost: listHost };
  }

  function loadExtensions(host) {
    fetchState().then(function (payload) {
      var items = payload.extensions || [];
      renderList(host, items);
      renderMMenu(items);
      renderSystemMenu(items);
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
          renderMMenu(items);
          renderSystemMenu(items);
        });
      }, 120);
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var refs = ensureHostElements();
    if (!refs) {
      return;
    }

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

    fetchState().then(function (payload) {
      var items = payload.extensions || [];
      renderMMenu(items);
      renderSystemMenu(items);
      applyManagerVisibility(payload.meta || {}, refs);
    });
    observeMMenu();
  });
})();
