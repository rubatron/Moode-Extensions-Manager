(function () {
  'use strict';
  console.log('template-extension: template.js loaded');
})();

(function () {
  'use strict';

  function suppressSettingsTabs() {
    var tabs = document.getElementById('config-tabs');
    if (tabs) {
      tabs.style.setProperty('display', 'none', 'important');
    }
  }

  suppressSettingsTabs();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', suppressSettingsTabs);
  }

  if (window.MutationObserver) {
    var observer = new MutationObserver(suppressSettingsTabs);
    observer.observe(document.documentElement, { childList: true, subtree: true });
    setTimeout(function () {
      observer.disconnect();
    }, 3000);
  }
})();
