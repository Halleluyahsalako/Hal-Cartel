/* Cartel - admin JS: toggles [data-hal-cartel-advanced] visibility per the user's stored UI mode (no reload). */
(function () {
  if (typeof HalCartelUIData === 'undefined') return;

  function applyMode() {
    var advanced = HalCartelUIData.mode === 'advanced';
    document.querySelectorAll('[data-hal-cartel-advanced]').forEach(function (el) {
      el.classList.toggle('hal-cartel-advanced-field--hidden', !advanced);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyMode);
  } else {
    applyMode();
  }
})();

/* Cartel - product data tabs: switches the visible panel, no page reload. */
(function () {
  document.querySelectorAll('[data-hal-cartel-tabs]').forEach(function (wrap) {
    var tabs = wrap.querySelectorAll('.hal-cartel-product-data__tabs a');
    var panels = wrap.querySelectorAll('.hal-cartel-product-data__panel');

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        var name = tab.getAttribute('data-tab');

        tabs.forEach(function (t) {
          t.parentElement.classList.toggle('hal-cartel-product-data__tab--active', t === tab);
        });
        panels.forEach(function (panel) {
          panel.hidden = panel.getAttribute('data-panel') !== name;
        });
      });
    });
  });
})();

/* Cartel - copy-to-clipboard for shortcode boxes (product editor + reference page). */
(function () {
  document.querySelectorAll('[data-hal-cartel-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var row = button.closest('.hal-cartel-copy-row');
      var source = row && row.querySelector('.hal-cartel-copy-source');
      if (!source) return;

      var finish = function () {
        var original = button.textContent;
        button.setAttribute('data-copied', '1');
        button.textContent = (typeof HalCartelUIData !== 'undefined' && HalCartelUIData.copiedLabel) || 'Copied';
        setTimeout(function () {
          button.removeAttribute('data-copied');
          button.textContent = original;
        }, 1500);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(source.value).then(finish, function () {
          source.select();
          document.execCommand('copy');
          finish();
        });
      } else {
        source.select();
        document.execCommand('copy');
        finish();
      }
    });
  });
})();

/* Cartel - gallery image picker, backed by wp.media (only loaded on the product editor screen). */
(function () {
  if (typeof wp === 'undefined' || !wp.media) return;

  document.querySelectorAll('[data-hal-cartel-gallery]').forEach(function (list) {
    var wrap = list.closest('.hal-cartel-product-data__panel');
    var addButton = wrap && wrap.querySelector('[data-hal-cartel-gallery-add]');
    var input = wrap && wrap.querySelector('[data-hal-cartel-gallery-input]');
    if (!addButton || !input) return;

    var frame = null;

    function ids() {
      return list.querySelectorAll('.hal-cartel-gallery__item');
    }

    function syncInput() {
      var values = [];
      ids().forEach(function (item) { values.push(item.getAttribute('data-id')); });
      input.value = values.join(',');
    }

    function addItem(attachment) {
      var item = document.createElement('li');
      item.className = 'hal-cartel-gallery__item';
      item.setAttribute('data-id', attachment.id);

      var img = document.createElement('img');
      img.src = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
      item.appendChild(img);

      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'hal-cartel-gallery__remove';
      remove.setAttribute('aria-label', (typeof HalCartelUIData !== 'undefined' && HalCartelUIData.removeImageLabel) || 'Remove image');
      remove.textContent = String.fromCharCode(215);
      item.appendChild(remove);

      list.appendChild(item);
    }

    list.addEventListener('click', function (e) {
      if (e.target.classList.contains('hal-cartel-gallery__remove')) {
        e.target.closest('.hal-cartel-gallery__item').remove();
        syncInput();
      }
    });

    addButton.addEventListener('click', function (e) {
      e.preventDefault();

      if (!frame) {
        frame = wp.media({
          title: (typeof HalCartelUIData !== 'undefined' && HalCartelUIData.galleryTitle) || 'Select images',
          button: { text: (typeof HalCartelUIData !== 'undefined' && HalCartelUIData.galleryButton) || 'Add to gallery' },
          multiple: true,
        });

        frame.on('select', function () {
          var existing = [];
          ids().forEach(function (item) { existing.push(item.getAttribute('data-id')); });

          frame.state().get('selection').each(function (attachment) {
            attachment = attachment.toJSON();
            if (existing.indexOf(String(attachment.id)) === -1) {
              addItem(attachment);
            }
          });
          syncInput();
        });
      }

      frame.open();
    });
  });
})();

/* Cartel - product type bar: shows/hides tabs to match the chosen product type + virtual/downloadable flags. */
(function () {
  document.querySelectorAll('[data-hal-cartel-tabs]').forEach(function (wrap) {
    var typeSelect = wrap.querySelector('[data-hal-cartel-type-select]');
    var virtualBox = wrap.querySelector('[data-hal-cartel-virtual]');
    var downloadableBox = wrap.querySelector('[data-hal-cartel-downloadable]');
    if (!typeSelect) return;

    var tabItems = wrap.querySelectorAll('.hal-cartel-product-data__tabs > li');
    var panels = wrap.querySelectorAll('.hal-cartel-product-data__panel');

    function isVisible(li) {
      var forTypes = li.getAttribute('data-tab-for-types');
      if (forTypes && forTypes.split(/\s+/).indexOf(typeSelect.value) === -1) return false;
      if (li.getAttribute('data-tab-hide-if-virtual') === '1' && virtualBox && virtualBox.checked) return false;
      if (li.getAttribute('data-tab-show-if-downloadable') === '1' && (!downloadableBox || !downloadableBox.checked)) return false;
      return true;
    }

    function refresh() {
      var activeName = null;
      var fallbackLi = null;

      tabItems.forEach(function (li) {
        var visible = isVisible(li);
        li.hidden = !visible;
        if (visible && !fallbackLi) { fallbackLi = li; }
        if (!visible) {
          li.classList.remove('hal-cartel-product-data__tab--active');
        } else if (li.classList.contains('hal-cartel-product-data__tab--active')) {
          activeName = li.querySelector('a').getAttribute('data-tab');
        }
      });

      if (!activeName && fallbackLi) {
        fallbackLi.classList.add('hal-cartel-product-data__tab--active');
        activeName = fallbackLi.querySelector('a').getAttribute('data-tab');
      }

      panels.forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-panel') !== activeName;
      });
    }

    typeSelect.addEventListener('change', refresh);
    if (virtualBox) { virtualBox.addEventListener('change', refresh); }
    if (downloadableBox) { downloadableBox.addEventListener('change', refresh); }
    refresh();
  });
})();

/* Cartel - Attributes tab: repeatable name/values rows, serialized to a hidden JSON field on submit. */
(function () {
  function label(key, fallback) {
    return (typeof HalCartelUIData !== 'undefined' && HalCartelUIData[key]) || fallback;
  }

  function splitValues(raw) {
    return raw.split('|').map(function (v) { return v.trim(); }).filter(function (v) { return v.length; });
  }

  document.querySelectorAll('[data-hal-cartel-attributes]').forEach(function (list) {
    var panel = list.closest('.hal-cartel-product-data__panel');
    var addButton = panel && panel.querySelector('[data-hal-cartel-attribute-add]');
    var input = panel && panel.querySelector('[data-hal-cartel-attributes-input]');
    var form = list.closest('form');
    if (!addButton || !input) return;

    function rows() {
      return list.querySelectorAll('[data-hal-cartel-attribute-row]');
    }

    function createRow() {
      var row = document.createElement('div');
      row.className = 'hal-cartel-attribute-row';
      row.setAttribute('data-hal-cartel-attribute-row', '');
      row.innerHTML =
        '<input type="text" data-attr-name />' +
        '<input type="text" data-attr-values />' +
        '<label class="hal-cartel-flag-label"><input type="checkbox" data-attr-used-for-variations /> <span></span></label>' +
        '<button type="button" class="hal-cartel-attribute-remove" data-hal-cartel-attribute-remove></button>';

      row.querySelector('[data-attr-name]').setAttribute('placeholder', label('attrNamePlaceholder', 'e.g. Color'));
      row.querySelector('[data-attr-values]').setAttribute('placeholder', label('attrValuesPlaceholder', 'Red | Blue | Green'));
      row.querySelector('.hal-cartel-flag-label span').textContent = label('usedForVariations', 'Used for variations');

      var removeBtn = row.querySelector('[data-hal-cartel-attribute-remove]');
      removeBtn.setAttribute('aria-label', label('removeAttributeLabel', 'Remove attribute'));
      removeBtn.textContent = String.fromCharCode(215);

      return row;
    }

    addButton.addEventListener('click', function (e) {
      e.preventDefault();
      list.appendChild(createRow());
    });

    list.addEventListener('click', function (e) {
      var removeBtn = e.target.closest('[data-hal-cartel-attribute-remove]');
      if (removeBtn) {
        removeBtn.closest('[data-hal-cartel-attribute-row]').remove();
      }
    });

    function serialize() {
      var data = [];
      rows().forEach(function (row) {
        var name = row.querySelector('[data-attr-name]').value.trim();
        var values = splitValues(row.querySelector('[data-attr-values]').value);
        if (!name || !values.length) return;
        data.push({
          name: name,
          values: values,
          used_for_variations: row.querySelector('[data-attr-used-for-variations]').checked
        });
      });
      input.value = JSON.stringify(data);
    }

    if (form) { form.addEventListener('submit', serialize); }
  });
})();

/* Cartel - Variations tab: generates rows from attributes marked "Used for variations", manages per-row images, serializes to a hidden JSON field on submit. */
(function () {
  if (typeof wp === 'undefined' || !wp.media) return;

  function label(key, fallback) {
    return (typeof HalCartelUIData !== 'undefined' && HalCartelUIData[key]) || fallback;
  }

  function cartesian(lists) {
    return lists.reduce(function (acc, values) {
      var next = [];
      acc.forEach(function (combo) {
        values.forEach(function (value) {
          next.push(combo.concat([value]));
        });
      });
      return next;
    }, [[]]);
  }

  function formatLabel(attributes) {
    var parts = [];
    Object.keys(attributes).forEach(function (name) {
      parts.push(name + ': ' + attributes[name]);
    });
    return parts.length ? parts.join(', ') : label('noAttributesLabel', '(no attributes selected)');
  }

  document.querySelectorAll('[data-hal-cartel-variations]').forEach(function (list) {
    var panel = list.closest('.hal-cartel-product-data__panel');
    var tabsWrap = list.closest('[data-hal-cartel-tabs]');
    if (!panel || !tabsWrap) return;

    var generateButton = panel.querySelector('[data-hal-cartel-generate-variations]');
    var input = panel.querySelector('[data-hal-cartel-variations-input]');
    var template = panel.querySelector('[data-hal-cartel-variation-template]');
    var attributesContainer = tabsWrap.querySelector('[data-hal-cartel-attributes]');
    var form = list.closest('form');
    if (!generateButton || !input || !template || !template.content) return;

    function rows() {
      return Array.prototype.slice.call(list.querySelectorAll('[data-hal-cartel-variation]'));
    }

    function refreshEmptyNotice() {
      var notice = list.querySelector('[data-hal-cartel-variations-empty]');
      if (rows().length) {
        if (notice) { notice.remove(); }
      } else if (!notice) {
        var p = document.createElement('p');
        p.className = 'hal-cartel-variations-empty';
        p.setAttribute('data-hal-cartel-variations-empty', '');
        p.textContent = label('noVariationsLabel', 'No variations yet. Mark at least one attribute "Used for variations" and click Generate above.');
        list.appendChild(p);
      }
    }

    function variationAttributeLists() {
      if (!attributesContainer) { return []; }
      var lists = [];
      attributesContainer.querySelectorAll('[data-hal-cartel-attribute-row]').forEach(function (row) {
        var name = row.querySelector('[data-attr-name]').value.trim();
        var usedForVariations = row.querySelector('[data-attr-used-for-variations]').checked;
        if (!name || !usedForVariations) { return; }
        var values = row.querySelector('[data-attr-values]').value.split('|')
          .map(function (v) { return v.trim(); })
          .filter(function (v) { return v.length; });
        if (values.length) { lists.push({ name: name, values: values }); }
      });
      return lists;
    }

    function existingAttributeKeys() {
      var keys = {};
      rows().forEach(function (row) {
        keys[row.getAttribute('data-attributes') || '{}'] = true;
      });
      return keys;
    }

    function addRow(attributes) {
      var clone = template.content.firstElementChild.cloneNode(true);
      clone.setAttribute('data-attributes', JSON.stringify(attributes));
      var badge = clone.querySelector('.hal-cartel-badge');
      if (badge) { badge.textContent = formatLabel(attributes); }
      list.appendChild(clone);
    }

    generateButton.addEventListener('click', function (e) {
      e.preventDefault();
      var lists = variationAttributeLists();
      if (!lists.length) {
        window.alert(label('noAttributesForGen', 'Mark at least one attribute as "Used for variations" first, then click Generate again.'));
        return;
      }

      var names = lists.map(function (l) { return l.name; });
      var combos = cartesian(lists.map(function (l) { return l.values; }));
      var existing = existingAttributeKeys();

      combos.forEach(function (combo) {
        var attributes = {};
        names.forEach(function (name, i) { attributes[name] = combo[i]; });
        var key = JSON.stringify(attributes);
        if (existing[key]) { return; }
        addRow(attributes);
        existing[key] = true;
      });

      refreshEmptyNotice();
    });

    list.addEventListener('click', function (e) {
      var removeBtn = e.target.closest('[data-hal-cartel-variation-remove]');
      if (removeBtn) {
        removeBtn.closest('[data-hal-cartel-variation]').remove();
        refreshEmptyNotice();
        return;
      }

      var imageBox = e.target.closest('[data-hal-cartel-variation-image]');
      if (imageBox) {
        e.preventDefault();
        var frame = wp.media({
          title: label('variationImageTitle', 'Select a variation image'),
          button: { text: label('variationImageButton', 'Use image') },
          multiple: false
        });
        frame.on('select', function () {
          var attachment = frame.state().get('selection').first().toJSON();
          imageBox.setAttribute('data-image-id', attachment.id);
          imageBox.innerHTML = '';
          var img = document.createElement('img');
          img.src = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
          imageBox.appendChild(img);
        });
        frame.open();
      }
    });

    function serialize() {
      var data = [];
      rows().forEach(function (row) {
        var attributes = {};
        try { attributes = JSON.parse(row.getAttribute('data-attributes') || '{}'); } catch (err) { attributes = {}; }
        if (!Object.keys(attributes).length) { return; }

        data.push({
          id: parseInt(row.querySelector('[data-var-id]').value, 10) || 0,
          attributes: attributes,
          enabled: row.querySelector('[data-var-enabled]').checked,
          sku: row.querySelector('[data-var-sku]').value.trim(),
          price: row.querySelector('[data-var-price]').value,
          sale_price: row.querySelector('[data-var-sale-price]').value,
          manage_stock: row.querySelector('[data-var-manage-stock]').checked,
          stock: row.querySelector('[data-var-stock]').value,
          weight: row.querySelector('[data-var-weight]').value,
          image: parseInt(imageIdOf(row), 10) || 0
        });
      });
      input.value = JSON.stringify(data);
    }

    function imageIdOf(row) {
      var box = row.querySelector('[data-hal-cartel-variation-image]');
      return box ? box.getAttribute('data-image-id') : '0';
    }

    if (form) { form.addEventListener('submit', serialize); }
    refreshEmptyNotice();
  });
})();

/* Cartel - Downloads tab: single-file picker backed by wp.media. */
(function () {
  if (typeof wp === 'undefined' || !wp.media) return;

  function label(key, fallback) {
    return (typeof HalCartelUIData !== 'undefined' && HalCartelUIData[key]) || fallback;
  }

  document.querySelectorAll('[data-hal-cartel-download-choose]').forEach(function (button) {
    var panel = button.closest('.hal-cartel-product-data__panel');
    var input = panel && panel.querySelector('[data-hal-cartel-download-file-input]');
    var filenameEl = panel && panel.querySelector('[data-hal-cartel-download-filename]');
    if (!input || !filenameEl) return;

    var frame = null;

    button.addEventListener('click', function (e) {
      e.preventDefault();

      if (!frame) {
        frame = wp.media({
          title: label('downloadFileTitle', 'Select a downloadable file'),
          button: { text: label('downloadFileButton', 'Use file') },
          multiple: false
        });

        frame.on('select', function () {
          var attachment = frame.state().get('selection').first().toJSON();
          input.value = attachment.id;
          filenameEl.textContent = attachment.filename || attachment.title || ('#' + attachment.id);
          button.textContent = label('changeFileLabel', 'Change file');
        });
      }

      frame.open();
    });
  });
})();

/* Cartel - Shipping zones admin: nested zone/method repeater (one level deeper than Variations), serialized to a hidden JSON field on submit. */
(function () {
  function label(key, fallback) {
    return (typeof HalCartelUIData !== 'undefined' && HalCartelUIData[key]) || fallback;
  }

  var wrap = document.querySelector('[data-hal-cartel-shipping-zones]');
  if (!wrap) return;

  var page           = wrap.closest('.wrap');
  var addZoneBtn     = page.querySelector('[data-hal-cartel-shipping-zone-add]');
  var zoneTemplate   = page.querySelector('[data-hal-cartel-shipping-zone-template]');
  var methodTemplate = page.querySelector('[data-hal-cartel-shipping-method-template]');
  var input          = page.querySelector('[data-hal-cartel-shipping-zones-input]');
  var form           = wrap.closest('form');
  if (!addZoneBtn || !zoneTemplate || !methodTemplate || !input || !form) return;
  if (!zoneTemplate.content || !methodTemplate.content) return;

  function zones() {
    return Array.prototype.slice.call(wrap.querySelectorAll('[data-hal-cartel-shipping-zone]'));
  }

  function methodsOf(zone) {
    var list = zone.querySelector('[data-hal-cartel-shipping-methods]');
    return list ? Array.prototype.slice.call(list.querySelectorAll('[data-hal-cartel-shipping-method]')) : [];
  }

  function refreshMethodVisibility(method) {
    var type = method.querySelector('[data-method-type-select]').value;
    method.querySelectorAll('[data-method-settings-for]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-method-settings-for') !== type;
    });
  }

  function addBracketRow(container, bracket) {
    bracket = bracket || {};
    var row = document.createElement('div');
    row.className = 'hal-cartel-weight-bracket';
    row.setAttribute('data-hal-cartel-weight-bracket', '');
    row.innerHTML =
      '<input type="number" step="0.01" data-bracket-up-to />' +
      '<input type="number" step="0.01" data-bracket-cost />' +
      '<button type="button" data-hal-cartel-weight-bracket-remove></button>';
    row.querySelector('[data-bracket-up-to]').setAttribute('placeholder', label('weightBracketUpTo', 'Up to'));
    row.querySelector('[data-bracket-cost]').setAttribute('placeholder', label('weightBracketCost', 'Cost'));
    var removeBtn = row.querySelector('[data-hal-cartel-weight-bracket-remove]');
    removeBtn.setAttribute('aria-label', label('removeBracketLabel', 'Remove bracket'));
    removeBtn.textContent = String.fromCharCode(215);

    if (bracket.up_to !== undefined && bracket.up_to !== null) { row.querySelector('[data-bracket-up-to]').value = bracket.up_to; }
    if (bracket.cost !== undefined && bracket.cost !== null) { row.querySelector('[data-bracket-cost]').value = bracket.cost; }
    container.appendChild(row);
  }

  function wireMethod(method) {
    var typeSelect = method.querySelector('[data-method-type-select]');
    typeSelect.addEventListener('change', function () { refreshMethodVisibility(method); });
    refreshMethodVisibility(method);

    var bracketsContainer = method.querySelector('[data-hal-cartel-weight-brackets]');
    var addBracketBtn = method.querySelector('[data-hal-cartel-weight-bracket-add]');
    if (addBracketBtn && bracketsContainer) {
      addBracketBtn.addEventListener('click', function (e) {
        e.preventDefault();
        addBracketRow(bracketsContainer);
      });
    }
  }

  function addMethodToZone(zone) {
    var clone = methodTemplate.content.firstElementChild.cloneNode(true);
    zone.querySelector('[data-hal-cartel-shipping-methods]').appendChild(clone);
    wireMethod(clone);
  }

  function wireZone(zone) {
    var addMethodBtn = zone.querySelector('[data-hal-cartel-shipping-method-add]');
    if (addMethodBtn) {
      addMethodBtn.addEventListener('click', function (e) {
        e.preventDefault();
        addMethodToZone(zone);
      });
    }
    methodsOf(zone).forEach(wireMethod);
  }

  function addZone() {
    var clone = zoneTemplate.content.firstElementChild.cloneNode(true);
    wrap.appendChild(clone);
    wireZone(clone);
  }

  addZoneBtn.addEventListener('click', function (e) {
    e.preventDefault();
    addZone();
  });

  wrap.addEventListener('click', function (e) {
    var zoneRemove = e.target.closest('[data-hal-cartel-shipping-zone-remove]');
    if (zoneRemove) { zoneRemove.closest('[data-hal-cartel-shipping-zone]').remove(); return; }

    var methodRemove = e.target.closest('[data-hal-cartel-shipping-method-remove]');
    if (methodRemove) { methodRemove.closest('[data-hal-cartel-shipping-method]').remove(); return; }

    var bracketRemove = e.target.closest('[data-hal-cartel-weight-bracket-remove]');
    if (bracketRemove) { bracketRemove.closest('[data-hal-cartel-weight-bracket]').remove(); return; }
  });

  function serializeMethod(method) {
    var type = method.querySelector('[data-method-type-select]').value;
    var settings = {};

    if ('flat_rate' === type) {
      settings.base_cost = method.querySelector('[data-setting-base-cost]').value;
      var classCosts = {};
      method.querySelectorAll('[data-setting-class-cost]').forEach(function (el) {
        if ('' !== el.value) { classCosts[el.getAttribute('data-term-id')] = el.value; }
      });
      settings.class_costs = classCosts;
    } else if ('free_shipping' === type) {
      settings.min_order_amount = method.querySelector('[data-setting-min-order]').value;
    } else if ('local_pickup' === type) {
      settings.cost = method.querySelector('[data-setting-cost]').value;
    } else if ('weight_based' === type) {
      var brackets = [];
      method.querySelectorAll('[data-hal-cartel-weight-bracket]').forEach(function (row) {
        var upTo = row.querySelector('[data-bracket-up-to]').value;
        brackets.push({
          up_to: '' === upTo ? null : upTo,
          cost: row.querySelector('[data-bracket-cost]').value
        });
      });
      settings.brackets = brackets;
    }

    return {
      id: parseInt(method.querySelector('[data-method-id]').value, 10) || 0,
      type: type,
      title: method.querySelector('[data-method-title]').value.trim(),
      enabled: method.querySelector('[data-method-enabled]').checked,
      settings: settings
    };
  }

  function serializeZone(zone) {
    var locations = [];
    zone.querySelectorAll('[data-zone-locations] option:checked').forEach(function (opt) {
      locations.push(opt.value);
    });

    return {
      id: parseInt(zone.querySelector('[data-zone-id]').value, 10) || 0,
      name: zone.querySelector('[data-zone-name]').value.trim(),
      locations: locations,
      methods: methodsOf(zone).map(serializeMethod)
    };
  }

  function serialize() {
    input.value = JSON.stringify(zones().map(serializeZone));
  }

  zones().forEach(wireZone);
  form.addEventListener('submit', serialize);
})();
