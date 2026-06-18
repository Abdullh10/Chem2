<?php
require __DIR__ . '/../includes/bootstrap.php';
require_role('teacher');

if (!verify_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    json_response(['error' => 'جلسة غير صالحة'], 403);
}

$data = json_body();
$student_id = (int)($data['student_id'] ?? 0);
$reason = $data['reason'] ?? '';
$note = trim((string)($data['note'] ?? ''));
$allowed_reasons = ['حمام', 'ممرضة', 'إدارة', 'أخرى'];

if (!$student_id || !in_array($reason, $allowed_reasons, true)) {
    json_response(['error' => 'بيانات غير صحيحة'], 400);
}

$user = current_user();

$stmt = $pdo->prepare('
    SELECT s.id FROM students s
    JOIN teacher_sections ts ON ts.section_id = s.section_id
    WHERE s.id = ? AND ts.teacher_id = ?
');
$stmt->execute([$student_id, $user['id']]);
if (!$stmt->fetch()) {
    json_response(['error' => 'لا تملك صلاحية على هذا الطالب'], 403);
}

$stmt = $pdo->prepare("SELECT id FROM exit_logs WHERE student_id = ? AND status = 'out'");
$stmt->execute([$student_id]);
if ($stmt->fetch()) {
    json_response(['error' => 'الطالب مسجل خروجه مسبقاً'], 409);
}

$stmt = $pdo->prepare('
    INSERT INTO exit_logs (student_id, reason, reason_note, recorded_by, out_time, status)
    VALUES (?, ?, ?, ?, NOW(), "out")
');
$stmt->execute([$student_id, $reason, $note !== '' ? $note : null, $user['id']]);

json_response(['success' => true]);
