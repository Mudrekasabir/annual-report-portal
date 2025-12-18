<?php
include('includes/db_connect.php');
include('includes/header.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
  $role = $_POST['role'];

  // Only allow teacher or student signup
  if ($role == 'admin') {
    echo "<div class='alert alert-danger'>❌ You cannot register as an admin.</div>";
  } else {
    // Teacher = pending approval, Student = approved immediately
    $approval_status = ($role == 'teacher') ? 'pending' : 'approved';

    // Check if email exists already
    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
      echo "<div class='alert alert-danger mt-3'>Email already registered! Please use another email.</div>";
    } else {
      // Insert new user
      $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, approval_status) VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param("sssss", $name, $email, $password, $role, $approval_status);
      $stmt->execute();

      if ($role == 'teacher') {
        echo "<div class='alert alert-info mt-3'>
                👨‍🏫 Teacher account created! Please wait for admin approval before logging in.
              </div>";
      } else {
        echo "<div class='alert alert-success mt-3'>
                🎓 Student registered successfully! <a href='login.php'>Login here</a>.
              </div>";
      }
    }
  }
}
?>

<div class="card p-4 mt-4">
  <h2 class="mb-3"><i class="bi bi-person-plus"></i> Signup</h2>
  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Full Name</label>
      <input name="name" type="text" class="form-control" placeholder="Enter your full name" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Email</label>
      <input name="email" type="email" class="form-control" placeholder="Enter your email" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Password</label>
      <input name="password" type="password" class="form-control" placeholder="Create a password" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Select Role</label>
      <select name="role" class="form-select" required>
        <option value="">-- Choose Role --</option>
        <option value="teacher">Teacher</option>
        <option value="student">Student</option>
      </select>
    </div>

    <button type="submit" class="btn btn-primary w-100">
      <i class="bi bi-person-add"></i> Register
    </button>
  </form>
</div>

<?php include('includes/footer.php'); ?>
