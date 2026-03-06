/**
 * OZ Variations BCW — Admin JS
 * Handles the reprocess button on the BCW settings page.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('oz-bcw-reprocess');
    var result = document.getElementById('oz-bcw-reprocess-result');

    if (!btn || !result) return;

    btn.addEventListener('click', function () {
      btn.disabled = true;
      btn.textContent = 'Bezig met verwerken...';
      result.textContent = '';

      var data = new FormData();
      data.append('action', 'oz_bcw_reprocess');
      data.append('_wpnonce', ozBcwAdmin.nonce);

      fetch(ozBcwAdmin.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data,
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          btn.disabled = false;
          btn.textContent = 'Alle producten herverwerken';

          if (json.success) {
            result.style.color = '#00a32a';
            result.textContent = json.data.message;
          } else {
            result.style.color = '#d63638';
            result.textContent = json.data || 'Er is een fout opgetreden.';
          }
        })
        .catch(function () {
          btn.disabled = false;
          btn.textContent = 'Alle producten herverwerken';
          result.style.color = '#d63638';
          result.textContent = 'Verbindingsfout. Probeer het opnieuw.';
        });
    });
  });
})();
