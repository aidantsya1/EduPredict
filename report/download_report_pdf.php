<?php
session_start();
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/tcpdf/tcpdf.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'educator') {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Get educator_id
$educatorStmt = $pdo->prepare("SELECT id FROM educators WHERE user_id = ?");
$educatorStmt->execute([$userId]);
$educatorId = $educatorStmt->fetchColumn();

// Fetch prediction history
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

// Create PDF
$pdf = new TCPDF();
$pdf->SetCreator('EduPredict');
$pdf->SetAuthor('EduPredict System');
$pdf->SetTitle('Prediction Report');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

$html = '<h2>Prediction Report</h2><table border="1" cellpadding="4"><thead>
<tr style="background-color:#f0f0f0;"><th>Date</th><th>Student ID</th><th>Name</th><th>Attendance (%)</th>
<th>U1</th><th>U2</th><th>U3</th><th>T1</th><th>T2</th><th>T3</th><th>Prediction</th></tr></thead><tbody>';

foreach ($rows as $row) {
    $html .= '<tr><td>' . $row['date_recorded'] . '</td>
              <td>' . $row['student_id'] . '</td>
              <td>' . $row['student_name'] . '</td>
              <td>' . $row['attendance_percent'] . '</td>
              <td>' . $row['internal_test1'] . '</td>
              <td>' . $row['internal_test2'] . '</td>
              <td>' . $row['internal_test3'] . '</td>
              <td>' . $row['trial1'] . '</td>
              <td>' . $row['trial2'] . '</td>
              <td>' . $row['trial3'] . '</td>
              <td>' . $row['prediction_result'] . '</td></tr>';
}

$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('prediction_report.pdf', 'I');
exit;
?>