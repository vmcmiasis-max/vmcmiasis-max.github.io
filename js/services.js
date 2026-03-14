/**
 * services.js
 * Завантажує services.json і надає функції для рендерингу послуг.
 * Замінює стару логіку на основі статичних HTML-джерел.
 */

(function () {
  'use strict';

  // ── Завантаження даних ────────────────────────────────────────────────────

  function loadServices() {
    return fetch('./services.json')
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        window.SERVICES_DATA = data;

        // Заповнимо SERVICE_DESCRIPTIONS у форматі, який очікує решта коду
        buildServiceDescriptions(data);

        // Позначимо, що дані готові (щоб initializeAllCategories знайшло їх)
        window._servicesReady = true;

        return data;
      })
      .catch(function (err) {
        console.error('services.js: не вдалося завантажити services.json', err);
      });
  }

  /**
   * Будує window.SERVICE_DESCRIPTIONS[categoryId][serviceTitle] = htmlString
   * Формат сумісний з логікою index.html що шукає опис по назві.
   */
  function buildServiceDescriptions(data) {
    window.SERVICE_DESCRIPTIONS = {};
    data.categories.forEach(function (cat) {
      window.SERVICE_DESCRIPTIONS[cat.id] = {};
      cat.services.forEach(function (svc) {
        var imgHtml = '';
        if (svc.image) {
          var src = svc.image.trim();
          if (!src.startsWith('./') && !src.startsWith('/') &&
              !src.startsWith('http://') && !src.startsWith('https://') &&
              !src.startsWith('data:')) {
            src = './' + src;
          }
          imgHtml = '<img src="' + escHtml(src) + '" alt="' + escHtml(svc.title) +
            '" style="max-width:100%; height:auto; margin-bottom:15px; border-radius:8px;">';
        }
        window.SERVICE_DESCRIPTIONS[cat.id][svc.title] =
          '<div class="description">' + imgHtml + (svc.description || '') + '</div>';
      });
    });
  }

  // ── Генерація HTML для popup-body ─────────────────────────────────────────

  /**
   * Заповнює popup-body для категорії з JSON (замість generateServicesFromPrices).
   * Генерує ту саму HTML-структуру, що очікують openCompactList та openDetailFromElement.
   * @param {string} categoryId
   * @param {HTMLElement} bodyEl  — popup .popup-body
   */
  function renderServicesIntoBody(categoryId, bodyEl) {
    if (!window.SERVICES_DATA) {
      bodyEl.innerHTML = '<p style="padding:20px;text-align:center;">Завантажуємо...</p>';
      // Спробуємо ще раз коли данні завантажаться
      var t = setInterval(function () {
        if (window.SERVICES_DATA) {
          clearInterval(t);
          renderServicesIntoBody(categoryId, bodyEl);
        }
      }, 100);
      setTimeout(function () { clearInterval(t); }, 5000);
      return;
    }

    var cat = getCategoryById(categoryId);
    if (!cat) {
      bodyEl.innerHTML = '<p style="padding:20px;text-align:center;color:red;">Категорію не знайдено: ' + escHtml(categoryId) + '</p>';
      return;
    }

    var html = '';
    cat.services.forEach(function (svc) {
      var imgHtml = '';
      if (svc.image) {
        var src = svc.image.trim();
        if (!src.startsWith('./') && !src.startsWith('/') &&
            !src.startsWith('http://') && !src.startsWith('https://') &&
            !src.startsWith('data:')) {
          src = './' + src;
        }
        imgHtml = '<img src="' + escHtml(src) + '" alt="' + escHtml(svc.title) +
          '" style="max-width:100%;height:auto;margin-bottom:12px;border-radius:8px;">';
      }

      var priceHtml = '';
      if (svc.priceNote) {
        priceHtml += '<p style="margin:8px 0 10px;font-size:15px;color:var(--text);font-weight:600;">' +
          escHtml(svc.priceNote) + '</p>';
      }
      priceHtml += (svc.priceHtml || '<p style="font-size:14px;color:var(--accent-text);font-weight:600;">Вартість: Індивідуальний розрахунок</p>');

      html +=
        '<div class="svc-block" style="margin-bottom:30px;padding:20px;background:#e3f2fd;border-radius:10px;border-left:4px solid #2196f3;">' +
          '<h4 style="margin:0 0 10px;font-size:18px;color:#1565c0;cursor:pointer;">' + escHtml(svc.title) + '</h4>' +
          imgHtml +
          '<div class="description">' + (svc.description || '') + '</div>' +
          '<div class="svc-price-block" style="overflow-x:auto;margin-top:15px;">' + priceHtml + '</div>' +
        '</div>';
    });

    bodyEl.innerHTML = html || '<p style="padding:20px;text-align:center;">Послуги відсутні</p>';
  }

  // ── Ціни для детального попапу ────────────────────────────────────────────

  /**
   * Вставляє прайс у .detail-price контейнер детального попапу.
   * Замінює insertPricesFromDoc, але з даних JSON.
   * @param {string} title — назва послуги (наприклад "1. Топографічна зйомка")
   * @param {HTMLElement} detailBody — контейнер #popup-detail .popup-body
   */
  function insertPricesFromJSON(title, detailBody) {
    if (!detailBody) return;

    var priceContainer = detailBody.querySelector('.detail-price');
    if (!priceContainer) {
      priceContainer = document.createElement('div');
      priceContainer.className = 'detail-price';
      detailBody.appendChild(priceContainer);
    }

    var svc = findServiceByTitle(title);
    if (svc) {
      var content = '';
      if (svc.priceNote) {
        content += '<p style="margin:0 0 10px;font-size:15px;color:var(--text);font-weight:600;">' +
          escHtml(svc.priceNote) + '</p>';
      }
      content += svc.priceHtml || '<p style="margin:0;font-size:14px;color:var(--accent-text);font-weight:600;">Вартість: Індивідуальний розрахунок</p>';
      priceContainer.innerHTML = content;
    } else {
      priceContainer.innerHTML = '<p style="margin:0;font-size:14px;color:var(--accent-text);font-weight:600;">Вартість: Індивідуальний розрахунок</p>';
    }
  }

  // ── Пошук по JSON ─────────────────────────────────────────────────────────

  /**
   * Повертає масив результатів пошуку по всіх послугах.
   * @param {string} query
   * @returns {Array<{categoryTitle, serviceTitle, description, categoryId}>}
   */
  function searchServices(query) {
    if (!window.SERVICES_DATA || !query) return [];
    var q = normalizeText(query);
    var results = [];
    window.SERVICES_DATA.categories.forEach(function (cat) {
      cat.services.forEach(function (svc) {
        var titleNorm = normalizeText(svc.title);
        var descNorm = normalizeText(stripHtml(svc.description || ''));
        if (titleNorm.includes(q) || descNorm.includes(q)) {
          results.push({
            categoryId: cat.id,
            categoryTitle: cat.title,
            serviceTitle: svc.title,
            description: svc.description || '',
            image: svc.image || ''
          });
        }
      });
    });
    return results;
  }

  // ── Допоміжні функції ─────────────────────────────────────────────────────

  function getCategoryById(id) {
    if (!window.SERVICES_DATA) return null;
    return window.SERVICES_DATA.categories.find(function (c) { return c.id === id; }) || null;
  }

  function findServiceByTitle(title) {
    if (!window.SERVICES_DATA) return null;
    var norm = function (s) {
      return (s || '').replace(/[\u{1F300}-\u{1FFFF}]/gu, '').replace(/^\d+\.\s*/, '')
        .toLowerCase().replace(/\s+/g, ' ').replace(/['`\u2019]/g, "'").trim();
    };
    var t = norm(title);
    var best = null;
    var bestScore = 0;
    window.SERVICES_DATA.categories.forEach(function (cat) {
      cat.services.forEach(function (svc) {
        var s = norm(svc.title);
        var score = 0;
        if (s === t) score = 1000;
        else if (s.includes(t) || t.includes(s)) score = 800;
        else {
          var words = t.split(' ');
          var matched = 0;
          words.forEach(function (w) { if (w.length > 3 && s.includes(w)) matched++; });
          score = matched * 100;
        }
        if (score > bestScore) { bestScore = score; best = svc; }
      });
    });
    return bestScore >= 100 ? best : null;
  }

  function normalizeText(s) {
    return (s || '').toLowerCase().replace(/\s+/g, ' ').trim();
  }

  function stripHtml(s) {
    return s.replace(/<[^>]+>/g, ' ');
  }

  function escHtml(s) {
    return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // ── Публічний API ─────────────────────────────────────────────────────────

  window.ServicesModule = {
    load: loadServices,
    renderServicesIntoBody: renderServicesIntoBody,
    insertPricesFromJSON: insertPricesFromJSON,
    searchServices: searchServices,
    getCategoryById: getCategoryById,
    findServiceByTitle: findServiceByTitle
  };

  // Автоматично завантажуємо при підключенні скрипта
  loadServices();

})();
