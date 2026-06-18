<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$user = current_user();
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$grade_id = (int)($_GET['grade_id'] ?? 0);
$section_id = (int)($_GET['section_id'] ?? 0);
$format = $_GET['format'] ?? 'json';

$sql = '
    SELECT el.id, el.reason, el.reason_note, el.out_time, el.in_time, el.status,
           s.name AS student_name, sec.name AS section_name, g.name AS grade_name,
           ut.name AS teacher_name
    FROM exit_logs el
    JOIN students s ON s.id = el.student_id
    JOIN sections sec ON sec.id = s.section_id
    JOIN grades g ON g.id = sec.grade_id
    JOIN users ut ON ut.id = el.recorded_by
    WHERE 1=1
';
$params = [];

if ($user['role'] === 'teacher') {
    $sql .= ' AND sec.id IN (SELECT section_id FROM teacher_sections WHERE teacher_id = ?)';
    $params[] = $user['id'];
} elseif ($user['role'] === 'counselor') {
    $sql .= ' AND g.id = ?';
    $params[] = $user['grade_id'];
} elseif ($grade_id) {
    $sql .= ' AND g.id = ?';
    $params[] = $grade_id;
}

if ($section_id) {
    $sql .= ' AND sec.id = ?';
    $params[] = $section_id;
}
if ($from !== '') {
    $sql .= ' AND el.out_time >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $sql .= ' AND el.out_time <= ?';
    $params[] = $to . ' 23:59:59';
}

$sql .= ' ORDER BY el.out_time DESC LIMIT 500';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="exit_history_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['الطالب', 'الشعبة', 'المرحلة', 'السبب', 'ملاحظة', 'المعلم', 'وقت الخروج', 'وقت العودة', 'الحالة']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['student_name'], $r['section_name'], $r['grade_name'],
            $r['reason'], $r['reason_note'] ?? '',
            $r['teacher_name'], $r['out_time'], $r['in_time'] ?? '',
            $r['status'] === 'out' ? 'خارج الآن' : 'عاد',
        ]);
    }
    fclose($out);
    exit;
}

json_response(['data' => $rows]);
