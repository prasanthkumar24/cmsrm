<?php
// escalated_tasks.php
require_once 'config.php';
require_login();

// Fetch Escalated or Unassigned tasks
// "Escalated" status OR volunteer_id IS NULL
$stmt = $conn->query("SELECT f.*, p.person_name, p.mobile_number 
                      FROM followup_master f 
                      JOIN people p ON f.person_id = p.id 
                      WHERE f.status = 'Escalated' OR f.volunteer_id IS NULL 
                      ORDER BY f.updated_at DESC");
$tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Escalated / Unassigned Tasks - RM Flow</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Escalated / Unassigned Tasks</h3>
                <a href="dashboard.php" class="btn btn-secondary" style="font-size: 0.9em;">Back to Dashboard</a>
            </div>

            <?php if (count($tasks) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Person</th>
                            <th>Mobile</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Last Updated</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['person_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['mobile_number']); ?></td>
                                <td>
                                    <span class="badge" style="background: #ffc107; padding: 4px 8px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($row['status'] ?? 'Unassigned'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['notes']); ?></td>
                                <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
                                <td>
                                    <!-- Assign to Me? Or just view? -->
                                    <!-- For now, allow viewing/updating which effectively claims it if they change status? -->
                                    <!-- Or maybe a specific "Claim" button? -->
                                    <a href="followup_form.php?id=<?php echo $row['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.9em;">View/Update</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No escalated or unassigned tasks found.</p>
            <?php endif; ?>
        </div>
    </div>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>