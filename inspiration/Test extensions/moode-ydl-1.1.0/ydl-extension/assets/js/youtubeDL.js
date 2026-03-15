/**
 * youtubeDL.js — moode-ydl v1.1.0
 *
 * Injected into moOde's main page (index.php) via the Stephanowicz
 * addon mechanism. This file contains two things:
 *
 * 1. The original Stephanowicz youtubeDL modal UI code (preserved)
 * 2. The coverart injection module (new in v1.1.0)
 *
 * Install: add to /var/www/addons/Stephanowicz/ and reference from
 * moOde's header.php or main.js (Stephanowicz AddRemoveAddons.sh handles this).
 * The ext-mgr install process handles activation via a sandboxed copy.
 */

/* ── 1. Original Stephanowicz modal UI ───────────────────────── */

var tempstr = '<div id="YoutubeDL-modal" class="hide" tabindex="-1" role="dialog"' +
    ' aria-labelledby="YoutubeDL-modal-label" aria-hidden="true" style="' +
    'border-radius:6px;position:fixed;width:100%;z-index:10001;' +
    'box-shadow:0 3px 7px rgba(0,0,0,.3);"></div>';
$('#shutdown').after(tempstr);

tempstr = '<li><a href="#notarget" data-cmd="" data-addoncmd="YoutubeDl">' +
    '<i class="fab fa-youtube sx"></i>Youtube Audioplayback</a></li>';
$('#context-menu-playback ul').append(tempstr);

function youtubeDL_render() {
    $.getJSON('addons/Stephanowicz/commands.php?cmd=checkYoutubePlayback', function(data) {
        if (data) {
            MPD.json['album']    = data['album'];
            MPD.json['artist']   = data['artist'];
            MPD.json['coverurl'] = data['coverurl'];
            MPD.json['disc']     = data['disc'];
            MPD.json['title']    = data['title'];
            if (MPD.json['state'] !== 'stop') {
                document.title = MPD.json['title'] + ' - ' + MPD.json['album'];
            } else {
                document.title = 'moOde Audioplayer';
            }
            renderUI_extended();
        }
    });
}

function YoutubeDl() {
    $('#YoutubeDL-modal').empty();
    $('#YoutubeDL-modal').load('addons/Stephanowicz/youtubeDL/ytmodal.txt', function() {
        $('#YoutubeDL').load('addons/Stephanowicz/youtubeDL/youtubeDL.html');
        $('#YoutubeDL-modal').modal();
    });
}

/* ── 2. Coverart injection module ────────────────────────────── */
(function () {
  'use strict';

  var EXT_API   = '/extensions/installed/moode-ydl/backend/api.php';
  var POLL_MS   = 2500;
  var TOGGLE_ID = 'ydl-cv-toggle';
  var WRAP_ID   = 'ydl-cv-wrap';

  var ydlState = {
    active:      false,
    userHidden:  false,
    videoId:     null,
    origHTML:    null,
    timer:       null,
  };

  /* Inject toggle button into #coverart-url */
  function ensureToggle() {
    if (document.getElementById(TOGGLE_ID)) return;

    var btn = document.createElement('button');
    btn.id  = TOGGLE_ID;
    btn.setAttribute('aria-label', 'Toggle YouTube video');
    btn.innerHTML = '<i class="fa-brands fa-youtube"></i> <span class="ydl-cv-btn-lbl">Video</span>';

    /* moOde-aligned styles — inherits theme tokens */
    var s = btn.style;
    s.cssText = [
      'display:none',
      'position:absolute',
      'top:.4em',
      'right:.5em',
      'z-index:9000',
      'align-items:center',
      'gap:.25em',
      'background:var(--btnshade2)',
      'color:var(--adapttext)',
      'border:1px solid var(--textvariant)',
      'border-radius:var(--btn-border-radius,.25em)',
      'padding:.18em .5em',
      'font-size:.70em',
      'cursor:pointer',
      'line-height:1.6',
      'opacity:.82',
      'transition:opacity .15s,background .15s,color .15s',
      'font-family:inherit',
    ].join(';');

    /* Accent-colour hover — matches moOde button behaviour */
    btn.addEventListener('mouseenter', function () {
      btn.style.background   = 'var(--accentxts)';
      btn.style.color        = '#fff';
      btn.style.borderColor  = 'var(--accentxts)';
      btn.style.opacity      = '1';
    });
    btn.addEventListener('mouseleave', function () {
      btn.style.background   = 'var(--btnshade2)';
      btn.style.color        = 'var(--adapttext)';
      btn.style.borderColor  = 'var(--textvariant)';
      btn.style.opacity      = '.82';
    });

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (ydlState.active) {
        hideYdlVideo();
        ydlState.userHidden = true;
      } else {
        ydlState.userHidden = false;
        if (ydlState.videoId) showYdlVideo(ydlState.videoId);
      }
    });

    var container = document.getElementById('coverart-url');
    if (container) {
      container.style.position = 'relative';
      container.appendChild(btn);
    }
  }

  /* Show YouTube iframe inside #coverart-url */
  function showYdlVideo(videoId) {
    var container = document.getElementById('coverart-url');
    if (!container) return;
    if (ydlState.active && ydlState.videoId === videoId) return;

    /* Save original coverart HTML once */
    if (!ydlState.origHTML) {
      /* Clone without the toggle button */
      var clone = container.cloneNode(true);
      var existBtn = clone.querySelector('#' + TOGGLE_ID);
      if (existBtn) existBtn.remove();
      ydlState.origHTML = clone.innerHTML;
    }

    /* Build wrapper — 1:1 aspect ratio matches moOde coverart square */
    var wrap = document.createElement('div');
    wrap.id  = WRAP_ID;
    wrap.style.cssText = [
      'position:relative',
      'width:100%',
      'padding-bottom:100%',
      'overflow:hidden',
      'border-radius:var(--img-border-radius,.35em)',
      'box-shadow:0 .25em 1em rgba(32,32,32,.35)',
      'background:#000',
    ].join(';');

    var iframe       = document.createElement('iframe');
    iframe.src       = 'https://www.youtube.com/embed/' + videoId +
                       '?autoplay=1&rel=0&modestbranding=1&color=white&playsinline=1';
    iframe.title     = 'YouTube';
    iframe.allow     = 'autoplay;encrypted-media;picture-in-picture';
    iframe.allowFullscreen = true;
    iframe.style.cssText = [
      'position:absolute',
      'top:0',
      'left:0',
      'width:100%',
      'height:100%',
      'border:none',
      'border-radius:var(--img-border-radius,.35em)',
    ].join(';');

    wrap.appendChild(iframe);

    /* Replace contents — preserve toggle button */
    var btn = document.getElementById(TOGGLE_ID);
    container.innerHTML = '';
    container.appendChild(wrap);
    if (btn) container.appendChild(btn);

    /* Update toggle label */
    var lbl = container.querySelector('.ydl-cv-btn-lbl');
    var ico = container.querySelector('#' + TOGGLE_ID + ' i');
    if (lbl) lbl.textContent = 'Cover';
    if (ico) ico.className   = 'fa-regular fa-image';

    var toggleBtn = document.getElementById(TOGGLE_ID);
    if (toggleBtn) toggleBtn.style.display = 'inline-flex';

    ydlState.active  = true;
    ydlState.videoId = videoId;
  }

  /* Restore original coverart */
  function hideYdlVideo() {
    var container = document.getElementById('coverart-url');
    if (!container) return;

    var btn = document.getElementById(TOGGLE_ID);

    if (ydlState.origHTML !== null) {
      container.innerHTML = ydlState.origHTML;
      ydlState.origHTML   = null;
    }

    /* Re-attach button (innerHTML wiped it) */
    if (btn) {
      var lbl = btn.querySelector('.ydl-cv-btn-lbl');
      var ico = btn.querySelector('i');
      if (lbl) lbl.textContent = 'Video';
      if (ico) ico.className   = 'fa-brands fa-youtube';
      container.style.position = 'relative';
      container.appendChild(btn);
    }

    ydlState.active = false;
  }

  /* Full deactivation (no youtube playing) */
  function deactivateYdl() {
    if (ydlState.active) hideYdlVideo();
    var btn = document.getElementById(TOGGLE_ID);
    if (btn) btn.style.display = 'none';
    ydlState.videoId    = null;
    ydlState.origHTML   = null;
    ydlState.userHidden = false;
  }

  /* Poll now-playing endpoint */
  function pollNowPlaying() {
    fetch(EXT_API + '?action=nowplaying', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var isYt    = data.is_youtube === true;
        var videoId = data.video_id   || '';
        var mpdSt   = data.state      || 'stop';

        if (!isYt || mpdSt === 'stop') {
          if (ydlState.active || ydlState.videoId) deactivateYdl();
          return;
        }

        /* Ensure toggle button present */
        ensureToggle();
        var toggleBtn = document.getElementById(TOGGLE_ID);
        if (toggleBtn) toggleBtn.style.display = 'inline-flex';

        /* New video resets user preference */
        if (videoId && videoId !== ydlState.videoId) {
          ydlState.userHidden = false;
        }

        if (!ydlState.userHidden && videoId) {
          if (!ydlState.active || ydlState.videoId !== videoId) {
            ydlState.origHTML = null; /* force refresh on track change */
            showYdlVideo(videoId);
          }
        }

        if (videoId) ydlState.videoId = videoId;
      })
      .catch(function () { /* extension not installed / api down — silent */ });
  }

  /* Start polling after DOM is ready */
  function startYdlCoverart() {
    pollNowPlaying();
    ydlState.timer = setInterval(pollNowPlaying, POLL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startYdlCoverart);
  } else {
    /* DOM already ready (script loaded deferred) */
    setTimeout(startYdlCoverart, 500);
  }

})();
