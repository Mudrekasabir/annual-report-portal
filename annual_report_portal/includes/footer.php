</div> <!-- end .container from header -->

<!-- Loader -->
<div id="globalLoader" class="d-flex">
  <div class="spinner-border" role="status">
    <span class="visually-hidden">Loading...</span>
  </div>
</div>

<!-- Toast Container -->
<div id="toastContainer"></div>

<footer class="text-center mt-5 py-3 bg-light border-top">
  <small class="text-muted">
    © <?= date('Y') ?> Annual Report Portal | Developed by Mudreka Sabir
  </small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Simple global loader controls
function showLoader() {
  const el = document.getElementById('globalLoader');
  if (el) el.style.display = 'flex';
}
function hideLoader() {
  const el = document.getElementById('globalLoader');
  if (el) el.style.display = 'none';
}

// Toast system
function showToast(message, type = 'info') {
  const container = document.getElementById('toastContainer');
  if (!container) return;
  const div = document.createElement('div');
  div.className = 'custom-toast ' + (type === 'success' ? 'success' : type === 'error' ? 'error' : '');
  div.innerHTML = `
    <span>${message}</span>
    <button class="close-btn">&times;</button>
  `;
  container.appendChild(div);
  const closeBtn = div.querySelector('.close-btn');
  closeBtn.onclick = () => div.remove();
  setTimeout(() => div.remove(), 4000);
}
</script>

</body>
</html>
