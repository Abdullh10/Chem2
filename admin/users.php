<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$pageTitle = 'الحسابات';

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'جلسة غير صالحة، أعد المحاولة';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_user') {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';
            $grade_id = (int)($_POST['grade_id'] ?? 0) ?: null;
            $section_ids = array_map('intval', $_POST['section_ids'] ?? []);

            if ($name === '' || $username === '' || $password === '' || !in_array($role, ['admin', 'deputy', 'counselor', 'teacher'], true)) {
                $errors[] = 'يرجى تعبئة جميع الحقول المطلوبة';
            } elseif ($role === 'counselor' && !$grade_id) {
                $errors[] = 'يجب اختيار مرحلة للمرشد الطلابي';
            } else {
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO users (name, username, password_hash, role, grade_id)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $role === 'counselor' ? $grade_id : null]);
                    $newId = $pdo->lastInsertId();

                    if ($role === 'teacher' && $section_ids) {
                        $ins = $pdo->prepare('INSERT INTO teacher_sections (teacher_id, section_id) VALUES (?, ?)');
                        foreach ($section_ids as $sid) {
                            $ins->execute([$newId, $sid]);
                        }
                    }
                    $message = 'تم إنشاء الحساب';
                } catch (PDOException $e) {
                    $errors[] = 'اسم المستخدم موجود مسبقاً';
                }
            }
        } elseif ($action === 'delete_user') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id !== (int)current_user()['id']) {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                $message = 'تم حذف الحساب';
            } else {
                $errors[] = 'لا يمكنك حذف حسابك الحالي';
            }
        } elseif ($action === 'reset_password') {
            $id = (int)($_POST['id'] ?? 0);
            $password = $_POST['password'] ?? '';
            if (strlen($password) < 4) {
                $errors[] = 'كلمة المرور قصيرة جداً';
            } else {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                $message = 'تم تحديث كلمة المرور';
            }
        }
    }
}

$grades = $pdo->query('SELECT * FROM grades ORDER BY sort_order')->fetchAll();
$sections = $pdo->query('
    SELECT sec.id, sec.name, g.name AS grade_name FROM sections sec
    JOIN grades g ON g.id = sec.grade_id
    ORDER BY g.sort_order, sec.name
')->fetchAll();
$users = $pdo->query('
    SELECT u.*, g.name AS grade_name FROM users u
    LEFT JOIN grades g ON g.id = u.grade_id
    ORDER BY u.role, u.name
')->fetchAll();

$teacherSections = [];
$stmt = $pdo->query('SELECT teacher_id, section_id FROM teacher_sections');
foreach ($stmt->fetchAll() as $row) {
    $teacherSections[$row['teacher_id']][] = $row['section_id'];
}

include __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
  <?php include __DIR__ . '/_nav.php'; ?>
  <div class="admin-content">
    <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="card">
      <h2>إنشاء حساب جديد</h2>
      <form method="post" id="userForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="add_user">
        <div class="inline-form">
          <input type="text" name="name" placeholder="الاسم الكامل" required>
          <input type="text" name="username" placeholder="اسم المستخدم" required>
          <input type="password" name="password" placeholder="كلمة المرور" required>
          <select name="role" id="roleSelect" required>
            <option value="teacher">معلم</option>
            <option value="counselor">مرشد طلابي</option>
            <option value="deputy">وكيل شؤون الطلاب</option>
            <option value="admin">مدير نظام</option>
          </select>
        </div>

        <div id="gradeField" style="margin-top:12px;">
          <label class="section-title" style="margin:10px 0 6px;font-size:14px;">المرحلة (للمرشد الطلابي)</label>
          <select name="grade_id">
            <option value="">اختر المرحلة</option>
            <?php foreach ($grades as $g): ?>
              <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="sectionsField" style="margin-top:12px;">
          <label class="section-title" style="margin:10px 0 6px;font-size:14px;">الشعب (للمعلم)</label>
          <div class="checkbox-grid">
            <?php foreach ($sections as $s): ?>
              <label><input type="checkbox" name="section_ids[]" value="<?= $s['id'] ?>"> <?= htmlspecialchars($s['grade_name'] . ' - ' . $s['name']) ?></label>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="btn-primary" style="margin-top:14px;">إنشاء الحساب</button>
      </form>
    </div>

    <div class="card">
      <h2>الحسابات الحالية</h2>
      <table class="data-table">
        <thead><tr><th>الاسم</th><th>اسم المستخدم</th><th>الدور</th><th>التفاصيل</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars(role_label($u['role'])) ?></td>
            <td>
              <?php if ($u['role'] === 'counselor'): ?>
                <?= htmlspecialchars($u['grade_name'] ?? '-') ?>
              <?php elseif ($u['role'] === 'teacher'): ?>
                <?= count($teacherSections[$u['id']] ?? []) ?> شعبة
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td style="display:flex; gap:6px;">
              <form method="post" onsubmit="return promptPassword(this);">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <input type="hidden" name="password" class="pw-field">
                <button type="submit" class="btn-secondary">كلمة مرور جديدة</button>
              </form>
              <form method="post" onsubmit="return confirm('حذف الحساب؟');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn-danger-sm">حذف</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function promptPassword(form) {
  const pw = prompt('أدخل كلمة المرور الجديدة:');
  if (!pw) return false;
  form.querySelector('.pw-field').value = pw;
  return true;
}

const roleSelect = document.getElementById('roleSelect');
const gradeField = document.getElementById('gradeField');
const sectionsField = document.getElementById('sectionsField');

function syncFields() {
  gradeField.style.display = roleSelect.value === 'counselor' ? 'block' : 'none';
  sectionsField.style.display = roleSelect.value === 'teacher' ? 'block' : 'none';
}
roleSelect.addEventListener('change', syncFields);
syncFields();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
