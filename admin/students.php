<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$pageTitle = 'الطلاب';

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'جلسة غير صالحة، أعد المحاولة';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_student') {
            $section_id = (int)($_POST['section_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '' || !$section_id) {
                $errors[] = 'يجب اختيار الشعبة وإدخال اسم الطالب';
            } else {
                $pdo->prepare('INSERT INTO students (section_id, name) VALUES (?, ?)')->execute([$section_id, $name]);
                $message = 'تمت إضافة الطالب';
            }
        } elseif ($action === 'delete_student') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM students WHERE id = ?')->execute([$id]);
            $message = 'تم حذف الطالب';
        }
    }
}

$sections = $pdo->query('
    SELECT sec.id, sec.name, g.name AS grade_name FROM sections sec
    JOIN grades g ON g.id = sec.grade_id
    ORDER BY g.sort_order, sec.name
')->fetchAll();

$filterSection = (int)($_GET['section_id'] ?? 0);
$studentsSql = '
    SELECT s.*, sec.name AS section_name, g.name AS grade_name FROM students s
    JOIN sections sec ON sec.id = s.section_id
    JOIN grades g ON g.id = sec.grade_id
';
$studentsParams = [];
if ($filterSection) {
    $studentsSql .= ' WHERE sec.id = ?';
    $studentsParams[] = $filterSection;
}
$studentsSql .= ' ORDER BY g.sort_order, sec.name, s.name';
$stmt = $pdo->prepare($studentsSql);
$stmt->execute($studentsParams);
$students = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
  <?php include __DIR__ . '/_nav.php'; ?>
  <div class="admin-content">
    <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="card">
      <h2>إضافة طالب</h2>
      <?php if (!$sections): ?>
        <div class="empty-msg">أضف شعبة أولاً من صفحة "المراحل والشعب"</div>
      <?php else: ?>
      <form method="post" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="add_student">
        <select name="section_id" required>
          <?php foreach ($sections as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['grade_name'] . ' - ' . $s['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="name" placeholder="اسم الطالب" required>
        <button type="submit" class="btn-primary">إضافة</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>قائمة الطلاب</h2>
      <form method="get" class="inline-form" style="margin-bottom:14px;">
        <select name="section_id" onchange="this.form.submit()">
          <option value="">كل الشعب</option>
          <?php foreach ($sections as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $filterSection === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['grade_name'] . ' - ' . $s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <table class="data-table">
        <thead><tr><th>الاسم</th><th>الشعبة</th><th>المرحلة</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($students as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><?= htmlspecialchars($s['section_name']) ?></td>
            <td><?= htmlspecialchars($s['grade_name']) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('حذف الطالب؟');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_student">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn-danger-sm">حذف</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$students): ?><div class="empty-msg">لا يوجد طلاب</div><?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
