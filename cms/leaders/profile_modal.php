<?php
// profile_modal.php - User Profile Modal
require_once 'config.php';
require_login();

$user_id = $_SESSION['user_id'];
$team_lead_id = $_SESSION['team_lead_id'];

// Fetch current data for the modal
try {
    // User data
    $stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // Team Lead data if applicable
    $tl_data = null;
    if ($team_lead_id) {
        $stmt_tl = $conn->prepare("SELECT * FROM team_leads WHERE team_lead_id = ?");
        $stmt_tl->execute([$team_lead_id]);
        $tl_data = $stmt_tl->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Silent error for modal data fetch
}

$first_name_val = $tl_data['first_name'] ?? explode(' ', $user_data['full_name'])[0];
$last_name_val = $tl_data['last_name'] ?? (explode(' ', $user_data['full_name'])[1] ?? '');
?>

<!-- Profile Edit Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel"><i class="fas fa-user-edit me-2"></i>Edit Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="profileUpdateForm">
                <div class="modal-body">
                    <div id="profileAlert" class="alert d-none"></div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name_val); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name_val); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email (Login/Contact)</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($tl_data['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" name="mobile_number" class="form-control" value="<?php echo htmlspecialchars($user_data['mobile_number'] ?? ''); ?>">
                        </div>
                    </div>

                    <?php if ($team_lead_id): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Phone (Work)</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($tl_data['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Campus</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($tl_data['campus'] ?? 'N/A'); ?>" readonly disabled>
                            <small class="text-muted">Contact Admin to change campus.</small>
                        </div>
                    </div>
                    <?php endif; ?>

                    <hr>
                    <h6 class="mb-3 text-primary">Change Password (Optional)</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                        <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('profileUpdateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const btn = document.getElementById('saveProfileBtn');
    const spinner = btn.querySelector('.spinner-border');
    const alertDiv = document.getElementById('profileAlert');
    
    // Reset alert
    alertDiv.className = 'alert d-none';
    
    // Show spinner and disable button
    spinner.classList.remove('d-none');
    btn.disabled = true;
    
    const formData = new FormData(form);
    
    fetch('update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        spinner.classList.add('d-none');
        btn.disabled = false;
        
        if (data.success) {
            alertDiv.className = 'alert alert-success';
            alertDiv.textContent = data.message;
            alertDiv.classList.remove('d-none');
            
            // Update the name in the header if it exists
            const nameSpan = document.querySelector('.nav-links span[style*="font-weight: bold"]');
            if (nameSpan) {
                const currentRole = nameSpan.textContent.split('(')[1];
                nameSpan.textContent = data.full_name + ' (' + currentRole;
            }
            
            // Close modal after 1.5 seconds
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
                modal.hide();
                // Optional: reload page if needed for other components
                // location.reload();
            }, 1500);
        } else {
            alertDiv.className = 'alert alert-danger';
            alertDiv.textContent = data.message;
            alertDiv.classList.remove('d-none');
        }
    })
    .catch(error => {
        spinner.classList.add('d-none');
        btn.disabled = false;
        alertDiv.className = 'alert alert-danger';
        alertDiv.textContent = 'An error occurred. Please try again.';
        alertDiv.classList.remove('d-none');
        console.error('Error:', error);
    });
});
</script>