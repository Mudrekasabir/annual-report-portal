<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== Security Check =====
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

include('../includes/db_connect.php');

// ===== CSV Export Logic (Before Header) =====
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Get filters for export
    $user_filter = $_GET['user'] ?? '';
    $role_filter = $_GET['role'] ?? '';
    $from_date = $_GET['from'] ?? '';
    $to_date = $_GET['to'] ?? '';
    
    // Build WHERE clause with prepared statements
    $where_conditions = [];
    $params = [];
    $types = "";
    
    if ($user_filter !== '') {
        $where_conditions[] = "u.id = ?";
        $params[] = $user_filter;
        $types .= "i";
    }
    if ($role_filter !== '') {
        $where_conditions[] = "u.role = ?";
        $params[] = $role_filter;
        $types .= "s";
    }
    if ($from_date !== '' && $to_date !== '') {
        $where_conditions[] = "DATE(al.created_at) BETWEEN ? AND ?";
        $params[] = $from_date;
        $params[] = $to_date;
        $types .= "ss";
    }
    
    $where_sql = count($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Export query with prepared statement
    $export_query = "
        SELECT u.name, u.role, al.activity, al.created_at
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        $where_sql
        ORDER BY al.created_at DESC
    ";
    
    $stmt_export = $conn->prepare($export_query);
    if ($stmt_export && !empty($params)) {
        $stmt_export->bind_param($types, ...$params);
        $stmt_export->execute();
        $export_result = $stmt_export->get_result();
    } else {
        $export_result = $conn->query($export_query);
    }
    
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=audit_log_" . date('Y-m-d_His') . ".csv");
    $out = fopen("php://output", "w");
    fputcsv($out, ['User', 'Role', 'Activity', 'Timestamp']);
    
    while ($r = $export_result->fetch_assoc()) {
        fputcsv($out, [
            $r['name'] ?? 'Unknown',
            $r['role'] ?? '-',
            $r['activity'],
            $r['created_at']
        ]);
    }
    
    fclose($out);
    if (isset($stmt_export)) $stmt_export->close();
    exit();
}

include('../includes/header.php');

// ===== Get Filters =====
$user_filter = $_GET['user'] ?? '';
$role_filter = $_GET['role'] ?? '';
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';
$search = trim($_GET['search'] ?? '');

// ===== Validate Role Filter =====
$allowed_roles = ['admin', 'teacher', 'student', 'coordinator'];
if ($role_filter !== '' && !in_array($role_filter, $allowed_roles)) {
    $role_filter = '';
}

// ===== Build WHERE Clause with Prepared Statements =====
$where_conditions = [];
$params = [];
$types = "";

if ($user_filter !== '') {
    $where_conditions[] = "u.id = ?";
    $params[] = $user_filter;
    $types .= "i";
}

if ($role_filter !== '') {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if ($from_date !== '' && $to_date !== '') {
    $where_conditions[] = "DATE(al.created_at) BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
    $types .= "ss";
}

if ($search !== '') {
    $where_conditions[] = "(al.activity LIKE ? OR u.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$where_sql = count($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ===== Pagination =====
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// ===== Get Total Count =====
$count_query = "SELECT COUNT(*) AS c FROM activity_log al LEFT JOIN users u ON al.user_id = u.id $where_sql";
$stmt_count = $conn->prepare($count_query);

if ($stmt_count && !empty($params)) {
    $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $total = $stmt_count->get_result()->fetch_assoc()['c'];
    $stmt_count->close();
} else {
    $total = $conn->query($count_query)->fetch_assoc()['c'];
}

$total_pages = ceil($total / $limit);

// ===== Fetch Logs with Prepared Statement =====
$log_query = "
    SELECT al.id, al.activity, al.created_at, 
           u.name, u.role, u.email
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    $where_sql
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt_logs = $conn->prepare($log_query);

// Add limit and offset to params
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

if ($stmt_logs) {
    $stmt_logs->bind_param($types, ...$params);
    $stmt_logs->execute();
    $logs = $stmt_logs->get_result();
} else {
    die("Database Error: " . $conn->error);
}

// ===== Get Users for Filter Dropdown =====
$stmt_users = $conn->prepare("SELECT id, name, role FROM users ORDER BY name ASC");
$stmt_users->execute();
$users = $stmt_users->get_result();

// ===== Get Statistics =====
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as c FROM activity_log")->fetch_assoc()['c'];
$stats['today'] = $conn->query("SELECT COUNT(*) as c FROM activity_log WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
$stats['this_week'] = $conn->query("SELECT COUNT(*) as c FROM activity_log WHERE WEEK(created_at) = WEEK(NOW())")->fetch_assoc()['c'];
$stats['this_month'] = $conn->query("SELECT COUNT(*) as c FROM activity_log WHERE MONTH(created_at) = MONTH(NOW())")->fetch_assoc()['c'];
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f7fa;
    }

    .audit-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 30px;
        border-radius: 15px;
        color: white;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        margin-bottom: 30px;
    }

    .stat-card {
        border-radius: 12px;
        padding: 20px;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border-left: 4px solid;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }

    .stat-card.total { border-left-color: #667eea; }
    .stat-card.today { border-left-color: #48bb78; }
    .stat-card.week { border-left-color: #f6ad55; }
    .stat-card.month { border-left-color: #fc8181; }

    .stat-card h3 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stat-card p {
        color: #6c757d;
        margin: 0;
        font-size: 0.9rem;
    }

    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    .table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .table {
        margin-bottom: 0;
    }

    .table thead {
        background: #f8f9fa;
    }

    .table thead th {
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
        padding: 15px;
        color: #495057;
    }

    .table tbody td {
        padding: 15px;
        vertical-align: middle;
    }

    .table tbody tr {
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s ease;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .activity-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .role-badge {
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .user-avatar {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .pagination {
        margin-top: 20px;
    }

    .page-link {
        border-radius: 8px;
        margin: 0 3px;
        border: none;
        color: #667eea;
    }

    .page-item.active .page-link {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border: none;
    }

    .btn-export {
        background: linear-gradient(135deg, #48bb78, #38a169);
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
        color: white;
    }

    .search-input {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 10px 15px;
    }

    .search-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .filter-select {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 10px 15px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 4rem;
        opacity: 0.3;
        margin-bottom: 20px;
    }

    @media (max-width: 768px) {
        .stat-card h3 {
            font-size: 1.5rem;
        }
    }
</style>

<div class="container mt-4">
    <!-- Header -->
    <div class="audit-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-2">
                    <i class="bi bi-shield-check"></i> Audit Log
                </h2>
                <p class="mb-0 opacity-90">Track all system activities and user actions</p>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-light btn-sm">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn-export">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card total">
                <h3><?= number_format($stats['total']) ?></h3>
                <p><i class="bi bi-bar-chart"></i> Total Activities</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card today">
                <h3><?= number_format($stats['today']) ?></h3>
                <p><i class="bi bi-calendar-check"></i> Today</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card week">
                <h3><?= number_format($stats['this_week']) ?></h3>
                <p><i class="bi bi-calendar-week"></i> This Week</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card month">
                <h3><?= number_format($stats['this_month']) ?></h3>
                <p><i class="bi bi-calendar-month"></i> This Month</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <h5 class="mb-3"><i class="bi bi-funnel"></i> Filters</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small text-muted">Search Activity</label>
                <input type="text" name="search" class="form-control search-input" 
                       placeholder="Search activity or user..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label small text-muted">User</label>
                <select name="user" class="form-select filter-select">
                    <option value="">All Users</option>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Role</label>
                <select name="role" class="form-select filter-select">
                    <option value="">All Roles</option>
                    <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="coordinator" <?= $role_filter == 'coordinator' ? 'selected' : '' ?>>Coordinator</option>
                    <option value="teacher" <?= $role_filter == 'teacher' ? 'selected' : '' ?>>Teacher</option>
                    <option value="student" <?= $role_filter == 'student' ? 'selected' : '' ?>>Student</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">From Date</label>
                <input type="date" name="from" class="form-control filter-select" value="<?= htmlspecialchars($from_date) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">To Date</label>
                <input type="date" name="to" class="form-control filter-select" value="<?= htmlspecialchars($to_date) ?>">
            </div>

            <div class="col-md-1 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
                <a href="audit_log.php" class="btn btn-outline-secondary" title="Reset">
                    <i class="bi bi-arrow-repeat"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Results Info -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">
            <i class="bi bi-list-ul"></i> 
            Activity Logs 
            <span class="badge bg-primary"><?= number_format($total) ?> records</span>
        </h5>
        <small class="text-muted">
            Page <?= $page ?> of <?= $total_pages ?>
        </small>
    </div>

    <!-- Audit Log Table -->
    <div class="table-container">
        <?php if ($logs->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th width="200">User</th>
                            <th width="100">Role</th>
                            <th>Activity</th>
                            <th width="180">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = $offset + 1;
                        while ($log = $logs->fetch_assoc()): 
                            $user_name = $log['name'] ?? 'Unknown User';
                            $user_initial = strtoupper(substr($user_name, 0, 1));
                            
                            // Role badge colors
                            $role_colors = [
                                'admin' => 'bg-danger',
                                'coordinator' => 'bg-purple',
                                'teacher' => 'bg-primary',
                                'student' => 'bg-success'
                            ];
                            $role_color = $role_colors[$log['role']] ?? 'bg-secondary';
                        ?>
                            <tr>
                                <td class="text-muted"><?= $i++ ?></td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar"><?= $user_initial ?></div>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($user_name) ?></div>
                                            <?php if (!empty($log['email'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars($log['email']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge <?= $role_color ?> text-white">
                                        <?= htmlspecialchars(ucfirst($log['role'] ?? '-')) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="activity-badge bg-light text-dark">
                                        <i class="bi bi-activity"></i>
                                        <?= htmlspecialchars($log['activity']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <i class="bi bi-clock"></i>
                                        <?= date('d M Y, h:i A', strtotime($log['created_at'])) ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h5>No Activity Found</h5>
                <p class="text-muted">No logs match your current filters. Try adjusting your search criteria.</p>
                <a href="audit_log.php" class="btn btn-primary mt-3">
                    <i class="bi bi-arrow-repeat"></i> Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <!-- Previous Button -->
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>

                <!-- Page Numbers -->
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                    <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">
                            <?= $total_pages ?>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Next Button -->
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php 
// Close statements
if (isset($stmt_logs)) $stmt_logs->close();
if (isset($stmt_users)) $stmt_users->close();

include('../includes/footer.php'); 
?>