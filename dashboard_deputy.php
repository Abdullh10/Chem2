<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('deputy');
$pageTitle = 'لوحة وكيل شؤون الطلاب';

$grades = $pdo->query('SELECT * FROM grades ORDER BY sort_order')->fetchAll();
$sections = $pdo->query('SELECT id, name, grade_id FROM sections ORDER BY name')->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="stat-cards" id="gradeStats"></div>

<div class="card">
  <h2>الطلاب الخارجون حالياً (كل المراحل)</h2>
  <table class="data-table">
    <thead><tr><th>الطالب</th><th>الشعبة</th><th>المرحلة</th><th>السبب</th><th>المعلم</th><th>وقت الخروج</th><th>المدة</th><th></th></tr></thead>
    <tbody id="currentBody"></tbody>
  </table>
  <div class="empty-msg" id="currentEmpty" style="display:none;">لا يوجد طلاب خارج الصف حالياً</div>
</div>

<div class="card">
  <h2>السجل</h2>
  <form class="filter-form" id="filterForm">
    <select id="gradeFilter">
      <option value="">كل المراحل</option>
      <?php foreach ($grades as $g): ?>
        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="sectionFilter">
      <option value="">كل الشعب</option>
    </select>
    <input type="date" id="fromDate">
    <input type="date" id="toDate">
    <button type="submit" class="btn-primary">تصفية</button>
    <a class="btn-secondary" id="exportBtn" href="#">تصدير CSV</a>
  </form>
  <table class="data-table" style="margin-top:14px;">
    <thead><tr><th>الطالب</th><th>الشعبة</th><th>المرحلة</th><th>السبب</th><th>المعلم</th><th>خروج</th><th>عودة</th><th>المدة</th></tr></thead>
    <tbody id="historyBody"></tbody>
  </table>
  <div class="empty-msg" id="historyEmpty" style="display:none;">لا يوجد سجل</div>
</div>

<script>
const GRADES = <?= json_encode($grades, JSON_UNESCAPED_UNICODE) ?>;
const SECTIONS = <?= json_encode($sections, JSON_UNESCAPED_UNICODE) ?>;

const gradeFilter = document.getElementById('gradeFilter');
const sectionFilter = document.getElementById('sectionFilter');

gradeFilter.addEventListener('change', () => {
  const gradeId = Number(gradeFilter.value);
  sectionFilter.innerHTML = '<option value="">كل الشعب</option>';
  SECTIONS.filter(s => !gradeId || s.grade_id === gradeId).forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.id;
    opt.textContent = s.name;
    sectionFilter.appendChild(opt);
  });
});

function currentQuery() {
  const params = new URLSearchParams();
  if (gradeFilter.value) params.set('grade_id', gradeFilter.value);
  if (sectionFilter.value) params.set('section_id', sectionFilter.value);
  const from = document.getElementById('fromDate').value;
  const to = document.getElementById('toDate').value;
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

  const statsBox = document.getElementById('gradeStats');
  statsBox.innerHTML = '';
  GRADES.forEach(g => {
    const rows = res.data.filter(r => r.grade_id === g.id);
    const overdue = rows.filter(r => r.overdue).length;
    const card = document.createElement('div');
    card.className = 'stat-card' + (overdue ? ' is-red' : (rows.length ? ' is-amber' : ' is-green'));
    card.innerHTML = `<div class="stat-value">${rows.length}</div><div class="stat-label">${g.name} - خارجون الآن (${overdue} متأخر)</div>`;
    statsBox.appendChild(card);
  });

  res.data.forEach(row => {
    const tr = document.createElement('tr');
    tr.appendChild(HallPass.td(row.student_name));
    tr.appendChild(HallPass.td(row.section_name));
    tr.appendChild(HallPass.td(row.grade_name));
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

  res.data.forEach(row => {
    const tr = document.createElement('tr');
    tr.appendChild(HallPass.td(row.student_name));
    tr.appendChild(HallPass.td(row.section_name));
    tr.appendChild(HallPass.td(row.grade_name));
    tr.appendChild(HallPass.td(row.reason));
    tr.appendChild(HallPass.td(row.teacher_name));
    tr.appendChild(HallPass.td(HallPass.formatTime(row.out_time)));
    tr.appendChild(HallPass.td(row.in_time ? HallPass.formatTime(row.in_time) : 'خارج الآن'));
    tr.appendChild(HallPass.td(row.in_time ? HallPass.formatElapsed((new Date(row.in_time.replace(' ','T')) - new Date(row.out_time.replace(' ','T'))) / 1000) : '-'));
    body.appendChild(tr);
  });
  document.getElementById('exportBtn').href = `/api/history.php${qs ? '?' + qs + '&' : '?'}format=csv`;
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
