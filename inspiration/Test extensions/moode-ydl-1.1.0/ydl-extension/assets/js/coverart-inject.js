/**
 * coverart-inject.js — moode-ydl v1.1.0
 *
 * Injects YouTube video player into moOde's coverart area when a
 * YouTube stream is active in MPD. Uses the Stephanowicz addon
 * injection mechanism (loaded via youtubeDL.js into moOde main page).
 *
 * What it does:
 *  1. Polls /extensions/installed/moode-ydl/backend/api.php?action=nowplaying
 *  2. When a YouTube stream is detected: replaces #coverart-url img with iframe
 *  3. Adds a moOde-styled toggle button inside the coverart area
 *  4. When MPD stops or switches to non-YouTube: restores original coverart
 *
 * moOde design tokens used:
 *   var(--accentxts)   — accent colour (orange in default theme)
 *   var(--btnshade2)   — button background
 *   var(--adapttext)   — primary text colour
 *   var(--textvariant) — muted text
 *   var(--img-border-radius) — image border radius
 */

(function () {
  'use strict';

  var API_BASE   = '/extensions/installed/moode-ydl/backend/api.php';
  var POLL_MS    = 2500;
  var TOGGLE_ID  = 'ydl-coverart-toggle';
  var IFRAME_ID  = 'ydl-coverart-iframe-wrap';

  var state = {
    videoActive:  false,   // are we showing the iframe?
    userHidden:   false,   // did user manually hide the video?
    lastVideoId:  null,
    lastMpdState: null,
    pollTimer:    null,
    origContent:  null,    // saved innerHTML of #coverart-url before override
  };

  // ── Inject toggle button ────────────────────────────────────────
  function injectToggleButton() {
    if (document.getElementById(TOGGLE_ID)) return;

    var btn = document.createElement('button');
    btn.id        = TOGGLE_ID;
    btn.innerHTML = '<i class="fa-brands fa-youtube"></i><span>Video</span>';
    btn.title     = 'Toggle YouTube video in coverart';
    btn.setAttribute('aria-label', 'Toggle YouTube video');

    // moOde-aligned styling — uses CSS vars so it adapts to any theme
    btn.style.cssText = [
      'display:none',
      'position:absolute',
      'top:.45em',
      'right:.5em',
      'z-index:200',
      'background:var(--btnshade2)',
      'color:var(--adapttext)',
      'border:1px solid var(--textvariant)',
      'border-radius:var(--btn-border-radius,4px)',
      'padding:.2em .55em',
      'font-size:.72em',
      'cursor:pointer',
      'line-height:1.5',
      'opacity:.85',
      'transition:opacity .2s,background .2s',
      'font-family:inherit',
    ].join(';');

    btn.addEventListener('mouseenter', function () { btn.style.opacity = '1'; btn.style.background = 'var(--accentxts)'; btn.style.color = '#fff'; btn.style.borderColor = 'var(--accentxts)'; });
    btn.addEventListener('mouseleave', function () { btn.style.opacity = '.85'; btn.style.background = 'var(--btnshade2)'; btn.style.color = 'var(--adapttext)'; btn.style.borderColor = 'var(--textvariant)'; });

    btn.addEventListener('click', function () {
      if (state.videoActive) {
        hideVideo();
        state.userHidden = true;
      } else {
        state.userHidden = false;
        if (state.lastVideoId) showVideo(state.lastVideoId);
      }
    });

    // Insert into #coverart-url container (position:relative already set by moOde)
    var container = document.getElementById('coverart-url');
    if (container) {
      container.style.position = 'relative';
      container.appendChild(btn);
    }
  }

  // ── Show YouTube iframe ─────────────────────────────────────────
  function showVideo(videoId) {
    var container = document.getElementById('coverart-url');
    if (!container) return;

    // Save original content (the <img class="coverart">) once
    if (!state.origContent) {
      state.origContent = container.innerHTML;
    }

    // Don't re-render if same video already shown
    if (state.videoActive && state.lastVideoId === videoId) return;

    // Build iframe wrapper — uses same box-shadow and border-radius as img.coverart
    var wrap = document.createElement('div');
    wrap.id  = IFRAME_ID;
    wrap.style.cssText = [
      'position:relative',
      'width:100%',
      'padding-bottom:100%',  // 1:1 square — matches coverart proportions
      'overflow:hidden',
      'border-radius:var(--img-border-radius,.35em)',
      'box-shadow:0em .25em 1em rgba(32,32,32,0.25)',
    ].join(';');

    var iframe = document.createElement('iframe');
    iframe.src = 'https://www.youtube.com/embed/' + videoId +
                 '?autoplay=1&rel=0&modestbranding=1&color=white&playsinline=1';
    iframe.title           = 'YouTube video';
    iframe.allow           = 'autoplay; encrypted-media; picture-in-picture';
    iframe.allowFullscreen = true;
    iframe.style.cssText   = [
      'position:absolute',
      'top:0',
      'left:0',
      'width:100%',
      'height:100%',
      'border:none',
      'border-radius:var(--img-border-radius,.35em)',
    ].join(';');

    wrap.appendChild(iframe);

    // Replace #coverart-url contents — keep the toggle button
    var existingBtn = document.getElementById(TOGGLE_ID);
    container.innerHTML = '';
    container.appendChild(wrap);
    if (existingBtn) container.appendChild(existingBtn);

    // Update toggle button state
    var btn = document.getElementById(TOGGLE_ID);
    if (btn) {
      btn.style.display = 'inline-flex';
      btn.style.alignItems = 'center';
      btn.style.gap = '.25em';
      btn.querySelector('span').textContent = 'Cover';
      btn.querySelector('i').className = 'fa-regular fa-image';
    }

    state.videoActive = true;
    state.lastVideoId = videoId;
  }

  // ── Hide video, restore coverart ────────────────────────────────
  function hideVideo() {
    var container = document.getElementById('coverart-url');
    if (!container) return;

    var existingBtn = document.getElementById(TOGGLE_ID);

    if (state.origContent) {
      container.innerHTML = state.origContent;
      // Re-attach toggle button since innerHTML replaced it
      if (existingBtn) container.appendChild(existingBtn);
      state.origContent = null;
    }

    var btn = document.getElementById(TOGGLE_ID);
    if (btn) {
      btn.querySelector('span').textContent = 'Video';
      btn.querySelector('i').className = 'fa-brands fa-youtube';
    }

    state.videoActive = false;
  }

  // ── Remove toggle button + iframe completely ────────────────────
  function deactivate() {
    if (state.videoActive) hideVideo();
    var btn = document.getElementById(TOGGLE_ID);
    if (btn) btn.style.display = 'none';
    state.lastVideoId = null;
    state.userHidden  = false;
    state.origContent = null;
  }

  // ── Poll now-playing ────────────────────────────────────────────
  function poll() {
    fetch(API_BASE + '?action=nowplaying', { cache: 'no-store' })
      .then(function(r) { return r.json(); })
      .then(function(data) {

        var isYt    = data.is_youtube === true;
        var videoId = data.video_id  || '';
        var mpdSt   = data.state     || 'stop';

        // Not playing YouTube anymore
        if (!isYt || mpdSt === 'stop') {
          if (state.videoActive || state.lastVideoId) deactivate();
          return;
        }

        // Make sure toggle button exists
        injectToggleButton();

        // Show toggle button whenever YouTube is active
        var btn = document.getElementById(TOGGLE_ID);
        if (btn) btn.style.display = 'inline-flex';

        // Auto-show video if: new video ID, or video was previously active and not user-hidden
        if (videoId && videoId !== state.lastVideoId) {
          state.userHidden = false;  // new track resets user preference
        }

        if (!state.userHidden) {
          if (videoId && !state.videoActive) {
            showVideo(videoId);
          } else if (videoId && state.lastVideoId !== videoId) {
            // Track changed
            state.origContent = null;
            showVideo(videoId);
          }
        }

        state.lastVideoId = videoId || state.lastVideoId;
        state.lastMpdState = mpdSt;
      })
      .catch(function() {
        // API unreachable — silently ignore, keep current state
      });
  }

  // ── Screen saver override ───────────────────────────────────────
  // When moOde enters screen saver (cover view), also show the iframe there
  function hookScreenSaver() {
    var ssCover = document.getElementById('ss-coverart-url');
    if (!ssCover || !state.videoActive || !state.lastVideoId) return;
    if (document.getElementById('ydl-ss-iframe')) return;

    var iframe = document.createElement('iframe');
    iframe.id              = 'ydl-ss-iframe';
    iframe.src             = 'https://www.youtube.com/embed/' + state.lastVideoId +
                             '?autoplay=1&rel=0&modestbranding=1&mute=1&playsinline=1';
    iframe.allow           = 'autoplay; encrypted-media';
    iframe.allowFullscreen = true;
    iframe.style.cssText   = 'width:100%;height:100%;border:none;position:absolute;top:0;left:0;';
    ssCover.style.position = 'relative';
    ssCover.appendChild(iframe);
  }

  // ── Init ────────────────────────────────────────────────────────
  function init() {
    // Start polling
    poll();
    state.pollTimer = setInterval(poll, POLL_MS);

    // Hook into moOde's cover-view toggle if available
    var observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(m) {
        if (m.type === 'attributes' && m.attributeName === 'class') {
          if (m.target.id === 'ss-coverart-url') hookScreenSaver();
        }
      });
    });

    var ssCover = document.getElementById('ss-coverart-url');
    if (ssCover) observer.observe(ssCover, { attributes: true, subtree: false });
  }

  // Start after DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
