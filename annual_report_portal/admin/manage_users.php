<?php
include('../includes/db_connect.php');
include('../includes/header.php');
include('../includes/functions.php');
if ($_SESSION['role'] != 'admin') {
  alert("Access Denied!", 'danger');
  exit();
}

// ===== Handle Admin Actions =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
  $user_id = intval($_POST['user_id']);

  if (isset($_POST['approve'])) {
    $conn->query("UPDATE users SET approval_status='approved' WHERE id=$user_id");
    notify_user($conn, $user_id, "✅ Your account has been approved by the Admin.");
    log_action($conn, $_SESSION['user_id'], "Approved user ID $user_id");
    alert("✅ User approved successfully!", 'success');
  } elseif (isset($_POST['reject'])) {
    $conn->query("UPDATE users SET approval_status='rejected' WHERE id=$user_id");
    notify_user($conn, $user_id, "❌ Your registration was rejected by the Admin.");
    log_action($conn, $_SESSION['user_id'], "Rejected user ID $user_id");
    alert("⚠️ User rejected.", 'warning');
  } elseif (isset($_POST['toggle'])) {
    $status = ($_POST['current_status'] == 'active') ? 'disabled' : 'active';
    $conn->query("UPDATE users SET account_status='$status' WHERE id=$user_id");
    log_action($conn, $_SESSION['user_id'], "Toggled account for user ID $user_id ($status)");
    alert("🔁 Account status updated!", 'info');
  } elseif (isset($_POST['delete'])) {
    $conn->query("DELETE FROM users WHERE id=$user_id");
    log_action($conn, $_SESSION['user_id'], "Deleted user ID $user_id");
    alert("🗑️ User deleted successfully!", 'danger');
  }
}

// ===== Filters & Search =====
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$approval_filter = $_GET['approval'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = [];
if ($search != '') $where[] = "(name LIKE '%$search%' OR email LIKE '%$search%')";
if ($role_filter != '') $where[] = "role = '$role_filter'";
if ($approval_filter != '') $where[] = "approval_status = '$approval_filter'";
if ($status_filter != '') $where[] = "account_status = '$status_filter'";

$where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// ===== Pagination =====
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$total_query = $conn->query("SELECT COUNT(*) AS total FROM users $where_sql");
$total_rows = $total_query->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$users = $conn->query("SELECT * FROM users $where_sql ORDER BY role, name ASC LIMIT $limit OFFSET $offset");

// ===== Stats =====
$total_users = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$teachers_pending = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='teacher' AND approval_status='pending'")->fetch_assoc()['c'];
$teachers_approved = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='teacher' AND approval_status='approved'")->fetch_assoc()['c'];
$students = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='student'")->fetch_assoc()['c'];
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
  .stat-card { border: none; border-radius: 12px; transition: all .3s; }
  .stat-card:hover { transform: translateY(-3px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
  .search-bar input, .search-bar select { border-radius: 10px; }
  .badge-role { text-transform: capitalize; }
  .pagination a { text-decoration: none; }
  .page-link.active { background: #0d6efd !important; color: #fff !important; border: none; }
</style>

<div class="container mt-4">

  <!-- Header -->
  <div class="p-4 mb-4 rounded-3 text-white shadow-sm" style="background: linear-gradient(135deg, #0d6efd, #4f8ef7);">
    <div class="d-flex justify-content-between align-items-center">
      <h2 class="fw-bold mb-0"><i class="bi bi-people-fill"></i> Manage Users</h2>
      <a href="dashboard.php" class="btn btn-light btn-sm"><i class="bi bi-speedometer2"></i> Back</a>
    </div>
  </div>

  <!-- Quick Stats -->
  <div class="row text-center mb-4">
    <div class="col-md-3">
      <div class="card stat-card shadow-sm p-3">
        <h4 class="fw-bold text-primary"><?= $total_users ?></h4>
        <p class="text-muted mb-0">Total Users</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card shadow-sm p-3">
        <h4 class="fw-bold text-success"><?= $teachers_approved ?></h4>
        <p class="text-muted mb-0">Approved Teachers</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card shadow-sm p-3">
        <h4 class="fw-bold text-warning"><?= $teachers_pending ?></h4>
        <p class="text-muted mb-0">Pending Teachers</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card stat-card shadow-sm p-3">
        <h4 class="fw-bold text-secondary"><?= $students ?></h4>
        <p class="text-muted mb-0">Students</p>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form class="row g-2 mb-3 search-bar" method="GET">
    <div class="col-md-3">
      <input type="text" name="search" class="form-control" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-2">
      <select name="role" class="form-select">
        <option value="">All Roles</option>
        <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Admin</option>
        <option value="teacher" <?= $role_filter == 'teacher' ? 'selected' : '' ?>>Teacher</option>
        <option value="student" <?= $role_filter == 'student' ? 'selected' : '' ?>>Student</option>
      </select>
    </div>
    <div class="col-md-2">
      <select name="approval" class="form-select">
        <option value="">All Approvals</option>
        <option value="approved" <?= $approval_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
        <option value="pending" <?= $approval_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="rejected" <?= $approval_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select">
        <option value="">All Status</option>
        <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
        <option value="disabled" <?= $status_filter == 'disabled' ? 'selected' : '' ?>>Disabled</option>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
    </div>
    <div class="col-md-1">
      <a href="manage_users.php" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-repeat"></i></a>
    </div>
  </form>

  <!-- Users Table -->
  <div class="card shadow-sm border-0 p-3">
    <div class="table-responsive">
      <table class="table align-middle table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Approval</th>
            <th>Status</th>
            <th>Created</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($users->num_rows > 0): $i = $offset + 1; ?>
            <?php while ($u = $users->fetch_assoc()): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge bg-info text-dark badge-role"><?= htmlspecialchars($u['role']) ?></span></td>
                <td>
                  <?php if ($u['role'] == 'teacher'): ?>
                    <?php if ($u['approval_status'] == 'approved'): ?>
                      <span class="badge bg-success">Approved</span>
                    <?php elseif ($u['approval_status'] == 'pending'): ?>
                      <span class="badge bg-warning text-dark">Pending</span>
                    <?php else: ?>
                      <span class="badge bg-danger">Rejected</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge bg-secondary">N/A</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= ($u['account_status'] == 'active') ? 'bg-success' : 'bg-danger' ?>">
                    <?= ucfirst($u['account_status'] ?? 'active') ?>
                  </span>
                </td>
                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <?php if ($u['role'] != 'admin'): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="current_status" value="<?= $u['account_status'] ?>">

                      <?php if ($u['role'] == 'teacher' && $u['approval_status'] == 'pending'): ?>
                        <button name="approve" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i></button>
                        <button name="reject" class="btn btn-danger btn-sm"><i class="bi bi-x-circle"></i></button>
                      <?php endif; ?>

                      <button name="toggle" class="btn btn-outline-secondary btn-sm" title="Toggle account status">
                        <i class="bi bi-power"></i>
                      </button>

                      <button name="delete" class="btn btn-outline-danger btn-sm" title="Delete user" onclick="return confirm('Delete this user?');">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php else: ?>
                    <em class="text-muted">Admin</em>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="text-center text-muted py-3">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination justify-content-center">
        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
          <li class="page-item <?= $page == $p ? 'active' : '' ?>">
            <a class="page-link <?= $page == $p ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
              <?= $p ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>
