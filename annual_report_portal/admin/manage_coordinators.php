<?php
// ============ FILE: admin/manage_coordinators.php ============
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
  header("Location: ../login.php");
  exit();
}

include('../includes/db_connect.php');
include('../includes/functions.php');

// ========== HANDLE ALL POST REQUESTS BEFORE ANY OUTPUT ==========

// Handle Add/Update Coordinator
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_coordinator'])) {
  $user_id = intval($_POST['user_id']);
  $access_start = trim($_POST['access_start']);
  $access_end = trim($_POST['access_end']);
  $permissions = isset($_POST['permissions']) ? implode(',', $_POST['permissions']) : 'approve_reports';
  
  // Validate dates
  if (strtotime($access_end) <= strtotime($access_start)) {
    $_SESSION['flash_message'] = "❌ End date must be after start date!";
    header("Location: manage_coordinators.php");
    exit();
  }
  
  // Check if user exists and is a teacher using prepared statement
  $stmt_check = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND role IN ('teacher', 'coordinator') AND approval_status = 'approved'");
  $stmt_check->bind_param("i", $user_id);
  $stmt_check->execute();
  $user = $stmt_check->get_result()->fetch_assoc();
  $stmt_check->close();
  
  if ($user) {
    // Update user role to coordinator (if not already)
    if ($user['role'] !== 'coordinator') {
      $stmt_update_role = $conn->prepare("UPDATE users SET role = 'coordinator' WHERE id = ?");
      $stmt_update_role->bind_param("i", $user_id);
      $stmt_update_role->execute();
      $stmt_update_role->close();
    }
    
    // Check existing coordinator entry
    $stmt_exists = $conn->prepare("SELECT id FROM coordinators WHERE user_id = ?");
    $stmt_exists->bind_param("i", $user_id);
    $stmt_exists->execute();
    $existing = $stmt_exists->get_result()->fetch_assoc();
    $stmt_exists->close();
    
    if ($existing) {
      // Update existing coordinator
      $stmt = $conn->prepare("UPDATE coordinators SET access_start=?, access_end=?, permissions=?, status='active' WHERE user_id=?");
      $stmt->bind_param("sssi", $access_start, $access_end, $permissions, $user_id);
      $success_msg = "✅ Coordinator updated successfully!";
    } else {
      // Insert new coordinator
      $stmt = $conn->prepare("INSERT INTO coordinators (user_id, access_start, access_end, permissions, status, created_by) VALUES (?, ?, ?, ?, 'active', ?)");
      $stmt->bind_param("isssi", $user_id, $access_start, $access_end, $permissions, $_SESSION['user_id']);
      $success_msg = "✅ Coordinator assigned successfully!";
    }
    
    if ($stmt->execute()) {
      notify_user($conn, $user_id, "🎉 You have been assigned as Coordinator until " . date('d M Y', strtotime($access_end)));
      log_action($conn, $_SESSION['user_id'], "Assigned coordinator role to user ID $user_id until $access_end");
      $_SESSION['flash_message'] = $success_msg;
    } else {
      $_SESSION['flash_message'] = "❌ Error assigning coordinator: " . $stmt->error;
    }
    $stmt->close();
  } else {
    $_SESSION['flash_message'] = "⚠️ User not found or not an approved teacher!";
  }
  
  header("Location: manage_coordinators.php");
  exit();
}

// Handle Revoke Coordinator
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['revoke_coordinator'])) {
  $coord_id = intval($_POST['coord_id']);
  
  // Get coordinator user_id
  $stmt_get = $conn->prepare("SELECT user_id FROM coordinators WHERE id = ?");
  $stmt_get->bind_param("i", $coord_id);
  $stmt_get->execute();
  $coord = $stmt_get->get_result()->fetch_assoc();
  $stmt_get->close();
  
  if ($coord) {
    // Update coordinator status
    $stmt_revoke = $conn->prepare("UPDATE coordinators SET status = 'revoked' WHERE id = ?");
    $stmt_revoke->bind_param("i", $coord_id);
    $stmt_revoke->execute();
    $stmt_revoke->close();
    
    // Revert user role back to teacher
    $stmt_role = $conn->prepare("UPDATE users SET role = 'teacher' WHERE id = ?");
    $stmt_role->bind_param("i", $coord['user_id']);
    $stmt_role->execute();
    $stmt_role->close();
    
    notify_user($conn, $coord['user_id'], "⚠️ Your coordinator access has been revoked.");
    log_action($conn, $_SESSION['user_id'], "Revoked coordinator access for user ID {$coord['user_id']}");
    $_SESSION['flash_message'] = "⚠️ Coordinator access revoked.";
  } else {
    $_SESSION['flash_message'] = "❌ Coordinator not found!";
  }
  
  header("Location: manage_coordinators.php");
  exit();
}

// Handle Extend Access
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['extend_access'])) {
  $coord_id = intval($_POST['coord_id']);
  $new_end = trim($_POST['new_end_date']);
  
  // Get current coordinator info
  $stmt_get = $conn->prepare("SELECT user_id, access_start FROM coordinators WHERE id = ?");
  $stmt_get->bind_param("i", $coord_id);
  $stmt_get->execute();
  $coord = $stmt_get->get_result()->fetch_assoc();
  $stmt_get->close();
  
  if ($coord) {
    // Validate new end date
    if (strtotime($new_end) <= strtotime($coord['access_start'])) {
      $_SESSION['flash_message'] = "❌ New end date must be after start date!";
    } else {
      $stmt_extend = $conn->prepare("UPDATE coordinators SET access_end = ? WHERE id = ?");
      $stmt_extend->bind_param("si", $new_end, $coord_id);
      
      if ($stmt_extend->execute()) {
        notify_user($conn, $coord['user_id'], "✅ Your coordinator access extended until " . date('d M Y', strtotime($new_end)));
        log_action($conn, $_SESSION['user_id'], "Extended coordinator access for ID $coord_id until $new_end");
        $_SESSION['flash_message'] = "✅ Access extended successfully!";
      } else {
        $_SESSION['flash_message'] = "❌ Error extending access: " . $stmt_extend->error;
      }
      $stmt_extend->close();
    }
  } else {
    $_SESSION['flash_message'] = "❌ Coordinator not found!";
  }
  
  header("Location: manage_coordinators.php");
  exit();
}

// ========== NOW INCLUDE HEADER (AFTER ALL POST PROCESSING) ==========
include('../includes/header.php');

// Get eligible teachers (not already active coordinators)
$eligible = $conn->query("
  SELECT u.* FROM users u 
  LEFT JOIN coordinators c ON u.id = c.user_id AND c.status = 'active' 
  WHERE u.role IN ('teacher', 'coordinator') 
  AND u.approval_status = 'approved' 
  AND c.id IS NULL 
  ORDER BY u.name
");

// Get all coordinators
$coordinators = $conn->query("
  SELECT c.*, u.name, u.email, 
    CASE 
      WHEN c.status = 'revoked' THEN 'revoked'
      WHEN NOW() > c.access_end THEN 'expired'
      WHEN NOW() < c.access_start THEN 'scheduled'
      ELSE 'active'
    END as current_status
  FROM coordinators c 
  JOIN users u ON c.user_id = u.id 
  ORDER BY c.status ASC, c.access_end DESC
");

// Stats
$active_count = $conn->query("SELECT COUNT(*) AS c FROM coordinators WHERE status='active' AND NOW() BETWEEN access_start AND access_end")->fetch_assoc()['c'];
$expired_count = $conn->query("SELECT COUNT(*) AS c FROM coordinators WHERE status='active' AND NOW() > access_end")->fetch_assoc()['c'];
$total_count = $conn->query("SELECT COUNT(*) AS c FROM coordinators")->fetch_assoc()['c'];

// Display flash message
if (isset($_SESSION['flash_message'])) {
  echo '<div class="container mt-3"><div class="alert alert-info alert-dismissible fade show" role="alert">';
  echo htmlspecialchars($_SESSION['flash_message']);
  echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>';
  unset($_SESSION['flash_message']);
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background: #f4f6f9; }
  .stat-card { border-radius: 12px; border: none; }
  .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; }
  .status-active { background: #d1fae5; color: #065f46; }
  .status-expired { background: #fee2e2; color: #991b1b; }
  .status-revoked { background: #e5e7eb; color: #374151; }
  .status-scheduled { background: #dbeafe; color: #1e40af; }
  .permission-badge { font-size: 0.7rem; margin-right: 4px; }
</style>

<div class="container mt-4">
  <!-- Header -->
  <div class="p-4 mb-4 rounded-4 text-white shadow" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-person-badge"></i> Manage Coordinators</h2>
        <small class="opacity-75">Assign temporary report approval access</small>
      </div>
      <a href="dashboard.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card stat-card p-3 shadow-sm text-center">
        <h3 class="fw-bold text-success"><?= $active_count ?></h3>
        <small class="text-muted">Active Coordinators</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card p-3 shadow-sm text-center">
        <h3 class="fw-bold text-danger"><?= $expired_count ?></h3>
        <small class="text-muted">Expired Access</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card p-3 shadow-sm text-center">
        <h3 class="fw-bold text-secondary"><?= $total_count ?></h3>
        <small class="text-muted">Total Assigned</small>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Add Coordinator Form -->
    <div class="col-lg-4">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Assign Coordinator</h5>
        </div>
        <div class="card-body">
          <form method="POST">
            <div class="mb-3">
              <label class="form-label fw-semibold">Select Teacher</label>
              <select name="user_id" class="form-select" required>
                <option value="">-- Select Teacher --</option>
                <?php 
                if ($eligible && $eligible->num_rows > 0):
                  while($t = $eligible->fetch_assoc()): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['email']) ?>)</option>
                <?php endwhile; 
                endif; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Access Start</label>
              <input type="datetime-local" name="access_start" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Access End</label>
              <input type="datetime-local" name="access_end" class="form-control" required value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Permissions</label>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="approve_reports" id="perm1" checked>
                <label class="form-check-label" for="perm1">Approve Reports</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="view_analytics" id="perm2">
                <label class="form-check-label" for="perm2">View Analytics</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="view_users" id="perm3">
                <label class="form-check-label" for="perm3">View Users</label>
              </div>
            </div>
            <button type="submit" name="add_coordinator" class="btn btn-primary w-100">
              <i class="bi bi-person-plus"></i> Assign Coordinator
            </button>
          </form>
        </div>
      </div>

      <!-- Info Card -->
      <div class="card shadow-sm border-0 mt-3">
        <div class="card-body bg-light">
          <h6 class="fw-bold text-primary"><i class="bi bi-info-circle"></i> About Coordinators</h6>
          <small class="text-muted">
            Coordinators can approve reports on behalf of admin for a limited time. 
            Access automatically expires after the end date. You can extend or revoke access anytime.
          </small>
        </div>
      </div>
    </div>

    <!-- Coordinators List -->
    <div class="col-lg-8">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-list-ul"></i> All Coordinators</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Name</th>
                  <th>Access Period</th>
                  <th>Permissions</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($coordinators && $coordinators->num_rows > 0): ?>
                  <?php while($c = $coordinators->fetch_assoc()): ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($c['name']) ?></strong>
                      <div class="small text-muted"><?= htmlspecialchars($c['email']) ?></div>
                    </td>
                    <td>
                      <small>
                        <?= date('d M Y H:i', strtotime($c['access_start'])) ?><br>
                        <span class="text-muted">to</span> <?= date('d M Y H:i', strtotime($c['access_end'])) ?>
                      </small>
                    </td>
                    <td>
                      <?php 
                      $perms = explode(',', $c['permissions']);
                      foreach($perms as $p): ?>
                        <span class="badge bg-info permission-badge"><?= htmlspecialchars(str_replace('_', ' ', $p)) ?></span>
                      <?php endforeach; ?>
                    </td>
                    <td>
                      <span class="status-badge status-<?= $c['current_status'] ?>">
                        <?= ucfirst($c['current_status']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($c['current_status'] == 'active' || $c['current_status'] == 'scheduled'): ?>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="coord_id" value="<?= $c['id'] ?>">
                        <button type="submit" name="revoke_coordinator" class="btn btn-outline-danger btn-sm" onclick="return confirm('Revoke access for <?= htmlspecialchars($c['name']) ?>?')">
                          <i class="bi bi-x-circle"></i>
                        </button>
                      </form>
                      <?php endif; ?>
                      
                      <?php if ($c['current_status'] != 'revoked'): ?>
                      <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#extendModal<?= $c['id'] ?>">
                        <i class="bi bi-calendar-plus"></i>
                      </button>
                      
                      <!-- Extend Modal -->
                      <div class="modal fade" id="extendModal<?= $c['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                          <div class="modal-content">
                            <form method="POST">
                              <div class="modal-header">
                                <h6 class="modal-title">Extend Access</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                              </div>
                              <div class="modal-body">
                                <input type="hidden" name="coord_id" value="<?= $c['id'] ?>">
                                <label class="form-label">New End Date</label>
                                <input type="datetime-local" name="new_end_date" class="form-control" required value="<?= date('Y-m-d\TH:i', strtotime($c['access_end'] . ' +7 days')) ?>">
                              </div>
                              <div class="modal-footer">
                                <button type="submit" name="extend_access" class="btn btn-primary btn-sm">Extend</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="5" class="text-center text-muted py-4">No coordinators assigned yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>