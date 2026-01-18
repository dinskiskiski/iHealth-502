<?php
session_start();
include 'dbconn.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'STAFF') {
    header("Location: login.php");
    exit();
}

// ---------------------------------------------------------
// DATA QUERIES
// ---------------------------------------------------------

// 1. Service Popularity
$sql_1 = "SELECT S.NAME, COUNT(A.APPOINTMENTID) AS TOTAL 
          FROM APPOINTMENT A 
          JOIN SERVICE S ON A.SERVICEID = S.SERVICEID 
          GROUP BY S.NAME";
$stmt_1 = oci_parse($conn, $sql_1);
oci_execute($stmt_1);
$lbl_1 = []; $dat_1 = [];
while ($row = oci_fetch_assoc($stmt_1)) {
    $lbl_1[] = $row['NAME'];
    $dat_1[] = (int)$row['TOTAL'];
}

// 2. Staff Workload
$sql_2 = "SELECT U.NAME, COUNT(A.APPOINTMENTID) AS TOTAL
          FROM APPOINTMENT A
          JOIN STAFF ST ON A.STAFFID = ST.STAFFID
          JOIN USERS U ON ST.USERID = U.USERID
          GROUP BY U.NAME";
$stmt_2 = oci_parse($conn, $sql_2);
oci_execute($stmt_2);
$lbl_2 = []; $dat_2 = [];
while ($row = oci_fetch_assoc($stmt_2)) {
    $lbl_2[] = "Dr. " . $row['NAME'];
    $dat_2[] = (int)$row['TOTAL'];
}

// 3. Peak Times
$sql_3 = "SELECT APPOINTMENTTIME, COUNT(*) AS TOTAL 
          FROM APPOINTMENT 
          GROUP BY APPOINTMENTTIME 
          ORDER BY APPOINTMENTTIME ASC";
$stmt_3 = oci_parse($conn, $sql_3);
oci_execute($stmt_3);
$lbl_3 = []; $dat_3 = [];
while ($row = oci_fetch_assoc($stmt_3)) {
    $lbl_3[] = $row['APPOINTMENTTIME'];
    $dat_3[] = (int)$row['TOTAL'];
}

// 4. Appointment Status
$sql_4 = "SELECT APPOINTMENTSTATUS, COUNT(*) AS TOTAL 
          FROM APPOINTMENT 
          GROUP BY APPOINTMENTSTATUS";
$stmt_4 = oci_parse($conn, $sql_4);
oci_execute($stmt_4);
$lbl_4 = []; $dat_4 = [];
while ($row = oci_fetch_assoc($stmt_4)) {
    $lbl_4[] = $row['APPOINTMENTSTATUS'];
    $dat_4[] = (int)$row['TOTAL'];
}

// 5. Revenue
$sql_5 = "SELECT S.NAME, SUM(S.FEE) AS REVENUE
          FROM APPOINTMENT A
          JOIN SERVICE S ON A.SERVICEID = S.SERVICEID
          WHERE A.APPOINTMENTSTATUS = 'Completed'
          GROUP BY S.NAME
          ORDER BY REVENUE DESC";
$stmt_5 = oci_parse($conn, $sql_5);
oci_execute($stmt_5);
$lbl_5 = []; $dat_5 = [];
while ($row = oci_fetch_assoc($stmt_5)) {
    $lbl_5[] = $row['NAME'];
    $dat_5[] = (float)$row['REVENUE'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics Dashboard | iHealth</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* SHARED DASHBOARD CSS */
        :root { --primary: #2C3E50; --accent: #3498db; --bg: #f5f7fa; --sidebar-width: 250px; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg); display: flex; }
        
        /* SIDEBAR STYLE */
        .sidebar { width: var(--sidebar-width); background: var(--primary); color: white; height: 100vh; position: fixed; display: flex; flex-direction: column; padding: 20px 0; z-index: 1000; }
        .brand { font-size: 1.8rem; font-weight: bold; text-align: center; margin-bottom: 40px; }
        .brand span { color: var(--accent); }
        .nav-links a { display: block; padding: 15px 30px; color: #bdc3c7; text-decoration: none; transition: 0.3s; font-size: 1.05rem; }
        .nav-links a:hover, .nav-links a.active { background: #34495e; color: white; border-left: 5px solid var(--accent); }

        /* MAIN CONTENT STYLE */
        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; width: calc(100% - var(--sidebar-width)); }
        
        h1 { color: var(--primary); margin-bottom: 5px; }
        p.subtitle { color: #7f8c8d; margin-top: 0; margin-bottom: 30px; }

        /* CHARTS GRID SYSTEM */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; margin-bottom: 25px; }
        .full-width { grid-column: 1 / -1; }

        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .card h3 { margin: 0 0 15px 0; color: #555; font-size: 1.1rem; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        .insight { background: #eef2f3; padding: 12px; border-radius: 6px; font-size: 0.9rem; color: #444; border-left: 4px solid #3498db; margin-top: 20px; }
        .insight strong { color: #2980b9; display: block; margin-bottom: 4px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">iHealth<span>Pro</span></div>
        <div class="nav-links">
            <a href="staff_home.php">📅 My Schedule</a>
            <a href="reports.php" class="active">📊 Analytics</a>
            <a href="#">👤 My Profile</a>
            <a href="logout.php" style="color: #ff6b6b; margin-top: 50px;">🚪 Logout</a>
        </div>
    </div>

    <div class="main-content">
        
        <header>
            <h1>Strategic Analytics</h1>
            <p class="subtitle">Real-time data insights for clinic optimization.</p>
        </header>

        <div class="grid">
            <div class="card">
                <h3>1. Service Popularity</h3>
                <canvas id="chart1"></canvas>
                <div class="insight">
                    <strong>Suggestion:</strong>
                    Promote services with low appointment counts using bundles or discounts.
                </div>
            </div>
            <div class="card">
                <h3>2. Staff Workload</h3>
                <canvas id="chart2"></canvas>
                <div class="insight">
                    <strong>Suggestion:</strong>
                    Ensure tasks are distributed evenly to prevent staff burnout.
                </div>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h3>3. Peak Hours</h3>
                <canvas id="chart3"></canvas>
                <div class="insight">
                    <strong>Suggestion:</strong>
                    Offer "Happy Hour" rates during low-traffic hours to maximize utilization.
                </div>
            </div>
            <div class="card">
                <h3>4. Appointment Status</h3>
                <div style="width:70%; margin:0 auto;"><canvas id="chart4"></canvas></div>
                <div class="insight">
                    <strong>Suggestion:</strong>
                    If "Cancelled" is high (>10%), implement a deposit policy or SMS reminders.
                </div>
            </div>
        </div>

        <div class="grid">
            <div class="card full-width">
                <h3>5. Revenue by Service (Completed Only)</h3>
                <canvas id="chart5" height="100"></canvas>
                <div class="insight">
                    <strong>Conclusion:</strong>
                    This chart shows actual earnings. Focus marketing on High Revenue items, not just popular ones.
                </div>
            </div>
        </div>

    </div>

    <script>
        // --- EXPANDED COLOR PALETTE ---
        // 10 distinct colors to ensure every bar gets a unique look
        const colorPalette = [
            '#FF6384', // Pink
            '#36A2EB', // Blue
            '#FFCE56', // Yellow
            '#4BC0C0', // Teal
            '#9966FF', // Purple
            '#FF9F40', // Orange
            '#C9CBCF', // Grey
            '#2ECC71', // Green
            '#E74C3C', // Red
            '#34495E'  // Dark Blue
        ];

        // 1. PIE (Service Popularity)
        new Chart(document.getElementById('chart1'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($lbl_1); ?>,
                datasets: [{ 
                    data: <?php echo json_encode($dat_1); ?>, 
                    backgroundColor: colorPalette, 
                    hoverOffset: 4
                }]
            }
        });

        // 2. BAR (Staff Workload) - NOW COLORFUL
        new Chart(document.getElementById('chart2'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($lbl_2); ?>,
                datasets: [{ 
                    label: 'Appointments', 
                    data: <?php echo json_encode($dat_2); ?>, 
                    backgroundColor: colorPalette // Applies unique color per doctor
                }]
            },
            options: {
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } } // Hide legend since labels are on axis
            }
        });

        // 3. LINE (Time) - Keeps single color for continuity line
        new Chart(document.getElementById('chart3'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($lbl_3); ?>,
                datasets: [{ 
                    label: 'Volume', 
                    data: <?php echo json_encode($dat_3); ?>, 
                    borderColor: '#e74c3c', 
                    fill: true, 
                    backgroundColor: 'rgba(231,76,60,0.1)' 
                }]
            }
        });

        // 4. DOUGHNUT (Status) - Specific traffic light colors
        new Chart(document.getElementById('chart4'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($lbl_4); ?>,
                datasets: [{ 
                    data: <?php echo json_encode($dat_4); ?>, 
                    backgroundColor: ['#2ecc71', '#f1c40f', '#e74c3c'] 
                }]
            }
        });

        // 5. HORIZONTAL BAR (Revenue) - NOW COLORFUL
        new Chart(document.getElementById('chart5'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($lbl_5); ?>,
                datasets: [{ 
                    label: 'Revenue (RM)', 
                    data: <?php echo json_encode($dat_5); ?>, 
                    backgroundColor: colorPalette // Applies unique color per service
                }]
            },
            options: { 
                indexAxis: 'y',
                plugins: { legend: { display: false } }
            } 
        });
    </script>

</body>
</html>