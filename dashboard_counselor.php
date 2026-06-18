<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('counselor');
$user = current_user();
$pageTitle = 'لوحة المرشد الطلابي';

$stmt = $pdo->prepare('SELECT * FROM grades WHERE id = ?');
$stmt->execute([$user['grade_id']]);
$grade = $stmt->fetch();

$stmt = $pdo->prepare('SELECT id, name FROM sections WHERE grade_id = ? ORDER BY name');
$stmt->execute([$user['grade_id']]);
$sections = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="section-title"><?= htmlspecialchars($grade['name'] ?? 'مرحلتي') ?></div>

<div class="stat-cards">
  <div class="stat-card is-amber"><div class="stat-value" id="statOut">0</div><div class="stat-label">خارجون الآن</div></div>
  <div class="stat-card is-red"><div class="stat-value" id="statOverdue">0</div><div class="stat-label">تجاوزوا الوقت المسموح</div></div>
  <div class="stat-card is-green"><div class="stat-value" id="statToday">0</div><div class="stat-label">عمليات خروج اليوم</div></div>
</div>

<div class="card">
  <h2>الطلاب الخارجون حالياً</h2>
  <table class="data-table">
    <thead><tr><th>الطالب</th><th>الشعبة</th><th>السبب</th><th>المعلم</th><th>وقت الخروج</th><th>المدة</th><th></th></tr></thead>
    <tbody id="currentBody"></tbody>
  </table>
  <div class="empty-msg" id="currentEmpty" style="display:none;">لا يوجد طلاب خارج الصف حالياً</div>
</div>

<div class="card">
  <h2>السجل</h2>
  <form class="filter-form" id="filterForm">
    <select id="sectionFilter">
      <option value="">كل الشعب</option>
      <?php foreach ($sections as $s): ?>
        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" id="fromDate">
    <input type="date" id="toDate">
    <button type="submit" class="btn-primary">تصفية</button>
    <a class="btn-secondary" id="exportBtn" href="#">تصدير CSV</a>
  </form>
  <table class="data-table" style="margin-top:14px;">
    <thead><tr><th>الطالب</th><th>الشعبة</th><th>السبب</th><th>المعلم</th><th>خروج</th><th>عودة</th><th>المدة</th></tr></thead>
    <tbody id="historyBody"></tbody>
  </table>
  <div class="empty-msg" id="historyEmpty" style="display:none;">لا يوجد سجل</div>
</div>

<script>
function currentQuery() {
  const section = document.getElementById('sectionFilter').value;
  const from = document.getElementById('fromDate').value;
  const to = document.getElementById('toDate').value;
  const params = new URLSearchParams();
  if (section) params.set('section_id', section);
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  return params.toString();
}

async function loadCurrent() {
  const res = await HallPass.fetchJSON('/api/current_state.php');
  const body = document.getElementById('currentBody');
  const empty = document.getElementById('currentEmpty');
  body.innerHTML = '';
  empty.style.display = res.data.length ? 'none' : 'block';
  document.getElementById('statOut').textContent = res.data.length;
  document.getElementById('statOverdue').textContent = res.data.filter(r => r.overdue).length;

  res.data.forEach(row => {
    const tr = document.createElement('tr');
    tr.appendChild(HallPass.td(row.student_name));
    tr.appendChild(HallPass.td(row.section_name));
    tr.appendChild(HallPass.td(row.reason + (row.reason_note ? ` (${row.reason_note})` : '')));
    tr.appendChild(HallPass.td(row.teacher_name));
    tr.appendChild(HallPass.td(HallPass.formatTime(row.out_time)));
    tr.appendChild(HallPass.td(HallPass.formatElapsed(row.elapsed_seconds), HallPass.elapsedClass(row.elapsed_seconds, res.overdue_minutes)));
    const actionTd = document.createElement('td');
    const btn = document.createElement('button');
    btn.className = 'btn-return';
    btn.textContent = 'تسجيل عودة';
    btn.addEventListener('click', async () => {
      try {
        await HallPass.fetchJSON('/api/exit_end.php', { method: 'POST', body: JSON.stringify({ log_id: row.id }) });
        loadCurrent();
        loadHistory();
      } catch (err) { alert(err.message); }
    });
    actionTd.appendChild(btn);
    tr.appendChild(actionTd);
    body.appendChild(tr);
  });
}

async function loadHistory() {
  const qs = currentQuery();
  const res = await HallPass.fetchJSON(`/api/history.php${qs ? '?' + qs : ''}`);
  const body = document.getElementById('historyBody');
  const empty = document.getElementById('historyEmpty');
  body.innerHTML = '';
  empty.style.display = res.data.length ? 'none' : 'block';

  const today = new Date().toISOString().slice(0, 10);
  document.getElementById('statToday').textContent = res.data.filter(r => r.out_time.slice(0, 10) === today).length;

  res.data.forEach(row => {
    const tr = document.createElement('tr');
    tr.appendChild(HallPass.td(row.student_name));
    tr.appendChild(HallPass.td(row.section_name));
    tr.appendChild(HallPass.td(row.reason));
    tr.appendChild(HallPass.td(row.teacher_name));
    tr.appendChild(HallPass.td(HallPass.formatTime(row.out_time)));
    tr.appendChild(HallPass.td(row.in_time ? HallPass.formatTime(row.in_time) : 'خارج الآن'));
    tr.appendChild(HallPass.td(row.in_time ? HallPass.formatElapsed((new Date(row.in_time.replace(' ','T')) - new Date(row.out_time.replace(' ','T'))) / 1000) : '-'));
    body.appendChild(tr);
  });
  document.getElementById('exportBtn').href = `/api/history.php${qs ? '?' + qs : '?'}${qs ? '&' : ''}format=csv`;
}

document.getElementById('filterForm').addEventListener('submit', (e) => {
  e.preventDefault();
  loadHistory();
});

loadCurrent();
loadHistory();
setInterval(loadCurrent, 5000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
