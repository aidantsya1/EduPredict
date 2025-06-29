<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
require __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = $_POST['class_id'] ?? null;

    // Semak sama ada ada pelajar dalam class
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
    $stmt->execute([$class_id]);
    $student_count = $stmt->fetchColumn();

    if ($student_count > 0) {
        $_SESSION['message'] = 'Cannot delete: Class has students assigned.';
    } else {
        $delete = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $delete->execute([$class_id]);
        $_SESSION['message'] = 'Class deleted successfully.';
    }
}
header("Location: manage_classes.php");
exit;
