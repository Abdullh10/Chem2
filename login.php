<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect_to_dashboard(current_user()['role']);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'جلسة غير صالحة، أعد المحاولة';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password_hash'])) {
            $_SESSION['user'] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'role' => $row['role'],
                'grade_id' => $row['grade_id'],
            ];
            redirect_to_dashboard($row['role']);
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل الدخول - نظام تتبع خروج الطلاب</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-page">
  <div class="login-card">
    <div class="login-brand">
      <span class="brand-dot"></span>
      نظام تتبع خروج الطلاب
    </div>
    <p class="login-subtitle">تسجيل دخول الموظفين</p>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <label>اسم المستخدم</label>
      <input type="text" name="username" required autofocus>
      <label>كلمة المرور</label>
      <input type="password" name="password" required>
      <button type="submit" class="btn-primary btn-block">دخول</button>
    </form>
  </div>
</body>
</html>
