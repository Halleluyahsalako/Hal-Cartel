/* Cartel - public JS. Wires Add-to-cart, Buy-now, variable-product option pickers, and one-page checkout. */
(function () {
  if (typeof HalCartelData === 'undefined') return;

  function api(path, opts = {}) {
    return fetch(HalCartelData.restUrl + path, Object.assign({
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': HalCartelData.nonce },
    }, opts)).then(function (r) { return r.json(); });
  }

  /* Formats a number with the given currency descriptor's symbol/position/decimals — falls back to the store default. */
  function formatMoney(n, format) {
    var f = format || HalCartelData.currencyFormat || { symbol: '', position: 'before', decimals: 2 };
    var number = Number(n).toFixed(f.decimals);
    return 'after' === f.position ? (number + ' ' + f.symbol) : (f.symbol + number);
  }
  function label(key, fallback) { return HalCartelData[key] || fallback; }

  function renderSummary(target, summary) {
    if (!target) return;
    if (!summary || !summary.items || !summary.items.length) {
      target.innerHTML = '<p>Your cart is empty.</p>';
      return;
    }
    target.innerHTML =
      '<ul>' +
        summary.items.map(function (i) {
          return '<li>' + i.quantity + ' ' + String.fromCharCode(215) + ' ' + i.name + ' ' + String.fromCharCode(8212) + ' ' + formatMoney(i.total) + '</li>';
        }).join('') +
      '</ul>' +
      '<p><strong>Subtotal: ' + formatMoney(summary.subtotal) + '</strong></p>';
  }

  function refreshSummary() {
    var summaryEl = document.querySelector('[data-hal-cartel-cart-summary]');
    if (summaryEl) { api('cart').then(function (s) { renderSummary(summaryEl, s); }); }
  }

  /* Variable products: keep displayed price, availability, and the hidden variation id in sync with the chosen options. */
  document.querySelectorAll('.hal-cartel-product--variable').forEach(function (card) {
    var variations = [];
    try { variations = JSON.parse(card.getAttribute('data-variations') || '[]'); } catch (err) { variations = []; }

    var currencyFormat = null;
    try { currencyFormat = JSON.parse(card.getAttribute('data-currency-format') || 'null'); } catch (err) { currencyFormat = null; }

    var selects = card.querySelectorAll('[data-hal-cartel-attribute]');
    var priceEl = card.querySelector('[data-hal-cartel-price]');
    var availabilityEl = card.querySelector('[data-hal-cartel-availability]');
    var variationInput = card.querySelector('[data-hal-cartel-variation-id]');
    var addBtn = card.querySelector('.hal-cartel-add-to-cart');
    var buyBtn = card.querySelector('.hal-cartel-buy-now');
    var initialPrice = priceEl ? priceEl.textContent : '';

    function currentSelection() {
      var picked = {};
      var complete = true;
      selects.forEach(function (select) {
        var name = select.getAttribute('data-hal-cartel-attribute');
        if (select.value) {
          picked[name] = select.value;
        } else {
          complete = false;
        }
      });
      return { picked: picked, complete: complete };
    }

    function findMatch(picked) {
      for (var i = 0; i < variations.length; i++) {
        var attrs = variations[i].attributes || {};
        var keys = Object.keys(attrs);
        if (keys.length !== Object.keys(picked).length) { continue; }
        if (keys.every(function (k) { return attrs[k] === picked[k]; })) { return variations[i]; }
      }
      return null;
    }

    function update() {
      var state = currentSelection();
      var match = state.complete ? findMatch(state.picked) : null;

      if (match) {
        if (priceEl) { priceEl.textContent = formatMoney(match.price, currencyFormat); }
        if (variationInput) { variationInput.value = match.id; }
        if (availabilityEl) {
          availabilityEl.hidden = false;
          availabilityEl.textContent = match.in_stock ? label('inStockLabel', 'In stock') : label('outOfStockLabel', 'Out of stock');
          availabilityEl.classList.toggle('hal-cartel-product__availability--out', !match.in_stock);
        }
        if (addBtn) { addBtn.disabled = !match.in_stock; }
        if (buyBtn) { buyBtn.disabled = !match.in_stock; }
      } else {
        if (priceEl) { priceEl.textContent = initialPrice; }
        if (variationInput) { variationInput.value = '0'; }
        if (availabilityEl) { availabilityEl.hidden = true; }
        if (addBtn) { addBtn.disabled = true; }
        if (buyBtn) { buyBtn.disabled = true; }
      }
    }

    selects.forEach(function (select) { select.addEventListener('change', update); });
    update();
  });

  document.addEventListener('click', function (e) {
    var addBtn = e.target.closest('.hal-cartel-add-to-cart');
    var buyBtn = e.target.closest('.hal-cartel-buy-now, .hal-cartel-buy-now-inline');
    var card   = e.target.closest('[data-product-id]');
    if (!card || (!addBtn && !buyBtn)) return;
    if ((addBtn && addBtn.disabled) || (buyBtn && buyBtn.disabled)) return;

    var pid = parseInt(card.getAttribute('data-product-id'), 10);
    var variationInput = card.querySelector('[data-hal-cartel-variation-id]');
    var variationId = variationInput ? (parseInt(variationInput.value, 10) || 0) : 0;
    var payload = { product_id: pid, variation_id: variationId, quantity: 1 };

    if (addBtn) {
      api('cart/add', { method: 'POST', body: JSON.stringify(payload) })
        .then(function (s) {
          if (s && s.code) { window.alert((s.message) || 'Could not add to cart.'); return; }
          renderSummary(document.querySelector('[data-hal-cartel-cart-summary]'), s);
        });
    }
    if (buyBtn) {
      api('cart/add', { method: 'POST', body: JSON.stringify(payload) })
        .then(function (s) {
          if (s && s.code) { window.alert((s.message) || 'Could not add to cart.'); return; }
          window.location.href = (HalCartelData.checkoutUrl || '/checkout/');
        });
    }
  });

  refreshSummary();

  var form = document.getElementById('hal-cartel-checkout-form');
  if (form) {
    var needsShipping   = form.getAttribute('data-hal-cartel-needs-shipping') !== '0';
    var countrySelect = form.querySelector('[data-hal-cartel-shipping-country]');
    var ratesEl       = form.querySelector('[data-hal-cartel-shipping-rates]');
    var paymentEl     = form.querySelector('[data-hal-cartel-payment-methods]');
    var placeOrderBtn = form.querySelector('[data-hal-cartel-place-order]');
    var shippingErrorEl = null;
    var selectedRate    = null;
    var selectedGateway = null;
    var ratesRequest  = 0;
    var stripeState   = { stripe: null, card: null };

    function showShippingError(msg) {
      if (!ratesEl) return;
      if (!shippingErrorEl) {
        shippingErrorEl = document.createElement('p');
        shippingErrorEl.className = 'hal-cartel-checkout__shipping-error';
      }
      shippingErrorEl.textContent = msg;
      ratesEl.appendChild(shippingErrorEl);
    }

    function clearShippingError() {
      if (shippingErrorEl && shippingErrorEl.parentNode) {
        shippingErrorEl.parentNode.removeChild(shippingErrorEl);
      }
    }

    function refreshPlaceOrderState() {
      var ready = needsShipping ? (!!selectedRate && !!selectedGateway) : !!selectedGateway;
      if (placeOrderBtn) { placeOrderBtn.disabled = !ready; }
    }

    function renderRates(rates) {
      if (!ratesEl) return;
      selectedRate = null;
      clearShippingError();
      refreshPlaceOrderState();

      if (!rates || !rates.length) {
        ratesEl.innerHTML = '<p class="hal-cartel-checkout__shipping-hint">' + label('shippingNoneLabel', 'No shipping methods are available for your destination.') + '</p>';
        showShippingError(label('shippingBlockedLabel', 'Your cart contains physical items but no shipping is set up for your country. Please contact the store owner.'));
        return;
      }

      ratesEl.innerHTML = rates.map(function (rate, index) {
        var checked = 0 === index ? ' checked' : '';
        return '<label class="hal-cartel-checkout__shipping-rate">' +
          '<input type="radio" name="shipping_method_id" value="' + rate.id + '"' + checked + ' />' +
          '<span>' + rate.title + '</span>' +
          '<span>' + (rate.formatted_cost || formatMoney(rate.cost)) + '</span>' +
        '</label>';
      }).join('');

      var checkedInput = ratesEl.querySelector('input[name="shipping_method_id"]:checked');
      if (checkedInput) {
        selectedRate = rates[0];
      }
      refreshPlaceOrderState();

      ratesEl.querySelectorAll('input[name="shipping_method_id"]').forEach(function (input, index) {
        input.addEventListener('change', function () {
          selectedRate = rates[index];
          refreshPlaceOrderState();
        });
      });
    }

    /* Loads Stripe.js once, lazily — only stores with Stripe configured pay this cost. */
    function loadStripeJs(callback) {
      if (window.Stripe) { callback(); return; }
      var script = document.createElement('script');
      script.src = 'https://js.stripe.com/v3/';
      script.onload = callback;
      document.head.appendChild(script);
    }

    function renderGatewayFields(gateway, container) {
      if (!container) return;
      container.innerHTML = '';
      (gateway.fields || []).forEach(function (field) {
        if ('instructions' === field.type) {
          var p = document.createElement('p');
          p.className = 'hal-cartel-checkout__payment-instructions';
          p.textContent = field.value || '';
          container.appendChild(p);
        }
        if ('card_element' === field.type) {
          var mount = document.createElement('div');
          mount.className = 'hal-cartel-checkout__card-element';
          mount.id = 'hal-cartel-stripe-card-element';
          container.appendChild(mount);

          if (field.publishable_key) {
            loadStripeJs(function () {
              var stripe = window.Stripe(field.publishable_key);
              var card   = stripe.elements().create('card');
              card.mount('#hal-cartel-stripe-card-element');
              card.on('focus', function () { mount.classList.add('hal-cartel-checkout__card-element--focus'); });
              card.on('blur', function () { mount.classList.remove('hal-cartel-checkout__card-element--focus'); });
              stripeState.stripe = stripe;
              stripeState.card   = card;
            });
          }
        }
      });
    }

    function renderPaymentMethods(methods) {
      if (!paymentEl) return;
      selectedGateway = null;
      refreshPlaceOrderState();

      if (!methods || !methods.length) {
        paymentEl.innerHTML = '<p>' + label('paymentNoneLabel', 'No payment methods are available.') + '</p>';
        return;
      }

      paymentEl.innerHTML = methods.map(function (method, index) {
        var checked = 0 === index ? ' checked' : '';
        return '<label class="hal-cartel-checkout__payment-method">' +
            '<input type="radio" name="gateway_id" value="' + method.id + '"' + checked + ' />' +
            '<span>' + method.title + '</span>' +
          '</label>' +
          '<div class="hal-cartel-checkout__payment-fields" data-gateway="' + method.id + '"' + (0 === index ? '' : ' hidden') + '></div>';
      }).join('');

      selectedGateway = methods[0];
      refreshPlaceOrderState();
      renderGatewayFields(methods[0], paymentEl.querySelector('[data-gateway="' + methods[0].id + '"]'));

      paymentEl.querySelectorAll('input[name="gateway_id"]').forEach(function (input, index) {
        input.addEventListener('change', function () {
          selectedGateway = methods[index];
          refreshPlaceOrderState();
          paymentEl.querySelectorAll('.hal-cartel-checkout__payment-fields').forEach(function (el) {
            el.hidden = el.getAttribute('data-gateway') !== selectedGateway.id;
          });
          renderGatewayFields(selectedGateway, paymentEl.querySelector('[data-gateway="' + selectedGateway.id + '"]'));
        });
      });
    }

    if (paymentEl) {
      paymentEl.innerHTML = '<p>' + label('paymentLoadingLabel', 'Loading payment methods…') + '</p>';
      api('payment/methods').then(function (methods) {
        renderPaymentMethods(Array.isArray(methods) ? methods : []);
      });
    }

    function setButtonLoading(loading) {
      if (!placeOrderBtn) return;
      placeOrderBtn.disabled = loading;
      placeOrderBtn.textContent = loading
        ? label('placeOrderLoadingLabel', 'Placing order…')
        : label('placeOrderLabel', 'Place order');
    }

    function renderConfirmation(res) {
      var payment = res.payment || {};
      var items   = res.items  || [];
      var fmt     = function(n) { return formatMoney(n, { symbol: res.currency || '', position: 'before', decimals: 2 }); };

      var itemRows = items.map(function(i) {
        return '<tr class="hal-cartel-confirmation__item-row">' +
          '<td>' + String(i.quantity) + ' &times; ' + String(i.name) + '</td>' +
          '<td class="hal-cartel-confirmation__amount">' + fmt(i.total) + '</td>' +
        '</tr>';
      }).join('');

      var totalsRows =
        '<tr class="hal-cartel-confirmation__totals-row"><td>Subtotal</td><td class="hal-cartel-confirmation__amount">' + fmt(res.subtotal || 0) + '</td></tr>' +
        (res.shipping > 0 ? '<tr class="hal-cartel-confirmation__totals-row"><td>Shipping</td><td class="hal-cartel-confirmation__amount">' + fmt(res.shipping) + '</td></tr>' : '') +
        (res.tax > 0      ? '<tr class="hal-cartel-confirmation__totals-row"><td>Tax</td><td class="hal-cartel-confirmation__amount">' + fmt(res.tax) + '</td></tr>' : '') +
        '<tr class="hal-cartel-confirmation__totals-row hal-cartel-confirmation__totals-row--total"><td>Total</td><td class="hal-cartel-confirmation__amount">' + fmt(res.total || 0) + '</td></tr>';

      var instructionsHtml = (payment.instructions)
        ? '<div class="hal-cartel-confirmation__instructions">' +
            '<p class="hal-cartel-confirmation__instructions-title">Payment instructions</p>' +
            '<p class="hal-cartel-confirmation__instructions-body">' + String(payment.instructions).replace(/</g,'&lt;') + '</p>' +
          '</div>'
        : '';

      var emailNote = res.email
        ? '<p class="hal-cartel-confirmation__email-note">A confirmation email has been sent to <strong>' + String(res.email).replace(/</g,'&lt;') + '</strong>.</p>'
        : '';

      form.innerHTML =
        '<div class="hal-cartel-confirmation">' +
          '<div class="hal-cartel-confirmation__icon">&#10003;</div>' +
          '<h2 class="hal-cartel-confirmation__title">' + label('orderReceivedLabel', 'Order received!') + '</h2>' +
          '<p class="hal-cartel-confirmation__number">Order <strong>#' + String(res.order_number) + '</strong> &mdash; ' + String(res.payment_method || '') + '</p>' +
          emailNote +
          '<table class="hal-cartel-confirmation__table">' + itemRows + totalsRows + '</table>' +
          instructionsHtml +
        '</div>';
    }

    /* Hands the checkout response to the chosen gateway: Stripe needs an in-browser card
       confirmation step (card data never reaches our server); Manual just shows instructions. */
    function handlePaymentResult(res) {
      var payment = res.payment || {};

      if (payment.error) {
        setButtonLoading(false);
        alert(payment.error);
        return;
      }

      if ('requires_confirmation' === payment.status && payment.client_secret && stripeState.stripe && stripeState.card) {
        stripeState.stripe.confirmCardPayment(payment.client_secret, { payment_method: { card: stripeState.card } })
          .then(function (result) {
            setButtonLoading(false);
            if (result.error) {
              alert(result.error.message || 'Payment failed.');
              return;
            }
            renderConfirmation(res);
          });
        return;
      }

      renderConfirmation(res);
    }

    function refreshRates() {
      if (!ratesEl) return;
      var country = countrySelect ? countrySelect.value : '';
      selectedRate = null;
      if (placeOrderBtn) { placeOrderBtn.disabled = true; }

      if (!country) {
        ratesEl.innerHTML = '<p class="hal-cartel-checkout__shipping-hint">' + label('shippingHintLabel', 'Enter your destination country to see available shipping methods.') + '</p>';
        return;
      }

      var requestId = ++ratesRequest;
      ratesEl.innerHTML = '<p>' + label('shippingLoadingLabel', 'Loading shipping methods…') + '</p>';
      api('shipping/rates', { method: 'POST', body: JSON.stringify({ country: country }) })
        .then(function (rates) {
          if (requestId !== ratesRequest) return;
          renderRates(Array.isArray(rates) ? rates : []);
        });
    }

    if (countrySelect) {
      countrySelect.addEventListener('change', refreshRates);
      refreshRates();
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!selectedGateway) {
        alert(label('paymentRequiredLabel', 'Please select a payment method.'));
        return;
      }
      if (needsShipping && !selectedRate) {
        alert(label('shippingRequiredLabel', 'Please select a shipping method for your destination.'));
        return;
      }

      var fd = new FormData(form);
      var payload = {
        email: fd.get('email'),
        shipping: {},
        shipping_method_id: needsShipping && selectedRate ? selectedRate.id : 0,
        gateway_id: selectedGateway.id,
        mailchimp_opt_in: fd.get('mailchimp_opt_in') ? true : false,
      };
      ['name','address','city','postcode','country'].forEach(function (k) {
        payload.shipping[k] = fd.get('shipping[' + k + ']') || '';
      });
      var recaptchaEl = form.querySelector('[data-hal-cartel-recaptcha]');
      if (recaptchaEl && window.grecaptcha) {
        payload.recaptcha_token = window.grecaptcha.getResponse();
      }

      setButtonLoading(true);
      api('checkout', { method: 'POST', body: JSON.stringify(payload) })
        .then(function (res) {
          if (res && res.order_number) {
            handlePaymentResult(res);
          } else {
            setButtonLoading(false);
            alert((res && res.message) || 'Checkout failed. Please try again.');
          }
        })
        .catch(function () {
          setButtonLoading(false);
          alert('Something went wrong. Please check your connection and try again.');
        });
    });
  }
})();