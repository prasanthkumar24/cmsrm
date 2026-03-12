<?php
require_once __DIR__ . '/BaseRepository.php';

class PastorRepository extends BaseRepository
{
    public function getSystemHealth()
    {
        $metrics = [];

        // Helper for trends (Current vs Previous Month)
        $prevMonth = date('m', strtotime('-1 month'));
        $prevYear = date('Y', strtotime('-1 month'));
        $currMonth = date('m');
        $currYear = date('Y');

        // 1. Active Volunteers
        $stmt = $this->conn->query("SELECT COUNT(*) FROM volunteers WHERE status = 'Active'");
        $metrics['active_volunteers'] = $stmt->fetchColumn();

        // 2. Active Team Leads
        $stmt = $this->conn->query("SELECT COUNT(*) FROM team_leads");
        $metrics['active_team_leads'] = $stmt->fetchColumn();

        // 3. First-Time Visitors MTD
        try {
            $sql = "SELECT COUNT(*) FROM people 
                    WHERE visit_type = 'First-Time Visitor'
                    AND MONTH(first_visit_date) = MONTH(CURDATE()) 
                    AND YEAR(first_visit_date) = YEAR(CURDATE())";
            $metrics['first_time_mtd'] = $this->conn->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            $metrics['first_time_mtd'] = 0;
        }

        // 4. Follow-Ups Completed MTD
        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE status NOT IN ('Active', 'Escalated') 
                AND MONTH(updated_at) = MONTH(CURDATE()) 
                AND YEAR(updated_at) = YEAR(CURDATE())";
        $metrics['completed_mtd'] = $this->conn->query($sql)->fetchColumn();

        // 5. System vNPS
        try {
            $currentQuarter = 'Q' . ceil(date('n') / 3);
            $currentYearVal = date('Y');
            $sql = "SELECT AVG(vnps_score) FROM vnps_surveys WHERE quarter = ? AND year = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$currentQuarter, $currentYearVal]);
            $vnps = $stmt->fetchColumn();
            $metrics['system_vnps'] = ($vnps !== false && $vnps !== null) ? round($vnps, 1) : 'N/A';

            // Prev vNPS (Previous Quarter)
            $prevQuarterNum = ceil(date('n') / 3) - 1;
            $prevYearVal = date('Y');
            if ($prevQuarterNum == 0) {
                $prevQuarterNum = 4;
                $prevYearVal--;
            }
            $prevQuarter = 'Q' . $prevQuarterNum;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$prevQuarter, $prevYearVal]);
            $vnpsPrev = $stmt->fetchColumn();
            $metrics['system_vnps_prev'] = ($vnpsPrev !== false && $vnpsPrev !== null) ? round($vnpsPrev, 1) : 0;
        } catch (PDOException $e) {
            $metrics['system_vnps'] = 'N/A';
            $metrics['system_vnps_prev'] = 0;
        }

        // 6. Volunteer Retention (3-month cohort logic)
        $sql = "SELECT 
                (COUNT(CASE WHEN status = 'Active' THEN 1 END) * 100.0 / COUNT(*))
                FROM volunteers
                WHERE start_date <= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        $retention = $this->conn->query($sql)->fetchColumn();
        $metrics['retention_rate_raw'] = ($retention !== false) ? round($retention) : 0;
        $metrics['retention_rate'] = $metrics['retention_rate_raw'] . '%';
        $metrics['retention_rate_val'] = $metrics['retention_rate_raw']; // Keep for compat

        // Prev Retention (Approximation or separate query)
        // For now, assume stable or fetch from historical log if existed. We'll set a dummy prev for trend.
        $metrics['retention_rate_prev'] = $metrics['retention_rate_raw']; // No trend line change if no data

        // 7. Completion Rate MTD
        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE MONTH(date_assigned) = MONTH(CURDATE()) 
                AND YEAR(date_assigned) = YEAR(CURDATE())";
        $assigned_mtd = $this->conn->query($sql)->fetchColumn();
        $metrics['completion_rate_raw'] = ($assigned_mtd > 0) ? round(($metrics['completed_mtd'] / $assigned_mtd) * 100) : 0;
        $metrics['completion_rate_val'] = $metrics['completion_rate_raw'];
        $metrics['completion_rate'] = $metrics['completion_rate_raw'] . '%';

        // Prev Completion Rate
        $sql = "SELECT COUNT(*) FROM followup_master WHERE MONTH(date_assigned) = ? AND YEAR(date_assigned) = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$prevMonth, $prevYear]);
        $assigned_prev = $stmt->fetchColumn();

        $sql = "SELECT COUNT(*) FROM followup_master WHERE status NOT IN ('Active', 'Escalated') AND MONTH(updated_at) = ? AND YEAR(updated_at) = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$prevMonth, $prevYear]);
        $completed_prev = $stmt->fetchColumn();

        $metrics['completion_rate_prev'] = ($assigned_prev > 0) ? round(($completed_prev / $assigned_prev) * 100) : 0;

        // 8. Avg Response Time (Days)
        $sql = "SELECT AVG(DATEDIFF(updated_at, date_assigned)) FROM followup_master 
                WHERE status != 'Active'
                AND MONTH(updated_at) = MONTH(CURDATE()) 
                AND YEAR(updated_at) = YEAR(CURDATE())";
        $avg_days = $this->conn->query($sql)->fetchColumn();
        $metrics['avg_response_time'] = ($avg_days !== false) ? round($avg_days, 1) . ' days' : 'N/A';

        // 9. First Contact < 48h
        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE DATEDIFF(updated_at, date_assigned) <= 2 
                AND status != 'Active'
                AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())";
        $fast = $this->conn->query($sql)->fetchColumn();

        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE status != 'Active'
                AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())";
        $total_processed = $this->conn->query($sql)->fetchColumn();

        $metrics['first_contact_rate_raw'] = ($total_processed > 0) ? round(($fast / $total_processed) * 100) : 0;
        $metrics['first_contact_rate'] = $metrics['first_contact_rate_raw'] . '%';

        // Prev First Contact
        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE DATEDIFF(updated_at, date_assigned) <= 2 
                AND status != 'Active'
                AND MONTH(updated_at) = ? AND YEAR(updated_at) = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$prevMonth, $prevYear]);
        $fastPrev = $stmt->fetchColumn();

        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE status != 'Active'
                AND MONTH(updated_at) = ? AND YEAR(updated_at) = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$prevMonth, $prevYear]);
        $totalPrev = $stmt->fetchColumn();

        $metrics['first_contact_rate_prev'] = ($totalPrev > 0) ? round(($fastPrev / $totalPrev) * 100) : 0;

        // 10. Escalation Rate
        try {
            $sql = "SELECT COUNT(*) FROM escalations 
                    WHERE MONTH(escalation_date) = MONTH(CURDATE()) AND YEAR(escalation_date) = YEAR(CURDATE())";
            $escalations = $this->conn->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            $escalations = 0;
        }
        $metrics['escalation_rate_raw'] = ($assigned_mtd > 0) ? round(($escalations / $assigned_mtd) * 100) : 0;
        $metrics['escalation_rate'] = $metrics['escalation_rate_raw'] . '%';

        // Prev Escalation Rate
        try {
            $sql = "SELECT COUNT(*) FROM escalations 
                    WHERE MONTH(escalation_date) = ? AND YEAR(escalation_date) = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$prevMonth, $prevYear]);
            $escalationsPrev = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $escalationsPrev = 0;
        }
        $metrics['escalation_rate_prev'] = ($assigned_prev > 0) ? round(($escalationsPrev / $assigned_prev) * 100) : 0;

        // 11. Crisis Handled Safely
        $metrics['crisis_handled_raw'] = 100;
        $metrics['crisis_handled'] = '100%';

        $metrics['crisis_handled_prev'] = 100;
        try {
            $sql = "SELECT 
                    (SUM(CASE WHEN crisis_protocol_followed = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*))
                    FROM escalations
                    WHERE escalation_tier = 'Emergency'
                    AND MONTH(escalation_date) = ? AND YEAR(escalation_date) = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$prevMonth, $prevYear]);
            $handledPrev = $stmt->fetchColumn();
            if ($handledPrev !== false && $handledPrev !== null) {
                $metrics['crisis_handled_prev'] = round($handledPrev);
            }
        } catch (PDOException $e) {
        }

        try {
            $sql = "SELECT 
                    (SUM(CASE WHEN crisis_protocol_followed = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*))
                    FROM escalations
                    WHERE escalation_tier = 'Emergency'
                    AND MONTH(escalation_date) = MONTH(CURDATE()) AND YEAR(escalation_date) = YEAR(CURDATE())";
            $handled = $this->conn->query($sql)->fetchColumn();
            if ($handled !== false && $handled !== null) {
                $metrics['crisis_handled_raw'] = round($handled);
                $metrics['crisis_handled'] = $metrics['crisis_handled_raw'] . '%';
            }
        } catch (PDOException $e) {
        }

        // Overall Flag
        $metrics['overall_flag'] = 'RED';
        $metrics['overall_color'] = 'danger';
        $metrics['overall_health'] = 'AT RISK';
        $metrics['insight'] = 'Multiple critical metrics are below target. Immediate attention required.';

        $targetsMet = 0;
        if ($metrics['completion_rate_raw'] >= 85) $targetsMet++;
        if ($metrics['retention_rate_raw'] >= 90) $targetsMet++;
        if ($metrics['first_contact_rate_raw'] >= 90) $targetsMet++;
        if ($metrics['escalation_rate_raw'] < 15) $targetsMet++;

        if ($targetsMet >= 3) {
            $metrics['overall_flag'] = 'HEALTHY';
            $metrics['overall_color'] = 'success';
            $metrics['overall_health'] = 'HEALTHY';
            $metrics['insight'] = 'System is performing well. Maintain current volunteer engagement strategies.';
        } elseif ($targetsMet >= 2) {
            $metrics['overall_flag'] = 'NEEDS ATTENTION';
            $metrics['overall_color'] = 'warning';
            $metrics['overall_health'] = 'NEEDS ATTENTION';
            $metrics['insight'] = 'Some metrics are slipping. Review team lead performance and escalation rates.';
        }

        return $metrics;
    }

    public function getKPIs()
    {
        // Reuse system health for shared metrics
        $sysHealth = $this->getSystemHealth();

        $metrics = [];

        // 1. Completion Rate (Current vs Last)
        $metrics['completion_rate'] = [
            'current' => $sysHealth['completion_rate_val'],
            'target' => 85,
            'trend' => 0,
            'status' => ($sysHealth['completion_rate_val'] >= 85) ? 'green' : 'red'
        ];

        // Last Month Completion
        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE MONTH(date_assigned) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                AND YEAR(date_assigned) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        $assigned_last = $this->conn->query($sql)->fetchColumn();

        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE status NOT IN ('Active', 'Escalated') 
                AND MONTH(updated_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                AND YEAR(updated_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        $completed_last = $this->conn->query($sql)->fetchColumn();

        $last_rate = ($assigned_last > 0) ? round(($completed_last / $assigned_last) * 100) : 0;
        $metrics['completion_rate']['trend'] = $metrics['completion_rate']['current'] - $last_rate;

        // 2. First Contact < 48h
        $metrics['first_contact_48h'] = ['current' => 0, 'target' => 90, 'status' => 'red'];

        // Logic: assigned_date to updated_at (first attempt) <= 2 days
        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE DATEDIFF(updated_at, date_assigned) <= 2 
                AND status != 'Active'
                AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())";
        $fast = $this->conn->query($sql)->fetchColumn();

        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE status != 'Active'
                AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())";
        $total_processed = $this->conn->query($sql)->fetchColumn();

        $metrics['first_contact_48h']['current'] = ($total_processed > 0) ? round(($fast / $total_processed) * 100) : 0;
        $metrics['first_contact_48h']['status'] = ($metrics['first_contact_48h']['current'] >= 90) ? 'green' : 'red';

        // 3. Escalation Rate (Target < 15%)
        $metrics['escalation_rate'] = ['current' => 0, 'target' => 15, 'status' => 'red'];

        try {
            $sql = "SELECT COUNT(*) FROM escalations 
                    WHERE MONTH(escalation_date) = MONTH(CURDATE()) AND YEAR(escalation_date) = YEAR(CURDATE())";
            $escalations = $this->conn->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            $escalations = 0;
        }

        // Denominator: Total Follow-ups assigned
        $sql = "SELECT COUNT(*) FROM followup_master WHERE MONTH(date_assigned) = MONTH(CURDATE()) AND YEAR(date_assigned) = YEAR(CURDATE())";
        $assigned = $this->conn->query($sql)->fetchColumn();

        $metrics['escalation_rate']['current'] = ($assigned > 0) ? round(($escalations / $assigned) * 100) : 0;
        $metrics['escalation_rate']['status'] = ($metrics['escalation_rate']['current'] < 15) ? 'green' : 'red';

        // 4. Crisis Handled Safely (Target 100%)
        $metrics['crisis_handled_safely'] = ['current' => 100, 'target' => 100, 'status' => 'green'];

        try {
            $sql = "SELECT 
                    (SUM(CASE WHEN crisis_protocol_followed = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*))
                    FROM escalations
                    WHERE escalation_tier = 'Emergency'
                    AND MONTH(escalation_date) = MONTH(CURDATE()) AND YEAR(escalation_date) = YEAR(CURDATE())";
            $handled = $this->conn->query($sql)->fetchColumn();
            if ($handled !== false && $handled !== null) {
                $metrics['crisis_handled_safely']['current'] = round($handled);
                $metrics['crisis_handled_safely']['status'] = ($handled == 100) ? 'green' : 'red';
            }
        } catch (PDOException $e) {
        }

        // 5. Volunteer Retention (Added as per projectlogic.txt)
        $metrics['volunteer_retention'] = [
            'current' => $sysHealth['retention_rate_val'],
            'target' => 90,
            'status' => ($sysHealth['retention_rate_val'] >= 90) ? 'green' : 'red'
        ];

        // 6. System vNPS (Added as per projectlogic.txt)
        $metrics['system_vnps'] = [
            'current' => $sysHealth['system_vnps'],
            'target' => 50,
            'status' => ($sysHealth['system_vnps'] >= 50) ? 'green' : 'red'
        ];

        return $metrics;
    }

    public function getTeamLeadPerformance()
    {
        $sql = "SELECT tl.team_lead_id as id, CONCAT(tl.first_name, ' ', tl.last_name) as full_name FROM team_leads tl";
        $teamLeads = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $performance = [];

        foreach ($teamLeads as $tl) {
            $tlId = $tl['id'];
            $data = [
                'name' => $tl['full_name'], // Changed from team_lead_name
                'team_size' => 0,
                'completion_rate' => 0,
                'vnps' => 0, // Changed from team_vnps
                'retention' => 0, // Changed from retention_rate
                'flag' => 'green',
                'below_target_count' => 0
            ];

            // Team Size
            $sql = "SELECT COUNT(*) FROM volunteers WHERE team_lead_id = ? AND status = 'Active'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tlId]);
            $data['team_size'] = $stmt->fetchColumn();

            // Completion Rate
            $sql = "SELECT COUNT(*) FROM followup_master fm
                    JOIN volunteers v ON fm.assigned_volunteer = v.volunteer_name
                    WHERE v.team_lead_id = ?
                    AND MONTH(fm.date_assigned) = MONTH(CURDATE())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tlId]);
            $assigned = $stmt->fetchColumn();

            $sql = "SELECT COUNT(*) FROM followup_master fm
                    JOIN volunteers v ON fm.assigned_volunteer = v.volunteer_name
                    WHERE v.team_lead_id = ?
                    AND fm.status NOT IN ('Active', 'Escalated')
                    AND MONTH(fm.updated_at) = MONTH(CURDATE())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tlId]);
            $completed = $stmt->fetchColumn();

            $data['completion_rate'] = ($assigned > 0) ? round(($completed / $assigned) * 100) : 0;

            // vNPS
            $sql = "SELECT AVG(vnps_score) FROM volunteers WHERE team_lead_id = ? AND status = 'Active'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tlId]);
            $vnps = $stmt->fetchColumn();
            $data['vnps'] = $vnps ? round($vnps) : 0;

            // Retention
            $sql = "SELECT 
                    (COUNT(CASE WHEN status = 'Active' THEN 1 END) * 100.0 / COUNT(*))
                    FROM volunteers
                    WHERE team_lead_id = ?
                    AND start_date <= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tlId]);
            $retention = $stmt->fetchColumn();
            $data['retention'] = ($retention !== false) ? round($retention) : 100;

            // Flag Logic
            $belowTargetCount = 0;
            if ($data['completion_rate'] < 85) $belowTargetCount++;
            if ($data['vnps'] < 50) $belowTargetCount++;
            if ($data['retention'] < 90) $belowTargetCount++;

            $data['below_target_count'] = $belowTargetCount;
            if ($belowTargetCount >= 2) $data['flag'] = 'red';
            elseif ($belowTargetCount == 1) $data['flag'] = 'yellow';
            else $data['flag'] = 'green';

            $performance[] = $data;
        }

        usort($performance, function ($a, $b) {
            $flagOrder = ['red' => 1, 'yellow' => 2, 'green' => 3];
            return $flagOrder[$a['flag']] - $flagOrder[$b['flag']];
        });

        return $performance;
    }

    public function getVisitorPipelineHealth()
    {
        $pipeline = [];

        try {
            // Using people table with accurate date diff logic
            $sql = "SELECT 
                    follow_up_status,
                    COUNT(*) as count,
                    AVG(DATEDIFF(CURDATE(), 
                        CASE 
                            WHEN follow_up_status = 'NEW' THEN created_at
                            WHEN follow_up_status = 'ASSIGNED' THEN assigned_date
                            ELSE last_contact_date
                        END
                    )) as avg_days_in_stage
                    FROM people 
                    WHERE MONTH(first_visit_date) = MONTH(CURDATE())
                    GROUP BY follow_up_status";
            $stmt = $this->conn->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) throw new Exception("No data");

            $total = array_sum(array_column($rows, 'count'));

            foreach ($rows as $row) {
                $pipeline[] = [
                    'stage' => $row['follow_up_status'],
                    'count' => $row['count'],
                    'percentage' => ($total > 0) ? round(($row['count'] / $total) * 100) : 0,
                    'avg_days' => $row['avg_days_in_stage'] ? round($row['avg_days_in_stage'], 1) : 0
                ];
            }
        } catch (Exception $e) {
            // Fallback
            return [];
        }

        return $pipeline;
    }

    public function getEscalationData()
    {
        $data = [
            'total' => 0,
            'standard' => ['count' => 0, 'percent' => 0],
            'emergency' => ['count' => 0, 'percent' => 0],
            'resolution' => [
                'standard' => ['avg' => 0, 'ok' => false],
                'emergency' => ['percent_handled' => 0, 'ok' => false]
            ],
            'pending' => [],
            'reasons' => []
        ];

        try {
            // Total Counts
            $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN escalation_tier = 'Standard' THEN 1 ELSE 0 END) as standard,
                    SUM(CASE WHEN escalation_tier = 'Urgent' THEN 1 ELSE 0 END) as urgent,
                    SUM(CASE WHEN escalation_tier = 'Emergency' THEN 1 ELSE 0 END) as emergency,
                    
                    AVG(CASE WHEN escalation_tier = 'Standard' 
                        THEN TIMESTAMPDIFF(HOUR, escalation_date, COALESCE(resolved_date, NOW())) / 24 
                    END) as avg_resolution_standard,
                    
                    AVG(CASE WHEN escalation_tier = 'Urgent' 
                        THEN TIMESTAMPDIFF(HOUR, escalation_date, COALESCE(resolved_date, NOW())) / 24 
                    END) as avg_resolution_urgent
                    
                    FROM escalations
                    WHERE MONTH(escalation_date) = MONTH(CURDATE()) AND YEAR(escalation_date) = YEAR(CURDATE())";

            $counts = $this->conn->query($sql)->fetch(PDO::FETCH_ASSOC);

            if ($counts) {
                $data['total'] = $counts['total'];

                // Standard (Combining Standard + Urgent for simplicity if desired, or just Standard)
                // Dashboard only shows Standard and Emergency. Let's group Urgent into Standard for display or ignore?
                // Actually dashboard has Standard and Emergency. Let's assume Standard includes Urgent or just use Standard.
                // Let's stick to Standard tier for Standard metric.
                $data['standard']['count'] = $counts['standard'] ?? 0;
                $data['standard']['percent'] = ($data['total'] > 0) ? round(($data['standard']['count'] / $data['total']) * 100) : 0;

                $data['emergency']['count'] = $counts['emergency'] ?? 0;
                $data['emergency']['percent'] = ($data['total'] > 0) ? round(($data['emergency']['count'] / $data['total']) * 100) : 0;

                // Resolution Standard
                $avgStd = $counts['avg_resolution_standard'] ?? 0;
                $data['resolution']['standard']['avg'] = round($avgStd, 1);
                $data['resolution']['standard']['ok'] = ($avgStd < 2); // Target < 2 days

                // Resolution Emergency (% handled according to protocol)
                $sql = "SELECT 
                                (SUM(CASE WHEN crisis_protocol_followed = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*))
                                FROM escalations
                                WHERE escalation_tier = 'Emergency'
                                AND MONTH(escalation_date) = MONTH(CURDATE()) AND YEAR(escalation_date) = YEAR(CURDATE())";
                $handled = $this->conn->query($sql)->fetchColumn();
                $data['resolution']['emergency']['percent_handled'] = ($handled !== false) ? round($handled) : 100;
                $data['resolution']['emergency']['ok'] = ($data['resolution']['emergency']['percent_handled'] == 100);
            }

            // Pending Escalations (Detailed)
            $sql = "SELECT 
                                escalation_tier as type, 
                                (SELECT full_name FROM people WHERE id = escalations.followup_id LIMIT 1) as name, 
                                DATEDIFF(NOW(), escalation_date) as age,
                                escalation_reason as reason
                                FROM escalations 
                                WHERE status IN ('New', 'In Progress')
                                ORDER BY escalation_date ASC
                                LIMIT 5";
            $pending = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pending as &$p) {
                $p['age'] = $p['age'] . 'd';
                if (empty($p['name'])) $p['name'] = 'Unknown';
            }
            $data['pending'] = $pending;

            // Top Reasons
            $sql = "SELECT escalation_reason as response_type, COUNT(*) as cnt 
                    FROM escalations 
                    WHERE MONTH(escalation_date) = MONTH(CURDATE()) AND YEAR(escalation_date) = YEAR(CURDATE())
                    GROUP BY escalation_reason 
                    ORDER BY cnt DESC LIMIT 5";
            $data['reasons'] = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            // Compatibility for Alert Logic which uses 'top_reasons'
            $data['top_reasons'] = [];
            foreach ($data['reasons'] as $r) {
                $data['top_reasons'][] = ['escalation_reason' => $r['response_type'], 'count' => $r['cnt']];
            }
        } catch (PDOException $e) {
        }

        return $data;
    }

    public function getTrendAnalysis()
    {
        $trends = ['months' => [], 'data' => []];

        for ($i = 2; $i >= 0; $i--) {
            $timestamp = strtotime("-$i months");
            $month = date('M', $timestamp);
            $monthNum = date('m', $timestamp);
            $year = date('Y', $timestamp);

            $trends['months'][] = $month;

            // 1. Visitors
            try {
                $sql = "SELECT COUNT(*) FROM people WHERE visit_type='First-Time Visitor' AND MONTH(first_visit_date)=? AND YEAR(first_visit_date)=?";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$monthNum, $year]);
                $visitors = $stmt->fetchColumn();
            } catch (PDOException $e) {
                $visitors = 0;
            }

            // 2. Completion Rate
            $sql = "SELECT COUNT(*) FROM followup_master WHERE MONTH(date_assigned)=? AND YEAR(date_assigned)=?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$monthNum, $year]);
            $assigned = $stmt->fetchColumn();

            $sql = "SELECT COUNT(*) FROM followup_master WHERE status NOT IN ('Active','Escalated') AND MONTH(updated_at)=? AND YEAR(updated_at)=?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$monthNum, $year]);
            $completed = $stmt->fetchColumn();
            $compRate = ($assigned > 0) ? round(($completed / $assigned) * 100) : 0;

            // 3. vNPS
            $quarter = 'Q' . ceil($monthNum / 3);
            try {
                $sql = "SELECT AVG(vnps_score) FROM vnps_surveys WHERE quarter=? AND year=?";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$quarter, $year]);
                $vnps = $stmt->fetchColumn();
                $vnps = $vnps ? round($vnps) : 0;
            } catch (PDOException $e) {
                $vnps = 0;
            }

            // 4. Volunteer Count
            $lastDay = date('Y-m-t', $timestamp);
            $sql = "SELECT COUNT(*) FROM volunteers WHERE status = 'Active' AND start_date <= ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$lastDay]);
            $volCount = $stmt->fetchColumn();

            // 5. Crisis Count
            try {
                $sql = "SELECT COUNT(*) FROM escalations WHERE escalation_tier = 'Emergency' AND MONTH(escalation_date)=? AND YEAR(escalation_date)=?";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$monthNum, $year]);
                $crisis = $stmt->fetchColumn();
            } catch (PDOException $e) {
                $crisis = 0;
            }

            // 6. Turnover
            $sql = "SELECT COUNT(*) FROM volunteers WHERE end_date BETWEEN ? AND ?";
            $firstDay = date('Y-m-01', $timestamp);
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$firstDay, $lastDay]);
            $turnover = $stmt->fetchColumn();

            $trends['data'][$month] = [
                'visitors' => $visitors,
                'completion_rate' => $compRate,
                'vnps' => $vnps,
                'volunteers' => $volCount,
                'crisis' => $crisis,
                'turnover' => $turnover
            ];
        }
        return $trends;
    }

    public function getImpactOutcomes()
    {
        $impact = [
            'this_month' => [
                'completed' => 0,
                'connected_groups' => 0,
                'received_prayer' => 0,
                'connected_benevolence' => 0,
                'scheduled_counseling' => 0,
                'connected_serve' => 0
            ],
            'qtd' => [
                'total_contacts' => 0,
                'percent_cared' => 0,
                'percent_next_step' => 0
            ]
        ];

        // --- THIS MONTH ---

        // Total Conversations Completed
        $sql = "SELECT COUNT(*) FROM followup_master WHERE status = 'Archive' AND MONTH(updated_at) = MONTH(CURDATE())";
        $impact['this_month']['completed'] = $this->conn->query($sql)->fetchColumn();

        // Small Group (using 'notes' table if available, else fallback)
        try {
            $sql = "SELECT COUNT(*) FROM notes 
                    WHERE entity_type = 'Follow-Up'
                    AND (note_text LIKE '%small group%' OR tags LIKE '%small-group%')
                    AND MONTH(created_at) = MONTH(CURDATE())";
            $impact['this_month']['connected_groups'] = $this->conn->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            // Fallback to followup_master notes
            $sql = "SELECT COUNT(*) FROM followup_master WHERE notes LIKE '%small group%' AND MONTH(updated_at) = MONTH(CURDATE())";
            $impact['this_month']['connected_groups'] = $this->conn->query($sql)->fetchColumn();
        }

        // Prayer Requests
        try {
            $sql = "SELECT COUNT(*) FROM people 
                    WHERE prayer_requests IS NOT NULL AND prayer_requests != ''
                    AND MONTH(first_visit_date) = MONTH(CURDATE())";
            $impact['this_month']['received_prayer'] = $this->conn->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            $impact['this_month']['received_prayer'] = 0;
        }

        // Benevolence
        try {
            $sql = "SELECT COUNT(*) FROM escalations 
                    WHERE escalation_reason = 'Financial Crisis'
                    AND MONTH(escalation_date) = MONTH(CURDATE())";
            $impact['this_month']['connected_benevolence'] = $this->conn->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            $impact['this_month']['connected_benevolence'] = 0;
        }

        // Counseling
        try {
            $sql = "SELECT COUNT(*) FROM escalations 
                    WHERE outcome = 'Counseling Referral'
                    AND MONTH(escalation_date) = MONTH(CURDATE())";
            $impact['this_month']['scheduled_counseling'] = $this->conn->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            $impact['this_month']['scheduled_counseling'] = 0;
        }

        // Serve Connections
        try {
            $sql = "SELECT COUNT(*) FROM notes 
                    WHERE entity_type = 'Follow-Up'
                    AND (note_text LIKE '%serving%' OR tags LIKE '%serve%')
                    AND MONTH(created_at) = MONTH(CURDATE())";
            $impact['this_month']['connected_serve'] = $this->conn->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            $sql = "SELECT COUNT(*) FROM followup_master WHERE notes LIKE '%serve%' AND MONTH(updated_at) = MONTH(CURDATE())";
            $impact['this_month']['connected_serve'] = $this->conn->query($sql)->fetchColumn();
        }

        // --- QTD (Quarter to Date) ---
        $currentQuarter = ceil(date('n') / 3);
        $startMonth = ($currentQuarter - 1) * 3 + 1;
        $endMonth = $startMonth + 2;

        // Total Contacts
        $sql = "SELECT COUNT(*) FROM followup_master 
                        WHERE MONTH(date_assigned) BETWEEN $startMonth AND $endMonth 
                        AND YEAR(date_assigned) = YEAR(CURDATE())";
        $totalContacts = $this->conn->query($sql)->fetchColumn();
        $impact['qtd']['total_contacts'] = $totalContacts;

        // Percent Cared (Completed Conversations)
        $sql = "SELECT COUNT(*) FROM followup_master 
                        WHERE status IN ('Archive', 'Completed') 
                        AND MONTH(updated_at) BETWEEN $startMonth AND $endMonth 
                        AND YEAR(updated_at) = YEAR(CURDATE())";
        $completed = $this->conn->query($sql)->fetchColumn();
        $impact['qtd']['percent_cared'] = ($totalContacts > 0) ? round(($completed / $totalContacts) * 100) : 0;

        // Percent Next Step (Proxy: Connected to something)
        // We'll approximate this by summing connection types for the quarter
        // Or just use a dummy logic if complex queries are needed. 
        // Let's use: (Small Group + Serve) / Total Contacts
        // Simple approx:
        $nextStepCount = 0;

        // Groups QTD
        try {
            $sql = "SELECT COUNT(*) FROM notes WHERE entity_type='Follow-Up' AND (note_text LIKE '%small group%' OR tags LIKE '%small-group%') AND MONTH(created_at) BETWEEN $startMonth AND $endMonth";
            $nextStepCount += $this->conn->query($sql)->fetchColumn();
        } catch (Exception $e) {
        }

        // Serve QTD
        try {
            $sql = "SELECT COUNT(*) FROM notes WHERE entity_type='Follow-Up' AND (note_text LIKE '%serving%' OR tags LIKE '%serve%') AND MONTH(created_at) BETWEEN $startMonth AND $endMonth";
            $nextStepCount += $this->conn->query($sql)->fetchColumn();
        } catch (Exception $e) {
        }

        $impact['qtd']['percent_next_step'] = ($totalContacts > 0) ? round(($nextStepCount / $totalContacts) * 100) : 0;
        if ($impact['qtd']['percent_next_step'] > 100) $impact['qtd']['percent_next_step'] = 100;

        return $impact;
    }

    public function getVolunteerPipeline()
    {
        // Level 0
        $sql = "SELECT COUNT(*) FROM volunteers WHERE level = 'Level 0' OR status = 'Onboarding'";
        $level0 = $this->conn->query($sql)->fetchColumn();

        // Level 1 Active
        $sql = "SELECT COUNT(*) FROM volunteers WHERE level = 'Level 1' AND status = 'Active'";
        $level1Active = $this->conn->query($sql)->fetchColumn();

        // Level 1 Care Path
        $sql = "SELECT COUNT(*) FROM volunteers WHERE level = 'Level 1' AND status IN ('Paused', 'Care')";
        $level1Care = $this->conn->query($sql)->fetchColumn();

        // Promotion Ready (Active > 6 months, Completion > 85%)
        $sql = "SELECT COUNT(*) FROM volunteers 
                WHERE level = 'Level 1' 
                AND status = 'Active'
                AND DATEDIFF(CURDATE(), start_date) >= 180
                AND completion_rate >= 85";
        $promotionReady = $this->conn->query($sql)->fetchColumn();

        // Level 2
        $sql = "SELECT COUNT(*) FROM volunteers WHERE level = 'Level 2' AND status = 'Active'";
        $level2 = $this->conn->query($sql)->fetchColumn();

        $stages = [
            'Level 0 (Onboarding)' => ['count' => $level0, 'notes' => 'Pending training'],
            'Level 1 (Active)' => ['count' => $level1Active, 'notes' => 'Regular serving'],
            'Level 1 (Care/Paused)' => ['count' => $level1Care, 'notes' => 'Needs check-in'],
            'Promotion Ready' => ['count' => $promotionReady, 'notes' => '>6mo, High Perf'],
            'Level 2 (Leaders)' => ['count' => $level2, 'notes' => 'Team Leads']
        ];

        $health = ['color' => 'success', 'text' => 'Healthy Flow'];
        if ($promotionReady == 0) {
            $health = ['color' => 'warning', 'text' => 'Stagnant (No promotions ready)'];
        }
        if ($level0 == 0 && $level1Active < 10) {
            $health = ['color' => 'danger', 'text' => 'Empty Pipeline (No new recruits)'];
        }

        return [
            'stages' => $stages,
            'health' => $health,
            'promotion_ready' => $promotionReady // Keep for alerts logic
        ];
    }

    public function getSystemAlerts($teamLeadPerf, $escalations, $trends, $systemHealth)
    {
        $alerts = ['urgent' => [], 'important' => [], 'strategic' => []];

        // Urgent: Team Leads
        foreach ($teamLeadPerf as $tl) {
            if ($tl['flag'] === 'red') {
                $alerts['urgent'][] = [
                    'title' => "{$tl['name']} (Team Lead) - Below target on {$tl['below_target_count']} metrics",
                    'action' => 'Schedule 1-on-1 this week'
                ];
            }
        }

        // Important: Financial Escalations
        // Logic: if financial crisis count > 8 (using hardcoded threshold from projectlogic.txt)
        // Check top reasons for count
        $financialCount = 0;
        if (!empty($escalations['top_reasons'])) {
            foreach ($escalations['top_reasons'] as $reason) {
                if (stripos($reason['escalation_reason'], 'Financial') !== false) {
                    $financialCount = $reason['count'];
                    break;
                }
            }
        }

        if ($financialCount > 8) {
            $alerts['important'][] = [
                'title' => "Financial escalations increasing ({$financialCount} cases vs avg 8)",
                'action' => 'Review Benevolence Fund status'
            ];
        }

        // Important: Visitor Volume Growth
        // Logic: +20% growth over 3 months
        if (isset($trends['months']) && count($trends['months']) >= 3) {
            $months = $trends['months'];
            $oldestMonth = $months[0];
            $newestMonth = $months[count($months) - 1];

            if (isset($trends['data'][$oldestMonth]) && isset($trends['data'][$newestMonth])) {
                $start = $trends['data'][$oldestMonth]['visitors'];
                $end = $trends['data'][$newestMonth]['visitors'];

                if ($start > 0) {
                    $growth = (($end - $start) / $start) * 100;
                    if ($growth > 20) {
                        $alerts['important'][] = [
                            'title' => "Visitor volume +" . round($growth) . "% in 3 months",
                            'action' => 'Plan to recruit 5-8 more volunteers in Q2'
                        ];
                    }
                }
            }
        }

        // Strategic: Promotion Readiness
        $pipeline = $this->getVolunteerPipeline();
        if ($pipeline['promotion_ready'] >= 5) {
            $alerts['strategic'][] = [
                'title' => "{$pipeline['promotion_ready']} volunteers ready for Level 2 promotion",
                'action' => 'Schedule Advanced Training cohort'
            ];
        } else {
            $alerts['strategic'][] = [
                'title' => "Low promotion readiness (Only {$pipeline['promotion_ready']} candidates)",
                'action' => 'Focus on Level 1 mentorship'
            ];
        }

        // Strategic: System Health / Campus Expansion
        // Logic: Completion >= 85% AND vNPS >= 50
        $compRate = isset($systemHealth['completion_rate_val']) ? $systemHealth['completion_rate_val'] : 0;
        $vnps = isset($systemHealth['system_vnps']) ? $systemHealth['system_vnps'] : 0;

        if ($compRate >= 85 && $vnps >= 50) {
            $alerts['strategic'][] = [
                'title' => "System running smoothly at {$compRate}% completion rate",
                'action' => 'Consider expanding to additional campuses'
            ];
        }

        return $alerts;
    }

    public function getUpcomingMilestones()
    {
        $milestones = [];

        try {
            // Volunteer Anniversaries (Next 30 Days)
            $sql = "SELECT 
                                volunteer_name, 
                                start_date,
                                TIMESTAMPDIFF(YEAR, start_date, CURDATE()) + 1 as years,
                                DATE_ADD(start_date, INTERVAL TIMESTAMPDIFF(YEAR, start_date, CURDATE()) + 1 YEAR) as anniversary_date
                                FROM volunteers 
                                WHERE status = 'Active' 
                                AND start_date IS NOT NULL
                                HAVING anniversary_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                                ORDER BY anniversary_date ASC
                                LIMIT 5";

            $stmt = $this->conn->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($rows) {
                foreach ($rows as $row) {
                    $milestones[] = [
                        'type' => 'Anniversary',
                        'name' => $row['volunteer_name'],
                        'details' => $row['years'] . ' Year' . ($row['years'] > 1 ? 's' : ''),
                        'date' => date('M j', strtotime($row['anniversary_date'])),
                        'event' => $row['volunteer_name'] . " (" . $row['years'] . " Year Anniversary)"
                    ];
                }
            }
        } catch (Exception $e) {
            // Gracefully handle errors
        }

        return $milestones;
    }
}
