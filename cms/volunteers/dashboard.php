<?php
require_once 'config.php';
require_once 'dashboard_db.php';
require_login();

$msg = '';
$msg_title = '';
$msg_type = '';

if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $msg = "Action completed successfully.";
    $msg_title = "Success";
    $msg_type = "success";
}

$volunteer_id = $_SESSION['volunteer_id'];
$volunteer_name = $_SESSION['volunteer_name'];

// Fetch Team Lead Name
$team_lead_name = get_team_lead($conn, $volunteer_id);

// Fetch assignments for this volunteer
$assignments = get_volunteer_assignments($conn, $volunteer_id);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Assignments</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
    <!-- <link rel="stylesheet" href="../style.css"> -->
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Apple-like UI Variables */
        :root {
            --bg-color: #F2F2F7;
            --card-bg: #FFFFFF;
            --primary-color: #007AFF;
            --text-primary: #000000;
            --text-secondary: #8E8E93;
            --separator: #C6C6C8;
            --radius-l: 12px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --font-stack: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background: var(--bg-color);
            /* Overrides external background-image/gradient */
            font-family: var(--font-stack);
            margin: 0;
            padding-bottom: 40px;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        /* Header */
        .app-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .header-left p {
            margin: 2px 0 0 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .logout-btn {
            font-size: 15px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        /* List Container */
        .list-container {
            padding: 20px 16px;
            max-width: 600px;
            margin: 0 auto;
        }

        .section-title {
            font-size: 13px;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 10px;
            padding-left: 10px;
            font-weight: 600;
        }

        /* Card Style */
        .task-card {
            background: var(--card-bg);
            border-radius: var(--radius-l);
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: transform 0.2s ease;
        }

        .task-card:active {
            transform: scale(0.98);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .person-name {
            font-size: 17px;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
        }

        .person-phone {
            font-size: 15px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .status-badge {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 100px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #E5E5EA;
            color: #8E8E93;
        }

        .status-contacted {
            background: #E4F9E4;
            color: #34C759;
        }

        .card-footer {
            border-top: 1px solid #E5E5EA;
            padding-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .last-update {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .btn-action {
            background: var(--primary-color);
            color: white;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-action.update {
            background: #E5E5EA;
            color: var(--primary-color);
        }
    </style>
</head>

<body>

    <header class="app-header">
        <div class="header-left">
            <h1>Assignments</h1>
            <p><?php echo htmlspecialchars($volunteer_name); ?> (Team Lead: <?php echo htmlspecialchars($team_lead_name); ?>)</p>
        </div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </header>

    <div class="list-container">
        <div class="section-title">My List</div>

        <?php if (count($assignments) > 0): ?>
            <?php foreach ($assignments as $task): ?>
                <?php
                $is_contacted = ($task['is_contacted'] == 'Yes');
                $status_class = $is_contacted ? 'status-contacted' : 'status-pending';
                $status_text = $is_contacted ? 'Contacted' : 'Pending';
                ?>
                <div class="task-card">
                    <div class="card-header">
                        <div>
                            <h2 class="person-name"><?php echo htmlspecialchars($task['person_name']); ?></h2>
                            <div class="person-phone"><?php echo htmlspecialchars($task['phone']); ?></div>
                        </div>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </div>
                    <?php if (strtolower($task['response_type']) === 'no response'): ?>
                        <div style="font-size: 13px; color: #FF9500; font-weight: 500;">
                            <?php
                            echo htmlspecialchars(
                                'Contacted 3 days Ago'
                            );
                            ?>

                        </div>
                    <?php endif; ?>
                    <div class="card-footer">
                        <span class="last-update">
                            <?php echo $task['updated_at'] ? date('M j', strtotime($task['updated_at'])) : 'Assigned: ' . date('M j', strtotime($task['date_assigned'])); ?>
                        </span>
                        <a href="followup_form.php?assignment_id=<?php echo $task['id']; ?>&volunteer_id=<?php echo $volunteer_id; ?>&redirect=dashboard"
                            class="btn-action <?php echo $is_contacted ? 'update' : ''; ?>">
                            <?php echo $is_contacted ? 'Update Status' : 'Start Follow-up'; ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="task-card" style="text-align: center; color: var(--text-secondary);">
                <p>No assignments yet.</p>
            </div>
        <?php endif; ?>

    </div>

    <!-- Notifications -->
    <!-- <script src="../notifications.js"></script> -->
    <script>
        <?php if ($msg): ?>
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: '<?php echo $msg_type; ?>',
                title: '<?php echo $msg_title; ?>',
                text: '<?php echo $msg; ?>',
                showConfirmButton: false,
                timer: 3000
            });
        <?php endif; ?>
    </script>
</body>

</html>