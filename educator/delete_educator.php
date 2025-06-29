<?php
// delete_educator.php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: login.php');
  exit;
}
require __DIR__ . '/db_connect.php';

$eid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($eid) {
  try {
    $pdo->beginTransaction();

    // find the linked user_id
    $stmt = $pdo->prepare("SELECT user_id FROM educators WHERE id = :eid");
    $stmt->execute([':eid' => $eid]);
    $user_id = $stmt->fetchColumn();

    // delete educator record
    $stmt = $pdo->prepare("DELETE FROM educators WHERE id = :eid");
    $stmt->execute([':eid' => $eid]);

    // delete user record
    if ($user_id) {
      $stmt = $pdo->prepare("DELETE FROM users WHERE id = :uid");
      $stmt->execute([':uid' => $user_id]);
    }

    $pdo->commit();
    $_SESSION['message'] = 'Educator deleted successfully.';
  } catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['message'] = 'Delete failed: ' . $e->getMessage();
  }
}

header('Location: manage_educators.php');
exit;
?>
