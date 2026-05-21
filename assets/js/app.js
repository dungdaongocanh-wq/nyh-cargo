/* ============================================================
   NYH CARGO - Main JS
   ============================================================ */

// Auto-dismiss alerts after 4s
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        document.querySelectorAll('.alert.alert-dismissible').forEach(function (el) {
            bootstrap.Alert.getOrCreateInstance(el).close();
        });
    }, 4000);
});

// Confirm delete
function confirmDelete(msg) {
    return confirm(msg || 'Are you sure you want to delete this record?');
}