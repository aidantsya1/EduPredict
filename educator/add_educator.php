<?php
// add_educator.php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db_connect.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['name']         ?? '');
    $email        = trim($_POST['email']        ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $password     = 'Edu@1234'; // default

    // Validasi
    if ($full_name === '') $errors[] = 'Full Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid Email is required.';
    } elseif (!str_ends_with($email, '@edupredict.com')) {
        $errors[] = 'Email must use @edupredict.com domain.';
    }
    if ($phone_number === '') $errors[] = 'Phone Number is required.';

    // Semak jika email sudah wujud
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        $errors[] = 'This email is already registered.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $username = strtolower(preg_replace('/\s+/', '', $full_name));

            $stmtU = $pdo->prepare("
                INSERT INTO users (name, username, email, phone_number, password_hash, role, must_change_password)
                VALUES (?, ?, ?, ?, ?, 'educator', 1)
            ");
            $stmtU->execute([$full_name, $username, $email, $phone_number, $hash]);
            $newUserId = $pdo->lastInsertId();

            $stmtE = $pdo->prepare("
                INSERT INTO educators (user_id, phone_number)
                VALUES (?, ?)
            ");
            $stmtE->execute([$newUserId, $phone_number]);

            $pdo->commit();
            $_SESSION['message'] = 'Educator added successfully. Default password is Edu@1234.';
            header('Location: manage_educators.php?added=1');
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Add Educator | EduPredict</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f0f2f5;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }
    .form-box {
      background: #fff;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 450px;
    }
    .form-box h2 {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .form-group {
      margin-bottom: 1rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
    }
    .form-group input {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 1rem;
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
      font-size: 1.5rem;
      cursor: pointer;
    }
    .error {
      background: #fee;
      border: 1px solid #f99;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <div class="form-box">
    <button class="close-btn" onclick="location.href='manage_educators.php'">&times;</button>
    <h2>Add New Educator</h2>

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
        <input id="name" name="name" type="text" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="phone_number">Phone Number</label>
        <input id="phone_number" name="phone_number" type="text" value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>" required>
      </div>
      <button type="submit" class="btn-submit">Add Educator</button>
    </form>
  </div>
</body>
</html>
