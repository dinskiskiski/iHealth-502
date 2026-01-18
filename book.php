<?php
session_start();
include 'dbconn.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'PATIENT') {
    header("Location: login.php");
    exit();
}

$message = "";
$msg_type = "";

// --- 1. HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Inputs from Form
    $service_id = $_POST['service'];
    $staff_id   = $_POST['staff'];
    $appt_date  = $_POST['date']; 
    $appt_time  = $_POST['time']; 
    $symptoms   = $_POST['symptoms'];
    $injury_loc = $_POST['injury_place'];
    $injury_type = $_POST['injury_type']; 
    
    // 1. Get USERID using CUSTOMERID
    $cust_id = $_SESSION['customer_id'];
    $get_user = oci_parse($conn, "SELECT USERID FROM CUSTOMERS WHERE CUSTOMERID = :cid");
    oci_bind_by_name($get_user, ":cid", $cust_id);
    oci_execute($get_user);
    $row_user = oci_fetch_assoc($get_user);
    
    if (!$row_user) { die("Error: User profile not found."); }
    $user_id = $row_user['USERID'];

    // 2. Handle File Upload (Referral Letter)
    $referral_filename = "None"; // Default
    
    if (isset($_FILES['referral']) && $_FILES['referral']['error'] == 0) {
        $allowed = array("jpg", "jpeg", "png", "pdf");
        $filename = $_FILES['referral']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            // Filename: ref_USERID_TIMESTAMP.ext
            $new_filename = "ref_" . $user_id . "_" . time() . "." . $ext;
            $destination = "uploads/" . $new_filename;
            
            if (move_uploaded_file($_FILES['referral']['tmp_name'], $destination)) {
                $referral_filename = $new_filename;
            } else {
                $message = "Error uploading file.";
                $msg_type = "error";
            }
        } else {
            $message = "Invalid file type. Only JPG, PNG, PDF allowed.";
            $msg_type = "error";
        }
    }

    if (empty($message)) { // Only proceed if no upload error
        $formatted_date = date("d-M-y", strtotime($appt_date));

        // 3. Generate IDs
        $s1 = oci_parse($conn, "SELECT NVL(MAX(APPOINTMENTID), 100) + 1 AS NEXT_ID FROM APPOINTMENT");
        oci_execute($s1);
        $r1 = oci_fetch_assoc($s1);
        $new_appt_id = $r1['NEXT_ID'];

        $s2 = oci_parse($conn, "SELECT NVL(MAX(RECORDID), 0) + 1 AS NEXT_ID FROM PATIENTRECORD");
        oci_execute($s2);
        $r2 = oci_fetch_assoc($s2);
        $new_record_id = $r2['NEXT_ID'];

        // 4. Insert into APPOINTMENT
        $sql_appt = "INSERT INTO APPOINTMENT (APPOINTMENTID, APPOINTMENTDATE, APPOINTMENTTIME, APPOINTMENTSTATUS, STAFFID, SERVICEID) 
                     VALUES (:aid, :adate, :atime, 'Scheduled', :sid, :serid)";
        
        $stmt_appt = oci_parse($conn, $sql_appt);
        oci_bind_by_name($stmt_appt, ":aid", $new_appt_id);
        oci_bind_by_name($stmt_appt, ":adate", $formatted_date);
        oci_bind_by_name($stmt_appt, ":atime", $appt_time);
        oci_bind_by_name($stmt_appt, ":sid", $staff_id);
        oci_bind_by_name($stmt_appt, ":serid", $service_id);

        // 5. Insert into PATIENTRECORD
        // FIXED: Renamed bind variables (e.g., :uid -> :b_uid) to avoid ORA-01745
        $sql_bridge = "INSERT INTO PATIENTRECORD (RECORDID, APPOINTMENTID, USERID, REFERLETTER, INJURYPLACE, TYPEOFINJURY, SYMPTOMS) 
                       VALUES (:rid, :aid_fk, :b_uid, :ref, :inj, :b_type, :sym)";
                       
        $stmt_bridge = oci_parse($conn, $sql_bridge);
        oci_bind_by_name($stmt_bridge, ":rid", $new_record_id);
        oci_bind_by_name($stmt_bridge, ":aid_fk", $new_appt_id);
        oci_bind_by_name($stmt_bridge, ":b_uid", $user_id); // Changed name
        oci_bind_by_name($stmt_bridge, ":ref", $referral_filename);
        oci_bind_by_name($stmt_bridge, ":inj", $injury_loc);
        oci_bind_by_name($stmt_bridge, ":b_type", $injury_type); // Changed name
        oci_bind_by_name($stmt_bridge, ":sym", $symptoms);

        // Execute Transaction
        $e1 = oci_execute($stmt_appt, OCI_NO_AUTO_COMMIT);
        $e2 = oci_execute($stmt_bridge, OCI_NO_AUTO_COMMIT);

        if ($e1 && $e2) {
            oci_commit($conn);
            $message = "Appointment Booked Successfully!";
            $msg_type = "success";
            header("refresh:2;url=patient_home.php");
        } else {
            // Capture errors safely
            $err1 = oci_error($stmt_appt);
            $err2 = oci_error($stmt_bridge);
            $err_msg = "";
            if($err1) $err_msg .= $err1['message'];
            if($err2) $err_msg .= " " . $err2['message'];
            
            oci_rollback($conn);
            $message = "DB Error: " . $err_msg;
            $msg_type = "error";
        }
    }
}

// --- FETCH DROPDOWNS ---
$services = [];
$s_serv = oci_parse($conn, "SELECT SERVICEID, NAME, FEE FROM SERVICE");
oci_execute($s_serv);
while ($row = oci_fetch_assoc($s_serv)) { $services[] = $row; }

$staff_list = [];
$s_staff = oci_parse($conn, "SELECT S.STAFFID, U.NAME, S.POSITION 
                             FROM STAFF S JOIN USERS U ON S.USERID = U.USERID");
oci_execute($s_staff);
while ($row = oci_fetch_assoc($s_staff)) { $staff_list[] = $row; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment | iHealth</title>
    <style>
        /* FIXED: CSS Variable syntax was wrong. Removed 'var()' from definition */
        :root { --primary: #008080; --light: #F4F7F6; }
        
        body { background-color: var(--light); font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; padding: 40px; }
        .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); width: 100%; max-width: 600px; }
        h2 { color: #2C3E50; border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-top: 0; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #444; }
        select, input, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box; }
        textarea { resize: vertical; height: 80px; }
        
        button { width: 100%; padding: 12px; background-color: var(--primary); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; margin-top: 1rem; }
        button:hover { background-color: #006666; }
        
        .btn-cancel { background-color: #95a5a6; display: block; text-align: center; text-decoration: none; margin-top: 10px; color: white; border-radius: 8px; padding: 12px; }
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 1rem; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .note { font-size: 0.85rem; color: #666; margin-top: -5px; margin-bottom: 10px; display: block;}
    </style>
</head>
<body>

    <div class="card">
        <h2>📅 Book New Appointment</h2>

        <?php if($message): ?>
            <div class="alert <?php echo $msg_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            
            <div class="form-group">
                <label>Select Service</label>
                <select name="service" required>
                    <option value="">-- Choose a Service --</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?php echo $s['SERVICEID']; ?>">
                            <?php echo $s['NAME']; ?> (RM <?php echo $s['FEE']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Select Doctor / Therapist</label>
                <select name="staff" required>
                    <option value="">-- Choose Staff --</option>
                    <?php foreach ($staff_list as $st): ?>
                        <option value="<?php echo $st['STAFFID']; ?>">
                            <?php echo $st['NAME']; ?> - <?php echo $st['POSITION']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Preferred Date & Time</label>
                <div style="display:flex; gap:10px;">
                    <input type="date" name="date" min="<?php echo date('Y-m-d'); ?>" required>
                    <select name="time" required>
                        <option value="09:00 AM">09:00 AM</option>
                        <option value="10:30 AM">10:30 AM</option>
                        <option value="02:00 PM">02:00 PM</option>
                        <option value="03:30 PM">03:30 PM</option>
                        <option value="04:30 PM">04:30 PM</option>
                    </select>
                </div>
            </div>

            <hr style="border:0; border-top:1px solid #eee; margin: 20px 0;">
            <h3 style="margin-top:0; color:#555;">Medical Details</h3>

            <div class="form-group">
                <label>Body Part / Injury Area</label>
                <input type="text" name="injury_place" placeholder="e.g. Right Knee, Lower Back" required>
            </div>

            <div class="form-group">
                <label>Cause / Type (Optional)</label>
                <input type="text" name="injury_type" placeholder="e.g. Sports Injury, Car Accident">
            </div>

            <div class="form-group">
                <label>Current Symptoms</label>
                <textarea name="symptoms" placeholder="Describe your pain, swelling, or stiffness..." required></textarea>
            </div>

            <div class="form-group">
                <label>Referral Letter (Optional)</label>
                <span class="note">Upload PDF or Image (Max 2MB)</span>
                <input type="file" name="referral" accept=".pdf,.jpg,.jpeg,.png">
            </div>

            <button type="submit">Confirm Booking</button>
            <a href="patient_home.php" class="btn-cancel">Cancel</a>
        </form>
    </div>

</body>
</html>