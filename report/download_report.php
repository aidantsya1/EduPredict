<?php
session_start();
require __DIR__ . '/db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'educator') {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Get educator_id
$educatorStmt = $pdo->prepare("SELECT id FROM educators WHERE user_id = ?");
$educatorStmt->execute([$userId]);
$educatorId = $educatorStmt->fetchColumn();

// Get all prediction history by class
$historyStmt = $pdo->prepare("
    SELECT p.date_recorded, s.student_id, u.name AS student_name,
           p.attendance_percent, p.internal_test1, p.internal_test2, p.internal_test3,
           p.trial1, p.trial2, p.trial3, p.prediction_result
    FROM performance p
    JOIN students s ON p.student_id = s.student_id
    JOIN users u ON s.user_id = u.id
    WHERE s.class_id IN (
      SELECT id FROM classes WHERE educator_id = ?
    )
    ORDER BY p.date_recorded DESC, u.name
");
$historyStmt->execute([$educatorId]);
$rows = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=prediction_report.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Date', 'Student ID', 'Name', 'Attendance (%)',
                  'U1', 'U2', 'U3', 'T1', 'T2', 'T3', 'Prediction']);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['date_recorded'], $row['student_id'], $row['student_name'],
        $row['attendance_percent'], $row['internal_test1'], $row['internal_test2'],
        $row['internal_test3'], $row['trial1'], $row['trial2'],
        $row['trial3'], $row['prediction_result']
    ]);
}
exit;
?>