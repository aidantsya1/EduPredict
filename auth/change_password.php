<?php
session_start();
require __DIR__ . '/db_connect.php';

$error   = '';
$notice  = '';

// One-time notice if first-login
if (isset($_SESSION['must_change_password'])) {
    $notice = 'This is your first login. Please change your password now.';
    unset($_SESSION['must_change_password']);
}

// Determine dashboard URL for 'back' button based on user role
$dashboardUrl = 'login.php';
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            $dashboardUrl = 'dashboard_admin.php';
            break;
        case 'educator':
            $dashboardUrl = 'dashboard_educator.php';
            break;
        case 'student':
            $dashboardUrl = 'dashboard_student.php';
            break;
    }
}

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['new_password']     ?? '';
    $con = $_POST['confirm_password'] ?? '';

    if (!$new || !$con) {
        $error = 'Please fill in both password fields.';
    } elseif ($new !== $con) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[@#$%&*]/', $new)) {
        $error = 'Password must include at least one symbol: @ # $ % & *.';
    } else {
        // All checks passed: update password & clear flag
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        if ($stmt->execute([$hash, $_SESSION['user_id']])) {
            session_unset();
            session_destroy();
            echo "<script>
                    alert('Your password has been reset. Please log in again.');
                    window.location='login.php';
                  </script>";
            exit;
        } else {
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password — EduPredict</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css" />
  <style>/* Reset password modal layout */
.modal-backdrop {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
  background: #f0f4f8;
  font-family: 'Poppins', sans-serif;
}

.reset-modal {
  background: white;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 420px;
  position: relative;
}

/* Close button */
.close-btn {
  position: absolute;
  right: 16px;
  top: 16px;
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
}

/* Headings */
.reset-modal h2 {
  margin-bottom: 20px;
  text-align: center;
  font-weight: 600;
  color: #333;
}

/* Message boxes */
.notice,
.error {
  margin-bottom: 16px;
  padding: 12px 15px;
  border-radius: 6px;
  font-size: 14px;
  line-height: 1.5;
}
.notice {
  background-color: #e0f7fa;
  color: #00796b;
}
.error {
  background-color: #ffebee;
  color: #c62828;
}

/* Form styling */
.form-group {
  margin-bottom: 18px;
}

.form-group label {
  display: block;
  font-weight: 500;
  margin-bottom: 6px;
  color: #333;
}

.form-group input[type="password"] {
  width: 100%;
  padding: 10px 12px;
  font-size: 14px;
  border-radius: 6px;
  border: 1px solid #ccc;
  transition: border-color 0.3s ease;
}

.form-group input[type="password"]:focus {
  border-color: #007bff;
  outline: none;
}

/* Submit button */
.btn-submit {
  width: 100%;
  padding: 12px;
  font-size: 15px;
  font-weight: 600;
  color: white;
  background-color: #007bff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.3s ease;
}

.btn-submit:hover {
  background-color: #0056b3;
}
</style>
</head>
<body>
  <div class="modal-backdrop">
    <div class="reset-modal">
      <button type="button" class="close-btn" onclick="window.location='<?= htmlspecialchars($dashboardUrl) ?>'">&times;</button>
      <h2>Reset Password</h2>

      <!-- Always show the requirements note -->
      <div class="notice">
        <strong>Password requirements:</strong><br>
        • Minimum length: 6 characters<br>
        • At least one uppercase letter<br>
        • At least one symbol: @ # $ % & *
      </div>

      <?php if ($notice): ?>
        <div class="notice"><?= htmlspecialchars($notice) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="form-group">
          <label>New Password</label>
          <input
            type="password"
            name="new_password"
            placeholder="Enter new password"
            required
          />
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <input
            type="password"
            name="confirm_password"
            placeholder="Re-enter new password"
            required
          />
        </div>
        <button type="submit" class="btn-submit">Save Password</button>
      </form>
    </div>
  </div>
</body>
</html>
