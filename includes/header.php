<?php
$user = current_user();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>نظام تتبع خروج الطلاب</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-title">
    <span class="brand-dot"></span>
    نظام تتبع خروج الطلاب
  </div>
  <?php if ($user): ?>
  <div class="topbar-user">
    <span class="user-chip"><?= htmlspecialchars($user['name']) ?> &middot; <?= role_label($user['role']) ?></span>
    <a href="/logout.php" class="btn-logout">تسجيل خروج</a>
  </div>
  <?php endif; ?>
</header>
<main class="page-main">
