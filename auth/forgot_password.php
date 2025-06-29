<?php
session_start();
require __DIR__ . '/db_connect.php';

$error = '';
$stage = $_SESSION['stage'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($stage === 1 && empty($_POST['otp'])) {
        $phone = trim($_POST['phone_number'] ?? '');
        if ($phone === '') {
            $error = 'Please enter your phone number.';
        } elseif (!preg_match('/^\d{3}-\d{7,8}$/', $phone)) {
            $error = 'Phone number must be in format like 018-2527115 (with dash).';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ?");
            $stmt->execute([$phone]);
            if ($stmt->rowCount() === 0) {
                $error = "Phone number “{$phone}” not found.";
            } else {
                $_SESSION['otp']            = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['phone_verified'] = $phone;
                $_SESSION['stage']          = 2;
                header('Location: forgot_password.php');
                exit;
            }
        }
    } elseif ($stage === 2 && isset($_POST['otp'])) {
        if ($_POST['otp'] === ($_SESSION['otp'] ?? '')) {
            $_SESSION['stage'] = 3;
            header('Location: forgot_password.php');
            exit;
        } else {
            $error = 'Incorrect OTP, please try again.';
        }
    } elseif ($stage === 3 && isset($_POST['new_password'])) {
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if ($new === '' || $conf === '') {
            $error = 'Please fill both password fields.';
        } elseif ($new !== $conf) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new) < 6 
                  || !preg_match('/[A-Z]/', $new) 
                  || !preg_match('/[@#$%&*]/', $new)) {
            $error = 'Password must be ≥6 chars, include an uppercase letter and one of @#$%&*.';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $phone  = $_SESSION['phone_verified'];
            $upd    = $pdo->prepare("UPDATE users SET password_hash = ? WHERE phone_number = ?");
            $upd->execute([$hashed, $phone]);

            unset($_SESSION['otp'], $_SESSION['phone_verified'], $_SESSION['stage']);
            header('Location: login.php?reset=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Forgot Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <div class="modal-backdrop">
    <div class="forgot-modal">
      <button class="close-btn" onclick="location.href='login.php'">&times;</button>
      <h2>Forgot Password</h2>

      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($stage === 1): ?>
        <form method="post">
          <div class="form-group">
            <label>Phone Number *</label>
            <input 
              type="text" 
              name="phone_number" 
              placeholder="e.g. 018-2527115" 
              required 
              pattern="\d{3}-\d{7,8}" 
              title="Phone number must be in the format XXX-XXXXXXX with dash (-)"
            />
          </div>
          <button type="submit" class="btn-submit">Send OTP</button>
        </form>

      <?php elseif ($stage === 2): ?>
        <div class="notice">
          <strong>OTP (simulated SMS):</strong>
          <span><?= htmlspecialchars($_SESSION['otp']) ?></span>
        </div>
        <form method="post">
          <div class="form-group">
            <label>Enter OTP</label>
            <input type="text" name="otp" required />
          </div>
          <button type="submit" class="btn-submit">Verify OTP</button>
        </form>

      <?php else: ?>
        <div class="notice">
          <strong>Password requirements:</strong><br>
          &bull; Minimum length: 6 characters<br>
          &bull; At least one uppercase letter<br>
          &bull; At least one symbol: @ # $ % & *
        </div>
        <form method="post">
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" required />
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required />
          </div>
          <button type="submit" class="btn-submit">Reset Password</button>
        </form>
      <?php endif; ?>

    </div>
  </div>
</body>
</html>
