<?php
// add_student.php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db_connect.php';

// fetch classes and batches
$classes = $pdo
  ->query("SELECT id, class_name FROM classes ORDER BY class_name")
  ->fetchAll(PDO::FETCH_ASSOC);
$batches = $pdo
  ->query("SELECT DISTINCT batch FROM students ORDER BY batch DESC")
  ->fetchAll(PDO::FETCH_COLUMN);

$errors = [];
$success = $_SESSION['message'] ?? '';
unset($_SESSION['message']); // clear message

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ic_number    = trim($_POST['ic_number']    ?? '');
    $full_name    = trim($_POST['full_name']    ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $email        = trim($_POST['email']        ?? '');
    $address      = trim($_POST['address']      ?? '');
    $class_id     = (int)($_POST['class_id']    ?? 0);
    $batch        = trim($_POST['batch']        ?? '');
    $password     = $ic_number;

    if ($ic_number === '') $errors[] = 'Student ID is required.';
    if ($full_name === '') $errors[] = 'Full Name is required.';

    if ($phone_number === '') {
        $errors[] = 'Phone Number is required.';
    } elseif (!preg_match('/^\d{3}-\d{7,8}$/', $phone_number)) {
        $errors[] = 'Phone Number must be in format XXX-XXXXXXX (with dash).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@edupredict.com')) {
        $errors[] = 'Email must be a valid @edupredict.com address.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = 'This email is already registered.';
        }
    }

    if ($address === '') $errors[] = 'Address is required.';
    if ($class_id <= 0)  $errors[] = 'Please select a Class.';
    if ($batch === '')  $errors[] = 'Please select a Batch.';

    // Check for duplicate student ID
    $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
    $stmt->execute([$ic_number]);
    if ($stmt->rowCount() > 0) {
        $errors[] = 'This Student ID is already registered.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtU = $pdo->prepare("INSERT INTO users (username, name, email, password_hash, role, must_change_password) VALUES (:username, :name, :email, :pass, 'student', 1)");
            $stmtU->execute([
                ':username' => $email,
                ':name'     => $full_name,
                ':email'    => $email,
                ':pass'     => $hash
            ]);
            $newUserId = $pdo->lastInsertId();

            $stmtS = $pdo->prepare("INSERT INTO students (user_id, student_id, class_id, batch, address, phone_number) VALUES (:uid, :ic, :cid, :batch, :addr, :phone)");
            $stmtS->execute([
                ':uid'   => $newUserId,
                ':ic'    => $ic_number,
                ':cid'   => $class_id,
                ':batch' => $batch,
                ':addr'  => $address,
                ':phone' => $phone_number
            ]);

            $pdo->commit();
            $_SESSION['message'] = 'Student added successfully.';
            header('Location: add_student.php');
            exit;

        } catch (PDOException $e) {
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
  <title>Add Student Profile — EduPredict</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f9faf0;
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }
    .form-box {
      width: 100%;
      max-width: 500px;
      background: #fff;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      position: relative;
    }
    .form-box h2 {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .error, .success {
      padding: 1rem;
      margin-bottom: 1rem;
      border-radius: 8px;
    }
    .error { background: #fee; border: 1px solid #f99; color: #900; }
    .success { background: #e0fce8; border: 1px solid #5cb85c; color: #2e7d32; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
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
    .form-group textarea { resize: vertical; }
    .btn-submit {
      width: 100%;
      padding: 0.75rem;
      background: #000;
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      font-size: 15px;
    }
    .btn-submit:hover { background: #333; }
    .close-btn {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: transparent;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="form-box">
    <button class="close-btn" onclick="location.href='manage_students.php'">&times;</button>
    <h2>Add Student</h2>

    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="error">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="form-group">
        <label for="ic_number">Student ID</label>
        <input id="ic_number" name="ic_number" type="text" value="<?= htmlspecialchars($_POST['ic_number'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="full_name">Full Name</label>
        <input id="full_name" name="full_name" type="text" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="phone_number">Phone Number</label>
        <input id="phone_number" name="phone_number" type="text"
               pattern="\d{3}-\d{7,8}"
               title="Format: 012-3456789 (must include dash)"
               value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="address">Address</label>
        <textarea id="address" name="address" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label for="class_id">Class</label>
        <select id="class_id" name="class_id">
          <option value="">-- Select Class --</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (isset($_POST['class_id']) && $_POST['class_id']==$c['id'])?'selected':'' ?>>
              <?= htmlspecialchars($c['class_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="batch">Batch</label>
        <select id="batch" name="batch">
          <option value="">-- Select Batch --</option>
          <?php foreach ($batches as $b): ?>
            <option value="<?= $b ?>" <?= (isset($_POST['batch']) && $_POST['batch']==$b)?'selected':'' ?>><?= $b ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn-submit">Submit</button>
    </form>
  </div>
</body>
</html>

