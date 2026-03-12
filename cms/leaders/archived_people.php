<?php
require_once 'config.php';
require_login();

// Fetch Archived/Unresponsive People
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// We join with followup_master to get the LATEST assignment ID and details
// Filter for response_type containing 'Archive' or 'Unresponsive'/'No response'

$sql_people = "SELECT p.*, 
               fm.id as assignment_id, 
               fm.volunteer_id, 
               fm.assigned_volunteer as volunteer_name,
               fm.date_assigned,
               fm.response_type,
               fm.notes,
               fm.updated_at,
               fm.next_action_text,
               fm.status
               FROM people p 
               JOIN followup_master fm ON p.id = fm.person_id 
                    AND fm.id = (SELECT MAX(id) FROM followup_master fm2 WHERE fm2.person_id = p.id)
               WHERE 1=1";

$params = [];

if ($status_filter == 'Archive') {
    $sql_people .= " AND fm.status ='Archive'";
} elseif ($status_filter == 'Unresponsive') {
    $sql_people .= " AND fm.status ='Unresponsive'";
} else {
    // Default 'all'
    $sql_people .= " AND (fm.status ='Archive' 
                      OR fm.status= 'Unresponsive')";
}

$sql_people .= " ORDER BY fm.updated_at DESC";

$stmt = $conn->prepare($sql_people);
$stmt->execute($params);
$people = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Archived & Unresponsive - RM Flow</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f4f6f9;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
        }

        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .back-btn {
            text-decoration: none;
            color: #6c757d;
            font-weight: 500;
        }

        .back-btn:hover {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
            white-space: normal;
            display: inline-block;
            text-align: left;
            line-height: 1.2;
        }

        .badge-archive {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .badge-unresponsive {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container">
        <div class="header-flex">
            <h2><?php echo ($status_filter == 'Archive') ? 'Archived People' : (($status_filter == 'Unresponsive') ? 'Unresponsive Tasks' : 'Archived & Unresponsive List'); ?></h2>
            <div>
                <form method="get" style="display: inline-block; margin-right: 15px;">
                    <select name="status" onchange="this.form.submit()" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                        <option value="all" <?php if ($status_filter == 'all') echo 'selected'; ?>>All Statuses</option>
                        <option value="Archive" <?php if ($status_filter == 'Archive') echo 'selected'; ?>>Archived</option>
                        <option value="Unresponsive" <?php if ($status_filter == 'Unresponsive') echo 'selected'; ?>>Unresponsive</option>
                    </select>
                </form>
                <a href="tl_dashboard.php" class="back-btn">← Back to Dashboard</a>
            </div>
        </div>

        <div class="card">
            <?php if (count($people) > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Last Assigned To</th>
                                <th>Date</th>
                                <th style="width: 160px;">Status / Response</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($people as $p): ?>
                                <?php
                                $status_class = ($p['status'] == 'Archive') ? 'badge-archive' : 'badge-unresponsive';
                                $display_text = !empty($p['next_action_text']) ? $p['next_action_text'] : $p['response_type'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['person_name']); ?></td>
                                    <td><?php echo htmlspecialchars($p['mobile_number']); ?></td>
                                    <td><?php echo htmlspecialchars($p['volunteer_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($p['updated_at'])); ?></td>
                                    <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($display_text); ?></span></td>
                                    <td style="color: #666; font-size: 0.9em;"><?php echo htmlspecialchars($p['notes']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No archived or unresponsive records found.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>