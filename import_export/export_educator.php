<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit("Unauthorized");
}
require 'db_connect.php'; // must provide $pdo

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="educators_export.csv"');

$output = fopen("php://output", "w");
fputcsv($output, ['Name', 'Email', 'Phone Number']);

$query = "
    SELECT u.name, u.email, e.phone_number
    FROM educators e
    JOIN users u ON e.user_id = u.id
";

foreach ($pdo->query($query) as $row) {
    fputcsv($output, [$row['name'], $row['email'], $row['phone_number']]);
}
fclose($output);
exit;
?>
