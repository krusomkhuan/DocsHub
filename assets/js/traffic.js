(function () {
  var widget = document.querySelector('[data-traffic-url]');
  if (!widget) return;
  var pending = false;
  async function refresh() {
    if (document.hidden || pending) return;
    pending = true;
    try {
      var response = await fetch(widget.dataset.trafficUrl, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf: widget.dataset.trafficCsrf }),
        signal: AbortSignal.timeout(15000)
      });
      if (!response.ok || response.redirected) return;
      var stats = await response.json();
      widget.querySelectorAll('[data-traffic]').forEach(function (el) {
        var value = stats[el.dataset.traffic];
        if (Number.isSafeInteger(value) && value >= 0) el.textContent = value.toLocaleString('en-US');
      });
    } catch (e) {
      // คงตัวเลขล่าสุดเมื่อออฟไลน์หรือเซสชันหมดอายุ
    } finally { pending = false; }
  }
  setInterval(refresh, 60000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
})();
