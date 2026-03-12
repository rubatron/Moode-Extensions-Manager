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

  function loadExtensions(host) {
    fetch('/ext-mgr-api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body: 'action=list'
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        renderList(host, (data && data.data && data.data.extensions) || []);
      })
      .catch(function () {
        renderList(host, []);
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.querySelector('.extmgr-hover-menu');
    var panel = document.getElementById('extmgr-hover-panel');
    var listHost = document.getElementById('extmgr-hover-list');

    if (!wrap || !panel || !listHost) {
      return;
    }

    wrap.addEventListener('mouseenter', function () {
      panel.style.display = 'block';
      loadExtensions(listHost);
    });

    wrap.addEventListener('mouseleave', function () {
      panel.style.display = 'none';
    });
  });
})();
