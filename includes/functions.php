<?php

const OVERDUE_MINUTES = 10;

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function role_label(string $role): string
{
    switch ($role) {
        case 'admin': return 'مدير النظام';
        case 'deputy': return 'وكيل شؤون الطلاب';
        case 'counselor': return 'مرشد طلابي';
        case 'teacher': return 'معلم';
        default: return $role;
    }
}

function redirect_to_dashboard(string $role): void
{
    $map = [
        'admin' => '/admin/index.php',
        'deputy' => '/dashboard_deputy.php',
        'counselor' => '/dashboard_counselor.php',
        'teacher' => '/dashboard_teacher.php',
    ];
    header('Location: ' . ($map[$role] ?? '/login.php'));
    exit;
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
