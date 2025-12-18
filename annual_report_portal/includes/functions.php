<?php
// ============ FILE: includes/functions.php ============
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Show a bootstrap alert
 */
function alert($message, $type = 'info') {
  echo "<div class='alert alert-$type alert-dismissible fade show text-center mt-3' role='alert'>
          $message
          <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
}

/**
 * Log an action to admin_logs and activity_log
 */
function log_action($conn, $user_id, $message, $role = null) {
  if ($stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_desc) VALUES (?, ?)")) {
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
    $stmt->close();
  }

  $r = $role ?? ($_SESSION['role'] ?? null);
  if ($stmt2 = $conn->prepare("INSERT INTO activity_log (user_id, role, activity) VALUES (?, ?, ?)")) {
    $stmt2->bind_param("iss", $user_id, $r, $message);
    $stmt2->execute();
    $stmt2->close();
  }
}

/**
 * Insert a notification for a user
 */
function notify_user($conn, $user_id, $message) {
  if ($stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())")) {
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
    $stmt->close();
  }
}

/**
 * Mark notifications read for a user
 */
function mark_notifications_read($conn, $user_id) {
  if ($stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
  }
}

// ============ NEW COORDINATOR FUNCTIONS ============

/**
 * Check if user is a valid coordinator with active access
 */
function is_valid_coordinator($conn, $user_id) {
  $stmt = $conn->prepare("
    SELECT id FROM coordinators 
    WHERE user_id = ? 
    AND status = 'active' 
    AND NOW() BETWEEN access_start AND access_end
  ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $valid = $result->num_rows > 0;
  $stmt->close();
  return $valid;
}

/**
 * Check if coordinator has specific permission
 */
function coordinator_has_permission($conn, $user_id, $permission) {
  $stmt = $conn->prepare("
    SELECT permissions FROM coordinators 
    WHERE user_id = ? 
    AND status = 'active' 
    AND NOW() BETWEEN access_start AND access_end
  ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($row = $result->fetch_assoc()) {
    $perms = explode(',', $row['permissions']);
    $stmt->close();
    return in_array($permission, $perms);
  }
  $stmt->close();
  return false;
}

/**
 * Get coordinator's remaining access time
 */
function get_coordinator_time_left($conn, $user_id) {
  $stmt = $conn->prepare("
    SELECT access_end FROM coordinators 
    WHERE user_id = ? 
    AND status = 'active' 
    AND NOW() BETWEEN access_start AND access_end
  ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($row = $result->fetch_assoc()) {
    $end = new DateTime($row['access_end']);
    $now = new DateTime();
    $diff = $now->diff($end);
    $stmt->close();
    return $diff->days . ' days, ' . $diff->h . ' hours';
  }
  $stmt->close();
  return null;
}

/**
 * Check if user can approve reports (admin or valid coordinator)
 */
function can_approve_reports($conn, $user_id, $role) {
  if ($role == 'admin') return true;
  if ($role == 'coordinator') {
    return coordinator_has_permission($conn, $user_id, 'approve_reports');
  }
  return false;
}

/**
 * Get coordinator info
 */
function get_coordinator_info($conn, $user_id) {
  $stmt = $conn->prepare("
    SELECT c.*, u.name, u.email 
    FROM coordinators c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.user_id = ? 
    AND c.status = 'active' 
    AND NOW() BETWEEN c.access_start AND c.access_end
  ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $info = $result->fetch_assoc();
  $stmt->close();
  return $info;
}

// ============ STUDENT-TEACHER ASSIGNMENT FUNCTIONS ============

/**
 * Get students assigned to a teacher
 */
function get_teacher_students($conn, $teacher_id) {
  $stmt = $conn->prepare("
    SELECT u.*, sa.class_id, sa.id as assignment_id, c.class_name, c.section, c.academic_year
    FROM student_assignments sa 
    JOIN users u ON sa.student_id = u.id 
    JOIN classes c ON sa.class_id = c.id 
    WHERE sa.teacher_id = ? 
    ORDER BY c.class_name, c.section, u.name
  ");
  $stmt->bind_param("i", $teacher_id);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result;
}

/**
 * Get classes assigned to a teacher
 */
function get_teacher_classes($conn, $teacher_id) {
  $stmt = $conn->prepare("
    SELECT c.*, ct.id as ct_id,
      (SELECT COUNT(*) FROM student_assignments WHERE class_id = c.id AND teacher_id = ?) as student_count
    FROM classes c 
    JOIN class_teachers ct ON c.id = ct.class_id 
    WHERE ct.teacher_id = ? 
    ORDER BY c.academic_year DESC, c.class_name, c.section
  ");
  $stmt->bind_param("ii", $teacher_id, $teacher_id);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result;
}

/**
 * Get class info by ID
 */
function get_class_info($conn, $class_id) {
  $stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
  $stmt->bind_param("i", $class_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $info = $result->fetch_assoc();
  $stmt->close();
  return $info;
}

/**
 * Get students in a specific class
 */
function get_class_students($conn, $class_id) {
  $stmt = $conn->prepare("
    SELECT u.*, sa.id as assignment_id, sa.teacher_id, t.name as teacher_name
    FROM student_assignments sa 
    JOIN users u ON sa.student_id = u.id 
    LEFT JOIN users t ON sa.teacher_id = t.id
    WHERE sa.class_id = ? 
    ORDER BY u.name
  ");
  $stmt->bind_param("i", $class_id);
  $stmt->execute();
  return $stmt->get_result();
}

/**
 * Get teachers assigned to a class
 */
function get_class_teachers($conn, $class_id) {
  $stmt = $conn->prepare("
    SELECT u.*, ct.id as ct_id 
    FROM class_teachers ct 
    JOIN users u ON ct.teacher_id = u.id 
    WHERE ct.class_id = ?
    ORDER BY u.name
  ");
  $stmt->bind_param("i", $class_id);
  $stmt->execute();
  return $stmt->get_result();
}

/**
 * Check if student is assigned to any class
 */
function is_student_assigned($conn, $student_id) {
  $stmt = $conn->prepare("SELECT id FROM student_assignments WHERE student_id = ? LIMIT 1");
  $stmt->bind_param("i", $student_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $assigned = $result->num_rows > 0;
  $stmt->close();
  return $assigned;
}

/**
 * Get unassigned students
 */
function get_unassigned_students($conn) {
  return $conn->query("
    SELECT u.* FROM users u 
    LEFT JOIN student_assignments sa ON u.id = sa.student_id 
    WHERE u.role = 'student' AND sa.id IS NULL 
    ORDER BY u.name
  ");
}

// ============ ACCESS CONTROL HELPERS ============

/**
 * Check if current user has admin or coordinator access
 */
function has_admin_access($conn) {
  if (!isset($_SESSION['user_id'])) return false;
  
  $role = $_SESSION['role'] ?? '';
  if ($role == 'admin') return true;
  if ($role == 'coordinator') {
    return is_valid_coordinator($conn, $_SESSION['user_id']);
  }
  return false;
}

/**
 * Require admin or coordinator access, redirect if not
 */
function require_admin_access($conn, $redirect = '../login.php') {
  if (!has_admin_access($conn)) {
    header("Location: $redirect");
    exit();
  }
}

/**
 * Require specific permission for coordinator
 */
function require_permission($conn, $permission, $redirect = '../login.php') {
  if (!isset($_SESSION['user_id'])) {
    header("Location: $redirect");
    exit();
  }
  
  $role = $_SESSION['role'] ?? '';
  if ($role == 'admin') return true;
  
  if ($role == 'coordinator') {
    if (!coordinator_has_permission($conn, $_SESSION['user_id'], $permission)) {
      alert("You don't have permission: $permission", 'danger');
      exit();
    }
    return true;
  }
  
  header("Location: $redirect");
  exit();
}
?>