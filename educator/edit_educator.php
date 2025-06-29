<?php
// edit_educator.php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db_connect.php';

$errors = [];
// 1) Dapatkan ID educator dari URL
$eid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$eid) {
    header('Location: manage_educators.php');
    exit;
}

// 2) Ambil data educator & user profile
$stmt = $pdo->prepare(
    "SELECT e.id AS eid,
            e.user_id,
            e.phone_number AS edu_phone,
            u.name,
            u.email,
            u.phone_number AS user_phone
     FROM educators e
     JOIN users u ON e.user_id = u.id
     WHERE e.id = ?"
);
$stmt->execute([$eid]);
$edu = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$edu) {
    header('Location: manage_educators.php');
    exit;
}

// 3) Proses POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name']    ?? '');
    $email        = trim($_POST['email']        ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $reset_pw = isset($_POST['reset_password']);


    // Validasi ringkas
    // Validasi input
if ($full_name === '') {
    $errors[] = 'Full Name is required.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid Email is required.';
} elseif (!str_ends_with($email, '@edupredict.com')) {
    $errors[] = 'Email must end with @edupredict.com.';
} else {
    // Check if email is already used by someone else
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmtCheck->execute([$email, $edu['user_id']]);
    if ($stmtCheck->fetch()) {
        $errors[] = 'Email is already registered with another account.';
    }
}

if ($phone_number === '') {
    $errors[] = 'Phone Number is required.';
}

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 3.1 Update users (name, email, phone, optional password)
            if ($reset_pw) {
    $hash = password_hash('Edu@1234', PASSWORD_DEFAULT);
    $stmtUp = $pdo->prepare(
        "UPDATE users
            SET name = ?, email = ?, phone_number = ?, password_hash = ?
         WHERE id = ?"
    );
    $stmtUp->execute([
        $full_name,
        $email,
        $phone_number,
        $hash,
        $edu['user_id']
    ]);
} else {
    $stmtUp = $pdo->prepare(
        "UPDATE users
            SET name = ?, email = ?, phone_number = ?
         WHERE id = ?"
    );
    $stmtUp->execute([
        $full_name,
        $email,
        $phone_number,
        $edu['user_id']
    ]);
}


            // 3.2 Update educators (phone number)
            $stmtEd = $pdo->prepare(
                "UPDATE educators
                    SET phone_number = ?
                  WHERE id = ?"
            );
            $stmtEd->execute([$phone_number, $eid]);

            $pdo->commit();
            $_SESSION['message'] = 'Educator updated successfully.';
            header('Location: manage_educators.php?updated=1');
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
  <title>Edit Educator | EduPredict</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <style>
    body { 
      font-family:'Poppins',sans-serif; 
      background:#f9faf0; 
      padding:2rem; 
    }
    .form-box { 
      max-width:450px; 
      margin:2rem auto; 
      background:#fff; 
      padding:2rem; 
      border-radius:12px; 
      box-shadow:0 5px 15px rgba(0,0,0,0.1); 
    }
    .form-box h2 { 
      text-align:center; 
      margin-bottom:1.5rem; 
    }
    .error {
      background:#fee; 
      border:1px solid #f99; 
      padding:1rem; 
      margin-bottom:1rem; 
    }
    .form-group { 
      margin-bottom:1rem; 
    }
    .form-group label { 
      display:block; 
      margin-bottom:0.5rem; 
      font-weight:500; 
    }
    .form-group input { 
      width:100%; 
      padding:0.75rem; 
      border:1px solid #ddd; 
      border-radius:8px; 
    }
    .btn-submit { 
      width:100%; 
      padding:0.75rem; 
      background:#000; 
      color:#fff; 
      border:none; 
      border-radius:8px; 
      cursor:pointer; 
      font-weight:600; 
    }
    .close-btn { 
      float:right; 
      background:transparent; 
      border:none; 
      font-size:1.25rem; 
      cursor:pointer; 
    }
  </style>
</head>
<body>
  <div class="form-box">
    <button class="close-btn" onclick="location.href='manage_educators.php'">&times;</button>
    <h2>Edit Educator</h2>

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
      <?php if (isset($_GET['updated'])): ?>
  <div class="message" style="margin-top:1rem;background:#e8fbe8;border:1px solid #6bb76b;padding:1rem;border-radius:8px;text-align:center;">
    ✅ Educator details updated successfully.
  </div>
<?php endif; ?>

      <div class="form-group">
        <label for="full_name">Full Name</label>
        <input id="full_name" name="full_name" type="text" value="<?= htmlspecialchars($_POST['full_name'] ?? $edu['name']) ?>">
      </div>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" value="<?= htmlspecialchars($_POST['email'] ?? $edu['email']) ?>">
      </div>
      <div class="form-group">
        <label for="phone_number">Phone Number</label>
        <input id="phone_number" name="phone_number" type="text" value="<?= htmlspecialchars($_POST['phone_number'] ?? $edu['edu_phone']) ?>">
      </div>
      <div class="form-group">
  <label>
    <input type="checkbox" name="reset_password" value="1">
    Reset password to <code>Edu@1234</code>
  </label>
</div>

      <button type="submit" class="btn-submit">Update Educator</button>
    </form>
  </div>
</body>
</html>
