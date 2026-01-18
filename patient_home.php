<?php
session_start();
include 'dbconn.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'PATIENT') {
    header("Location: login.php");
    exit();
}

$name = $_SESSION['user_name'];
$current_user_id = $_SESSION['user_id']; 

// --- 1. HANDLE CANCELLATION REQUEST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_appt_id'])) {
    $appt_to_cancel = $_POST['cancel_appt_id'];
    
    // VERIFICATION: Check if this appointment belongs to the logged-in user via the BRIDGE table
    // FIXED: Table name PATIENTRECORD and bind variable :current_user_id
    $check_sql = "SELECT COUNT(*) AS CNT FROM PATIENTRECORD 
                  WHERE APPOINTMENTID = :aid AND USERID = :current_user_id";
    $stmt_check = oci_parse($conn, $check_sql);
    oci_bind_by_name($stmt_check, ":aid", $appt_to_cancel);
    oci_bind_by_name($stmt_check, ":current_user_id", $current_user_id);
    oci_execute($stmt_check);
    $check_row = oci_fetch_assoc($stmt_check);

    if ($check_row['CNT'] > 0) {
        // Ownership confirmed, proceed to cancel
        $sql_cancel = "UPDATE APPOINTMENT 
                       SET APPOINTMENTSTATUS = 'Cancelled' 
                       WHERE APPOINTMENTID = :aid";
        $stmt_c = oci_parse($conn, $sql_cancel);
        oci_bind_by_name($stmt_c, ":aid", $appt_to_cancel);
        
        if (oci_execute($stmt_c)) {
            $msg = "Appointment #$appt_to_cancel cancelled successfully.";
            $msg_type = "success";
        } else {
            $e = oci_error($stmt_c);
            $msg = "Error: " . $e['message'];
            $msg_type = "error";
        }
    } else {
        $msg = "Error: You do not have permission to cancel this appointment.";
        $msg_type = "error";
    }
}

// --- 2. FETCH UPCOMING APPOINTMENTS ---
// FIXED: 
// 1. Used Table Name: PATIENTRECORD
// 2. Changed bind variable from :uid to :current_user_id to fix ORA-01745
$sql = "SELECT 
            A.APPOINTMENTID, 
            TO_CHAR(A.APPOINTMENTDATE, 'DD Mon YYYY') AS APP_DATE, 
            A.APPOINTMENTTIME, 
            A.APPOINTMENTSTATUS,
            U.NAME AS DOCTOR_NAME
        FROM APPOINTMENT A
        JOIN PATIENTRECORD PR ON A.APPOINTMENTID = PR.APPOINTMENTID
        JOIN STAFF S ON A.STAFFID = S.STAFFID
        JOIN USERS U ON S.USERID = U.USERID
        WHERE PR.USERID = :current_user_id 
        AND A.APPOINTMENTSTATUS = 'Scheduled'
        ORDER BY A.APPOINTMENTDATE ASC";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":current_user_id", $current_user_id);

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo "Database Error: " . $e['message'];
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Portal | iHealth</title>
    <style>
        :root { --primary: #008080; --light: #F4F7F6; --dark: #2C3E50; --red: #e74c3c; }
        body { background-color: var(--light); font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; }
        
        .navbar { background: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .brand { font-size: 1.5rem; font-weight: bold; color: var(--dark); }
        .brand span { color: var(--primary); }
        .nav-links a { text-decoration: none; color: var(--dark); margin-left: 20px; font-weight: 500; }
        .nav-links a:hover { color: var(--primary); }
        .btn-logout { background: var(--red); color: white !important; padding: 8px 15px; border-radius: 6px; }

        .container { max-width: 1000px; margin: 40px auto; padding: 20px; }
        .welcome-banner { background: var(--primary); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
        .welcome-banner h1 { margin: 0; font-size: 2rem; }
        .welcome-banner p { margin: 10px 0 0; opacity: 0.9; }

        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .card { background: white; padding: 30px; border-radius: 12px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.2s; cursor: pointer; text-decoration: none; color: var(--dark); }
        .card:hover { transform: translateY(-5px); border-bottom: 5px solid var(--primary); }
        .icon { font-size: 3rem; color: var(--primary); margin-bottom: 15px; display: block; }
        .card h3 { margin: 0 0 10px; font-size: 1.4rem; }
        .card p { color: #7f8c8d; }

        .section-header { font-size: 1.2rem; font-weight: bold; color: var(--dark); margin-bottom: 15px; border-left: 5px solid var(--primary); padding-left: 10px; }
        .table-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #eee; color: #7f8c8d; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .btn-cancel { 
            background-color: white; 
            color: var(--red); 
            border: 1px solid var(--red); 
            padding: 6px 12px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-cancel:hover { background-color: var(--red); color: white; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">iHealth<span>Portal</span></div>
        <div class="nav-links">
            <a href="patient_home.php">Home</a>
            <a href="profile.php">My Profile</a> 
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </div>

    <div class="container">
        
        <?php if(isset($msg)): ?>
            <div class="alert <?php echo $msg_type; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="welcome-banner">
            <h1>Welcome, <?php echo htmlspecialchars($name); ?></h1>
            <p>Select an option below to manage your healthcare appointments.</p>
        </div>

        <div class="grid">
            <a href="book.php" class="card">
                <span class="icon">📅</span>
                <h3>Book Appointment</h3>
                <p>Schedule a new visit with a doctor.</p>
            </a>
            <a href="my_appointments.php" class="card">
                <span class="icon">📂</span>
                <h3>My History</h3>
                <p>View past appointments and medical records.</p>
            </a>
        </div>

        <div class="section-header">Your Upcoming Scheduled Appointments</div>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 0;
                    while ($row = oci_fetch_assoc($stmt)): 
                        $count++;
                    ?>
                    <tr>
                        <td><?php echo $row['APP_DATE']; ?></td>
                        <td><?php echo $row['APPOINTMENTTIME']; ?></td>
                        <td>Dr. <?php echo htmlspecialchars($row['DOCTOR_NAME']); ?></td>
                        <td><span style="color:#f39c12; font-weight:bold;">Scheduled</span></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                <input type="hidden" name="cancel_appt_id" value="<?php echo $row['APPOINTMENTID']; ?>">
                                <button type="submit" class="btn-cancel">Cancel</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                    <?php if ($count == 0): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:#999; padding: 20px;">
                            You have no upcoming scheduled appointments.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>