<?php
session_start();
include 'dbconn.php';

// 1. Check if logged in & Role is STAFF
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'STAFF') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. Handle Cancellation Request (NEW LOGIC)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_appt_id'])) {
    $cancel_id = $_POST['cancel_appt_id'];
    
    // Update status to Cancelled
    $sql_cancel = "UPDATE APPOINTMENT SET APPOINTMENTSTATUS = 'Cancelled' WHERE APPOINTMENTID = :aid";
    $stmt_cancel = oci_parse($conn, $sql_cancel);
    oci_bind_by_name($stmt_cancel, ":aid", $cancel_id);
    
    if (oci_execute($stmt_cancel)) {
        // Redirect to self to prevent form resubmission
        header("Location: staff_home.php");
        exit();
    } else {
        $e = oci_error($stmt_cancel);
        $error_msg = "Error cancelling: " . $e['message'];
    }
}

// 3. Identify the Doctor
$sql_who = "SELECT S.STAFFID, U.NAME FROM STAFF S JOIN USERS U ON S.USERID = U.USERID WHERE S.USERID = :curr_id";
$stmt_who = oci_parse($conn, $sql_who);
oci_bind_by_name($stmt_who, ":curr_id", $user_id);
oci_execute($stmt_who);
$doctor = oci_fetch_assoc($stmt_who);

if (!$doctor) { die("Error: Staff record not found."); }
$staff_id = $doctor['STAFFID'];
$doctor_name = $doctor['NAME'];

// --- STATS QUERIES ---

// Stat 1: My Pending Appointments
$sql_pending = "SELECT COUNT(*) AS CNT FROM APPOINTMENT WHERE STAFFID = :sid AND APPOINTMENTSTATUS = 'Scheduled'";
$stmt_p = oci_parse($conn, $sql_pending);
oci_bind_by_name($stmt_p, ":sid", $staff_id);
oci_execute($stmt_p);
$row_p = oci_fetch_assoc($stmt_p);
$count_pending = $row_p['CNT'];

// Stat 2: Total Patients in Clinic
// Table name is CUSTOMERS (Plural)
$sql_pat = "SELECT COUNT(*) AS CNT FROM CUSTOMERS";
$stmt_pat = oci_parse($conn, $sql_pat);
oci_execute($stmt_pat);
$row_pat = oci_fetch_assoc($stmt_pat);
$count_pat = $row_pat['CNT'];

// Stat 3: My Completed Appts
$sql_comp = "SELECT COUNT(*) AS CNT FROM APPOINTMENT WHERE STAFFID = :sid AND APPOINTMENTSTATUS = 'Completed'";
$stmt_c = oci_parse($conn, $sql_comp);
oci_bind_by_name($stmt_c, ":sid", $staff_id);
oci_execute($stmt_c);
$row_c = oci_fetch_assoc($stmt_c);
$count_comp = $row_c['CNT'];

// --- MAIN TABLE: My Schedule ---
$sql_list = "
    SELECT 
        a.APPOINTMENTID,
        TO_CHAR(a.APPOINTMENTDATE, 'DD Mon YYYY') AS APP_DATE,
        a.APPOINTMENTTIME,
        a.APPOINTMENTSTATUS,
        srv.NAME AS SERVICE_NAME,
        p_user.NAME AS PATIENT_NAME,
        pr.SYMPTOMS
    FROM APPOINTMENT a
    JOIN SERVICE srv ON a.SERVICEID = srv.SERVICEID
    LEFT JOIN PATIENTRECORD pr ON a.APPOINTMENTID = pr.APPOINTMENTID
    LEFT JOIN USERS p_user ON pr.USERID = p_user.USERID
    WHERE a.STAFFID = :sid
    ORDER BY a.APPOINTMENTDATE DESC, a.APPOINTMENTTIME ASC
";

$stmt_list = oci_parse($conn, $sql_list);
oci_bind_by_name($stmt_list, ":sid", $staff_id);
oci_execute($stmt_list);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | iHealth</title>
    <style>
        :root { --primary: #2C3E50; --accent: #3498db; --bg: #f5f7fa; --text: #333; --sidebar-width: 250px; --red: #e74c3c; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); display: flex; }
        
        /* SIDEBAR */
        .sidebar { width: var(--sidebar-width); background: var(--primary); color: white; height: 100vh; position: fixed; display: flex; flex-direction: column; padding: 20px 0; }
        .brand { font-size: 1.8rem; font-weight: bold; text-align: center; margin-bottom: 40px; }
        .brand span { color: var(--accent); }
        .nav-links a { display: block; padding: 15px 30px; color: #bdc3c7; text-decoration: none; transition: 0.3s; font-size: 1.05rem; }
        .nav-links a:hover, .nav-links a.active { background: #34495e; color: white; border-left: 5px solid var(--accent); }
        
        /* MAIN CONTENT */
        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        h1 { color: var(--primary); margin: 0; }
        .user-profile { font-weight: bold; color: #555; }
        
        /* STATS CARDS */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 5px solid var(--accent); }
        .card h3 { margin: 0 0 10px 0; color: #7f8c8d; font-size: 0.9rem; text-transform: uppercase; }
        .number { font-size: 2rem; font-weight: bold; color: var(--primary); }
        
        /* TABLE STYLES */
        .recent-appts { background: white; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .section-header { padding: 20px; background: #fff; border-bottom: 1px solid #eee; font-weight: bold; font-size: 1.2rem; color: var(--primary); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #7f8c8d; font-weight: 600; font-size: 0.9rem; }
        tr:hover { background: #fbfbfb; }
        
        /* BUTTONS & STATUS */
        .status { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; }
        .status.scheduled { background: #fff3cd; color: #856404; }
        .status.completed { background: #d4edda; color: #155724; }
        .status.cancelled { background: #f8d7da; color: #721c24; }
        
        .btn-action { background: var(--accent); color: white; padding: 8px 12px; border-radius: 5px; text-decoration: none; font-size: 0.9rem; font-weight: bold; display: inline-block; }
        .btn-action:hover { background: #2980b9; }

        /* NEW CANCEL BUTTON STYLE */
        .btn-cancel { 
            background: var(--red); 
            color: white; 
            padding: 8px 12px; 
            border: none;
            border-radius: 5px; 
            font-size: 0.9rem; 
            font-weight: bold; 
            cursor: pointer;
            margin-left: 5px;
        }
        .btn-cancel:hover { background: #c0392b; }
        
        .action-group { display: flex; align-items: center; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">iHealth<span>Pro</span></div>
        <div class="nav-links">
            <a href="staff_home.php" class="active">📅 My Schedule</a>
            <a href="reports.php">📊 Analytics</a> 
            <a href="#">👤 My Profile</a>
            <a href="logout.php" style="color: #ff6b6b; margin-top: 50px;">🚪 Logout</a>
        </div>
    </div>

    <div class="main-content">
        <header>
            <div>
                <h1><?php echo htmlspecialchars($doctor_name); ?></h1>
                <p style="color: #7f8c8d;">Here is your schedule for today.</p>
            </div>
            <div class="user-profile">
                Date: <?php echo date("d M Y"); ?>
            </div>
        </header>

        <div class="stats-grid">
            <div class="card">
                <h3>My Pending Appts</h3>
                <div class="number"><?php echo $count_pending; ?></div>
            </div>
            <div class="card" style="border-left-color: #2ecc71;">
                <h3>My Completed</h3>
                <div class="number"><?php echo $count_comp; ?></div>
            </div>
            <div class="card" style="border-left-color: #9B59B6;">
                <h3>Total Patients</h3>
                <div class="number"><?php echo $count_pat; ?></div>
            </div>
        </div>

        <div class="recent-appts">
            <div class="section-header">My Appointment List</div>
            
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient Name</th>
                        <th>Symptoms / Notes</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $has_rows = false;
                    while (($row = oci_fetch_assoc($stmt_list)) != false): 
                        $has_rows = true;
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo $row['APP_DATE']; ?></strong><br>
                                <span style="font-size:0.85em; color:#888;"><?php echo $row['APPOINTMENTTIME']; ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['PATIENT_NAME'] ?: 'Walk-in / Unregistered'); ?>
                            </td>
                            <td>
                                <span style="font-style:italic; color:#555;">
                                    <?php 
                                        $symp = isset($row['SYMPTOMS']) ? $row['SYMPTOMS'] : ''; 
                                        echo htmlspecialchars(substr($symp, 0, 30)) . (strlen($symp)>30 ? '...' : ''); 
                                    ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['SERVICE_NAME']); ?></td>
                            <td>
                                <?php 
                                    $st = strtolower($row['APPOINTMENTSTATUS']);
                                    if ($st == 'completed') $cls = 'completed';
                                    elseif ($st == 'cancelled') $cls = 'cancelled';
                                    else $cls = 'scheduled';
                                ?>
                                <span class="status <?php echo $cls; ?>">
                                    <?php echo htmlspecialchars($row['APPOINTMENTSTATUS']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['APPOINTMENTSTATUS'] == 'Scheduled'): ?>
                                    <div class="action-group">
                                        <a href="consultation.php?id=<?php echo $row['APPOINTMENTID']; ?>" class="btn-action">Start</a>
                                        
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to CANCEL this appointment?');">
                                            <input type="hidden" name="cancel_appt_id" value="<?php echo $row['APPOINTMENTID']; ?>">
                                            <button type="submit" class="btn-cancel">Cancel</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <a href="view_consultation.php?id=<?php echo $row['APPOINTMENTID']; ?>" class="btn-action" style="background-color: #27ae60;">View Details</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if (!$has_rows): ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px;">No appointments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>