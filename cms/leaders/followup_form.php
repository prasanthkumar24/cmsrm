<?php
// followup_form.php
require_once 'config.php';
require_login();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id == 0) {
    die("Invalid ID");
}

// Fetch Task
$stmt = $conn->prepare("SELECT f.*, p.person_name, p.mobile_number 
                        FROM followup_master f 
                        JOIN people p ON f.person_id = p.id 
                        WHERE f.id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();

if (!$task) {
    die("Task not found.");
}

// Check permissions? (TL should be able to edit their own or escalated?)
// For now allow TL to edit any task they navigate to.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Update Follow-up - RM Flow</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <div class="card-header">
                <h3 class="card-title">Update Follow-up</h3>
            </div>

            <div style="margin-bottom: 20px;">
                <strong>Person:</strong> <?php echo htmlspecialchars($task['person_name']); ?><br>
                <strong>Mobile:</strong> <?php echo htmlspecialchars($task['mobile_number']); ?><br>
                <strong>Current Status:</strong> <?php echo htmlspecialchars($task['status'] ?? 'Active'); ?><br>
            </div>

            <form action="form_db.php" method="post">
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div class="form-group">
                    <label>Response Type</label>
                    <select name="response_type" class="form-control" required>
                        <option value="">Select Response...</option>
                        <option value="Normal">Normal (Connected)</option>
                        <option value="Needs Followup">Needs Followup (Escalate to TL)</option>
                        <option value="Crisis">Crisis (Escalate to TL)</option>
                        <option value="No response">No response</option>
                        <option value="Not Contacted">Not Contacted</option>
                        <option value="Close">Close (Archive)</option>
                        <?php if ($_SESSION['role'] != 'Admin'): ?>
                            <option value="Escalate to Pastor">Escalate to Pastor</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($task['notes']); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Update Task</button>
                <a href="people_list.php" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
            </form>
        </div>
    </div>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
