<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect_to_dashboard(current_user()['role']);
}
header('Location: /login.php');
exit;
