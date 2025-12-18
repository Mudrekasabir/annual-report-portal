<?php
include('../includes/db_connect.php');
include('../includes/header.php');
if (session_status()===PHP_SESSION_NONE) session_start();
if ($_SESSION['role']!='admin'){ alert('Access denied','danger'); exit(); }

// Data: counts per year
$years = $conn->query("SELECT academic_year, COUNT(*) AS c FROM annual_reports GROUP BY academic_year ORDER BY academic_year DESC");
$labels=[]; $counts=[];
while($y=$years->fetch_assoc()){ $labels[]=$y['academic_year']; $counts[]=(int)$y['c']; }

// Status counts
$stat = $conn->query("SELECT status, COUNT(*) AS c FROM annual_reports GROUP BY status");
$statusLabels=[]; $statusCounts=[];
while($s=$stat->fetch_assoc()){ $statusLabels[]=$s['status']; $statusCounts[]=(int)$s['c']; }
?>
<div class="container mt-4">
  <h2>Analytics</h2>
  <div class="row mt-3">
    <div class="col-md-8">
      <div class="card p-3 shadow-sm">
        <canvas id="byYear"></canvas>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-3 shadow-sm">
        <canvas id="byStatus"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
const counts = <?= json_encode($counts) ?>;
const ctx = document.getElementById('byYear');
new Chart(ctx, {
  type:'bar',
  data:{labels:labels,datasets:[{label:'Reports by Year',data:counts}]},
  options:{responsive:true}
});

const statusLabels = <?= json_encode($statusLabels) ?>;
const statusCounts = <?= json_encode($statusCounts) ?>;
const ctx2 = document.getElementById('byStatus');
new Chart(ctx2,{ type:'doughnut', data:{labels:statusLabels,datasets:[{data:statusCounts}]}, options:{responsive:true} });
</script>
<?php include('../includes/footer.php'); ?>
