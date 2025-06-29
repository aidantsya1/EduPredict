<?php
// delete_student.php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: login.php');
  exit;
}
require __DIR__ . '/db_connect.php';

$sid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($sid) {
  try {
    $pdo->beginTransaction();
    // find linked user
    $uid = $pdo->prepare("SELECT user_id FROM students WHERE id=:sid");
    $uid->execute([':sid'=>$sid]);
    $user_id = $uid->fetchColumn();

    // delete student
    $pdo->prepare("DELETE FROM students WHERE id=:sid")
        ->execute([':sid'=>$sid]);

    // delete user
    if ($user_id) {
      $pdo->prepare("DELETE FROM users WHERE id=:uid")
          ->execute([':uid'=>$user_id]);
    }

    $pdo->commit();
    $_SESSION['message'] = 'Student deleted successfully.';
  } catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['message'] = 'Delete failed: '.$e->getMessage();
  }
}
header('Location: manage_students.php');
exit;
?>
