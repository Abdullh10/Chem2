<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('teacher', 'counselor', 'deputy', 'admin');

if (!verify_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    json_response(['error' => 'جلسة غير صالحة'], 403);
}

$data = json_body();
$log_id = (int)($data['log_id'] ?? 0);
$user = current_user();

$stmt = $pdo->prepare('
    SELECT el.*, sec.id AS section_id, sec.grade_id
    FROM exit_logs el
    JOIN students s ON s.id = el.student_id
    JOIN sections sec ON sec.id = s.section_id
    WHERE el.id = ? AND el.status = "out"
');
$stmt->execute([$log_id]);
$log = $stmt->fetch();

if (!$log) {
    json_response(['error' => 'السجل غير موجود'], 404);
}

if ($user['role'] === 'teacher') {
    $stmt = $pdo->prepare('SELECT 1 FROM teacher_sections WHERE teacher_id = ? AND section_id = ?');
    $stmt->execute([$user['id'], $log['section_id']]);
    if (!$stmt->fetch()) {
        json_response(['error' => 'لا تملك صلاحية'], 403);
    }
} elseif ($user['role'] === 'counselor' && (int)$log['grade_id'] !== (int)$user['grade_id']) {
    json_response(['error' => 'لا تملك صلاحية'], 403);
}

$stmt = $pdo->prepare('UPDATE exit_logs SET in_time = NOW(), status = "returned", returned_by = ? WHERE id = ?');
$stmt->execute([$user['id'], $log_id]);

json_response(['success' => true]);
