<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db_connect.php';

//  statistik
$totalStudents  = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalEducators = $pdo->query("SELECT COUNT(*) FROM educators")->fetchColumn();
$totalClasses   = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

// Data untuk chart
$classData = $pdo->query("
    SELECT c.class_name, COUNT(s.id) AS total
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id
    GROUP BY c.id
")->fetchAll(PDO::FETCH_ASSOC);

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Dashboard Admin</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="css/style.css" />
</head>

<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="sidebar-header">
    <a href="dashboard_admin.php">
      <img src="picture/logo.png" alt="EduPredict Logo" class="sidebar-logo"/></a>
  </div>
      <nav>
        <ul>
          <li><a href="dashboard_admin.php"class="active">Dashboard</a></li>
          <li><a href="student_list.php">View Student</a></li>
          <li><a href="manage_students.php">Manage Student</a></li>
          <li><a href="manage_educators.php">Manage Educator</a></li>
          <li><a href="manage_classes.php">Manage Class</a></li>
          <li><a href="view_messages.php">View Message</a></li>
          <li><a href="logout.php">Logout</a></li>
        </ul>
      </nav>
    </aside>

    <div class="main-content">
       <header class="topbar">
        <button id="sidebarToggle" class="toggle-btn">☰</button>
        <h3>Welcome <?= $userName ?></h3>
        <div class="user-info">
          <span><small>Admin</small></span>
          <img src="picture/avatar.jpg" alt="Avatar">
        </div>
      </header>

      <div class="container">
        <div class="cards">
          <div class="card">
            <span class="label">Total Students</span>
            <span class="value"><?= $totalStudents ?></span>
          </div>
          <div class="card">
            <span class="label">Total Educators</span>
            <span class="value"><?= $totalEducators ?></span>
          </div>
          <div class="card">
            <span class="label">Total Classes</span>
            <span class="value"><?= $totalClasses ?></span>
          </div>
        </div>

        <div class="chart-panel">
          <h2>Total Students by Class</h2>
          <canvas id="classChart"></canvas>
        </div>

    <!-- Educator List Below Chart -->
    <h3 style="margin: 2rem 0 1rem;">Educators</h3>
    <div class="cards" style="flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
      <?php
      $pdo = new PDO("mysql:host=localhost;dbname=edupredict", "root", "");
      $stmt = $pdo->query("
          SELECT u.name, e.phone_number, c.class_name
          FROM educators e
          JOIN users u ON e.user_id = u.id
          LEFT JOIN classes c ON e.id = c.educator_id
          ORDER BY u.name
      ");
      $educators = $stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach ($educators as $edu): ?>
        <div class="card" style="flex: 1 1 200px; min-width: 200px; padding: 1rem;">
          <strong><?= htmlspecialchars($edu['name']) ?></strong><br>
          📞 <?= htmlspecialchars($edu['phone_number']) ?><br>
          🏫 <?= htmlspecialchars($edu['class_name']) ?>
        </div>
      <?php endforeach; ?>
    </div>

      </div>
    </div>
  </div>

  <script>
    // Toggle sidebar
    const layout = document.querySelector('.layout');
    const sidebar = document.querySelector('.sidebar');
    document.getElementById('sidebarToggle').addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      layout.classList.toggle('collapsed');
    });

    // Render Chart.js
    const ctx = document.getElementById('classChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($classData,'class_name')) ?>,
        datasets: [{
          label: 'Students',
          data: <?= json_encode(array_column($classData,'total')) ?>,
        }]
      },
      options: { scales:{ y:{ beginAtZero:true } } }
    });
  </script>
</body>
</html>