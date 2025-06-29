<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit("Unauthorized");
}
require 'db_connect.php'; // must provide $pdo

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="students_export.csv"');

$output = fopen("php://output", "w");
fputcsv($output, ['Name', 'Email', 'Class']);

$query = "
    SELECT u.name, u.email, c.class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN classes c ON s.class_id = c.id
";

foreach ($pdo->query($query) as $row) {
    fputcsv($output, [$row['name'], $row['email'], $row['class_name']]);
}
fclose($output);
exit;
?>
