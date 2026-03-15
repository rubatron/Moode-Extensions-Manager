/**
 * Ronnie Pickering's Extension — template.js
 * Extends the original icon-picker logic with embed copy functionality.
 */
(function () {
  'use strict';

  /* ── Original icon picker (kept intact) ────────────────────── */
  var picker = document.getElementById('ext-template-icon-picker');
  var icon   = document.getElementById('ext-template-icon');
  var value  = document.getElementById('ext-template-icon-value');

  if (picker && icon && value) {
    var icons = [
      'fa-solid fa-sharp fa-puzzle-piece',
      'fa-solid fa-sharp fa-music',
      'fa-solid fa-sharp fa-wave-square',
      'fa-solid fa-sharp fa-sliders',
      'fa-solid fa-sharp fa-gauge',
      'fa-solid fa-sharp fa-radio',
      'fa-solid fa-sharp fa-headphones',
      'fa-solid fa-sharp fa-folder-open'
    ];

    icons.forEach(function (iconClass) {
      var option = document.createElement('option');
      option.value = iconClass;
      option.textContent = iconClass;
      picker.appendChild(option);
    });

    function applyIcon(iconClass) {
      icon.className = iconClass;
      value.textContent = iconClass;
    }

    picker.value = icons[0];
    applyIcon(picker.value);
    picker.addEventListener('change', function () { applyIcon(picker.value); });
  }

  /* ── Copy embed code button ─────────────────────────────────── */
  var copyBtn   = document.getElementById('ext-rp-copy-btn');
  var codeBlock = document.getElementById('ext-rp-embed-code');

  if (copyBtn && codeBlock) {
    copyBtn.addEventListener('click', function () {
      var text = codeBlock.textContent || codeBlock.innerText || '';

      function onSuccess() {
        var originalHTML = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fa-solid fa-check"></i><span>Copied!</span>';
        copyBtn.classList.add('copied');
        copyBtn.disabled = true;

        setTimeout(function () {
          copyBtn.innerHTML = originalHTML;
          copyBtn.classList.remove('copied');
          copyBtn.disabled = false;
        }, 2200);
      }

      function onFail() {
        /* Fallback for older browsers / iframe restrictions */
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
          document.execCommand('copy');
          onSuccess();
        } catch (e) {
          copyBtn.innerHTML = '<i class="fa-solid fa-xmark"></i><span>Failed</span>';
          setTimeout(function () {
            copyBtn.innerHTML = '<i class="fa-regular fa-copy"></i><span>Copy embed</span>';
          }, 2000);
        }
        document.body.removeChild(ta);
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(onSuccess, onFail);
      } else {
        onFail();
      }
    });
  }

  /* ── Card entrance animation ─────────────────────────────────
     Staggered fade-in for each card on load (CSS class toggle).  */
  var cards = document.querySelectorAll('.ext-template-card');
  cards.forEach(function (card, i) {
    card.style.opacity = '0';
    card.style.transform = 'translateY(12px)';
    card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    setTimeout(function () {
      card.style.opacity = '';
      card.style.transform = '';
    }, 80 + i * 90);
  });

})();

/* ── Settings nav suppression failsafe ──────────────────────────
   Selector confirmed from ext-mgr source (ext-mgr-hover-menu.js
   line 188): moOde settings tabs live in #config-tabs.
   Primary suppression = inline <style> in template.php (fires
   before page content). This JS layer is a belt-and-braces guard
   for any case where moOde re-renders the nav after page load.   */
(function () {
  'use strict';

  function suppressNav() {
    var el = document.getElementById('config-tabs');
    if (el) { el.style.setProperty('display', 'none', 'important'); }
  }

  suppressNav();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', suppressNav);
  }
  // MutationObserver: catches moOde re-rendering #config-tabs after load
  if (window.MutationObserver) {
    var obs = new MutationObserver(suppressNav);
    obs.observe(document.documentElement, { childList: true, subtree: true });
    setTimeout(function () { obs.disconnect(); }, 3000);
  }
})();
