/**
 * template.js — moode-ydl v1.1.0
 * Extension page JS (loaded in template.php only, not in moOde main page).
 */
(function () {
  'use strict';

  /* ── Settings nav suppression ─────────────────────────────── */
  var NAV = '#navbar-settings,.navbar-settings,nav.moode-settings-nav,' +
            '#header .nav-tabs,#header ul.nav,#header .navbar-collapse,' +
            '#header .navbar-nav,.settings-nav,#topnav .nav';
  function hideNav() {
    document.querySelectorAll(NAV).forEach(function(e){
      e.style.setProperty('display','none','important');
    });
  }
  hideNav();
  document.addEventListener('DOMContentLoaded', hideNav);
  if (window.MutationObserver) {
    var obs = new MutationObserver(hideNav);
    obs.observe(document.documentElement, {childList:true,subtree:true});
    setTimeout(function(){obs.disconnect();}, 3000);
  }

  /* ── API helper ────────────────────────────────────────────── */
  function api(params, body) {
    var url = YDL_API + '?' + new URLSearchParams(params).toString();
    var opts = {method: body ? 'POST' : 'GET'};
    if (body) {
      opts.headers = {'Content-Type': 'application/json'};
      opts.body    = JSON.stringify(body);
    }
    return fetch(url, opts).then(function(r){ return r.json(); });
  }

  /* ── Status message ────────────────────────────────────────── */
  function showMsg(msg, type) {
    var el = document.getElementById('ydl-status-msg');
    if (!el) return;
    el.textContent   = msg;
    el.className     = 'ext-ydl-msg ext-ydl-msg-' + (type || 'info');
    el.style.display = 'block';
    setTimeout(function(){ el.style.display = 'none'; }, 4000);
  }

  /* ── Helpers ───────────────────────────────────────────────── */
  function ts() { return new Date().toLocaleTimeString(); }

  function logMsg(msg) {
    var el = document.getElementById('log');
    if (!el) return;
    el.innerHTML += ts() + ': ' + msg + '<br />';
    el.scrollTop = el.scrollHeight;
  }
  function mpdMsg(msg) {
    var el = document.getElementById('mpdlog');
    if (!el) return;
    el.innerHTML += ts() + ': ' + msg + '<br />';
    el.scrollTop = el.scrollHeight;
  }
  function xhr(url, cb, method) {
    var x = new XMLHttpRequest();
    x.onreadystatechange = function() {
      if (x.readyState === 4 && x.status === 200) cb(x.responseText);
    };
    x.open(method || 'GET', url, true);
    x.send();
  }
  function buttonState(input, btn) {
    var hasVal = input.value.length > 0;
    btn.disabled       = !hasVal;
    btn.style.opacity  = hasVal ? '' : '0.45';
  }
  function getOpt(name) {
    var els = document.querySelectorAll('[name="'+name+'"]:checked');
    return els.length ? els[0].value : 'add';
  }
  function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ── yt-dlp version check ──────────────────────────────────── */
  function checkVersion() {
    xhr(YDL_API + '?action=status', function(resp) {
      try {
        var d = JSON.parse(resp);
        var el = document.getElementById('ydl-version-badge');
        if (el) el.textContent = 'yt-dlp ' + (d.ytdlp_version || 'not found');
      } catch(e) {}
    });
  }

  /* ── Now-playing status badge ──────────────────────────────── */
  function checkNowPlaying() {
    xhr(YDL_API + '?action=nowplaying', function(resp) {
      try {
        var d = JSON.parse(resp);
        var el = document.getElementById('ydl-nowplaying-badge');
        if (!el) return;
        if (d.is_youtube && d.state !== 'stop') {
          el.textContent   = (d.state === 'play' ? '▶ ' : '⏸ ') + (d.title || 'YouTube');
          el.style.display = 'inline-block';
        } else {
          el.style.display = 'none';
        }
      } catch(e) {}
    });
  }

  /* ── MPD Playlist ──────────────────────────────────────────── */
  function MPDPlaylist() {
    xhr(YDL_API + '?playlist', function(resp) {
      var el = document.getElementById('playlist');
      if (el) el.innerHTML = resp || 'empty';
    });
  }

  /* ── Single video ──────────────────────────────────────────── */
  function singleVid() {
    var url  = (document.getElementById('singleUrl')  || {}).value || '';
    var opts = getOpt('plopts');
    if (!url) return;
    document.getElementById('log').innerHTML    = '';
    document.getElementById('mpdlog').innerHTML = '';
    var plItems = document.getElementById('plItems');
    if (plItems) plItems.style.display = 'none';
    createPLItem(url, opts);
  }

  function createPLItem(url, opts, index, length) {
    var idx = index || 1;
    var len = length || 1;
    var apiUrl = YDL_API + '?yturl=' + encodeURIComponent(url) +
                 '&plopts=' + opts + '&index=' + idx + '&length=' + len;
    logMsg('creating playlist entry for: ' + url + ' (' + opts + ')');
    xhr(apiUrl, function(resp) {
      mpdMsg(resp);
      MPDPlaylist();
    });
  }

  /* ── Load & play ───────────────────────────────────────────── */
  window.loadnplay = function() {
    xhr(YDL_API + '?loadnplay', function(resp) {
      document.getElementById('log').innerHTML = '';
      var el = document.getElementById('mpdlog');
      if (el) el.innerHTML = ts() + ': ' + resp + '<br />';
      setTimeout(checkNowPlaying, 3000);
    });
  };

  /* ── YouTube search ────────────────────────────────────────── */
  function ytQuery() {
    var q = (document.getElementById('ytQuery') || {}).value || '';
    if (!q) return;
    window.open('https://www.youtube.com/results?search_query=' + q.replace(/\s+/g, '+'), '_blank');
  }

  /* ── Fetch YT playlist ─────────────────────────────────────── */
  function YTPlaylist() {
    var plUrl = (document.getElementById('plUrl') || {}).value || '';
    if (!plUrl) return;
    var plList  = document.getElementById('plList');
    var plItems = document.getElementById('plItems');
    var plCount = document.getElementById('plCount');
    if (plList)  plList.innerHTML = '';
    if (plCount) plCount.textContent = '0';
    logMsg('reading youtube playlist…');
    document.getElementById('mpdlog').innerHTML = '';
    xhr(YDL_API + '?ytpl=' + encodeURIComponent(plUrl), function(resp) {
      try {
        var items = JSON.parse(resp);
        plArray = Object.entries(items);
        if (plArray.length > 0) {
          if (plCount) plCount.textContent = plArray.length;
          if (plItems) plItems.style.display = 'block';
          plArray.forEach(function(item) {
            plList.innerHTML += esc(item[0]) + '<br />';
          });
        } else { mpdMsg('no items found'); }
      } catch(e) { mpdMsg('error parsing playlist response'); }
    });
  }

  /* ── Create playlist from fetched array ────────────────────── */
  window.createPlaylistfromArray = function(url, opts, cnt) {
    if (cnt === undefined) cnt = 0;
    if (!plArray || !plArray.length) return;
    if (cnt === 0) {
      opts = getOpt('plplopts');
      document.getElementById('log').innerHTML    = '';
      document.getElementById('mpdlog').innerHTML = '';
    }
    url = url !== undefined ? url : plArray[0][1];
    var finalOpts = cnt > 0 && !['append','insert'].includes(opts) ? 'add' : opts;
    var apiUrl = YDL_API + '?yturl=' + encodeURIComponent(url) +
                 '&plopts=' + finalOpts + '&index=' + (cnt+1) + '&length=' + plArray.length;
    logMsg('entry (' + (cnt+1) + '/' + plArray.length + ') for ' + esc(plArray[cnt][0]));
    var x = new XMLHttpRequest();
    x.onreadystatechange = function() {
      if (x.readyState === 4 && x.status === 200) {
        mpdMsg(x.responseText);
        MPDPlaylist();
      }
    };
    x.open('GET', apiUrl, true); x.send();
    cnt++;
    if (cnt < plArray.length) {
      setTimeout(function() { window.createPlaylistfromArray(plArray[cnt][1], opts, cnt); }, 1500);
    } else {
      var pi = document.getElementById('plItems');
      if (pi) pi.style.display = 'none';
      var pl = document.getElementById('plList');
      if (pl) pl.innerHTML = '';
      var pc = document.getElementById('plCount');
      if (pc) pc.textContent = '0';
    }
  };

  /* ── Saved playlists ───────────────────────────────────────── */
  function fillYTPLList() {
    var sel = document.getElementById('selectPL');
    if (!sel) return;
    while (sel.options.length > 0) sel.remove(0);
    xhr(YDL_API + '?ytpllist', function(resp) {
      try {
        var items = JSON.parse(resp);
        if (Array.isArray(items) && items.length) {
          items.filter(Boolean).forEach(function(p) {
            if (p && p[0] && p[1]) {
              var opt = document.createElement('option');
              opt.value = p[0]; opt.textContent = p[1];
              sel.appendChild(opt);
            }
          });
        }
      } catch(e) {}
    });
  }

  window.loadPLentry = function() {
    var sel = document.getElementById('selectPL');
    var inp = document.getElementById('plUrl');
    if (!sel || !inp) return;
    inp.value = sel.value;
    buttonState(inp, document.getElementById('plSubmit'));
  };
  window.delPLentry = function() {
    var sel = document.getElementById('selectPL');
    if (!sel || !sel.selectedOptions[0]) return;
    var x = new XMLHttpRequest();
    x.onreadystatechange = function() { if (x.readyState === 4) setTimeout(fillYTPLList, 400); };
    x.open('PUT', YDL_API + '?delytpl=' + encodeURIComponent(sel.selectedOptions[0].value + ',' + sel.selectedOptions[0].textContent), true);
    x.send();
  };
  window.openPLentry = function() {
    var sel = document.getElementById('selectPL');
    if (sel && sel.value) window.open(sel.value, '_blank').focus();
  };
  window.savePLentry = function() {
    var plInp = document.getElementById('plUrl');
    var ti    = document.getElementById('ytplTitle');
    if (!plInp || !ti || !plInp.value || !ti.value) return;
    var x = new XMLHttpRequest();
    x.onreadystatechange = function() { if (x.readyState === 4) setTimeout(fillYTPLList, 400); };
    x.open('PUT', YDL_API + '?saveytpl=' + encodeURIComponent(plInp.value) + ',' + encodeURIComponent(ti.value), true);
    x.send();
  };

  /* ── Init ──────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function() {
    var singleUrl = document.getElementById('singleUrl');
    var singleBtn = document.getElementById('singleSubmit');
    var plUrlInp  = document.getElementById('plUrl');
    var plBtn     = document.getElementById('plSubmit');
    var ytQInp    = document.getElementById('ytQuery');
    var ytQBtn    = document.getElementById('ytQuerySubmit');

    if (singleUrl && singleBtn) {
      singleUrl.addEventListener('input', function(){ buttonState(singleUrl, singleBtn); });
      singleBtn.addEventListener('click', singleVid);
      singleUrl.addEventListener('keydown', function(e){ if(e.key==='Enter' && !singleBtn.disabled) singleVid(); });
    }
    if (plUrlInp && plBtn) {
      plUrlInp.addEventListener('input', function(){ buttonState(plUrlInp, plBtn); });
      plBtn.addEventListener('click', YTPlaylist);
    }
    if (ytQInp && ytQBtn) {
      ytQInp.addEventListener('input', function(){ buttonState(ytQInp, ytQBtn); });
      ytQBtn.addEventListener('click', ytQuery);
      ytQInp.addEventListener('keydown', function(e){ if(e.key==='Enter' && !ytQBtn.disabled) ytQuery(); });
    }

    var titleInp = document.getElementById('ytplTitle');
    var saveBtn  = document.getElementById('savePLentry');
    if (titleInp && saveBtn) {
      titleInp.addEventListener('input', function(){ saveBtn.disabled = titleInp.value.length === 0; });
    }
    var plCreate = document.getElementById('plCreate');
    if (plCreate) plCreate.addEventListener('click', function(){ window.createPlaylistfromArray(); });

    checkVersion();
    checkNowPlaying();
    MPDPlaylist();
    fillYTPLList();
    setInterval(checkNowPlaying, 5000);
  });

})();
