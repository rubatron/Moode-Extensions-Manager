(function () {
  'use strict';

  var picker = document.getElementById('ext-template-icon-picker');
  var icon = document.getElementById('ext-template-icon');
  var value = document.getElementById('ext-template-icon-value');
  if (!picker || !icon || !value) {
    return;
  }

  // YOUR CODE HERE: replace this starter list or append your own UI logic.
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
// Header menu visibility is controlled via PHP and ext-mgr registry (headerVisible setting).
