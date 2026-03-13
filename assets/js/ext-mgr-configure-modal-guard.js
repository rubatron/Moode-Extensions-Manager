(function (window, document) {
  'use strict';

  function ensureBackdrop() {
    var existing = document.querySelector('.modal-backdrop');
    if (existing) {
      return existing;
    }
    var backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop in';
    document.body.appendChild(backdrop);
    return backdrop;
  }

  function closeConfigureModal() {
    var modal = document.getElementById('configure-modal');
    if (!modal) {
      return;
    }

    modal.classList.add('hide');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');

    var backdrops = document.querySelectorAll('.modal-backdrop');
    var i;
    for (i = 0; i < backdrops.length; i += 1) {
      if (backdrops[i] && backdrops[i].parentNode) {
        backdrops[i].parentNode.removeChild(backdrops[i]);
      }
    }
  }

  function openConfigureModal(e) {
    var modal = document.getElementById('configure-modal');
    if (!modal) {
      return;
    }

    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
      e.stopPropagation();
    }

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
      window.jQuery(modal).removeClass('hide').modal('show');
      return;
    }

    modal.classList.remove('hide');
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    ensureBackdrop();
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target && e.target.closest
      ? e.target.closest('a[href="#configure-modal"], a[href*="configure-modal"], [data-target="#configure-modal"], [href*="open-configure"]')
      : null;
    if (!trigger) {
      return;
    }
    openConfigureModal(e);
  }, true);

  document.addEventListener('click', function (e) {
    var closeTrigger = e.target && e.target.closest
      ? e.target.closest('#configure-modal [data-dismiss="modal"], #configure-modal .close, .modal-backdrop')
      : null;
    if (!closeTrigger) {
      return;
    }
    closeConfigureModal();
  }, true);

  if (window.location.hash === '#configure-modal' || window.location.hash.indexOf('configure-modal') !== -1) {
    window.setTimeout(function () {
      openConfigureModal();
    }, 0);
  }
})(window, document);
