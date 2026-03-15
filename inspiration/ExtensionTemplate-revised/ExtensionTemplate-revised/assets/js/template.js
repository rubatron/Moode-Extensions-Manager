(function () {
  'use strict';

  var picker = document.getElementById('ext-template-icon-picker');
  var icon = document.getElementById('ext-template-icon');
  var value = document.getElementById('ext-template-icon-value');
  if (!picker || !icon || !value) {
    return;
  }

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

  function apply(iconClass) {
    icon.className = iconClass;
    value.textContent = iconClass;
  }

  picker.value = icons[0];
  apply(picker.value);

  picker.addEventListener('change', function () {
    apply(picker.value);
  });
})();

/* ── Settings nav suppression failsafe ──────────────────────────
   #config-tabs confirmed from ext-mgr source as the settings nav.
   Inline CSS in template.php is primary. This JS is belt-and-braces
   for moOde re-rendering after page load.                         */
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
  if (window.MutationObserver) {
    var obs = new MutationObserver(suppressNav);
    obs.observe(document.documentElement, { childList: true, subtree: true });
    setTimeout(function () { obs.disconnect(); }, 3000);
  }
})();
