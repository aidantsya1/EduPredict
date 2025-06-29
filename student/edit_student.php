<?php
// edit_student.php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db_connect.php';

$errors = [];
$sid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$sid) {
    header('Location: manage_students.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT 
        s.id AS sid,
        s.user_id,
        s.student_id,
        s.class_id,
        s.batch,
        s.address,
        s.phone_number AS stu_phone,
        u.id   AS uid,
        u.name AS user_name,
        u.email
     FROM students s
     JOIN users u ON s.user_id = u.id
     WHERE s.id = ?"
);
$stmt->execute([$sid]);
$st = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$st) {
    header('Location: manage_students.php');
    exit;
}

$classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name")->fetchAll();
$batches = $pdo->query("SELECT DISTINCT batch FROM students ORDER BY batch DESC")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']         ?? '');
    $email       = trim($_POST['email']        ?? '');
    $student_id  = trim($_POST['student_id']   ?? '');
    $class_id    = (int)($_POST['class_id']    ?? 0);
    $batch       = trim($_POST['batch']        ?? '');
    $address     = trim($_POST['address']      ?? '');
    $stu_phone   = trim($_POST['stu_phone']    ?? '');
    $reset_pw    = isset($_POST['reset_password']);

    if ($name === '')    $errors[] = 'Full Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@edupredict.com')) $errors[] = 'Email must be valid and end with @edupredict.com.';
    if ($student_id === '') $errors[] = 'Student ID is required.';
    if ($class_id <= 0) $errors[] = 'Please select a Class.';
    if ($batch === '') $errors[] = 'Batch is required.';
    if ($address === '') $errors[] = 'Address is required.';
    if ($stu_phone === '') $errors[] = 'Phone Number is required.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($reset_pw) {
                $hash = password_hash($student_id, PASSWORD_DEFAULT);
                $stmtUp = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?");
                $stmtUp->execute([$name, $email, $hash, $st['uid']]);
            } else {
                $stmtUp = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmtUp->execute([$name, $email, $st['uid']]);
            }

            $stmtSt = $pdo->prepare("UPDATE students SET student_id = ?, class_id = ?, batch = ?, address = ?, phone_number = ? WHERE id = ?");
            $stmtSt->execute([$student_id, $class_id, $batch, $address, $stu_phone, $sid]);

            $pdo->commit();
            $_SESSION['message'] = 'Student updated successfully.';
            header('Location: manage_students.php?updated=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Edit Student | EduPredict</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f9faf0;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
    }
    .form-box {
      max-width: 500px;
      width: 100%;
      background: #fff;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .form-box h2 {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .error {
      background: #fee;
      border: 1px solid #f99;
      padding: 1rem;
      margin-bottom: 1rem;
      border-radius: 8px;
    }
    .form-group {
      margin-bottom: 1rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      box-sizing: border-box;
    }
    .form-group input[type="checkbox"] {
      margin-right: 0.5rem;
    }
    .btn-submit {
      width: 100%;
      padding: 0.75rem;
      background: #000;
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
    }
    .btn-submit:hover {
      background: #333;
    }
    .close-btn {
      float: right;
      background: transparent;
      border: none;
      font-size: 1.25rem;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="form-box">
    <button class="close-btn" onclick="location.href='manage_students.php'">&times;</button>
    <h2>Edit Student</h2>

    <?php if (!empty($errors)): ?>
      <div class="error">
        <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="form-group">
        <label for="name">Full Name</label>
        <input id="name" name="name" type="text" value="<?= htmlspecialchars($_POST['name'] ?? $st['user_name']) ?>" required>
      </div>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($_POST['email'] ?? $st['email']) ?>" required>
      </div>
      <div class="form-group">
        <label for="student_id">Student ID</label>
        <input id="student_id" name="student_id" type="text" value="<?= htmlspecialchars($_POST['student_id'] ?? $st['student_id']) ?>" required>
      </div>
      <div class="form-group">
        <label for="class_id">Class</label>
        <select id="class_id" name="class_id" required>
          <option value="">-- Select Class --</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ((int)($_POST['class_id'] ?? $st['class_id']) === (int)$c['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['class_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="batch">Batch</label>
        <select id="batch" name="batch" required>
          <option value="">-- Select Batch --</option>
          <?php foreach ($batches as $b): ?>
            <option value="<?= $b ?>" <?= ((string)($_POST['batch'] ?? $st['batch']) === (string)$b) ? 'selected' : '' ?>>
              <?= htmlspecialchars($b) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="address">Address</label>
        <textarea id="address" name="address" rows="3" required><?= htmlspecialchars($_POST['address'] ?? $st['address']) ?></textarea>
      </div>
      <div class="form-group">
        <label for="stu_phone">Phone Number</label>
        <input id="stu_phone" name="stu_phone" type="text" value="<?= htmlspecialchars($_POST['stu_phone'] ?? $st['stu_phone']) ?>" required>
      </div>
      <div class="form-group">
        <label>
          <input type="checkbox" name="reset_password" value="1">
          Reset password to Student ID
        </label>
      </div>
      <button type="submit" class="btn-submit">Update Student</button>
    </form>
  </div>
</body>
</html>

