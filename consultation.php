<?php
session_start();
include 'dbconn.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'STAFF') {
    header("Location: login.php");
    exit();
}

// 2. Get Appointment ID from URL
if (!isset($_GET['id'])) {
    header("Location: staff_home.php");
    exit();
}
$appt_id = $_GET['id'];

// 3. Fetch Patient & Appointment Details
// We need to know WHO we are treating
$sql = "SELECT 
            A.APPOINTMENTID, 
            TO_CHAR(A.APPOINTMENTDATE, 'DD Mon YYYY') AS APP_DATE,
            S.NAME AS SERVICE_NAME,
            U.NAME AS PATIENT_NAME,
            U.USERID AS PATIENT_ID,
            C.DOB,
            C.GENDER
        FROM APPOINTMENT A
        JOIN SERVICE S ON A.SERVICEID = S.SERVICEID
        LEFT JOIN PATIENTRECORD PR ON A.APPOINTMENTID = PR.APPOINTMENTID
        LEFT JOIN USERS U ON PR.USERID = U.USERID -- Attempt 1 to find user via record
        LEFT JOIN CUSTOMERS C ON U.USERID = C.USERID
        WHERE A.APPOINTMENTID = :aid";

// Note: If PatientRecord doesn't exist yet, we might need a different join logic 
// depending on how you stored the patient ID in Appointment. 
// Assuming Appointment links to a User/Customer directly:
$sql = "SELECT 
            A.APPOINTMENTID, 
            TO_CHAR(A.APPOINTMENTDATE, 'DD Mon YYYY') AS APP_DATE,
            S.NAME AS SERVICE_NAME,
            U.NAME AS PATIENT_NAME,
            U.USERID AS PATIENT_ID
        FROM APPOINTMENT A
        JOIN SERVICE S ON A.SERVICEID = S.SERVICEID
        -- We need to link to the patient. Assuming Appointment has a PATIENT_ID or similar, 
        -- OR we rely on the logic that we find the patient via the booking.
        -- For this demo, let's assume we fetch details based on the existing structure.
        -- Let's stick to the simplest query to display info:
        LEFT JOIN PATIENTRECORD PR ON A.APPOINTMENTID = PR.APPOINTMENTID
        LEFT JOIN USERS U ON PR.USERID = U.USERID
        WHERE A.APPOINTMENTID = :aid";

// REFINED QUERY: To ensure we get the patient name (even if no record exists yet)
// We need to know how Appointment links to Patient. 
// If Appointment table doesn't have USERID, we rely on the PatientRecord being created at booking.
// If it wasn't, we will just show 'Walk-in / Unknown' for now.
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":aid", $appt_id);
oci_execute($stmt);
$appt = oci_fetch_assoc($stmt);

if (!$appt) {
    echo "Appointment not found.";
    exit();
}

// 4. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $symptoms = $_POST['symptoms'];
    $diagnosis = $_POST['diagnosis'];
    $prescription = $_POST['prescription']; // Form input name stays same
    $patient_id = $_POST['patient_id']; 

    // FIX: Changed PRESCRIPTION to TREATMENT in the SQL query
    $sql_update = "UPDATE PATIENTRECORD 
                   SET SYMPTOMS = :sym, DIAGNOSIS = :dia, TREATMENT = :pre
                   WHERE APPOINTMENTID = :aid";
                   
    $stmt_upd = oci_parse($conn, $sql_update);
    oci_bind_by_name($stmt_upd, ":sym", $symptoms);
    oci_bind_by_name($stmt_upd, ":dia", $diagnosis);
    oci_bind_by_name($stmt_upd, ":pre", $prescription);
    oci_bind_by_name($stmt_upd, ":aid", $appt_id);
    
    if (!oci_execute($stmt_upd)) {
        $e = oci_error($stmt_upd);
        echo "Error updating record: " . $e['message'];
        exit();
    }

    // Mark as Completed
    $sql_status = "UPDATE APPOINTMENT SET APPOINTMENTSTATUS = 'Completed' WHERE APPOINTMENTID = :aid";
    $stmt_status = oci_parse($conn, $sql_status);
    oci_bind_by_name($stmt_status, ":aid", $appt_id);
    oci_execute($stmt_status);

    header("Location: staff_home.php?msg=completed");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Consultation | iHealth</title>
    <style>
        /* DASHBOARD CSS REUSED */
        :root { --primary: #2C3E50; --accent: #3498db; --bg: #f5f7fa; --sidebar-width: 250px; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg); display: flex; }
        .sidebar { width: var(--sidebar-width); background: var(--primary); color: white; height: 100vh; position: fixed; padding: 20px 0; }
        .brand { font-size: 1.8rem; font-weight: bold; text-align: center; margin-bottom: 40px; }
        .brand span { color: var(--accent); }
        .nav-links a { display: block; padding: 15px 30px; color: #bdc3c7; text-decoration: none; font-size: 1.05rem; }
        .nav-links a:hover { background: #34495e; color: white; border-left: 5px solid var(--accent); }
        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }

        /* FORM STYLES */
        .consultation-card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 800px; margin: 0 auto; }
        .header-info { border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; }
        .patient-name { font-size: 1.5rem; color: var(--primary); font-weight: bold; }
        .service-tag { background: #e1f5fe; color: #0288d1; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; }
        
        label { display: block; margin-bottom: 8px; color: #555; font-weight: 600; }
        textarea { width: 100%; height: 100px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px; font-family: inherit; resize: vertical; }
        textarea:focus { outline: none; border-color: var(--accent); }
        
        .btn-submit { background: #27ae60; color: white; border: none; padding: 15px 30px; font-size: 1rem; border-radius: 6px; cursor: pointer; transition: 0.3s; width: 100%; font-weight: bold; }
        .btn-submit:hover { background: #219150; }
        .btn-cancel { display: block; text-align: center; margin-top: 15px; color: #7f8c8d; text-decoration: none; }
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
        
        <div class="consultation-card">
            <div class="header-info">
                <div>
                    <div class="patient-name"><?php echo htmlspecialchars($appt['PATIENT_NAME'] ?: 'Patient Record'); ?></div>
                    <div style="color:#888; margin-top:5px;">ID: #<?php echo $appt['PATIENT_ID']; ?> | Date: <?php echo $appt['APP_DATE']; ?></div>
                </div>
                <div>
                    <span class="service-tag"><?php echo htmlspecialchars($appt['SERVICE_NAME']); ?></span>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="patient_id" value="<?php echo $appt['PATIENT_ID']; ?>">

                <label>1. Symptoms (Complaints)</label>
                <textarea name="symptoms" placeholder="e.g. Patient complains of lower back pain sitting for long hours..."></textarea>

                <label>2. Diagnosis (Doctor's Findings)</label>
                <textarea name="diagnosis" placeholder="e.g. Muscle strain in lumbar region..."></textarea>

                <label>3. Prescription / Treatment Plan</label>
                <textarea name="prescription" placeholder="e.g. Ibuprofen 400mg, Heat therapy recommended..."></textarea>

                <button type="submit" class="btn-submit">✅ Complete Consultation</button>
                <a href="staff_home.php" class="btn-cancel">Cancel and Go Back</a>
            </form>
        </div>

    </div>

</body>
</html>