<?php
// ============ FILE: admin/dashboard.php ============
include('../includes/db_connect.php');
include('../includes/header.php');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coordinator'])) {
  header("Location: ../login.php");
  exit();
}

$is_admin = $_SESSION['role'] == 'admin';
$is_coordinator = $_SESSION['role'] == 'coordinator';

// Check coordinator access validity
if ($is_coordinator) {
  $coord_check = $conn->query("SELECT * FROM coordinators WHERE user_id = {$_SESSION['user_id']} AND status = 'active' AND NOW() BETWEEN access_start AND access_end");
  if ($coord_check->num_rows == 0) {
    $_SESSION['message'] = "Your coordinator access has expired.";
    header("Location: ../login.php");
    exit();
  }
}

// ===== Quick Stats =====
$total_users = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$total_teachers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='teacher'")->fetch_assoc()['c'];
$total_students = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='student'")->fetch_assoc()['c'];
$pending_teachers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='teacher' AND approval_status='pending'")->fetch_assoc()['c'];
$total_coordinators = $conn->query("SELECT COUNT(*) AS c FROM coordinators WHERE status='active'")->fetch_assoc()['c'];

$total_reports = $conn->query("SELECT COUNT(*) AS c FROM annual_reports")->fetch_assoc()['c'];
$approved_reports = $conn->query("SELECT COUNT(*) AS c FROM annual_reports WHERE status='approved'")->fetch_assoc()['c'];
$pending_reports = $conn->query("SELECT COUNT(*) AS c FROM annual_reports WHERE status='pending'")->fetch_assoc()['c'];
$rejected_reports = $conn->query("SELECT COUNT(*) AS c FROM annual_reports WHERE status='rejected'")->fetch_assoc()['c'];

// Classes with assignments
$total_classes = $conn->query("SELECT COUNT(DISTINCT class_name) AS c FROM classes")->fetch_assoc()['c'];
$unassigned_students = $conn->query("SELECT COUNT(*) AS c FROM users u LEFT JOIN student_assignments sa ON u.id = sa.student_id WHERE u.role = 'student' AND sa.id IS NULL")->fetch_assoc()['c'];

// ===== Recent Activity =====
$activity = $conn->query("SELECT al.*, u.name, u.role FROM activity_log al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 8");

// ===== Notifications =====
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = {$_SESSION['user_id']} ORDER BY created_at DESC LIMIT 5");

// ===== Active Coordinators =====
$active_coordinators = $conn->query("SELECT c.*, u.name, u.email FROM coordinators c JOIN users u ON c.user_id = u.id WHERE c.status = 'active' AND NOW() BETWEEN c.access_start AND c.access_end ORDER BY c.access_end ASC LIMIT 5");
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background-color: #f4f6f9; }
  .stat-card { border: none; border-radius: 16px; transition: all .3s ease; overflow: hidden; }
  .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
  .stat-card .icon { font-size: 2.5rem; opacity: 0.3; position: absolute; right: 15px; top: 15px; }
  .card-gradient-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
  .card-gradient-2 { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
  .card-gradient-3 { background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); }
  .card-gradient-4 { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
  .card-gradient-5 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
  .card-gradient-6 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
  .nav-shortcut { border-radius: 12px; transition: all .2s; }
  .nav-shortcut:hover { transform: scale(1.02); }
  .audit-item { border-left: 4px solid #667eea; padding-left: 12px; margin-bottom: 8px; }
  .coordinator-badge { animation: pulse 2s infinite; }
  @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
  .time-remaining { font-size: 0.75rem; color: #6c757d; }
</style>

<div class="container-fluid mt-4 px-4">
  <!-- Header -->
  <div class="p-4 mb-4 rounded-4 text-white shadow" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h2 class="fw-bold mb-1">
          <i class="bi bi-speedometer2"></i> 
          <?= $is_coordinator ? 'Coordinator' : 'Admin' ?> Dashboard
        </h2>
        <small class="opacity-75">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></small>
      </div>
      <div>
        <?php if ($is_coordinator): ?>
          <span class="badge bg-warning text-dark coordinator-badge me-2">
            <i class="bi bi-clock"></i> Limited Access
          </span>
        <?php endif; ?>
        <a href="../logout.php" class="btn btn-light btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </div>
  </div>

  <!-- Quick Stats Row 1 -->
  <div class="row g-3 mb-4">
    <div class="col-lg-2 col-md-4 col-6">
      <div class="card stat-card p-3 card-gradient-1 text-white position-relative">
        <i class="bi bi-people icon"></i>
        <h3 class="fw-bold mb-0"><?= $total_users ?></h3>
        <small>Total Users</small>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
      <div class="card stat-card p-3 card-gradient-2 text-white position-relative">
        <i class="bi bi-person-workspace icon"></i>
        <h3 class="fw-bold mb-0"><?= $total_teachers ?></h3>
        <small>Teachers</small>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
      <div class="card stat-card p-3 card-gradient-5 text-white position-relative">
        <i class="bi bi-mortarboard icon"></i>
        <h3 class="fw-bold mb-0"><?= $total_students ?></h3>
        <small>Students</small>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
      <div class="card stat-card p-3 card-gradient-6 text-white position-relative">
        <i class="bi bi-check-circle icon"></i>
        <h3 class="fw-bold mb-0"><?= $approved_reports ?></h3>
        <small>Approved Reports</small>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
      <div class="card stat-card p-3 card-gradient-3 text-white position-relative">
        <i class="bi bi-hourglass-split icon"></i>
        <h3 class="fw-bold mb-0"><?= $pending_reports ?></h3>
        <small>Pending Reports</small>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6">
      <div class="card stat-card p-3 card-gradient-4 text-white position-relative">
        <i class="bi bi-person-plus icon"></i>
        <h3 class="fw-bold mb-0"><?= $pending_teachers ?></h3>
        <small>Pending Teachers</small>
      </div>
    </div>
  </div>

  <!-- Navigation Shortcuts -->
  <div class="row g-2 mb-4">
    <?php if ($is_admin): ?>
    <div class="col-auto">
      <a href="manage_users.php" class="btn btn-outline-primary nav-shortcut">
        <i class="bi bi-people"></i> Manage Users
      </a>
    </div>
    <div class="col-auto">
      <a href="manage_coordinators.php" class="btn btn-outline-info nav-shortcut">
        <i class="bi bi-person-badge"></i> Manage Coordinators
      </a>
    </div>
    <div class="col-auto">
      <a href="assign_students.php" class="btn btn-outline-success nav-shortcut">
        <i class="bi bi-diagram-3"></i> Assign Students
      </a>
    </div>
    <?php endif; ?>
    <div class="col-auto">
      <a href="approve_reports.php" class="btn btn-outline-warning nav-shortcut">
        <i class="bi bi-clipboard-check"></i> Approve Reports
      </a>
    </div>
    <?php if ($is_admin): ?>
    <div class="col-auto">
      <a href="analytics.php" class="btn btn-outline-secondary nav-shortcut">
        <i class="bi bi-graph-up"></i> Analytics
      </a>
    </div>
    <div class="col-auto">
      <a href="audit_log.php" class="btn btn-outline-dark nav-shortcut">
        <i class="bi bi-journal-text"></i> Audit Log
      </a>
    </div>
    <?php endif; ?>
  </div>

  <div class="row g-4">
    <!-- Left Column -->
    <div class="col-lg-8">
      <!-- Active Coordinators (Admin Only) -->
      <?php if ($is_admin && $active_coordinators->num_rows > 0): ?>
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-info text-white">
          <h5 class="mb-0"><i class="bi bi-person-badge"></i> Active Coordinators</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr><th>Name</th><th>Email</th><th>Access Ends</th><th>Time Left</th></tr>
              </thead>
              <tbody>
                <?php while($c = $active_coordinators->fetch_assoc()): 
                  $end = new DateTime($c['access_end']);
                  $now = new DateTime();
                  $diff = $now->diff($end);
                  $time_left = $diff->days . 'd ' . $diff->h . 'h';
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                  <td><?= htmlspecialchars($c['email']) ?></td>
                  <td><?= date('d M Y, H:i', strtotime($c['access_end'])) ?></td>
                  <td><span class="badge bg-warning text-dark"><?= $time_left ?></span></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Charts -->
      <div class="row g-3 mb-4">
        <div class="col-md-7">
          <div class="card shadow-sm p-3 h-100">
            <h6 class="fw-bold"><i class="bi bi-bar-chart-line"></i> User Distribution</h6>
            <canvas id="userChart"></canvas>
          </div>
        </div>
        <div class="col-md-5">
          <div class="card shadow-sm p-3 h-100">
            <h6 class="fw-bold"><i class="bi bi-pie-chart"></i> Report Status</h6>
            <canvas id="reportChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="card shadow-sm border-0">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-activity"></i> Recent Activity</h5>
        </div>
        <div class="card-body">
          <?php if ($activity->num_rows > 0): ?>
            <?php while($a = $activity->fetch_assoc()): ?>
            <div class="audit-item">
              <strong><?= htmlspecialchars($a['name'] ?? 'System') ?></strong>
              <span class="badge bg-secondary"><?= $a['role'] ?? 'N/A' ?></span>
              <span class="text-muted">- <?= htmlspecialchars($a['activity']) ?></span>
              <span class="float-end text-muted small"><?= date('d M, H:i', strtotime($a['created_at'])) ?></span>
            </div>
            <?php endwhile; ?>
          <?php else: ?>
            <p class="text-muted text-center mb-0">No recent activity.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Right Column -->
    <div class="col-lg-4">
      <!-- Notifications -->
      <?php if ($notifications->num_rows > 0): ?>
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="bi bi-bell"></i> Notifications</h5>
        </div>
        <ul class="list-group list-group-flush">
          <?php while ($n = $notifications->fetch_assoc()): ?>
          <li class="list-group-item small">
            <i class="bi bi-dot text-primary"></i> <?= htmlspecialchars($n['message']) ?>
            <div class="time-remaining"><?= date('d M, h:i A', strtotime($n['created_at'])) ?></div>
          </li>
          <?php endwhile; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Quick Stats Card -->
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0"><i class="bi bi-clipboard-data"></i> Quick Overview</h5>
        </div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between">
            <span>Active Coordinators</span>
            <span class="badge bg-info"><?= $total_coordinators ?></span>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span>Total Classes</span>
            <span class="badge bg-secondary"><?= $total_classes ?></span>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span>Unassigned Students</span>
            <span class="badge bg-warning text-dark"><?= $unassigned_students ?></span>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span>Rejected Reports</span>
            <span class="badge bg-danger"><?= $rejected_reports ?></span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('reportChart'), {
  type: 'doughnut',
  data: {
    labels: ['Approved', 'Pending', 'Rejected'],
    datasets: [{
      data: [<?= $approved_reports ?>, <?= $pending_reports ?>, <?= $rejected_reports ?>],
      backgroundColor: ['#38ef7d', '#f7b733', '#eb3349']
    }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('userChart'), {
  type: 'bar',
  data: {
    labels: ['Teachers', 'Students', 'Coordinators', 'Admins'],
    datasets: [{
      label: 'Users',
      data: [<?= $total_teachers ?>, <?= $total_students ?>, <?= $total_coordinators ?>, 1],
      backgroundColor: ['#667eea', '#4facfe', '#11998e', '#764ba2']
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>

<?php include('../includes/footer.php'); ?>