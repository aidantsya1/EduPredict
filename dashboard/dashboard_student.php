<?php
session_start();
require __DIR__ . '/db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Student');
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT 
        u.name AS student_name,
        s.student_id,
        s.batch,
        s.address,
        s.phone_number,
        c.class_name,
        eu.name AS educator_name
     FROM students s
     JOIN users u ON s.user_id = u.id
     LEFT JOIN classes c ON s.class_id = c.id
     LEFT JOIN educators e ON c.educator_id = e.id
     LEFT JOIN users eu ON e.user_id = eu.id
     WHERE s.user_id = ?"
);
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare(
    "SELECT 
        attendance_percent,
        internal_test1,
        internal_test2,
        internal_test3,
        trial1,
        trial2,
        trial3,
        prediction_result,
        date_recorded
     FROM performance
     WHERE student_id = :sid
     ORDER BY date_recorded DESC
     LIMIT 1"
);
$stmt2->execute([':sid' => $profile['student_id']]);
$perf = $stmt2->fetch(PDO::FETCH_ASSOC);

$stmt3 = $pdo->prepare(
    "SELECT
        n.message,
        eu.name AS educator_name,
        n.sent_at AS sent_at
     FROM notifications n
     JOIN educators e ON n.educator_id = e.id
     JOIN users eu ON e.user_id = eu.id
     WHERE n.student_id = :sid
     ORDER BY n.sent_at DESC"
);
$stmt3->execute([':sid' => $profile['student_id']]);
$notifications = $stmt3->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — EduPredict</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body, html { margin: 0; padding: 0; font-family: 'Poppins', sans-serif; background: #f4f4f4; }
    .layout { display: flex; height: 100vh; }
    .container { padding: 2rem; overflow: auto; }
    .profile-panel, .notification-panel, .chart-panel {
      background: #fff; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .profile-panel h1 { margin-bottom: 1rem; }
    .profile-panel table { width: 100%; border-collapse: collapse; }
    .profile-panel td { padding: .5rem; vertical-align: top; }
    .btn-edit {
      background: #e11d48; color: #fff; border: none; padding: .5rem 1rem; border-radius: 4px;
      float: right; cursor: pointer;
    }
    .notification-panel ul { list-style: none; padding: 0; }
    .notification-panel li { padding: .75rem; border-bottom: 1px solid #eee; }
    .notification-panel li.latest { background: #fffbe6; border-left: 4px solid #f1c40f; }
    .chart-panel canvas { max-width: 100%; }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="sidebar-header">
        <a href="dashboard_student.php">
          <img src="picture/logo.png" alt="EduPredict Logo" class="sidebar-logo">
        </a>
      </div>
      <nav>
        <ul>
          <li><a href="dashboard_student.php" class="active">Dashboard</a></li>
          <li><a href="prediction_simulation.php">Simulator</a></li>
          <li><a href="report_student.php">History</a></li>
          <li><a href="change_password.php">Change Password</a></li>
          <li><a href="logout.php">Logout</a></li>
        </ul>
      </nav>
    </aside>

    <div class="main-content">
      <header class="topbar">
        <button id="sidebarToggle" class="toggle-btn">☰</button>
        <h3>Welcome, <?= $userName ?></h3>
        <div class="user-info">
          <span><small>Student</small></span>
          <img src="picture/avatar.jpg" alt="Avatar">
        </div>
      </header>

      <div class="container">
        <div class="profile-panel">
          <h1>My Profile <a href="edit_profile_student.php" class="btn-edit">Edit</a></h1>
          <table>
            <tr><td><strong>ID:</strong></td><td><?= htmlspecialchars($profile['student_id']) ?></td></tr>
            <tr><td><strong>Name:</strong></td><td><?= htmlspecialchars($profile['student_name']) ?></td></tr>
            <tr><td><strong>Batch:</strong></td><td><?= htmlspecialchars($profile['batch']) ?></td></tr>
            <tr><td><strong>Class:</strong></td><td><?= htmlspecialchars($profile['class_name'] ?? '-') ?></td></tr>
            <tr><td><strong>Educator:</strong></td><td><?= htmlspecialchars($profile['educator_name'] ?? '-') ?></td></tr>
            <tr><td><strong>Address:</strong></td><td><?= htmlspecialchars($profile['address']) ?></td></tr>
            <tr><td><strong>Phone:</strong></td><td><?= htmlspecialchars($profile['phone_number']) ?></td></tr>
          </table>
        </div>

        <div class="notification-panel">
          <h2>Notifications</h2>
          <?php if ($notifications): ?>
            <ul>
              <?php foreach ($notifications as $i => $note): ?>
                <li class="<?= $i === 0 ? 'latest' : '' ?>">
                  <strong><?= htmlspecialchars($note['educator_name']) ?>:</strong> <?= htmlspecialchars($note['message']) ?><br>
                  <small style="color:#666;">Sent at <?= date('d M Y, H:i', strtotime($note['sent_at'])) ?></small>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>No new notifications.</p>
          <?php endif; ?>
        </div>

        <div class="chart-panel">
          <h1>Latest Results</h1>
          <canvas id="performanceChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <script>
    const labels = ['Attendance','Ujian 1','Ujian 2','Ujian 3','Trial 1','Trial 2','Trial 3'];
    const rawData = [
      <?= json_encode($perf['attendance_percent'] ?? 0) ?>,
      <?= json_encode($perf['internal_test1'] ?? 0) ?>,
      <?= json_encode($perf['internal_test2'] ?? 0) ?>,
      <?= json_encode($perf['internal_test3'] ?? 0) ?>,
      <?= json_encode($perf['trial1'] ?? 0) ?>,
      <?= json_encode($perf['trial2'] ?? 0) ?>,
      <?= json_encode($perf['trial3'] ?? 0) ?>
    ];
    const colors = rawData.map((v, i) => {
      if (i === 0) return '#3498db'; // Attendance = Blue
      if (v < 40) return '#e74c3c';
      if (v <= 60) return '#f39c12';
      return '#2ecc71';
    });

    new Chart(document.getElementById('performanceChart').getContext('2d'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{ label: 'Score', data: rawData, backgroundColor: colors }]
      },
      options: {
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { display: false } }
      }
    });
  </script>
</body>
</html>
