<?php
session_start();

// ============ SECURITY: Check Authentication ============
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'coordinator'])) {
    header("Location: ../login.php");
    exit();
}

include('../includes/db_connect.php');
include('../includes/functions.php');

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// ============ Check if Teacher/Coordinator has Active Coordinator Permissions ============
$is_coordinator = false;
$can_approve_reports = false;
$coordinator_permissions = [];

// Check if this user is assigned as a coordinator with active status
$stmt_check_coord = $conn->prepare("
    SELECT id, permissions, access_start, access_end, status 
    FROM coordinators 
    WHERE user_id = ? 
    AND status = 'active'
    AND NOW() BETWEEN access_start AND access_end
    LIMIT 1
");

if ($stmt_check_coord) {
    $stmt_check_coord->bind_param("i", $user_id);
    $stmt_check_coord->execute();
    $result_check = $stmt_check_coord->get_result();
    
    if ($result_check->num_rows > 0) {
        $coord_data = $result_check->fetch_assoc();
        $is_coordinator = true;
        
        // Parse permissions
        $coordinator_permissions = explode(',', $coord_data['permissions']);
        $can_approve_reports = in_array('approve_reports', $coordinator_permissions);
        
        // Update session role if needed
        if ($_SESSION['role'] !== 'coordinator') {
            $_SESSION['role'] = 'coordinator';
        }
    } else {
        // If no active coordinator assignment, ensure role is teacher
        if ($_SESSION['role'] === 'coordinator') {
            $_SESSION['role'] = 'teacher';
        }
    }
    $stmt_check_coord->close();
}

// ============ SECURITY: Helper Function with Prepared Statements ============
function getCount($conn, $query, $types, $params) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare Error: " . $conn->error);
        return 0;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute Error: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $stmt->close();
    
    return $row[0] ?? 0;
}

// ============ Get Statistics (FIXED: No SQL Injection) ============
$stats = [
    'total'      => getCount($conn, "SELECT COUNT(*) FROM annual_reports WHERE uploaded_by = ?", "i", [$user_id]),
    'approved'   => getCount($conn, "SELECT COUNT(*) FROM annual_reports WHERE uploaded_by = ? AND status = 'approved'", "i", [$user_id]),
    'pending'    => getCount($conn, "SELECT COUNT(*) FROM annual_reports WHERE uploaded_by = ? AND status = 'pending'", "i", [$user_id]),
    'draft'      => getCount($conn, "SELECT COUNT(*) FROM annual_reports WHERE uploaded_by = ? AND status = 'draft'", "i", [$user_id]),
    'rejected'   => getCount($conn, "SELECT COUNT(*) FROM annual_reports WHERE uploaded_by = ? AND status = 'rejected'", "i", [$user_id])
];

// ============ Get Pending Reports Count (for coordinators) ============
if ($is_coordinator && $can_approve_reports) {
    $stats['pending_to_review'] = getCount($conn, "SELECT COUNT(*) FROM annual_reports WHERE status = 'pending'", "", []);
}

// ============ Get Filter Parameters ============
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

// ============ SECURITY: Validate Status Filter ============
$allowed_statuses = ['approved', 'pending', 'rejected', 'draft'];
if (!empty($status) && !in_array($status, $allowed_statuses)) {
    $status = '';
}

// ============ Build Query with Prepared Statements ============
$where_conditions = ["a.uploaded_by = ?"];
$params = [$user_id];
$types = "i";

// Add search condition
if (!empty($search)) {
    $where_conditions[] = "a.title LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= "s";
}

// Add status filter
if (!empty($status)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status;
    $types .= "s";
}

$where = "WHERE " . implode(" AND ", $where_conditions);

$query = "
    SELECT a.id, 
           a.title, 
           a.academic_year, 
           a.status, 
           a.created_at,
           IFNULL(ap.comment, '') AS admin_comment
    FROM annual_reports a
    LEFT JOIN approvals ap ON a.id = ap.report_id
    $where
    ORDER BY a.created_at DESC
";

$stmt_reports = $conn->prepare($query);
if (!$stmt_reports) {
    die("Prepare Error: " . $conn->error);
}

$stmt_reports->bind_param($types, ...$params);

if (!$stmt_reports->execute()) {
    die("Execute Error: " . $stmt_reports->error);
}

$reports = $stmt_reports->get_result();

// Load header
include('../includes/header.php');
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

<style>
    body {
        background: #f5f7fa;
        font-family: 'Inter', sans-serif;
    }

    .dashboard-title {
        background: linear-gradient(135deg, hsla(42, 64%, 69%, 0.83), #26504fff);
        padding: 25px;
        border-radius: 12px;
        color: white;
        box-shadow: 0 4px 10px rgba(253, 230, 195, 0.15);
    }

    .coordinator-badge {
        background: rgba(255, 255, 255, 0.2);
        padding: 8px 15px;
        border-radius: 8px;
        display: inline-block;
        margin-top: 10px;
        font-size: 0.9rem;
    }

    .stat-box {
        border-radius: 12px;
        padding: 20px;
        color: white;
        text-align: center;
        transition: all 0.2s;
    }

    .stat-box:hover {
        transform: translateY(-5px);
    }

    .stat-box.pending-review {
        background: linear-gradient(135deg, #ff6b6b, #ff5252) !important;
    }

    .table thead {
        background: #eff2f6;
    }

    .table tbody tr:hover {
        background: #f7f9fc;
    }

    .action-btn {
        padding: 4px 7px;
        border-radius: 6px;
    }

    .card {
        border-radius: 14px;
    }

    .top-actions button {
        border-radius: 10px;
    }

    .alert-coordinator {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
    }

    .alert-coordinator a {
        color: #fff;
        text-decoration: underline;
    }
</style>

<div class="container mt-4">
    <!-- Flash Messages ============ -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-info alert-dismissible fade show text-center shadow-sm" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <!-- Coordinator Mode Alert ============ -->
    <?php if ($is_coordinator && $can_approve_reports): ?>
        <div class="alert alert-coordinator alert-dismissible fade show" role="alert">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1"><i class="bi bi-shield-check"></i> Coordinator Mode Active</h5>
                    <p class="mb-0">You have permission to approve reports on behalf of admin. 
                    <a href="../admin/approve_reports.php" class="fw-bold">Go to Approval Dashboard →</a></p>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Dashboard Header -->
    <div class="dashboard-title mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-0">
                    <i class="bi bi-person-workspace"></i> 
                    <?= $is_coordinator ? 'Coordinator Dashboard' : 'Teacher Dashboard' ?>
                </h2>
                <?php if ($is_coordinator): ?>
                    <div class="coordinator-badge">
                        <i class="bi bi-shield-check"></i> Acting as Coordinator
                    </div>
                <?php endif; ?>
            </div>
            <a href="../logout.php" class="btn btn-light btn-sm">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- Statistics ============ -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-box bg-primary shadow-sm">
                <h3><?= $stats['total'] ?></h3>
                <p class="mb-0">Total Reports</p>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-box bg-success shadow-sm">
                <h3><?= $stats['approved'] ?></h3>
                <p class="mb-0">Approved</p>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-box bg-warning shadow-sm text-dark">
                <h3><?= $stats['pending'] ?></h3>
                <p class="mb-0">Pending</p>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-box bg-danger shadow-sm">
                <h3><?= $stats['rejected'] ?></h3>
                <p class="mb-0">Rejected</p>
            </div>
        </div>

        <!-- Coordinator View - Pending Reports to Review ============ -->
        <?php if ($is_coordinator && $can_approve_reports && isset($stats['pending_to_review'])): ?>
            <div class="col-12">
                <div class="stat-box pending-review shadow-sm d-flex justify-content-between align-items-center">
                    <div style="flex: 1;">
                        <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Reports Pending Your Approval</h5>
                        <small>Reports waiting for review and approval</small>
                    </div>
                    <div style="text-align: center;">
                        <h3 class="mb-0"><?= $stats['pending_to_review'] ?></h3>
                    </div>
                    <a href="../admin/approve_reports.php" class="btn btn-light btn-sm ms-3">
                        <i class="bi bi-arrow-right"></i> Review Now
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions ============ -->
    <div class="top-actions mb-3 d-flex justify-content-between align-items-center flex-wrap">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-folder"></i> My Reports
        </h4>

        <div class="d-flex gap-2">
            <a href="report_maker.php" class="btn btn-success btn-sm">
                <i class="bi bi-pencil-square"></i> Create Report
            </a>
            <a href="upload_report.php" class="btn btn-primary btn-sm">
                <i class="bi bi-cloud-upload"></i> Upload File
            </a>
        </div>
    </div>

    <!-- Filter & Search Bar ============ -->
    <form class="row g-2 mb-4" method="GET" action="dashboard.php">
        <div class="col-md-5">
            <input type="text" name="search" class="form-control" placeholder="Search by title..."
                   value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="approved" <?= ($status === 'approved' ? 'selected' : '') ?>>Approved</option>
                <option value="pending" <?= ($status === 'pending' ? 'selected' : '') ?>>Pending</option>
                <option value="rejected" <?= ($status === 'rejected' ? 'selected' : '') ?>>Rejected</option>
                <option value="draft" <?= ($status === 'draft' ? 'selected' : '') ?>>Draft</option>
            </select>
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-dark w-100">
                <i class="bi bi-search"></i> Filter
            </button>
        </div>

        <div class="col-md-2">
            <a href="dashboard.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-arrow-repeat"></i> Reset
            </a>
        </div>
    </form>

    <!-- Reports Table ============ -->
    <div class="card shadow-sm p-3">
        <?php if ($reports->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Admin Comment</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while($r = $reports->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($r['title']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($r['academic_year']) ?></td>

                            <td>
                                <?php
                                $status_colors = [
                                    'approved' => 'success',
                                    'pending' => 'warning text-dark',
                                    'rejected' => 'danger',
                                    'draft' => 'secondary'
                                ];
                                $badge_color = $status_colors[$r['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badge_color ?>">
                                    <?= ucfirst(htmlspecialchars($r['status'])) ?>
                                </span>
                            </td>

                            <td>
                                <small>
                                    <?= !empty($r['admin_comment']) ? htmlspecialchars($r['admin_comment']) : '<span class="text-muted">—</span>' ?>
                                </small>
                            </td>

                            <td class="text-muted">
                                <small><?= date("d M Y", strtotime($r['created_at'])) ?></small>
                            </td>

                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <!-- View Report ============ -->
                                    <a href="view_report.php?id=<?= intval($r['id']) ?>"
                                       class="btn btn-outline-success action-btn"
                                       title="View Report">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <!-- Edit (Draft/Rejected Only) ============ -->
                                    <?php if (in_array($r['status'], ['draft', 'rejected'])): ?>
                                        <a href="edit_report.php?id=<?= intval($r['id']) ?>"
                                           class="btn btn-outline-primary action-btn"
                                           title="Edit Report">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Submit for Approval (Draft Only) ============ -->
                                    <?php if ($r['status'] === 'draft'): ?>
                                        <form method="POST" action="submit_report.php" style="display:inline;"
                                              onsubmit="return confirm('Submit this report for approval?\n\n✅ It will be sent to the admin for review.');">
                                            <input type="hidden" name="id" value="<?= intval($r['id']) ?>">
                                            <button type="submit" class="btn btn-outline-info action-btn"
                                                    title="Submit for Approval">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Delete (Draft/Rejected Only) ============ -->
                                    <?php if (in_array($r['status'], ['draft', 'rejected'])): ?>
                                        <form method="POST" action="delete_report.php" style="display:inline;"
                                              onsubmit="return confirm('Delete this report permanently?');">
                                            <input type="hidden" name="id" value="<?= intval($r['id']) ?>">
                                            <button type="submit" class="btn btn-outline-danger action-btn"
                                                    title="Delete Report">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="alert alert-info text-center mb-0">
                <i class="bi bi-info-circle"></i> No reports found. 
                <a href="report_maker.php" class="alert-link">Create one now</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Close the reports statement
if (isset($stmt_reports) && $stmt_reports instanceof mysqli_stmt) {
    $stmt_reports->close();
}

include('../includes/footer.php'); 
?>