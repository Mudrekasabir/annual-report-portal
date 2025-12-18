<?php
// ============ FILE: admin/approve_reports.php ============
if (session_status() === PHP_SESSION_NONE) session_start();

// ============ SECURITY: Check Authentication ============
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

include('../includes/db_connect.php');
include('../includes/header.php');
include('../includes/functions.php');

$is_admin = $_SESSION['role'] == 'admin';
$is_coordinator = $_SESSION['role'] == 'coordinator' && is_valid_coordinator($conn, $_SESSION['user_id']);

if (!$is_admin && !$is_coordinator) {
  alert("Access Denied! You don't have permission to approve reports.", 'danger');
  echo "<div class='container mt-4'><a href='../login.php' class='btn btn-primary'>Go to Login</a></div>";
  include('../includes/footer.php');
  exit();
}

// Check specific permission for coordinator
if ($is_coordinator && !coordinator_has_permission($conn, $_SESSION['user_id'], 'approve_reports')) {
  alert("You don't have report approval permissions.", 'warning');
  include('../includes/footer.php');
  exit();
}

// ============ GENERATE CSRF TOKEN ============
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============ HANDLE APPROVAL/REJECTION ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
  
  // ============ CSRF VALIDATION ============
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    alert("Invalid security token. Please refresh and try again.", 'danger');
  } else {
    $report_id = intval($_POST['report_id']);
    $action = $_POST['action'];
    $comment = trim($_POST['comment'] ?? '');
    $admin_id = $_SESSION['user_id'];

    // Validate action
    if (!in_array($action, ['approve', 'reject'])) {
      alert("Invalid action.", 'danger');
    } else {
      $status = ($action == 'approve') ? 'approved' : 'rejected';
      
      // Validate rejection comment
      if ($action == 'reject' && empty($comment)) {
        alert("Please provide a reason for rejection.", 'warning');
      } else {
        
        // ============ START TRANSACTION ============
        $conn->begin_transaction();
        
        try {
          // Verify report exists and is pending
          $stmt_check = $conn->prepare("SELECT id, uploaded_by, title, status FROM annual_reports WHERE id = ?");
          $stmt_check->bind_param("i", $report_id);
          $stmt_check->execute();
          $report_data = $stmt_check->get_result()->fetch_assoc();
          $stmt_check->close();
          
          if (!$report_data) {
            throw new Exception("Report not found.");
          }
          
          if ($report_data['status'] !== 'pending') {
            throw new Exception("This report has already been reviewed.");
          }
          
          // Update main report status
          $stmt_update = $conn->prepare("UPDATE annual_reports SET status = ?, updated_at = NOW() WHERE id = ?");
          $stmt_update->bind_param("si", $status, $report_id);
          $stmt_update->execute();
          $stmt_update->close();

          // Log approval decision (check if approvals table exists)
          $table_check = $conn->query("SHOW TABLES LIKE 'approvals'");
          if ($table_check->num_rows > 0) {
            $stmt_approval = $conn->prepare("INSERT INTO approvals (report_id, admin_id, action, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_approval->bind_param("iiss", $report_id, $admin_id, $action, $comment);
            $stmt_approval->execute();
            $stmt_approval->close();
          }
          
          // Commit transaction
          $conn->commit();
          
          // ============ SEND NOTIFICATIONS (After Commit) ============
          if (function_exists('notify_user')) {
            $sanitized_title = htmlspecialchars($report_data['title'], ENT_QUOTES, 'UTF-8');
            $msg = ($status == 'approved') 
              ? "✅ Your report '$sanitized_title' has been approved!" 
              : "❌ Your report '$sanitized_title' was rejected. Reason: " . htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');
            notify_user($conn, $report_data['uploaded_by'], $msg);
          }

          // Write to system log
          if (function_exists('log_action')) {
            $reviewer_type = $is_coordinator ? 'Coordinator' : 'Admin';
            $reviewer_name = htmlspecialchars($_SESSION['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
            log_action($conn, $admin_id, "$reviewer_type $reviewer_name $status report ID $report_id");
          }

          alert("✅ Report successfully $status!", $status == 'approved' ? 'success' : 'warning');
          
          // Regenerate CSRF token
          $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
          
        } catch (Exception $e) {
          $conn->rollback();
          alert("Error: " . htmlspecialchars($e->getMessage()), 'danger');
        }
      }
    }
  }
}

// ============ FETCH PENDING REPORTS ============
$pending = $conn->query("
  SELECT a.*, u.name AS teacher_name, u.email AS teacher_email
  FROM annual_reports a
  JOIN users u ON a.uploaded_by = u.id
  WHERE a.status='pending'
  ORDER BY a.created_at ASC
");

// Stats
$pending_count = $pending->num_rows;
$approved_today = $conn->query("SELECT COUNT(*) AS c FROM annual_reports WHERE status='approved' AND DATE(updated_at) = CURDATE()")->fetch_assoc()['c'];
$total_approved = $conn->query("SELECT COUNT(*) AS c FROM annual_reports WHERE status='approved'")->fetch_assoc()['c'];
$total_rejected = $conn->query("SELECT COUNT(*) AS c FROM annual_reports WHERE status='rejected'")->fetch_assoc()['c'];
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background: #f4f6f9; }
  .stat-card { border-radius: 12px; border: none; transition: all 0.3s; }
  .stat-card:hover { transform: translateY(-3px); }
  .report-card { border-radius: 16px; border: none; overflow: hidden; transition: all 0.3s; }
  .report-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
  .report-header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 15px 20px; }
  .report-body { padding: 20px; }
  .teacher-avatar { width: 45px; height: 45px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #495057; }
  .action-btn { border-radius: 25px; padding: 8px 20px; font-weight: 500; }
  .coordinator-banner { background: linear-gradient(135deg, #f093fb, #f5576c); color: white; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; }
  .file-preview { background: #f8f9fa; border-radius: 8px; padding: 10px 15px; }
</style>

<div class="container-fluid mt-4 px-4">
  <!-- Header -->
  <div class="p-4 mb-4 rounded-4 text-white shadow" style="background: linear-gradient(135deg, #f7b733, #fc4a1a);">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h2 class="fw-bold mb-1">
          <i class="bi bi-clipboard-check"></i> Report Approvals
        </h2>
        <small class="opacity-75">Review and approve pending teacher reports</small>
      </div>
      <a href="dashboard.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
  </div>

  <!-- Coordinator Access Banner -->
  <?php if ($is_coordinator && function_exists('get_coordinator_time_left')): 
    $time_left = get_coordinator_time_left($conn, $_SESSION['user_id']);
  ?>
  <div class="coordinator-banner d-flex justify-content-between align-items-center">
    <div>
      <h6 class="mb-0"><i class="bi bi-shield-check"></i> Coordinator Mode Active</h6>
      <small>You are reviewing reports on behalf of admin</small>
    </div>
    <div class="text-end">
      <span class="badge bg-light text-dark"><i class="bi bi-clock"></i> <?= htmlspecialchars($time_left) ?> remaining</span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
      <div class="card stat-card p-3 shadow-sm text-center">
        <h3 class="fw-bold text-warning mb-0"><?= $pending_count ?></h3>
        <small class="text-muted">Pending Review</small>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card stat-card p-3 shadow-sm text-center">
        <h3 class="fw-bold text-success mb-0"><?= $approved_today ?></h3>
        <small class="text-muted">Approved Today</small>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card stat-card p-3 shadow-sm text-center">
        <h3 class="fw-bold text-primary mb-0"><?= $total_approved ?></h3>
        <small class="text-muted">Total Approved</small>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card stat-card p-3 shadow-sm text-center">
        <h3 class="fw-bold text-danger mb-0"><?= $total_rejected ?></h3>
        <small class="text-muted">Total Rejected</small>
      </div>
    </div>
  </div>

  <!-- Pending Reports -->
  <?php if ($pending_count > 0): ?>
  <div class="row g-4">
    <?php while($r = $pending->fetch_assoc()): ?>
    <div class="col-lg-6">
      <div class="card report-card shadow-sm">
        <div class="report-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($r['title']) ?></h5>
            <small class="opacity-75"><?= htmlspecialchars($r['academic_year']) ?></small>
          </div>
          <span class="badge bg-warning text-dark">Pending</span>
        </div>
        
        <div class="report-body">
          <!-- Teacher Info -->
          <div class="d-flex align-items-center mb-3">
            <div class="teacher-avatar me-3">
              <?= strtoupper(substr($r['teacher_name'], 0, 1)) ?>
            </div>
            <div>
              <h6 class="mb-0"><?= htmlspecialchars($r['teacher_name']) ?></h6>
              <small class="text-muted"><?= htmlspecialchars($r['teacher_email']) ?></small>
            </div>
          </div>

          <!-- Description -->
          <?php if (!empty($r['description'])): ?>
          <p class="text-muted small mb-3">
            <?= htmlspecialchars(substr($r['description'], 0, 150)) ?><?= strlen($r['description']) > 150 ? '...' : '' ?>
          </p>
          <?php endif; ?>

          <!-- File Attachment or Text-Only Notice -->
          <?php if (!empty($r['file_path'])): ?>
          <!-- File is attached -->
          <div class="file-preview d-flex justify-content-between align-items-center mb-3">
            <div>
              <?php
              $file_extension = strtolower(pathinfo($r['file_path'], PATHINFO_EXTENSION));
              $icon_class = 'bi-file-earmark';
              $icon_color = 'text-secondary';
              
              if ($file_extension === 'pdf') {
                $icon_class = 'bi-file-earmark-pdf';
                $icon_color = 'text-danger';
              } elseif (in_array($file_extension, ['doc', 'docx'])) {
                $icon_class = 'bi-file-earmark-word';
                $icon_color = 'text-primary';
              }
              
              // Build correct file path
              $file_path = $r['file_path'];
              if (strpos($file_path, 'uploads/') === 0) {
                $view_path = '../' . $file_path;
              } else {
                $view_path = '../uploads/reports/' . basename($file_path);
              }
              
              $file_exists = file_exists($view_path);
              ?>
              <i class="bi <?= $icon_class ?> <?= $icon_color ?>"></i>
              <span class="ms-2 small">Attached Document (.<?= htmlspecialchars($file_extension) ?>)</span>
            </div>
            <div>
              <?php if ($file_exists): ?>
                <a href="<?= htmlspecialchars($view_path) ?>" 
                   target="_blank" 
                   class="btn btn-sm btn-outline-success">
                  <i class="bi bi-download"></i> Download
                </a>
              <?php else: ?>
                <span class="btn btn-sm btn-outline-danger disabled" title="File not found">
                  <i class="bi bi-exclamation-triangle"></i> Missing
                </span>
              <?php endif; ?>
            </div>
          </div>
          <?php else: ?>
          <!-- No file attached - text-only report -->
          <div class="alert alert-info mb-3">
            <i class="bi bi-info-circle"></i> <strong>Text-Only Report</strong> - View content in report details
          </div>
          <?php endif; ?>
          
          <!-- View Full Report Button -->
          <div class="mb-3">
            <a href="view_report.php?id=<?= intval($r['id']) ?>" 
               target="_blank" 
               class="btn btn-sm btn-outline-primary w-100">
              <i class="bi bi-eye"></i> View Full Report Details
            </a>
          </div>

          <!-- Submission Date -->
          <div class="small text-muted mb-3">
            <i class="bi bi-calendar"></i> Submitted: <?= date('d M Y, h:i A', strtotime($r['created_at'])) ?>
          </div>

          <!-- Action Form -->
          <form method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
            
            <div class="mb-3">
              <textarea name="comment" class="form-control" rows="2" placeholder="Add remarks (required for rejection)..."></textarea>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" name="action" value="approve" class="btn btn-success action-btn flex-fill">
                <i class="bi bi-check-circle"></i> Approve
              </button>
              <button type="submit" name="action" value="reject" class="btn btn-danger action-btn flex-fill">
                <i class="bi bi-x-circle"></i> Reject
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
  
  <?php else: ?>
  <!-- No Pending Reports -->
  <div class="card shadow-sm border-0">
    <div class="card-body text-center py-5">
      <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
      <h4 class="mt-3 fw-bold">All Caught Up!</h4>
      <p class="text-muted mb-4">There are no pending reports to review at the moment.</p>
      <a href="dashboard.php" class="btn btn-primary">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
      </a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent Approvals -->
  <?php
  $recent = $conn->query("
    SELECT a.*, u.name AS teacher_name
    FROM annual_reports a
    JOIN users u ON a.uploaded_by = u.id
    WHERE a.status IN ('approved', 'rejected')
    ORDER BY a.updated_at DESC
    LIMIT 5
  ");
  
  if ($recent && $recent->num_rows > 0):
  ?>
  <div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-light">
      <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Decisions</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Report</th>
              <th>Teacher</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php while($rec = $recent->fetch_assoc()): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($rec['title']) ?></strong>
                <div class="small text-muted"><?= htmlspecialchars($rec['academic_year']) ?></div>
              </td>
              <td><?= htmlspecialchars($rec['teacher_name']) ?></td>
              <td>
                <?php if ($rec['status'] == 'approved'): ?>
                  <span class="badge bg-success">Approved</span>
                <?php else: ?>
                  <span class="badge bg-danger">Rejected</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted"><?= date('d M Y', strtotime($rec['updated_at'] ?? $rec['created_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function validateForm(form) {
  const action = form.querySelector('button[type="submit"]:focus')?.value || 
                 event.submitter?.value;
  const comment = form.querySelector('textarea[name="comment"]').value.trim();
  
  if (action === 'reject' && !comment) {
    alert('Please provide a reason for rejection.');
    return false;
  }
  
  const actionText = action === 'approve' ? 'approve' : 'reject';
  return confirm(`Are you sure you want to ${actionText} this report?`);
}

// Handle button click to track which button was pressed
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('button[type="submit"]').forEach(btn => {
    btn.addEventListener('click', function() {
      this.form.querySelector('input[name="action"]')?.remove();
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'action';
      input.value = this.value;
      this.form.appendChild(input);
    });
  });
});
</script>

<?php include('../includes/footer.php'); ?>