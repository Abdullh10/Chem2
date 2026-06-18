const HallPass = (() => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  async function fetchJSON(url, options = {}) {
    const opts = {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        ...(options.headers || {}),
      },
    };
    const res = await fetch(url, opts);
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(body.error || 'حدث خطأ غير متوقع');
    }
    return body;
  }

  function formatElapsed(seconds) {
    seconds = Math.max(0, Math.floor(seconds));
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) return `${h} س ${m} د`;
    if (m > 0) return `${m} د ${s} ث`;
    return `${s} ث`;
  }

  function formatTime(isoLike) {
    const d = new Date(isoLike.replace(' ', 'T'));
    return d.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });
  }

  function elapsedClass(seconds, overdueMinutes) {
    const overdueSeconds = overdueMinutes * 60;
    if (seconds > overdueSeconds) return 'elapsed-red';
    if (seconds > overdueSeconds * 0.6) return 'elapsed-amber';
    return 'elapsed-green';
  }

  function td(text, className) {
    const cell = document.createElement('td');
    if (className) cell.className = className;
    cell.textContent = text;
    return cell;
  }

  return { fetchJSON, formatElapsed, formatTime, elapsedClass, td };
})();
