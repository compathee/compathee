(function () {
  "use strict";

  var select = document.getElementById("lang");
  var node = document.getElementById("lang-map");
  if (select && node) {
    var map = JSON.parse(node.textContent);
    var current = document.documentElement.getAttribute("data-lang");
    var storageKey = "compath-lang";

    function go(code, replace) {
      if (!map[code] || code === current) return;
      if (replace) location.replace(map[code]);
      else location.href = map[code];
    }

    select.addEventListener("change", function () {
      try { localStorage.setItem(storageKey, select.value); } catch (error) {}
      go(select.value, false);
    });

    var saved = null;
    try { saved = localStorage.getItem(storageKey); } catch (error) {}
    var ua = navigator.userAgent || "";
    var bot = /bot|crawler|spider|slurp|gptbot|oai-searchbot|claudebot|perplexity|google-extended|facebookexternalhit|embedly|quora|pinterest|redditbot|ia_archiver|semrush|ahrefs|yandex|baidu|duckduck/i;
    var base = document.documentElement.getAttribute("data-base") || "";
    var homePath = base ? base + "/" : "/";
    if (saved && map[saved]) {
      go(saved, true);
    } else if (!navigator.webdriver && !bot.test(ua) && current === "en" && (location.pathname === homePath || (base && location.pathname === base))) {
      // First visit only rewrites the English home. A link to /et/ or /ru/
      // is already a language choice, and crawlers must keep the URL they fetched.
      var list = navigator.languages || [navigator.language || "en"];
      var pick = "en";
      for (var i = 0; i < list.length; i += 1) {
        var pref = String(list[i] || "").toLowerCase();
        if (pref.indexOf("et") === 0) { pick = "et"; break; }
        if (pref.indexOf("ru") === 0) { pick = "ru"; break; }
        if (pref.indexOf("en") === 0) { pick = "en"; break; }
      }
      try { localStorage.setItem(storageKey, pick); } catch (error) {}
      go(pick, true);
    }
  }

  var form = document.getElementById("contact-form");
  if (!form || !window.fetch) return;
  var status = document.getElementById("form-status");
  var strings = {
    sending: form.getAttribute("data-sending") || "",
    success: form.getAttribute("data-success") || "",
    invalid: form.getAttribute("data-invalid") || "",
    error: form.getAttribute("data-error") || ""
  };

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    var button = form.querySelector("button[type=submit]");
    if (button) button.disabled = true;
    if (status) {
      status.className = "status";
      status.textContent = strings.sending;
    }
    fetch(form.action, {
      method: "POST",
      body: new FormData(form),
      headers: { "Accept": "application/json" }
    }).then(function (response) {
      return response.json().then(function (body) {
        return { ok: response.ok, status: response.status, body: body };
      });
    }).then(function (result) {
      if (result.ok && result.body && result.body.ok) {
        if (status) {
          status.className = "status status--ok";
          status.textContent = strings.success;
        }
        form.reset();
        return;
      }
      if (status) {
        status.className = "status status--err";
        status.textContent = result.status === 400 ? strings.invalid : strings.error;
      }
    }).catch(function () {
      if (status) {
        status.className = "status status--err";
        status.textContent = strings.error;
      }
    }).then(function () {
      if (button) button.disabled = false;
    });
  });
})();
