<?php
// ============ FILE: index.php ============
include('includes/db_connect.php');
include('includes/header.php');
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? null;

// Fetch approved reports (latest 6)
$reports = $conn->query("
  SELECT ar.*, u.name as teacher_name 
  FROM annual_reports ar
  LEFT JOIN users u ON ar.uploaded_by = u.id
  WHERE ar.status='approved'
  ORDER BY ar.created_at DESC
  LIMIT 6
");

// Quick stats for display
$total_reports = $conn->query("SELECT COUNT(*) AS c FROM annual_reports WHERE status='approved'")->fetch_assoc()['c'];
$total_teachers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='teacher' AND approval_status='approved'")->fetch_assoc()['c'];
$total_students = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='student'")->fetch_assoc()['c'];
$academic_years = $conn->query("SELECT COUNT(DISTINCT academic_year) AS c FROM annual_reports")->fetch_assoc()['c'];
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

<style>
  body { font-family: 'Inter', sans-serif; }
  
  /* Hero Section */
  .hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 80px 0;
    color: white;
    position: relative;
    overflow: hidden;
  }
  .hero-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 600px;
    height: 600px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
  }
  .hero-section::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
  }
  .hero-content { position: relative; z-index: 1; }
  .hero-title { font-size: 3rem; font-weight: 800; margin-bottom: 1rem; }
  .hero-subtitle { font-size: 1.2rem; opacity: 0.9; margin-bottom: 2rem; }
  .hero-btn { padding: 12px 30px; border-radius: 50px; font-weight: 600; transition: all 0.3s; }
  .hero-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
  
  /* Stats Section */
  .stats-section { margin-top: -50px; position: relative; z-index: 10; }
  .stat-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    transition: all 0.3s;
  }
  .stat-card:hover { transform: translateY(-5px); }
  .stat-icon { font-size: 2.5rem; margin-bottom: 10px; }
  .stat-number { font-size: 2.5rem; font-weight: 800; }
  .stat-label { color: #6c757d; font-size: 0.9rem; }
  
  /* Reports Section */
  .reports-section { padding: 80px 0; background: #f8f9fa; }
  .section-title { font-weight: 700; margin-bottom: 40px; }
  .report-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: all 0.3s;
    height: 100%;
  }
  .report-card:hover { transform: translateY(-8px); box-shadow: 0 15px 40px rgba(0,0,0,0.15); }
  .report-header {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    padding: 20px;
    color: white;
  }
  .report-body { padding: 20px; }
  .report-year { 
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    margin-bottom: 10px;
  }
  .report-title { font-weight: 600; font-size: 1.1rem; }
  .report-meta { font-size: 0.85rem; color: #6c757d; }
  .view-btn {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 25px;
    font-size: 0.9rem;
    transition: all 0.3s;
  }
  .view-btn:hover { transform: scale(1.05); color: white; }
  
  /* Features Section */
  .features-section { padding: 80px 0; }
  .feature-card {
    text-align: center;
    padding: 30px;
    border-radius: 16px;
    transition: all 0.3s;
  }
  .feature-card:hover { background: #f8f9fa; }
  .feature-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
  }
  .feature-title { font-weight: 600; margin-bottom: 10px; }
  .feature-desc { color: #6c757d; font-size: 0.95rem; }
  
  /* CTA Section */
  .cta-section {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    padding: 60px 0;
    color: white;
  }
  .cta-title { font-weight: 700; margin-bottom: 15px; }
  
  /* Quick Links for logged in users */
  .quick-links { background: #fff3cd; border-radius: 12px; padding: 20px; margin-bottom: 30px; }
</style>

<!-- Hero Section -->
<section class="hero-section">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-7 hero-content">
        <h1 class="hero-title">Annual Report Portal</h1>
        <p class="hero-subtitle">
          A centralized platform for teachers to submit, manage, and track their annual performance reports. 
          Streamline the review process with digital submissions and real-time status updates.
        </p>
        <?php if ($is_logged_in): ?>
          <?php if ($user_role == 'admin' || $user_role == 'coordinator'): ?>
            <a href="admin/dashboard.php" class="btn btn-light hero-btn me-2">
              <i class="bi bi-speedometer2"></i> Go to Dashboard
            </a>
          <?php elseif ($user_role == 'teacher'): ?>
            <a href="teacher/dashboard.php" class="btn btn-light hero-btn me-2">
              <i class="bi bi-folder"></i> My Reports
            </a>
          <?php elseif ($user_role == 'student'): ?>
            <a href="student/dashboard.php" class="btn btn-light hero-btn me-2">
              <i class="bi bi-mortarboard"></i> Student Portal
            </a>
          <?php endif; ?>
          <a href="logout.php" class="btn btn-outline-light hero-btn">
            <i class="bi bi-box-arrow-right"></i> Logout
          </a>
        <?php else: ?>
          <a href="login.php" class="btn btn-light hero-btn me-2">
            <i class="bi bi-box-arrow-in-right"></i> Login
          </a>
          <a href="register.php" class="btn btn-outline-light hero-btn">
            <i class="bi bi-person-plus"></i> Register
          </a>
        <?php endif; ?>
      </div>
      <div class="col-lg-5 text-center d-none d-lg-block">
        <i class="bi bi-file-earmark-bar-graph" style="font-size: 12rem; opacity: 0.3;"></i>
      </div>
    </div>
  </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon text-primary"><i class="bi bi-file-earmark-check"></i></div>
          <div class="stat-number text-primary"><?= $total_reports ?></div>
          <div class="stat-label">Approved Reports</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon text-success"><i class="bi bi-person-workspace"></i></div>
          <div class="stat-number text-success"><?= $total_teachers ?></div>
          <div class="stat-label">Active Teachers</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon text-info"><i class="bi bi-mortarboard"></i></div>
          <div class="stat-number text-info"><?= $total_students ?></div>
          <div class="stat-label">Students</div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-icon text-warning"><i class="bi bi-calendar-check"></i></div>
          <div class="stat-number text-warning"><?= $academic_years ?></div>
          <div class="stat-label">Academic Years</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Quick Links for Logged In Users -->
<?php if ($is_logged_in): ?>
<section class="container mt-5">
  <div class="quick-links">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <div>
        <h5 class="mb-1"><i class="bi bi-person-circle"></i> Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>!</h5>
        <small class="text-muted">Role: <?= ucfirst($user_role) ?></small>
      </div>
      <div class="mt-2 mt-md-0">
        <?php if ($user_role == 'admin'): ?>
          <a href="admin/manage_users.php" class="btn btn-sm btn-outline-dark me-1"><i class="bi bi-people"></i> Users</a>
          <a href="admin/approve_reports.php" class="btn btn-sm btn-outline-warning me-1"><i class="bi bi-clipboard-check"></i> Pending</a>
          <a href="admin/assign_students.php" class="btn btn-sm btn-outline-success"><i class="bi bi-diagram-3"></i> Assign</a>
        <?php elseif ($user_role == 'coordinator'): ?>
          <a href="admin/approve_reports.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-clipboard-check"></i> Approve Reports</a>
        <?php elseif ($user_role == 'teacher'): ?>
          <a href="teacher/submit_report.php" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-plus-circle"></i> New Report</a>
          <a href="teacher/my_reports.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-folder"></i> My Reports</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Latest Reports Section -->
<section class="reports-section">
  <div class="container">
    <h2 class="section-title text-center">
      <i class="bi bi-file-earmark-text"></i> Latest Approved Reports
    </h2>
    
    <div class="row g-4">
      <?php if($reports->num_rows > 0): ?>
        <?php while($r = $reports->fetch_assoc()): ?>
          <div class="col-lg-4 col-md-6">
            <div class="report-card">
              <div class="report-header">
                <span class="report-year"><?= htmlspecialchars($r['academic_year']) ?></span>
                <h5 class="report-title mb-0"><?= htmlspecialchars($r['title']) ?></h5>
              </div>
              <div class="report-body">
                <p class="report-meta mb-3">
                  <i class="bi bi-person"></i> <?= htmlspecialchars($r['teacher_name'] ?? 'Unknown') ?><br>
                  <i class="bi bi-calendar"></i> <?= date('d M Y', strtotime($r['created_at'])) ?>
                </p>
                <a href="<?= htmlspecialchars($r['file_path']) ?>" class="view-btn" target="_blank">
                  <i class="bi bi-eye"></i> View Report
                </a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="text-center py-5">
            <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 text-muted">No approved reports available yet</h5>
            <p class="text-muted">Check back later for published reports.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
    
    <?php if($reports->num_rows > 0): ?>
    <div class="text-center mt-4">
      <a href="all_reports.php" class="btn btn-outline-primary">
        View All Reports <i class="bi bi-arrow-right"></i>
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- Features Section -->
<section class="features-section">
  <div class="container">
    <h2 class="section-title text-center">
      <i class="bi bi-stars"></i> Portal Features
    </h2>
    
    <div class="row g-4">
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-cloud-upload"></i>
          </div>
          <h5 class="feature-title">Easy Submission</h5>
          <p class="feature-desc">Teachers can easily submit their annual reports with a simple upload process.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon bg-success bg-opacity-10 text-success">
            <i class="bi bi-shield-check"></i>
          </div>
          <h5 class="feature-title">Secure Review</h5>
          <p class="feature-desc">Admin and coordinators review reports securely with approval workflows.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon bg-info bg-opacity-10 text-info">
            <i class="bi bi-graph-up"></i>
          </div>
          <h5 class="feature-title">Track Progress</h5>
          <p class="feature-desc">Real-time status updates and analytics for all submissions.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA Section -->
<?php if (!$is_logged_in): ?>
<section class="cta-section">
  <div class="container text-center">
    <h3 class="cta-title">Ready to Get Started?</h3>
    <p class="mb-4 opacity-75">Join our platform to submit and manage your annual reports efficiently.</p>
    <a href="register.php" class="btn btn-light hero-btn me-2">
      <i class="bi bi-person-plus"></i> Register Now
    </a>
    <a href="login.php" class="btn btn-outline-light hero-btn">
      <i class="bi bi-box-arrow-in-right"></i> Login
    </a>
  </div>
</section>
<?php endif; ?>

<?php include('includes/footer.php'); ?>