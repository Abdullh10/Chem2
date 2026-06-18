<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$user = current_user();

$sql = '
    SELECT el.id, el.reason, el.reason_note, el.out_time,
           s.id AS student_id, s.name AS student_name,
           sec.id AS section_id, sec.name AS section_name,
           g.id AS grade_id, g.name AS grade_name,
           u.name AS teacher_name
    FROM exit_logs el
    JOIN students s ON s.id = el.student_id
    JOIN sections sec ON sec.id = s.section_id
    JOIN grades g ON g.id = sec.grade_id
    JOIN users u ON u.id = el.recorded_by
    WHERE el.status = "out"
';
$params = [];

if ($user['role'] === 'teacher') {
    $sql .= ' AND sec.id IN (SELECT section_id FROM teacher_sections WHERE teacher_id = ?)';
    $params[] = $user['id'];
} elseif ($user['role'] === 'counselor') {
    $sql .= ' AND g.id = ?';
    $params[] = $user['grade_id'];
}

$sql .= ' ORDER BY el.out_time ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$now = time();
foreach ($rows as &$row) {
    $elapsed = $now - strtotime($row['out_time']);
    $row['elapsed_seconds'] = $elapsed;
    $row['overdue'] = $elapsed > (OVERDUE_MINUTES * 60);
}

json_response([
    'data' => $rows,
    'overdue_minutes' => OVERDUE_MINUTES,
    'can_return' => in_array($user['role'], ['teacher', 'counselor', 'deputy', 'admin'], true),
    'show_section' => $user['role'] !== 'teacher',
    'show_grade' => in_array($user['role'], ['deputy', 'admin'], true),
]);
