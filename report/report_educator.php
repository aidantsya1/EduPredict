<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'educator') {
    header("Location: login.php");
    exit;
}
require __DIR__ . '/db_connect.php';

$userId = $_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Educator');

// Get educator_id from user_id
$educatorStmt = $pdo->prepare("SELECT id FROM educators WHERE user_id = ?");
$educatorStmt->execute([$userId]);
$educatorId = $educatorStmt->fetchColumn();

// Get class_id from classes table
$classStmt = $pdo->prepare("SELECT id FROM classes WHERE educator_id = ?");
$classStmt->execute([$educatorId]);
$classId = $classStmt->fetchColumn();

// Get latest prediction per student in this class
$currentStmt = $pdo->prepare("
    SELECT p.*, s.student_id, u.name AS student_name
    FROM students s
    JOIN performance p ON s.student_id = p.student_id
    JOIN users u ON s.user_id = u.id
    JOIN (
        SELECT student_id, MAX(id) AS latest_id
        FROM performance
        GROUP BY student_id
    ) latest ON p.id = latest.latest_id
    WHERE s.class_id = ?
    ORDER BY u.name
");
$currentStmt->execute([$classId]);
$currentResults = $currentStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all history predicted by this educator
$historyStmt = $pdo->prepare("
  SELECT p.*, s.student_id, u.name AS student_name
  FROM performance p
  JOIN students s ON p.student_id = s.student_id
  JOIN users u ON s.user_id = u.id
  WHERE s.class_id IN (
    SELECT id FROM classes WHERE educator_id = ?
  )
  ORDER BY p.date_recorded DESC, u.name
");
$historyStmt->execute([$educatorId]);

$historyResults = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Student Report | EduPredict</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css" />
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; font-family: 'Poppins', sans-serif; background: #f9f9f9; }
    .layout { display: flex; height: 100vh; }
    .sidebar { flex: 0 0 250px; background: #e4e3de; overflow: hidden; }
    .main-content { flex: 1; margin-left: 250px; }
    .container { padding: 2rem; overflow: auto; }
    .tabs { display: flex; gap: 1rem; margin-bottom: 1rem; }
    .tab-btn { padding: 0.5rem 1rem; border: none; background: #ccc; cursor: pointer; }
    .tab-btn.active { background: #504A48; color: #fff; }
    .report-section { display: none; }
    .report-section.active { display: block; }
    table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 1rem; }
    th, td { border: 1px solid #ccc; padding: 0.75rem; text-align: center; }
    th { background: #f0f0f0; }
    .pred-box { padding: 0.25rem 0.75rem; border-radius: 4px; font-weight: 600; color: #fff; }
    .low { background: #e74c3c; }
    .medium { background: #f1c40f; color: #333; }
    .high { background: #2ecc71; }
    textarea {
      width: 100%; padding: 6px; font-family: 'Poppins', sans-serif; margin-top: 4px;
      border: 1px solid #ccc; border-radius: 4px; font-size: 0.85rem;
    }
    .btn-send {
      background: #28a745; color: #fff; border: none; padding: 6px 10px;
      margin-top: 4px; border-radius: 4px; cursor: pointer;
    }
    .btn-send:hover { background: #1e7e34; }
    .popup-success {
      position: fixed;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      background: #d4edda; color: #155724;
      padding: 16px 24px;
      border-left: 6px solid #28a745;
      border-radius: 8px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.15);
      z-index: 1000;
      transition: opacity 0.5s ease-in-out;
    }
    .btn-download {
  display: inline-block;
  margin-top: 1rem;
  background: #504A48;
  color: white;
  padding: 8px 14px;
  text-decoration: none;
  border-radius: 5px;
  font-weight: 400;
  transition: background 0.3s ease;
}
.btn-download:hover {
  background: #333;
}

  </style>
</head>
<body>
<?php if (isset($_SESSION['message'])): ?>
  <div id="popup" class="popup-success">
    <?= htmlspecialchars($_SESSION['message']) ?>
  </div>
  <script>
    setTimeout(() => {
      const popup = document.getElementById('popup');
      if (popup) popup.style.opacity = '0';
    }, 3000);
  </script>
  <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-header">
      <a href="dashboard_educator.php">
        <img src="picture/logo.png" alt="Logo" class="sidebar-logo">
      </a>
    </div>
    <nav>
      <ul>
        <li><a href="dashboard_educator.php">Dashboard</a></li>
        <li><a href="my_students.php">My Students</a></li>
        <li><a href="prediction_educator.php">Predict Result</a></li>
        <li><a href="report_educator.php" class="active">Student Report</a></li>
        <li><a href="change_password.php">Change Password</a></li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
    </nav>
  </aside>

  <div class="main-content">
    <header class="topbar">
      <h3>Welcome, <?= $userName ?></h3>
    </header>

    <div class="container">
      <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('current')">Current Prediction</button>
        <button class="tab-btn" onclick="switchTab('history')">Prediction History</button>
      </div>

      <div id="current" class="report-section active">
        <h2>Current Prediction</h2>
        <table>
          <thead>
            <tr><th>Student ID</th><th>Name</th><th>U1</th><th>U2</th><th>U3</th><th>T1</th><th>T2</th><th>T3</th><th>Prediction</th></tr>
          </thead>
          <tbody>
            <?php foreach ($currentResults as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['student_id']) ?></td>
              <td><?= htmlspecialchars($row['student_name']) ?></td>
              <td><?= $row['internal_test1'] ?></td>
              <td><?= $row['internal_test2'] ?></td>
              <td><?= $row['internal_test3'] ?></td>
              <td><?= $row['trial1'] ?></td>
              <td><?= $row['trial2'] ?></td>
              <td><?= $row['trial3'] ?></td>
              <td>
                <span class="pred-box <?= strtolower($row['prediction_result']) ?>">
                  <?= $row['prediction_result'] ?>
                </span>
                <?php if ($row['prediction_result'] === 'Low'): ?>
                  <form method="POST" action="send_notification.php">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($row['student_id']) ?>">
                    <textarea name="message" placeholder="Write message..." required></textarea>
                    <button type="submit" class="btn-send">Send</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div id="history" class="report-section">
        <h2>Prediction History</h2>
        <table>
          <thead>
            <tr><th>Date</th><th>Student ID</th><th>Name</th><th>U1</th><th>U2</th><th>U3</th><th>T1</th><th>T2</th><th>T3</th><th>Prediction</th></tr>
          </thead>
          <a href="download_report.php" class="btn-download" target="_blank">⬇️ Download Report (CSV)</a>
          
          <tbody>
            <?php foreach ($historyResults as $row): ?>
            <tr>
              <td><?= $row['date_recorded'] ?></td>
              <td><?= htmlspecialchars($row['student_id']) ?></td>
              <td><?= htmlspecialchars($row['student_name']) ?></td>
              <td><?= $row['internal_test1'] ?></td>
              <td><?= $row['internal_test2'] ?></td>
              <td><?= $row['internal_test3'] ?></td>
              <td><?= $row['trial1'] ?></td>
              <td><?= $row['trial2'] ?></td>
              <td><?= $row['trial3'] ?></td>
              <td><span class="pred-box <?= strtolower($row['prediction_result']) ?>"><?= $row['prediction_result'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.report-section').forEach(section => section.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    document.querySelector(`[onclick*="${tabId}"]`).classList.add('active');
  }
</script>
</body>
</html>
