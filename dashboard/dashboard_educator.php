<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'educator') {
    header("Location: login.php");
    exit;
}
require __DIR__ . '/db_connect.php';

$userId = $_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Educator');

$stmt = $pdo->prepare("SELECT id FROM educators WHERE user_id = ?");
$stmt->execute([$userId]);
$educatorRow = $stmt->fetch();
$educatorId = $educatorRow['id'] ?? 0;

$clStmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE educator_id = ?");
$clStmt->execute([$educatorId]);
$classList = $clStmt->fetchAll(PDO::FETCH_ASSOC);
$classListText = $classList ? implode(', ', array_column($classList, 'class_name')) : '-';
$classIds = array_column($classList, 'id');

$batches = [];
if ($classIds) {
    $ph = implode(',', array_fill(0, count($classIds), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT batch FROM students WHERE class_id IN ($ph)");
    $stmt->execute($classIds);
    $batches = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$batchDisplay = implode(', ', $batches);

$totalStudents = 0;
if (!empty($classIds)) {
    $ph = implode(',', array_fill(0, count($classIds), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM students WHERE class_id IN ($ph)");
    $stmt->execute($classIds);
    $totalStudents = $stmt->fetchColumn();
}


$records = [];
if (!empty($classIds)) {
    $ph = implode(',', array_fill(0, count($classIds), '?'));
    $sql = "SELECT s.student_id, s.class_id,
                   p.internal_test1, p.internal_test2, p.internal_test3,
                   p.trial1, p.trial2, p.trial3, p.prediction_result
            FROM performance p
            JOIN students s ON p.student_id = s.student_id
            WHERE p.educator_id = ? AND s.class_id IN ($ph)";
    $params = array_merge([$educatorId], $classIds);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


$sumScore = 0;
$countRecords = 0;
$atRiskCount = 0;
$countsByClass = [];
foreach ($classIds as $cid) {
  $countsByClass[$cid] = ['Low'=>0,'Medium'=>0,'High'=>0];
}
foreach ($records as $r) {
  $avg = ($r['internal_test1'] + $r['internal_test2'] + $r['internal_test3'] + $r['trial1'] + $r['trial2'] + $r['trial3']) / 6;
  $sumScore += $avg;
  $countRecords++;
  if ($r['prediction_result'] === 'Low') $atRiskCount++;
  $cid = $r['class_id'];
  $cat = $r['prediction_result'];
  if (isset($countsByClass[$cid][$cat])) $countsByClass[$cid][$cat]++;
}
$averagePercent = $countRecords ? round($sumScore / $countRecords) : 0;

$chartStmt = $pdo->query("
  SELECT c.id, c.class_name, p.prediction_result, COUNT(*) AS cnt
  FROM performance p
  JOIN students  s ON p.student_id = s.student_id
  JOIN classes   c ON s.class_id    = c.id
  GROUP BY c.id, p.prediction_result
  ORDER BY c.class_name
");
$raw = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

$classNamesChart = [];
$chartCounts     = [];
foreach ($raw as $r) {
  $cid = $r['id'];
  if (!isset($classNamesChart[$cid])) {
    $classNamesChart[$cid] = $r['class_name'];
    $chartCounts[$cid]     = ['Low'=>0,'Medium'=>0,'High'=>0];
  }
  $chartCounts[$cid][$r['prediction_result']] = $r['cnt'];
}

$topHigh = ['class'=>'-','count'=>0];
$topLow  = ['class'=>'-','count'=>0];
foreach ($chartCounts as $cid => $countSet) {
  $high = $countSet['High'];
  $low  = $countSet['Low'];
  if ($high > $topHigh['count']) $topHigh = ['class'=>$classNamesChart[$cid],'count'=>$high];
  if ($low  > $topLow['count'])  $topLow  = ['class'=>$classNamesChart[$cid],'count'=>$low];
}

$selectedCat = $_GET['category'] ?? 'Low';
$studentDetails = [];
if ($records) {
  $ids = [];
  foreach ($records as $r) {
    if ($r['prediction_result'] === $selectedCat) $ids[$r['student_id']] = true;
  }
  $ids = array_keys($ids);
  if (!empty($ids)) {
    $ph = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT s.student_id, u.name, u.phone_number
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.student_id IN ($ph)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $studentDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
$classIdsChart = array_keys($classNamesChart);
$classLabels   = array_values($classNamesChart);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Educator Dashboard | EduPredict</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="css/style.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; font-family: 'Poppins', sans-serif; }
    .layout { display: flex; height: 100vh; }
    .container { padding: 2rem; overflow: auto; }
    .cards { display: flex; gap: 1rem; margin-bottom: 2rem; }
    .card { flex: 1; background: #fff; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,.1); text-align: center; }
    .card.danger { background: #e74c3c; color: #fff; }
    .chart, .summary, .cat { padding-bottom: 2rem; }
    .chart select { margin-bottom: 1rem; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; }
    .summary { display: flex; gap: 1rem; }
    .summary div { flex: 1; color: #fff; padding: 1rem; border-radius: 8px; text-align: center; }
    .summary .high { background: #2ecc71; }
    .summary .low { background: #e74c3c; }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
    th, td { padding: .75rem; border: 1px solid #ddd; text-align: left; } th { background: #f5f5f5; }
    select { margin-top: 1rem; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="sidebar-header">
        <a href="dashboard_admin.php">
          <img src="picture/logo.png" alt="EduPredict Logo" class="sidebar-logo"/>
        </a>
      </div>
      <nav>
        <ul>
          <li><a href="dashboard_educator.php" class="active">Dashboard</a></li>
          <li><a href="my_students.php">My Students</a></li>
          <li><a href="prediction_educator.php">Predict Result</a></li>
          <li><a href="report_educator.php">Student Report</a></li>
          <li><a href="change_password.php">Change Password</a></li>
          <li><a href="logout.php">Logout</a></li>
        </ul>
      </nav>
    </aside>

    <div class="main-content">
      <header class="topbar">
        <button id="sidebarToggle" class="toggle-btn">☰</button>
        <h3>Welcome, <?= htmlspecialchars($userName) ?></h3>
        <div class="user-info">
          <span><small>Educator</small></span>
          <img src="picture/avatar.jpg" alt="Avatar">
        </div>
      </header>

      <div class="container">
        <div class="cards">
          <div class="card"><h3>Class Assigned</h3><p><?= htmlspecialchars(implode(', ', array_column($classList, 'class_name'))) ?></p></div>
          <div class="card"><h3>Batch</h3><p><?= htmlspecialchars($batchDisplay) ?></p></div>
          <div class="card"><h3>Total Students</h3><p><?= $totalStudents ?></p></div>
          <div class="card"><h3>Average Score</h3><p><?= $averagePercent ?>%</p></div>
          <div class="card danger"><h3>At Risk</h3><p><?= $atRiskCount ?></p></div>
        </div>

        <div class="chart">
          <h2>Class Performance Distribution</h2>
          <label for="classFilter">Select Class:</label>
          <select id="classFilter">
            <option value="all">All Classes</option>
            <?php foreach ($classLabels as $idx => $label): ?>
              <option value="<?= $classIdsChart[$idx] ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
          <canvas id="classChart"></canvas>
        </div>

        <div class="summary">
          <div class="high"><strong>Top High</strong><br><?= htmlspecialchars($topHigh['class']) ?> (<?= $topHigh['count'] ?>)</div>
          <div class="low"><strong>Top Low</strong><br><?= htmlspecialchars($topLow['class']) ?> (<?= $topLow['count'] ?>)</div>
        </div>

        <div class="cat">
          <h2>Students by Category</h2>
          <form method="get">
            <select name="category" onchange="this.form.submit()">
              <?php foreach (["Low","Medium","High"] as $c): ?>
                <option value="<?= $c ?>" <?= $c === $selectedCat ? "selected" : "" ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <table>
            <thead><tr><th>#</th><th>Student ID</th><th>Name</th><th>Phone</th></tr></thead>
            <tbody>
              <?php foreach ($studentDetails as $i => $stu): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><?= htmlspecialchars($stu['student_id']) ?></td>
                  <td><?= htmlspecialchars($stu['name']) ?></td>
                  <td><?= htmlspecialchars($stu['phone_number'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<script>
  const allLabels   = <?= json_encode($classLabels) ?>;
  const allLow      = <?= json_encode(array_map(fn($cid)=>$chartCounts[$cid]['Low'] ?? 0,    $classIdsChart)) ?>;
  const allMed      = <?= json_encode(array_map(fn($cid)=>$chartCounts[$cid]['Medium'] ?? 0, $classIdsChart)) ?>;
  const allHigh     = <?= json_encode(array_map(fn($cid)=>$chartCounts[$cid]['High'] ?? 0,   $classIdsChart)) ?>;
  const classIdsArr = <?= json_encode($classIdsChart) ?>;

  const ctx = document.getElementById('classChart').getContext('2d');

  function computeMax(low, med, high) {
    return Math.max(...low, ...med, ...high);
  }

  let initialMax = computeMax(allLow, allMed, allHigh);

  const classChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: allLabels,
      datasets: [
        { label: 'Low',    data: allLow,   backgroundColor: '#e74c3c' },
        { label: 'Medium', data: allMed,   backgroundColor: '#f39c12' },
        { label: 'High',   data: allHigh,  backgroundColor: '#2ecc71' }
      ]
    },
    options: {
      responsive: true,
      animation: false,
      scales: {
        y: {
          min: 0,
          max: initialMax,
          ticks: { stepSize: 1 }
        }
      },
      plugins: { legend: { position: 'top' } }
    }
  });

  document.getElementById('classFilter').addEventListener('change', function(){
    const sel = this.value;
    let labels, low, med, high;

    if (sel === 'all') {
      labels = allLabels;
      low    = allLow;
      med    = allMed;
      high   = allHigh;
    } else {
      const cid = parseInt(sel, 10);
      const i   = classIdsArr.indexOf(cid);
      labels = [ allLabels[i] ];
      low    = [ allLow[i] ];
      med    = [ allMed[i] ];
      high   = [ allHigh[i] ];
    }

    classChart.data.labels = labels;
    classChart.data.datasets[0].data = low;
    classChart.data.datasets[1].data = med;
    classChart.data.datasets[2].data = high;
    classChart.options.scales.y.max = computeMax(low, med, high);
    classChart.update();
  });
</script>

</body>
</html>
