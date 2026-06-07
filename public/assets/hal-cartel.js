/* Cartel — minimal public JS. Wires Add-to-cart, Buy-now, and one-page checkout. */
(function () {
  if (typeof HalCartelData === 'undefined') return;

  function api(path, opts = {}) {
    return fetch(HalCartelData.restUrl + path, Object.assign({
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': HalCartelData.nonce },
    }, opts)).then(function (r) { return r.json(); });
  }

  function fmt(n) { return Number(n).toFixed(2); }

  function renderSummary(target, summary) {
    if (!target) return;
    if (!summary || !summary.items || !summary.items.length) {
      target.innerHTML = '<p>Your cart is empty.</p>';
      return;
    }
    target.innerHTML =
      '<ul>' +
        summary.items.map(function (i) {
          return '<li>' + i.quantity + ' × ' + i.name + ' — ' + fmt(i.total) + '</li>';
        }).join('') +
      '</ul>' +
      '<p><strong>Subtotal: ' + summary.currency + ' ' + fmt(summary.subtotal) + '</strong></p>';
  }

  document.addEventListener('click', function (e) {
    var addBtn = e.target.closest('.hal-cartel-add-to-cart');
    var buyBtn = e.target.closest('.hal-cartel-buy-now, .hal-cartel-buy-now-inline');
    var card   = e.target.closest('[data-product-id]');
    if (!card) return;
    var pid = parseInt(card.getAttribute('data-product-id'), 10);

    if (addBtn) {
      api('cart/add', { method: 'POST', body: JSON.stringify({ product_id: pid, quantity: 1 }) })
        .then(function (s) { renderSummary(document.querySelector('[data-hal-cartel-cart-summary]'), s); });
    }
    if (buyBtn) {
      api('cart/add', { method: 'POST', body: JSON.stringify({ product_id: pid, quantity: 1 }) })
        .then(function () { window.location.href = (window.HalCartelData.checkoutUrl || '/checkout/'); });
    }
  });

  var summaryEl = document.querySelector('[data-hal-cartel-cart-summary]');
  if (summaryEl) api('cart').then(function (s) { renderSummary(summaryEl, s); });

  var form = document.getElementById('hal-cartel-checkout-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      var payload = { email: fd.get('email'), shipping: {} };
      ['name','address','city','postcode','country'].forEach(function (k) {
        payload.shipping[k] = fd.get('shipping[' + k + ']');
      });
      api('checkout', { method: 'POST', body: JSON.stringify(payload) })
        .then(function (res) {
          if (res && res.order_number) {
            form.innerHTML = '<h2>Thanks!</h2><p>Order ' + res.order_number + ' received.</p>';
          } else {
            alert((res && res.message) || 'Checkout failed.');
          }
        });
    });
  }
})();
