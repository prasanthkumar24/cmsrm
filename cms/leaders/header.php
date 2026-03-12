<?php
// header.php
if (!isset($_SESSION)) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header>
    <script src="notifications.js"></script>
    <link rel="stylesheet" href="toast.css">
    <script src="toast.js"></script>
    <div class="logo">CHURCH MANAGEMENT</div>
    <nav class="nav-links">
        <a href="#" onclick="openExternalAddModal('<?php echo defined('VISITORS_URL') ? VISITORS_URL : '../visitors/index.php'; ?>'); return false;">Add New People</a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'Pastor'): ?>
            <a href="pastor_dashboard.php" class="<?php echo $current_page == 'pastor_dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
        <?php else: ?>
            <a href="tl_dashboard.php" class="<?php echo $current_page == 'tl_dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
        <?php endif; ?>

        <a href="people_list.php" class="<?php echo $current_page == 'people_list.php' ? 'active' : ''; ?>">People List</a>
        <a href="manage_volunteer.php" class="<?php echo $current_page == 'manage_volunteer.php' ? 'active' : ''; ?>">Volunteers</a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'Pastor'): ?>
            <a href="manage_users.php" class="<?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>">Manage Users</a>
        <?php endif; ?>

        <a href="archived_people.php" class="<?php echo $current_page == 'archived_people.php' ? 'active' : ''; ?>">Archive/Unresponsive</a>

        <span style="margin-left: 20px; color: #777;">|</span>
        <span style="margin-left: 10px; font-weight: bold;"><?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo $_SESSION['role']; ?>)</span>
        <a href="#" title="Edit Profile" style="margin-left: 10px; color: #333; text-decoration: none;" data-bs-toggle="modal" data-bs-target="#profileModal">
            <i class="fas fa-user-circle fa-lg"></i>
        </a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
</header>

<?php include 'profile_modal.php'; ?>

<!-- External Page Modal (Bootstrap) -->
<div class="modal fade" id="externalModal" tabindex="-1" aria-labelledby="externalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content" style="height: 90vh;">
            <div class="modal-header">
                <h5 class="modal-title" id="externalModalLabel">Add New Person</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="externalFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    function openExternalAddModal(url) {
        var modalElement = document.getElementById('externalModal');
        var frame = document.getElementById('externalFrame');

        // Set src before showing
        frame.src = url;

        // Use Bootstrap 5 API
        var myModal = new bootstrap.Modal(modalElement);
        myModal.show();

        // Clean up on close
        modalElement.addEventListener('hidden.bs.modal', function() {
            frame.src = '';
            // Destroy instance to avoid duplicates/memory leaks if needed, 
            // though typically BS5 handles re-instantiation fine or we should reuse the instance.
            // For simplicity, we just clear src.
        });
    }
</script>