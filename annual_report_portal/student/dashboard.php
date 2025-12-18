<?php
include('../includes/db_connect.php');
include('../includes/header.php');
include('../includes/functions.php');

if ($_SESSION['role'] != 'student') {
  alert("Access Denied!", 'danger');
  exit();
}

// --- FILTERS ---
$yearFilter = $_GET['year'] ?? '';
$teacherFilter = $_GET['teacher'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$query = "
  SELECT a.*, u.name AS teacher_name
  FROM annual_reports a
  JOIN users u ON a.uploaded_by = u.id
  WHERE a.status = 'approved'
";

if (!empty($yearFilter)) {
  $query .= " AND a.academic_year = '" . $conn->real_escape_string($yearFilter) . "'";
}
if (!empty($teacherFilter)) {
  $query .= " AND u.name LIKE '%" . $conn->real_escape_string($teacherFilter) . "%'";
}
if (!empty($searchQuery)) {
  $query .= " AND (a.title LIKE '%" . $conn->real_escape_string($searchQuery) . "%' 
              OR a.description LIKE '%" . $conn->real_escape_string($searchQuery) . "%')";
}

$query .= " ORDER BY a.submitted_at DESC, a.created_at DESC";
$reports = $conn->query($query);

// Fetch unique academic years for filter dropdown
$years = $conn->query("SELECT DISTINCT academic_year FROM annual_reports WHERE status='approved' ORDER BY academic_year DESC");

// Get statistics
$stats = $conn->query("
  SELECT 
    COUNT(*) as total_reports,
    COUNT(DISTINCT uploaded_by) as total_teachers,
    COUNT(DISTINCT academic_year) as total_years
  FROM annual_reports WHERE status='approved'
")->fetch_assoc();
?>

<!-- Modern Styles -->
<link rel="stylesheet" href="../assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

<style>
  body { 
    font-family: 'Inter', sans-serif; 
    background-color: #f4f6f9; 
  }
  
  .hero-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
  }
  
  .stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: none;
    overflow: hidden;
  }
  
  .stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
  }
  
  .stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
  }
  
  .card-gradient-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
  .card-gradient-2 { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
  .card-gradient-3 { background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); }
  
  .report-card {
    border-radius: 16px;
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    height: 100%;
    background: white;
  }
  
  .report-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.12);
    border-color: #667eea;
  }
  
  .report-card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1.25rem;
    border-bottom: 3px solid #667eea;
  }
  
  .filter-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
  }
  
  .badge-custom {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
  }
  
  .search-input {
    border-radius: 12px;
    border: 2px solid #e5e7eb;
    padding: 0.7rem 1.2rem;
    transition: all 0.3s ease;
  }
  
  .search-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
  }
  
  .btn-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    transition: all 0.3s ease;
    font-weight: 600;
  }
  
  .btn-gradient:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    color: white;
  }
  
  .no-reports-illustration {
    max-width: 350px;
    margin: 3rem auto;
    opacity: 0.6;
  }
</style>

<div class="container mt-4">
  <!-- Hero Header -->
  <div class="hero-gradient text-white p-5 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <div>
        <h1 class="fw-bold mb-2">
          <i class="bi bi-mortarboard-fill"></i> Student Section
        </h1>
        <p class="mb-0 opacity-90" style="font-size: 1.1rem;">
          Welcome back, <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Student') ?></strong>!
        </p>
      </div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="stat-card text-white card-gradient-1">
        <div class="d-flex align-items-center">
          <div class="stat-icon bg-white bg-opacity-25 me-3">
            <i class="bi bi-journal-text"></i>
          </div>
          <div>
            <h2 class="fw-bold mb-0"><?= $stats['total_reports'] ?></h2>
            <p class="mb-0 opacity-90">Total Reports</p>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-md-4">
      <div class="stat-card text-white card-gradient-2">
        <div class="d-flex align-items-center">
          <div class="stat-icon bg-white bg-opacity-25 me-3">
            <i class="bi bi-person-check"></i>
          </div>
          <div>
            <h2 class="fw-bold mb-0"><?= $stats['total_teachers'] ?></h2>
            <p class="mb-0 opacity-90">Active Teachers</p>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-md-4">
      <div class="stat-card text-white card-gradient-3">
        <div class="d-flex align-items-center">
          <div class="stat-icon bg-white bg-opacity-25 me-3">
            <i class="bi bi-calendar-range"></i>
          </div>
          <div>
            <h2 class="fw-bold mb-0"><?= $stats['total_years'] ?></h2>
            <p class="mb-0 opacity-90">Academic Years</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Section -->
  <div class="filter-card p-4 mb-4">
    <h5 class="fw-bold mb-3">
      <i class="bi bi-funnel-fill text-primary"></i> Search & Filter Reports
    </h5>
    <form method="GET">
      <div class="row g-3">
        <!-- Search Bar -->
        <div class="col-md-12">
          <div class="input-group">
            <span class="input-group-text bg-white border-2 border-end-0">
              <i class="bi bi-search"></i>
            </span>
            <input type="text" 
                   name="search" 
                   value="<?= htmlspecialchars($searchQuery) ?>" 
                   class="form-control search-input border-start-0 border-2" 
                   placeholder="Search by title or keywords...">
          </div>
        </div>
        
        <!-- Year Filter -->
        <div class="col-md-5">
          <label class="form-label fw-semibold">
            <i class="bi bi-calendar3"></i> Academic Year
          </label>
          <select name="year" class="form-select form-select-lg">
            <option value="">All Years</option>
            <?php 
            $years->data_seek(0);
            while ($y = $years->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($y['academic_year']) ?>" 
                      <?= $yearFilter == $y['academic_year'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($y['academic_year']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        
        <!-- Teacher Filter -->
        <div class="col-md-5">
          <label class="form-label fw-semibold">
            <i class="bi bi-person"></i> Teacher Name
          </label>
          <input type="text" 
                 name="teacher" 
                 value="<?= htmlspecialchars($teacherFilter) ?>" 
                 class="form-control form-control-lg" 
                 placeholder="Search by teacher name...">
        </div>
        
        <!-- Buttons -->
        <div class="col-md-2 d-flex flex-column gap-2">
          <label class="form-label opacity-0">Actions</label>
          <button type="submit" class="btn btn-gradient btn-lg">
            <i class="bi bi-search"></i> Apply
          </button>
          <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle"></i>
          </a>
        </div>
      </div>
    </form>
  </div>

  <!-- Active Filters Display -->
  <?php if (!empty($yearFilter) || !empty($teacherFilter) || !empty($searchQuery)): ?>
  <div class="mb-3 p-3 bg-light rounded-3">
    <small class="fw-semibold text-muted">Active Filters:</small>
    <div class="d-inline-flex gap-2 ms-2 flex-wrap">
      <?php if (!empty($searchQuery)): ?>
        <span class="badge bg-primary badge-custom">
          <i class="bi bi-search"></i> "<?= htmlspecialchars($searchQuery) ?>"
        </span>
      <?php endif; ?>
      <?php if (!empty($yearFilter)): ?>
        <span class="badge bg-info badge-custom">
          <i class="bi bi-calendar"></i> <?= htmlspecialchars($yearFilter) ?>
        </span>
      <?php endif; ?>
      <?php if (!empty($teacherFilter)): ?>
        <span class="badge bg-success badge-custom">
          <i class="bi bi-person"></i> <?= htmlspecialchars($teacherFilter) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Report Cards -->
  <?php if ($reports->num_rows > 0): ?>
    <div class="row g-4 mb-4">
      <?php while ($r = $reports->fetch_assoc()): ?>
        <div class="col-lg-4 col-md-6">
          <div class="card report-card shadow-sm">
            <!-- Card Header -->
            <div class="report-card-header">
              <h6 class="fw-bold mb-0 text-truncate" title="<?= htmlspecialchars($r['title']) ?>">
                <i class="bi bi-file-earmark-text text-primary"></i>
                <?= htmlspecialchars($r['title']) ?>
              </h6>
            </div>
            
            <!-- Card Body -->
            <div class="card-body p-4">
              <div class="mb-3 d-flex flex-wrap gap-2">
                <span class="badge-custom bg-primary bg-opacity-10 text-primary">
                  <i class="bi bi-calendar3"></i> <?= htmlspecialchars($r['academic_year']) ?>
                </span>
                <span class="badge-custom bg-success bg-opacity-10 text-success">
                  <i class="bi bi-person-circle"></i> <?= htmlspecialchars($r['teacher_name']) ?>
                </span>
              </div>
              
              <?php if (!empty($r['submitted_at'])): ?>
              <p class="small text-muted mb-3">
                <i class="bi bi-clock"></i> 
                <?= date('M d, Y', strtotime($r['submitted_at'])) ?>
              </p>
              <?php endif; ?>
              
              <p class="text-muted" style="height: 70px; overflow: hidden; line-height: 1.6;">
                <?php 
                  $preview = !empty($r['description']) ? $r['description'] : strip_tags($r['content']);
                  echo htmlspecialchars(substr($preview, 0, 120));
                  if (strlen($preview) > 120) echo '...';
                ?>
              </p>
            </div>
            
            <!-- Card Footer -->
            <div class="card-footer bg-white border-0 d-flex gap-2 p-3">
              <a href="view_report.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm flex-fill">
                <i class="bi bi-eye"></i> View Report
              </a>
              <?php if (!empty($r['file_path'])): ?>
                <a href="../<?= htmlspecialchars($r['file_path']) ?>" 
                   download 
                   class="btn btn-success btn-sm"
                   title="Download">
                  <i class="bi bi-download"></i>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
    
    <!-- Results Count -->
    <div class="text-center p-4 bg-light rounded-3">
      <p class="mb-0 text-muted">
        <i class="bi bi-info-circle"></i>
        Showing <strong class="text-primary"><?= $reports->num_rows ?></strong> approved report<?= $reports->num_rows != 1 ? 's' : '' ?>
      </p>
    </div>
    
  <?php else: ?>
    <!-- No Reports Found -->
    <div class="text-center py-5">
      <div class="no-reports-illustration">
        <i class="bi bi-journal-x" style="font-size: 100px; color: #cbd5e1;"></i>
      </div>
      <h3 class="fw-bold text-muted mb-2">No Reports Found</h3>
      <p class="text-muted mb-4">
        <?php if (!empty($yearFilter) || !empty($teacherFilter) || !empty($searchQuery)): ?>
          Try adjusting your search criteria or filters.
        <?php else: ?>
          No approved reports are currently available.
        <?php endif; ?>
      </p>
      <?php if (!empty($yearFilter) || !empty($teacherFilter) || !empty($searchQuery)): ?>
        <a href="dashboard.php" class="btn btn-gradient btn-lg">
          <i class="bi bi-arrow-clockwise"></i> Clear All Filters
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<script>
// Auto-focus search input if page was loaded with search term
<?php if (!empty($searchQuery)): ?>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.querySelector('input[name="search"]');
  if (searchInput) {
    searchInput.focus();
    searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
  }
});
<?php endif; ?>
</script>

<?php include('../includes/footer.php'); ?>