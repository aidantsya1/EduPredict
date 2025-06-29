<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'educator') {
    die("Access denied.");
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename=report_educator.csv');

$output = fopen('php://output', 'w');

// Header CSV
fputcsv($output, [
    'Student ID', 'Student Name',
    'Internal Test 1', 'Internal Test 2', 'Internal Test 3',
    'Trial 1', 'Trial 2', 'Trial 3', 'Prediction'
]);

$sql = "
    SELECT 
        s.student_id,
        u.name AS student_name,
        p.internal_test1,
        p.internal_test2,
        p.internal_test3,
        p.trial1,
        p.trial2,
        p.trial3,
        p.prediction_result
    FROM performance p
    JOIN students s ON p.student_id = s.student_id
    JOIN users u ON s.user_id = u.id
    WHERE p.educator_id = :educator_id
    ORDER BY s.class_id, u.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':educator_id' => $_SESSION['user_id']]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['student_id'],
        $row['student_name'],
        $row['internal_test1'],
        $row['internal_test2'],
        $row['internal_test3'],
        $row['trial1'],
        $row['trial2'],
        $row['trial3'],
        $row['prediction_result']
    ]);
}

fclose($output);
exit;
