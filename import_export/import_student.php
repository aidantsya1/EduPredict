<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require 'db_connect.php';

$message = '';
$duplicates = [];
$invalidClasses = [];

if (isset($_POST['import'])) {
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        $header = fgetcsv($handle, 1000, ',');

        if ($header === FALSE) {
            $message = "CSV file is empty or invalid.";
        } else {
            $keys = array_map(function($h) {
                $h = strtolower(trim($h));
                return $h === 'class' ? 'class_name' : str_replace(' ', '_', $h);
            }, $header);

            $stmtClassSel = $pdo->prepare("SELECT id FROM classes WHERE LOWER(class_name) = LOWER(?)");
            $stmtUser     = $pdo->prepare("INSERT IGNORE INTO users (name, username, email, phone_number, password_hash, role) VALUES (:name, :username, :email, :phone, :phash, 'student')");
            $stmtUserUp   = $pdo->prepare("UPDATE users SET phone_number = :phone WHERE email = :email");
            $stmtStd = $pdo->prepare("INSERT INTO students (student_id, user_id, class_id, batch, address, phone_number)
                                      VALUES (:sid, :uid, :cid, :batch, :addr, :phone)
                                      ON DUPLICATE KEY UPDATE
                                        class_id = VALUES(class_id),
                                        batch = VALUES(batch),
                                        address  = VALUES(address),
                                        phone_number = VALUES(phone_number)");

            while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if (count($row) < count($keys)) continue;

                $data = array_combine($keys, array_map('trim', $row));
                $email = $data['email'] ?? '';
                $student_id = $data['student_id'] ?? $data['ic_number'] ?? '';

                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@edupredict.com')) continue;
                if (!preg_match('/^\d{3}-\d{7,8}$/', $data['phone_number'] ?? '')) continue;

                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $duplicates[] = $email;
                    continue;
                }

                $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
                $stmt->execute([$student_id]);
                if ($stmt->rowCount() > 0) {
                    $duplicates[] = $student_id;
                    continue;
                }

                $cls = $data['class_name'] ?? '';
                $stmtClassSel->execute([$cls]);
                $cid = $stmtClassSel->fetchColumn();
                if (!$cid) {
                    $invalidClasses[] = $cls;
                    continue;
                }

                $phash = password_hash($student_id, PASSWORD_DEFAULT);
                $stmtUser->execute([
                    ':name'     => $data['name'],
                    ':username' => $email,
                    ':email'    => $email,
                    ':phone'    => $data['phone_number'] ?? '',
                    ':phash'    => $phash
                ]);
                $stmtUserUp->execute([
                    ':phone' => $data['phone_number'] ?? '',
                    ':email' => $email
                ]);

                $uid = $pdo->lastInsertId();
                if (!$uid) {
                    $stmt2 = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt2->execute([$email]);
                    $uid = $stmt2->fetchColumn();
                }

                $stmtStd->execute([
                    ':sid'   => $student_id,
                    ':uid'   => $uid,
                    ':cid'   => $cid,
                    ':batch' => $data['batch'] ?? '23/24',
                    ':addr'  => $data['address'] ?? '',
                    ':phone' => $data['phone_number'] ?? ''
                ]);
            }

            fclose($handle);
            $message = "Import complete.";
            if ($duplicates) {
                $message .= "<br><strong>Duplicate entries (email or student ID already exists):</strong><br>" .
                            implode('<br>', array_map('htmlspecialchars', array_unique($duplicates)));
            }
            if ($invalidClasses) {
                $message .= "<br><strong>Invalid class (does not exist):</strong><br>" .
                            implode('<br>', array_map('htmlspecialchars', array_unique($invalidClasses)));
            }
        }
    } else {
        $message = "Please upload a CSV file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Import Students — EduPredict</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Poppins', sans-serif; background: #f9faf0; padding: 2rem; }
    .form-box { max-width: 520px; margin: 3rem auto; background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); position: relative; }
    .form-box h2 { text-align: center; margin-bottom: 1.5rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group input[type="file"] { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; }
    .btn-submit { width: 100%; padding: 0.75rem; background: #000; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .close-btn { position: absolute; top: 1rem; right: 1rem; background: transparent; border: none; font-size: 1.5rem; cursor: pointer; }
    .message { background: #e6ffed; border: 1px solid #27ae60; color: #2e7d32; padding: 1rem; border-radius: 8px; margin-top: 1rem; font-size: 0.95rem; }
    .note { margin-top: 2rem; font-size: 0.9rem; color: #333; background: #fffef4; border: 1px solid #e2d27f; padding: 1rem; border-radius: 8px; line-height: 1.5; }
    .note code { background: #eee; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.9rem; }
    .note a { display: inline-block; margin-top: 0.8rem; font-weight: 600; color: #2e7d32; text-decoration: none; }
    .note a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="form-box">
    <button class="close-btn" onclick="location.href='manage_students.php'">&times;</button>
    <h2>Import Student Data</h2>

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
      <code>name, email, phone_number, student_id, batch, address, class</code><br><br>
      • Example phone number format: <code>012-3456789</code><br>
      • Email must end with <code>@edupredict.com</code><br>
      • Password will be set to match <code>student_id</code> (IC number)<br><br>
      <a href="template/template_students.csv" download>📥 Download Sample CSV Template</a>
    </div>
  </div>
</body>
</html>
