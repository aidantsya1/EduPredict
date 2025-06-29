<?php

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: login.php');
  exit;
}
require __DIR__ . '/db_connect.php';

// fetch educators for the dropdown
$educators = $pdo
  ->query("SELECT e.id AS eid, u.name FROM educators e JOIN users u ON e.user_id=u.id ORDER BY u.name")
  ->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $class_name   = trim($_POST['class_name']    ?? '');
  $educator_id  = (int)($_POST['educator_id'] ?? 0);

  // validation
  if ($class_name === '') {
    $errors[] = 'Class Name is required.';
  }
  if ($educator_id <= 0) {
    $errors[] = 'Please select a Class Educator.';
  }

  if (empty($errors)) {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO classes (class_name, educator_id)
        VALUES (:name, :eid)
      ");
      $stmt->execute([
        ':name' => $class_name,
        ':eid'  => $educator_id
      ]);

      $_SESSION['message'] = 'Class added successfully.';
      header('Location: manage_classes.php');
      exit;

    } catch (PDOException $e) {
      $errors[] = 'Database error: ' . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Class | EduPredict</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">

  <div class="modal-backdrop"></div>
  <div class="login-modal">
    <button class="close-btn" onclick="location.href='manage_classes.php'">&times;</button>
    <h2>Add New Class</h2>

    <?php if ($errors): ?>
      <div class="error">
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="form-group">
        <label for="class_name">Class Name:</label>
        <input id="class_name" name="class_name" type="text"
               value="<?= htmlspecialchars($_POST['class_name'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label for="educator_id">Class Educator:</label>
        <select id="educator_id" name="educator_id">
          <option value="">-- Select Educator --</option>
          <?php foreach ($educators as $ed): ?>
            <option value="<?= $ed['eid'] ?>"
              <?= (isset($_POST['educator_id']) && $_POST['educator_id']==$ed['eid']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($ed['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn-submit">Submit</button>
    </form>
  </div>

</body>
</html>
