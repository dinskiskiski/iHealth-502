<?php
session_start();
include 'dbconn.php';

// Security: Check if Patient
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'PATIENT') {
    header("Location: login.php");
    exit();
}

// Get the logged-in User ID
$user_id = 0;
$cust_id = $_SESSION['customer_id'];

// Fetch USERID from CUSTOMERS table
$u_sql = oci_parse($conn, "SELECT USERID FROM CUSTOMERS WHERE CUSTOMERID = :cid");
oci_bind_by_name($u_sql, ":cid", $cust_id);
oci_execute($u_sql);
$row = oci_fetch_assoc($u_sql);

if ($row) {
    $user_id = $row['USERID'];
} else {
    echo "Error: Customer Record Not Found.";
    exit();
}

// --- FETCH APPOINTMENTS ---
// FIXED: Changed bind variable from :uid to :my_user_id to avoid ORA-01745
$sql = "SELECT A.APPOINTMENTID, 
               A.APPOINTMENTDATE, 
               A.APPOINTMENTTIME, 
               A.APPOINTMENTSTATUS, 
               S.NAME AS SERVICE_NAME, 
               S.FEE,
               U.NAME AS DOCTOR_NAME,
               PR.REFERLETTER
        FROM PATIENTRECORD PR
        JOIN APPOINTMENT A ON PR.APPOINTMENTID = A.APPOINTMENTID
        JOIN SERVICE S ON A.SERVICEID = S.SERVICEID
        JOIN STAFF ST ON A.STAFFID = ST.STAFFID
        JOIN USERS U ON ST.USERID = U.USERID
        WHERE PR.USERID = :my_user_id
        ORDER BY A.APPOINTMENTDATE DESC, A.APPOINTMENTTIME DESC";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":my_user_id", $user_id); // Using the safe name
oci_execute($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My History | iHealth</title>
    <style>
        /* FIXED CSS: Removed 'var()' from definition */
        :root { --primary: #008080; --light: #F4F7F6; }
        
        body { background-color: var(--light); font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid var(--primary); padding-bottom: 10px; }
        h2 { color: #2C3E50; margin: 0; }
        .btn-home { text-decoration: none; background: #95a5a6; color: white; padding: 8px 15px; border-radius: 6px; font-size: 0.9rem; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: var(--primary); color: white; }
        tr:hover { background-color: #f1f1f1; }
        
        .status { padding: 5px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; }
        .status-Scheduled { background: #fff3cd; color: #856404; }
        .status-Completed { background: #d4edda; color: #155724; }
        .status-Cancelled { background: #f8d7da; color: #721c24; }
        
        .btn-view { text-decoration: none; color: var(--primary); font-weight: bold; }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <h2>📂 My Appointment History</h2>
            <a href="patient_home.php" class="btn-home">Back to Home</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Service</th>
                    <th>Doctor / Therapist</th>
                    <th>Status</th>
                    <th>Referral</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = oci_fetch_assoc($stmt)): ?>
                    <tr>
                        <td>
                            <strong><?php echo $row['APPOINTMENTDATE']; ?></strong><br>
                            <span style="color:#666; font-size:0.9rem;"><?php echo $row['APPOINTMENTTIME']; ?></span>
                        </td>
                        <td>
                            <?php echo $row['SERVICE_NAME']; ?><br>
                            <span style="font-size:0.85rem; color:#888;">RM <?php echo $row['FEE']; ?></span>
                        </td>
                        <td>Dr. <?php echo $row['DOCTOR_NAME']; ?></td>
                        <td>
                            <span class="status status-<?php echo $row['APPOINTMENTSTATUS']; ?>">
                                <?php echo $row['APPOINTMENTSTATUS']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if(isset($row['REFERLETTER']) && $row['REFERLETTER'] != 'None'): ?>
                                <a href="uploads/<?php echo $row['REFERLETTER']; ?>" target="_blank" class="btn-view">View File</a>
                            <?php else: ?>
                                <span style="color:#ccc;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <?php 
        // Optional: Message if no rows found
        if (oci_num_rows($stmt) == 0) {
            echo "<p style='text-align:center; padding:20px; color:#777;'>No appointment history found.</p>";
        }
        ?>
    </div>

</body>
</html>