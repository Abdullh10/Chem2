<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('teacher');
$user = current_user();
$pageTitle = 'لوحة المعلم';

$stmt = $pdo->prepare('
    SELECT sec.id, sec.name, g.name AS grade_name
    FROM sections sec
    JOIN teacher_sections ts ON ts.section_id = sec.id
    JOIN grades g ON g.id = sec.grade_id
    WHERE ts.teacher_id = ?
    ORDER BY g.sort_order, sec.name
');
$stmt->execute([$user['id']]);
$sections = $stmt->fetchAll();

$sectionIds = array_column($sections, 'id');
$students = [];
if ($sectionIds) {
    $in = implode(',', array_fill(0, count($sectionIds), '?'));
    $stmt = $pdo->prepare("SELECT id, name, section_id FROM students WHERE section_id IN ($in) ORDER BY name");
    $stmt->execute($sectionIds);
    $students = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<?php if (!$sections): ?>
  <div class="alert alert-error">لم يتم تعيين أي شعبة لك حتى الآن. تواصل مع مدير النظام لإضافتك على الشعب الخاصة بك.</div>
<?php endif; ?>

<div class="card">
  <h2>تسجيل خروج طالب</h2>
  <form class="exit-form" id="exitForm">
    <select id="sectionSelect">
      <?php foreach ($sections as $s): ?>
        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['grade_name'] . ' - ' . $s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="studentSelect" required></select>
    <select id="reasonSelect">
      <option value="حمام">حمام</option>
      <option value="ممرضة">ممرضة</option>
      <option value="إدارة">إدارة</option>
      <option value="أخرى">أخرى</option>
    </select>
    <input type="text" id="noteInput" placeholder="ملاحظة (اختياري)">
    <button type="submit" class="btn-primary">تسجيل خروج</button>
  </form>
</div>

<div class="card">
  <h2>الطلاب الخارجون حالياً</h2>
  <table class="data-table">
    <thead><tr><th>الطالب</th><th>السبب</th><th>وقت الخروج</th><th>المدة</th><th></th></tr></thead>
    <tbody id="currentBody"></tbody>
  </table>
  <div class="empty-msg" id="currentEmpty" style="display:none;">لا يوجد طلاب خارج الصف حالياً</div>
</div>

<div class="card">
  <h2>سجل اليوم</h2>
  <a class="btn-secondary" id="exportBtn" href="#">تصدير CSV</a>
  <table class="data-table" style="margin-top:14px;">
    <thead><tr><th>الطالب</th><th>السبب</th><th>خروج</th><th>عودة</th><th>المدة</th></tr></thead>
    <tbody id="historyBody"></tbody>
  </table>
  <div class="empty-msg" id="historyEmpty" style="display:none;">لا يوجد سجل</div>
</div>

<script>
const STUDENTS = <?= json_encode($students, JSON_UNESCAPED_UNICODE) ?>;

const sectionSelect = document.getElementById('sectionSelect');
const studentSelect = document.getElementById('studentSelect');

function populateStudents() {
  const sectionId = Number(sectionSelect.value);
  studentSelect.innerHTML = '';
  STUDENTS.filter(s => s.section_id === sectionId).forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.id;
    opt.textContent = s.name;
    studentSelect.appendChild(opt);
  });
}
if (sectionSelect.options.length) {
  sectionSelect.addEventListener('change', populateStudents);
  populateStudents();
}

document.getElementById('exitForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const studentId = Number(studentSelect.value);
  if (!studentId) return;
  try {
    await HallPass.fetchJSON('/api/exit_start.php', {
      method: 'POST',
      body: JSON.stringify({
        student_id: studentId,
        reason: document.getElementById('reasonSelect').value,
        note: document.getElementById('noteInput').value,
      }),
    });
    document.getElementById('noteInput').value = '';
    loadCurrent();
  } catch (err) {
    alert(err.message);
  }
});

async function loadCurrent() {
  const res = await HallPass.fetchJSON('/api/current_state.php');
  const body = document.getElementById('currentBody');
  const empty = document.getElementById('currentEmpty');
  body.innerHTML = '';
  empty.style.display = res.data.length ? 'none' : 'block';
  res.data.forEach(row => {
    const tr = document.createElement('tr');
    tr.appendChild(HallPass.td(row.student_name));
    tr.appendChild(HallPass.td(row.reason + (row.reason_note ? ` (${row.reason_note})` : '')));
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
  const today = new Date().toISOString().slice(0, 10);
  const res = await HallPass.fetchJSON(`/api/history.php?from=${today}&to=${today}`);
  const body = document.getElementById('historyBody');
  const empty = document.getElementById('historyEmpty');
  body.innerHTML = '';
  empty.style.display = res.data.length ? 'none' : 'block';
  res.data.forEach(row => {
    const tr = document.createElement('tr');
    tr.appendChild(HallPass.td(row.student_name));
    tr.appendChild(HallPass.td(row.reason));
    tr.appendChild(HallPass.td(HallPass.formatTime(row.out_time)));
    tr.appendChild(HallPass.td(row.in_time ? HallPass.formatTime(row.in_time) : 'خارج الآن'));
    tr.appendChild(HallPass.td(row.in_time ? HallPass.formatElapsed((new Date(row.in_time.replace(' ','T')) - new Date(row.out_time.replace(' ','T'))) / 1000) : '-'));
    body.appendChild(tr);
  });
  document.getElementById('exportBtn').href = `/api/history.php?from=${today}&to=${today}&format=csv`;
}

loadCurrent();
loadHistory();
setInterval(loadCurrent, 5000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
