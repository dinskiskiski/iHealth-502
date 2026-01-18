<?php
session_start();
include 'dbconn.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'STAFF') {
    header("Location: login.php");
    exit();
}

// 2. Get Appointment ID
if (!isset($_GET['id'])) {
    header("Location: staff_home.php");
    exit();
}
$appt_id = $_GET['id'];

// 3. Fetch Completed Details (Corrected Column Name: TREATMENT)
$sql = "SELECT 
            A.APPOINTMENTID, 
            TO_CHAR(A.APPOINTMENTDATE, 'DD Mon YYYY') AS APP_DATE,
            A.APPOINTMENTTIME,
            S.NAME AS SERVICE_NAME,
            U.NAME AS PATIENT_NAME,
            U.USERID AS PATIENT_ID,
            PR.SYMPTOMS,
            PR.DIAGNOSIS,
            PR.TREATMENT  
        FROM APPOINTMENT A
        JOIN SERVICE S ON A.SERVICEID = S.SERVICEID
        LEFT JOIN PATIENTRECORD PR ON A.APPOINTMENTID = PR.APPOINTMENTID
        LEFT JOIN USERS U ON PR.USERID = U.USERID
        WHERE A.APPOINTMENTID = :aid";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":aid", $appt_id);

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo "Database Error: " . $e['message'];
    exit();
}

$row = oci_fetch_assoc($stmt);

if (!$row) {
    echo "Record not found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medical Record #<?php echo $appt_id; ?> | iHealth</title>
    <style>
        /* SAME CSS AS BEFORE */
        :root { --primary: #2C3E50; --accent: #3498db; --bg: #f5f7fa; --sidebar-width: 250px; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg); display: flex; }
        .sidebar { width: var(--sidebar-width); background: var(--primary); color: white; height: 100vh; position: fixed; padding: 20px 0; }
        .brand { font-size: 1.8rem; font-weight: bold; text-align: center; margin-bottom: 40px; }
        .brand span { color: var(--accent); }
        .nav-links a { display: block; padding: 15px 30px; color: #bdc3c7; text-decoration: none; font-size: 1.05rem; }
        .nav-links a:hover { background: #34495e; color: white; border-left: 5px solid var(--accent); }
        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }
        .record-card { background: white; padding: 50px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 800px; margin: 0 auto; border-top: 5px solid #27ae60; }
        .header-row { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 30px; }
        .patient-title { font-size: 1.8rem; color: var(--primary); font-weight: bold; margin: 0; }
        .meta-info { color: #7f8c8d; font-size: 0.9rem; margin-top: 5px; }
        .status-badge { background: #d4edda; color: #155724; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 0.85rem; text-transform: uppercase; }
        .section-title { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; color: #95a5a6; margin-bottom: 10px; font-weight: bold; }
        .data-box { background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid var(--accent); margin-bottom: 25px; line-height: 1.6; color: #333; }
        .data-box.prescription { border-left-color: #27ae60; background: #eafaf1; }
        .btn-back { display: inline-block; text-decoration: none; color: #7f8c8d; font-weight: bold; margin-top: 20px; }
        .btn-print { background: #2c3e50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; float: right; }
        @media print { .sidebar, .btn-back, .btn-print { display: none; } .main-content { margin: 0; padding: 0; } .record-card { box-shadow: none; border: 1px solid #ddd; } body { background: white; } }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">iHealth<span>Pro</span></div>
        <div class="nav-links">
            <a href="staff_home.php">📅 My Schedule</a>
            <a href="reports.php">📊 Analytics</a>
            <a href="#">👤 My Profile</a>
            <a href="logout.php" style="color: #ff6b6b; margin-top: 50px;">🚪 Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="record-card">
            <div class="header-row">
                <div>
                    <h1 class="patient-title"><?php echo htmlspecialchars($row['PATIENT_NAME'] ?: 'Walk-in / Unknown'); ?></h1>
                    <div class="meta-info">Patient ID: #<?php echo $row['PATIENT_ID']; ?></div>
                    <div class="meta-info">Service: <?php echo $row['SERVICE_NAME']; ?></div>
                </div>
                <div style="text-align: right;">
                    <div class="status-badge">Completed</div>
                    <div class="meta-info" style="margin-top:10px;"><?php echo $row['APP_DATE']; ?></div>
                    <div class="meta-info"><?php echo $row['APPOINTMENTTIME']; ?></div>
                </div>
            </div>

            <div class="section-title">1. Reported Symptoms</div>
            <div class="data-box">
                <?php echo nl2br(htmlspecialchars($row['SYMPTOMS'] ?: 'No symptoms recorded.')); ?>
            </div>

            <div class="section-title">2. Clinical Diagnosis</div>
            <div class="data-box">
                <?php echo nl2br(htmlspecialchars($row['DIAGNOSIS'] ?: 'No diagnosis recorded.')); ?>
            </div>

            <div class="section-title">3. Treatment / Prescription</div>
            <div class="data-box prescription">
                <?php echo nl2br(htmlspecialchars($row['TREATMENT'] ?: 'No treatment recorded.')); ?>
            </div>

            <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                <a href="staff_home.php" class="btn-back">← Back to Dashboard</a>
                <button onclick="window.print()" class="btn-print">🖨️ Print Record</button>
            </div>
        </div>
    </div>
</body>
</html>