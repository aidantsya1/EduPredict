<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db_connect.php';

$message = '';
$errorRows = [];

if (isset($_POST['import'])) {
    $file = $_FILES['file']['tmp_name'];
    if (is_uploaded_file($file) && $_FILES['file']['size'] > 0) {
        $h = fopen($file, "r");
        fgetcsv($h); // Skip header row
        while (($d = fgetcsv($h, 1000, ",")) !== false) {
            if (count($d) < 3) continue;

            list($name, $email, $phone) = array_map('trim', $d);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@edupredict.com')) {
                $errorRows[] = "$email (invalid email)";
                continue;
            }

            // Check if email already exists
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetchColumn() > 0) {
                $errorRows[] = "$email (already exists)";
                continue;
            }

            try {
                $pdo->beginTransaction();

                $defaultPassword = 'Edu@1234';
                $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
                $username = strtolower(preg_replace('/\s+/', '', $name));

                $stmtU = $pdo->prepare("
                    INSERT INTO users (name, username, email, phone_number, password_hash, role)
                    VALUES (?, ?, ?, ?, ?, 'educator')
                ");
                $stmtU->execute([$name, $username, $email, $phone, $hash]);
                $uid = $pdo->lastInsertId();

                $stmtE = $pdo->prepare("INSERT INTO educators (user_id, phone_number) VALUES (?, ?)");
                $stmtE->execute([$uid, $phone]);

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorRows[] = "$email (DB error)";
            }
        }
        fclose($h);

        if (empty($errorRows)) {
            $message = "✅ Educator import completed successfully.";
        } else {
            $message = "⚠️ Import completed with errors:<br>" . implode('<br>', $errorRows);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Import Educators — EduPredict</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #f9faf0;
      padding: 2rem;
    }
    .form-box {
      max-width: 480px;
      margin: 2rem auto;
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
    .form-group {
      margin-bottom: 1rem;
    }
    .form-group input[type="file"] {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #ddd;
      border-radius: 8px;
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
    .close-btn {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: transparent;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
    }
    .message {
      background: #fef9e7;
      border: 1px solid #f39c12;
      color: #b9770e;
      padding: 1rem;
      border-radius: 8px;
      margin-top: 1rem;
      font-size: 0.95rem;
    }
    .note {
      margin-top: 1.5rem;
      font-size: 0.9rem;
      color: #555;
      text-align: center;
      background: #f8f8f8;
      border: 1px dashed #ccc;
      padding: 1rem;
      border-radius: 8px;
    }
    .note code {
      background: #eee;
      padding: 2px 4px;
      border-radius: 4px;
    }
  </style>
</head>
<body>
  <div class="form-box">
    <button class="close-btn" onclick="location.href='manage_educators.php'">&times;</button>
    <h2>Import Educators</h2>

    <form method="post" enctype="multipart/form-data">
      <div class="form-group">
        <input type="file" name="file" accept=".csv" required>
      </div>
      <button type="submit" name="import" class="btn-submit">Import CSV</button>
    </form>

    <?php if ($message): ?>
      <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <div class="note">
      <strong>📌 Instructions for CSV Import:</strong><br><br>
      • Upload a valid <code>.csv</code> file with the following headers:<br>
      <code>name, email, phone_number</code><br><br>
      • Email must end with <code>@edupredict.com</code><br>
      • Example phone number format: <code>012-3456789</code><br>
      • Default password will be set to: <code>Edu@1234</code><br><br>
      <a href="template/template_educators.csv" download>📥 Download Sample CSV Template</a>
    </div>
  </div>
</body>
</html>
