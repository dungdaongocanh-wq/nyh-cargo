</main>
<!-- /MAIN CONTENT -->
</div>
<!-- /WRAPPER -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
<script>
// Sidebar toggle for mobile
document.getElementById('sidebarToggle')?.addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('d-none');
});
</script>
</body>
</html>