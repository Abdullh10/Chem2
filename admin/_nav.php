<?php $current = basename($_SERVER['SCRIPT_NAME']); ?>
<div class="admin-nav">
  <a href="/admin/index.php" class="<?= $current === 'index.php' ? 'active' : '' ?>">المراحل والشعب</a>
  <a href="/admin/students.php" class="<?= $current === 'students.php' ? 'active' : '' ?>">الطلاب</a>
  <a href="/admin/users.php" class="<?= $current === 'users.php' ? 'active' : '' ?>">الحسابات</a>
</div>
