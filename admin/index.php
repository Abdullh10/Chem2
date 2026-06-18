<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$pageTitle = 'المراحل والشعب';

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'جلسة غير صالحة، أعد المحاولة';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_section') {
            $grade_id = (int)($_POST['grade_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '' || !$grade_id) {
                $errors[] = 'يجب اختيار المرحلة وإدخال اسم الشعبة';
            } else {
                try {
                    $pdo->prepare('INSERT INTO sections (grade_id, name) VALUES (?, ?)')->execute([$grade_id, $name]);
                    $message = 'تمت إضافة الشعبة';
                } catch (PDOException $e) {
                    $errors[] = 'الشعبة موجودة مسبقاً في هذه المرحلة';
                }
            }
        } elseif ($action === 'delete_section') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM sections WHERE id = ?')->execute([$id]);
            $message = 'تم حذف الشعبة';
        }
    }
}

$grades = $pdo->query('SELECT * FROM grades ORDER BY sort_order')->fetchAll();
$sections = $pdo->query('
    SELECT sec.*, g.name AS grade_name FROM sections sec
    JOIN grades g ON g.id = sec.grade_id
    ORDER BY g.sort_order, sec.name
')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
  <?php include __DIR__ . '/_nav.php'; ?>
  <div class="admin-content">
    <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="card">
      <h2>إضافة شعبة جديدة</h2>
      <form method="post" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="add_section">
        <select name="grade_id" required>
          <?php foreach ($grades as $g): ?>
            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="name" placeholder="اسم/رقم الشعبة" required>
        <button type="submit" class="btn-primary">إضافة</button>
      </form>
    </div>

    <div class="card">
      <h2>الشعب الحالية</h2>
      <table class="data-table">
        <thead><tr><th>المرحلة</th><th>الشعبة</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($sections as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['grade_name']) ?></td>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('حذف الشعبة؟ سيتم حذف طلابها وسجلاتهم.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_section">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn-danger-sm">حذف</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$sections): ?><div class="empty-msg">لا توجد شعب بعد</div><?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
