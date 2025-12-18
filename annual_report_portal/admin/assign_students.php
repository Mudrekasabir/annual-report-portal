<?php
// ============ FILE: admin/assign_students.php ============
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
  header("Location: ../login.php");
  exit();
}

include('../includes/db_connect.php');
include('../includes/functions.php');

// ========== HANDLE ALL POST REQUESTS BEFORE ANY OUTPUT ==========

// Handle class creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_class'])) {
  $class_name = trim($_POST['class_name']);
  $section = trim($_POST['section']);
  $academic_year = trim($_POST['academic_year']);
  
  if ($class_name && $academic_year) {
    $stmt = $conn->prepare("INSERT INTO classes (class_name, section, academic_year, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $class_name, $section, $academic_year, $_SESSION['user_id']);
    if ($stmt->execute()) {
      log_action($conn, $_SESSION['user_id'], "Created class: $class_name $section ($academic_year)");
      $_SESSION['flash_message'] = "✅ Class created successfully!";
    }
    $stmt->close();
  }
  header("Location: assign_students.php");
  exit();
}

// Handle teacher assignment to class
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_teacher'])) {
  $class_id = intval($_POST['class_id']);
  $teacher_id = intval($_POST['teacher_id']);
  
  // Validate teacher exists
  $teacher_check = $conn->query("SELECT id FROM users WHERE id = $teacher_id AND role IN ('teacher', 'coordinator')");
  if ($teacher_check->num_rows === 0) {
    $_SESSION['flash_message'] = "❌ Invalid teacher selected!";
  } else {
    // Check if already assigned
    $exists = $conn->query("SELECT id FROM class_teachers WHERE class_id = $class_id AND teacher_id = $teacher_id")->num_rows;
    if (!$exists) {
      $stmt = $conn->prepare("INSERT INTO class_teachers (class_id, teacher_id) VALUES (?, ?)");
      $stmt->bind_param("ii", $class_id, $teacher_id);
      $stmt->execute();
      $stmt->close();
      log_action($conn, $_SESSION['user_id'], "Assigned teacher ID $teacher_id to class ID $class_id");
      $_SESSION['flash_message'] = "✅ Teacher assigned to class!";
    } else {
      $_SESSION['flash_message'] = "⚠️ Teacher already assigned to this class!";
    }
  }
  header("Location: assign_students.php?class_id=$class_id");
  exit();
}

// Handle bulk student assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_students'])) {
  $class_id = intval($_POST['class_id']);
  $teacher_id = intval($_POST['teacher_id']);
  $student_ids = $_POST['student_ids'] ?? [];
  
  // ✅ VALIDATION: Check if teacher_id is valid
  if ($teacher_id <= 0) {
    $_SESSION['flash_message'] = "❌ Please assign at least one teacher to this class before assigning students!";
    header("Location: assign_students.php?class_id=$class_id");
    exit();
  }
  
  // ✅ VALIDATION: Verify teacher exists
  $teacher_check = $conn->query("SELECT id FROM users WHERE id = $teacher_id AND role IN ('teacher', 'coordinator')");
  if ($teacher_check->num_rows === 0) {
    $_SESSION['flash_message'] = "❌ Invalid teacher ID. Please assign a valid teacher first!";
    header("Location: assign_students.php?class_id=$class_id");
    exit();
  }
  
  // Check if students array is empty
  if (empty($student_ids)) {
    $_SESSION['flash_message'] = "⚠️ Please select at least one student to assign!";
    header("Location: assign_students.php?class_id=$class_id");
    exit();
  }
  
  $assigned = 0;
  foreach ($student_ids as $sid) {
    $sid = intval($sid);
    $exists = $conn->query("SELECT id FROM student_assignments WHERE student_id = $sid AND class_id = $class_id")->num_rows;
    if (!$exists) {
      $stmt = $conn->prepare("INSERT INTO student_assignments (student_id, teacher_id, class_id, assigned_by) VALUES (?, ?, ?, ?)");
      $stmt->bind_param("iiii", $sid, $teacher_id, $class_id, $_SESSION['user_id']);
      if ($stmt->execute()) {
        $assigned++;
      }
      $stmt->close();
    }
  }
  
  if ($assigned > 0) {
    log_action($conn, $_SESSION['user_id'], "Assigned $assigned students to class ID $class_id, teacher ID $teacher_id");
    $_SESSION['flash_message'] = "✅ $assigned students assigned successfully!";
  } else {
    $_SESSION['flash_message'] = "⚠️ No new students assigned (already exist in this class).";
  }
  header("Location: assign_students.php?class_id=$class_id");
  exit();
}

// Handle removing student assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_assignment'])) {
  $assignment_id = intval($_POST['assignment_id']);
  $class_id = intval($_POST['class_id'] ?? 0);
  $conn->query("DELETE FROM student_assignments WHERE id = $assignment_id");
  log_action($conn, $_SESSION['user_id'], "Removed student assignment ID $assignment_id");
  $_SESSION['flash_message'] = "✅ Student removed from class.";
  header("Location: assign_students.php?class_id=$class_id");
  exit();
}

// Handle removing teacher from class
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_teacher'])) {
  $ct_id = intval($_POST['ct_id']);
  $class_id = intval($_POST['class_id'] ?? 0);
  $conn->query("DELETE FROM class_teachers WHERE id = $ct_id");
  $_SESSION['flash_message'] = "✅ Teacher removed from class.";
  header("Location: assign_students.php?class_id=$class_id");
  exit();
}

// Handle delete class
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_class'])) {
  $class_id = intval($_POST['class_id']);
  $conn->query("DELETE FROM student_assignments WHERE class_id = $class_id");
  $conn->query("DELETE FROM class_teachers WHERE class_id = $class_id");
  $conn->query("DELETE FROM classes WHERE id = $class_id");
  log_action($conn, $_SESSION['user_id'], "Deleted class ID $class_id");
  $_SESSION['flash_message'] = "✅ Class deleted.";
  header("Location: assign_students.php");
  exit();
}

// ========== NOW INCLUDE HEADER (AFTER ALL POST PROCESSING) ==========
include('../includes/header.php');

// Fetch data
$classes = $conn->query("SELECT c.*, u.name as created_by_name FROM classes c LEFT JOIN users u ON c.created_by = u.id ORDER BY c.academic_year DESC, c.class_name ASC");
$teachers = $conn->query("SELECT * FROM users WHERE role IN ('teacher', 'coordinator') AND approval_status = 'approved' ORDER BY name");
$students = $conn->query("SELECT * FROM users WHERE role = 'student' ORDER BY name");

// Stats
$total_classes = $conn->query("SELECT COUNT(*) AS c FROM classes")->fetch_assoc()['c'];
$total_assignments = $conn->query("SELECT COUNT(*) AS c FROM student_assignments")->fetch_assoc()['c'];
$unassigned = $conn->query("SELECT COUNT(*) AS c FROM users u LEFT JOIN student_assignments sa ON u.id = sa.student_id WHERE u.role = 'student' AND sa.id IS NULL")->fetch_assoc()['c'];

// Selected class filter
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Display flash message
if (isset($_SESSION['flash_message'])) {
  echo '<div class="container mt-3"><div class="alert alert-info alert-dismissible fade show" role="alert">';
  echo $_SESSION['flash_message'];
  echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>';
  unset($_SESSION['flash_message']);
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background: #f4f6f9; }
  .stat-card { border-radius: 12px; border: none; }
  .class-card { border-radius: 12px; transition: all .2s; cursor: pointer; }
  .class-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
  .class-card.active { border: 2px solid #667eea !important; }
  .student-checkbox { width: 18px; height: 18px; }
  .teacher-badge { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
  .student-list-item { border-left: 3px solid #38ef7d; }
</style>

<div class="container-fluid mt-4 px-4">
  <!-- Header -->
  <div class="p-4 mb-4 rounded-4 text-white shadow" style="background: linear-gradient(135deg, #667eea, #764ba2);">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-diagram-3"></i> Student-Teacher Assignment</h2>
        <small class="opacity-75">Organize students by class and assign to teachers</small>
      </div>
      <a href="dashboard.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card stat-card p-3 shadow-sm text-center">
        <h3 class="fw-bold text-primary"><?= $total_classes ?></h3>
        <small class="text-muted">Total Classes</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card p-3 shadow-sm text-center">
        <h3 class="fw-bold text-success"><?= $total_assignments ?></h3>
        <small class="text-muted">Student Assignments</small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card p-3 shadow-sm text-center">
        <h3 class="fw-bold text-warning"><?= $unassigned ?></h3>
        <small class="text-muted">Unassigned Students</small>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Left: Classes List -->
    <div class="col-lg-4">
      <!-- Create Class -->
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-primary text-white">
          <h6 class="mb-0"><i class="bi bi-plus-circle"></i> Create Class</h6>
        </div>
        <div class="card-body">
          <form method="POST">
            <div class="row g-2">
              <div class="col-5">
                <input type="text" name="class_name" class="form-control form-control-sm" placeholder="Class (e.g., 10)" required>
              </div>
              <div class="col-3">
                <input type="text" name="section" class="form-control form-control-sm" placeholder="Sec">
              </div>
              <div class="col-4">
                <input type="text" name="academic_year" class="form-control form-control-sm" placeholder="2024-25" required>
              </div>
            </div>
            <button type="submit" name="create_class" class="btn btn-primary btn-sm w-100 mt-2">
              <i class="bi bi-plus"></i> Create
            </button>
          </form>
        </div>
      </div>

      <!-- Classes List -->
      <div class="card shadow-sm border-0">
        <div class="card-header bg-light">
          <h6 class="mb-0"><i class="bi bi-collection"></i> Classes</h6>
        </div>
        <div class="card-body p-2" style="max-height: 500px; overflow-y: auto;">
          <?php 
          $classes->data_seek(0);
          if ($classes->num_rows > 0): 
            while($c = $classes->fetch_assoc()): 
              $student_count = $conn->query("SELECT COUNT(*) AS c FROM student_assignments WHERE class_id = {$c['id']}")->fetch_assoc()['c'];
              $teacher_count = $conn->query("SELECT COUNT(*) AS c FROM class_teachers WHERE class_id = {$c['id']}")->fetch_assoc()['c'];
          ?>
          <div class="card class-card mb-2 <?= $selected_class == $c['id'] ? 'active' : '' ?>" onclick="window.location='?class_id=<?= $c['id'] ?>'">
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-0 fw-bold">Class <?= htmlspecialchars($c['class_name']) ?><?= $c['section'] ? '-' . $c['section'] : '' ?></h6>
                  <small class="text-muted"><?= htmlspecialchars($c['academic_year']) ?></small>
                </div>
                <div class="text-end">
                  <span class="badge bg-primary"><?= $student_count ?> students</span>
                  <span class="badge bg-secondary"><?= $teacher_count ?> teachers</span>
                </div>
              </div>
            </div>
          </div>
          <?php endwhile; else: ?>
          <p class="text-muted text-center py-3">No classes created yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Right: Class Details & Assignment -->
    <div class="col-lg-8">
      <?php if ($selected_class > 0): 
        $class_info = $conn->query("SELECT * FROM classes WHERE id = $selected_class")->fetch_assoc();
        
        if (!$class_info) {
          echo '<div class="alert alert-danger">Class not found!</div>';
        } else {
          $class_teachers = $conn->query("SELECT ct.*, u.name, u.email FROM class_teachers ct JOIN users u ON ct.teacher_id = u.id WHERE ct.class_id = $selected_class");
          $class_students = $conn->query("SELECT sa.*, u.name, u.email FROM student_assignments sa JOIN users u ON sa.student_id = u.id WHERE sa.class_id = $selected_class ORDER BY u.name");
          $available_students = $conn->query("SELECT u.* FROM users u LEFT JOIN student_assignments sa ON u.id = sa.student_id AND sa.class_id = $selected_class WHERE u.role = 'student' AND sa.id IS NULL ORDER BY u.name");
          
          // Get first teacher for the class
          $first_teacher = $conn->query("SELECT teacher_id FROM class_teachers WHERE class_id = $selected_class LIMIT 1")->fetch_assoc();
          $has_teacher = !empty($first_teacher['teacher_id']);
      ?>
      
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="bi bi-mortarboard"></i> 
            Class <?= htmlspecialchars($class_info['class_name']) ?><?= $class_info['section'] ? '-' . $class_info['section'] : '' ?>
            <small class="ms-2 opacity-75">(<?= htmlspecialchars($class_info['academic_year']) ?>)</small>
          </h5>
          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this class and all assignments?')">
            <input type="hidden" name="class_id" value="<?= $selected_class ?>">
            <button type="submit" name="delete_class" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
          </form>
        </div>
      </div>

      <!-- Assign Teacher to Class -->
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-info text-white">
          <h6 class="mb-0"><i class="bi bi-person-workspace"></i> Assigned Teachers</h6>
        </div>
        <div class="card-body">
          <!-- Current Teachers -->
          <?php 
          $class_teachers->data_seek(0);
          if ($class_teachers->num_rows > 0): ?>
          <div class="mb-3">
            <?php while($ct = $class_teachers->fetch_assoc()): ?>
            <div class="d-inline-flex align-items-center me-2 mb-2 p-2 rounded teacher-badge">
              <span class="me-2"><?= htmlspecialchars($ct['name']) ?></span>
              <form method="POST" class="d-inline">
                <input type="hidden" name="ct_id" value="<?= $ct['id'] ?>">
                <input type="hidden" name="class_id" value="<?= $selected_class ?>">
                <button type="submit" name="remove_teacher" class="btn btn-sm btn-light p-0 px-1" onclick="return confirm('Remove?')">×</button>
              </form>
            </div>
            <?php endwhile; ?>
          </div>
          <?php else: ?>
          <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle"></i> <strong>No teachers assigned yet!</strong> 
            Please assign at least one teacher before adding students.
          </div>
          <?php endif; ?>
          
          <!-- Add Teacher -->
          <form method="POST" class="row g-2">
            <input type="hidden" name="class_id" value="<?= $selected_class ?>">
            <div class="col-md-8">
              <select name="teacher_id" class="form-select form-select-sm" required>
                <option value="">-- Select Teacher --</option>
                <?php 
                $teachers->data_seek(0);
                while($t = $teachers->fetch_assoc()): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['email'] ?>)</option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-4">
              <button type="submit" name="assign_teacher" class="btn btn-info btn-sm w-100">
                <i class="bi bi-plus"></i> Add Teacher
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Assign Students -->
      <div class="row g-3">
        <!-- Available Students -->
        <div class="col-md-6">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-warning text-dark">
              <h6 class="mb-0"><i class="bi bi-people"></i> Available Students (<?= $available_students->num_rows ?>)</h6>
            </div>
            <form method="POST">
              <input type="hidden" name="class_id" value="<?= $selected_class ?>">
              <input type="hidden" name="teacher_id" value="<?= $first_teacher['teacher_id'] ?? 0 ?>">
              
              <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                <?php if (!$has_teacher): ?>
                <div class="alert alert-danger m-3">
                  <i class="bi bi-exclamation-circle"></i> Please assign a teacher to this class first!
                </div>
                <?php elseif ($available_students->num_rows > 0): ?>
                <ul class="list-group list-group-flush">
                  <?php while($s = $available_students->fetch_assoc()): ?>
                  <li class="list-group-item d-flex align-items-center">
                    <input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="student-checkbox me-3">
                    <div>
                      <strong><?= htmlspecialchars($s['name']) ?></strong>
                      <div class="small text-muted"><?= htmlspecialchars($s['email']) ?></div>
                    </div>
                  </li>
                  <?php endwhile; ?>
                </ul>
                <?php else: ?>
                <p class="text-muted text-center py-4">All students assigned!</p>
                <?php endif; ?>
              </div>
              <?php if ($has_teacher && $available_students->num_rows > 0): ?>
              <div class="card-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAll(this)">Select All</button>
                <button type="submit" name="assign_students" class="btn btn-success btn-sm float-end">
                  <i class="bi bi-arrow-right"></i> Assign Selected
                </button>
              </div>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <!-- Assigned Students -->
        <div class="col-md-6">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white">
              <h6 class="mb-0"><i class="bi bi-check-circle"></i> Assigned Students (<?= $class_students->num_rows ?>)</h6>
            </div>
            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
              <?php if ($class_students->num_rows > 0): ?>
              <ul class="list-group list-group-flush">
                <?php while($cs = $class_students->fetch_assoc()): ?>
                <li class="list-group-item student-list-item d-flex justify-content-between align-items-center">
                  <div>
                    <strong><?= htmlspecialchars($cs['name']) ?></strong>
                    <div class="small text-muted"><?= htmlspecialchars($cs['email']) ?></div>
                  </div>
                  <form method="POST">
                    <input type="hidden" name="assignment_id" value="<?= $cs['id'] ?>">
                    <input type="hidden" name="class_id" value="<?= $selected_class ?>">
                    <button type="submit" name="remove_assignment" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove?')">
                      <i class="bi bi-x"></i>
                    </button>
                  </form>
                </li>
                <?php endwhile; ?>
              </ul>
              <?php else: ?>
              <p class="text-muted text-center py-4">No students assigned yet.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <?php 
        } // end if class_info exists
      else: ?>
      <div class="card shadow-sm border-0">
        <div class="card-body text-center py-5">
          <i class="bi bi-arrow-left-circle text-muted" style="font-size: 3rem;"></i>
          <h5 class="mt-3 text-muted">Select a class to manage assignments</h5>
          <p class="text-muted">Click on any class from the left panel to view and manage student-teacher assignments.</p>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function toggleAll(btn) {
  const checkboxes = document.querySelectorAll('.student-checkbox');
  const allChecked = Array.from(checkboxes).every(cb => cb.checked);
  checkboxes.forEach(cb => cb.checked = !allChecked);
  btn.textContent = allChecked ? 'Select All' : 'Deselect All';
}
</script>

<?php include('../includes/footer.php'); ?>