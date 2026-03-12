<?php
// followups.php
require_once 'config.php';
require_login();

$filter_volunteer = isset($_GET['volunteer_id']) ? $_GET['volunteer_id'] : 'all';

// Build Query
$sql = "SELECT f.*, v.volunteer_name as vol_name_real 
        FROM followup_master f 
        LEFT JOIN volunteers v ON f.volunteer_id = v.volunteer_id 
        WHERE 1=1";

$params = [];

if ($_SESSION['role'] == 'Team Lead') {
    // Team Lead sees only their team's assignments
    $sql .= " AND v.team_lead_id = :team_lead_id";
    $params[':team_lead_id'] = $_SESSION['user_id'];
}

if ($filter_volunteer != 'all') {
    $sql .= " AND f.volunteer_id = :volunteer_id";
    $params[':volunteer_id'] = $filter_volunteer;
}

$sql .= " ORDER BY f.date_assigned DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll();

// Get Volunteers for Filter
$sql_vols = "SELECT volunteer_id, volunteer_name FROM volunteers";
if ($_SESSION['role'] == 'Team Lead') {
    $sql_vols .= " WHERE team_lead_id = " . intval($_SESSION['user_id']);
}
$stmt_vols = $conn->query($sql_vols);
$vols = $stmt_vols->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Follow-up Master - RM Flow</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Follow-up Master List</h3>

                <!-- Filter -->
                <form method="get" style="display:flex; align-items:center;">
                    <label style="margin-right:10px;">Filter by Volunteer:</label>
                    <select name="volunteer_id" onchange="this.form.submit()" class="form-control" style="width:200px; display:inline-block;">
                        <option value="all">All Volunteers</option>
                        <?php foreach ($vols as $v): ?>
                            <option value="<?php echo $v['volunteer_id']; ?>" <?php echo ($filter_volunteer == $v['volunteer_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['volunteer_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Volunteer ID</th>
                            <th>Person Name</th>
                            <th>Mobile</th>
                            <th>Assigned Volunteer</th>
                            <th>Date Assigned</th>
                            <th>Contacted?</th>
                            <th>Response</th>
                            <th>Crisis?</th>
                            <th>Next Action</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($result) > 0): ?>
                            <?php foreach ($result as $row): ?>
                                <?php
                                // Row Highlighting Logic
                                $row_style = "";
                                if ($row['is_crisis'] == 'Yes') {
                                    $row_style = "background-color: #ffe6e6;"; // Light Red
                                } elseif ($row['response_type'] == 'No response' || $row['response_type'] == 'No Response') {
                                    $row_style = "background-color: #fff3cd;"; // Light Yellow
                                } elseif ($row['is_contacted'] == 'Yes') {
                                    $row_style = "background-color: #d4edda;"; // Light Green
                                }
                                ?>
                                <tr style="<?php echo $row_style; ?>">
                                    <td><?php echo $row['volunteer_id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['person_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['mobile_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['assigned_volunteer']); ?></td>
                                    <td><?php echo $row['date_assigned']; ?></td>
                                    <td><?php echo $row['is_contacted']; ?></td>
                                    <td><?php echo $row['response_type'] ? $row['response_type'] : 'Pending'; ?></td>
                                    <td style="font-weight:bold; color: <?php echo ($row['is_crisis'] == 'Yes' ? 'red' : 'green'); ?>">
                                        <?php echo $row['is_crisis']; ?>
                                    </td>
                                    <td><?php echo $row['next_action_date']; ?></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($row['notes']); ?>">
                                        <?php echo htmlspecialchars($row['notes']); ?>
                                    </td>
                                    <td>
                                        <a href="edit_followup.php?id=<?php echo $row['id']; ?>&redirect=followups" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Update</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">No records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>