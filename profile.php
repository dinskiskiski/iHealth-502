<?php
session_start();
include 'dbconn.php';

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'PATIENT') {
    header("Location: login.php");
    exit();
}

$cust_id = $_SESSION['customer_id'];

// FIXED SQL: 
// 1. Moved ADDRESS to USERS table (U.ADDRESS)
// 2. Kept GENDER in USERS table (U.GENDER)
$sql = "SELECT U.NAME, U.IC_NUMBER, U.PHONENUMBER, U.GENDER, U.ADDRESS 
        FROM CUSTOMERS C
        JOIN USERS U ON C.USERID = U.USERID
        WHERE C.CUSTOMERID = :cid";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":cid", $cust_id);
oci_execute($stmt);
$user = oci_fetch_assoc($stmt);

if (!$user) {
    echo "Profile not found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | iHealth</title>
    <style>
        :root { --primary: #008080; --light: #F4F7F6; }
        body { background-color: var(--light); font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; padding: 40px; }
        .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); width: 100%; max-width: 500px; }
        h2 { color: #2C3E50; border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-top: 0; }
        .row { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .label { font-weight: bold; color: #555; display: block; margin-bottom: 5px; }
        .value { color: #2C3E50; font-size: 1.1rem; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; background: #95a5a6; color: white; padding: 10px 20px; border-radius: 6px; }
        .btn-back:hover { background: #7f8c8d; }
    </style>
</head>
<body>

    <div class="card">
        <h2>👤 My Profile</h2>

        <div class="row">
            <span class="label">Full Name</span>
            <span class="value"><?php echo $user['NAME']; ?></span>
        </div>

        <div class="row">
            <span class="label">IC Number</span>
            <span class="value"><?php echo $user['IC_NUMBER']; ?></span>
        </div>

        <div class="row">
            <span class="label">Phone Number</span>
            <span class="value"><?php echo $user['PHONENUMBER']; ?></span>
        </div>

        <div class="row">
            <span class="label">Gender</span>
            <span class="value"><?php echo isset($user['GENDER']) ? $user['GENDER'] : '-'; ?></span>
        </div>

        <div class="row" style="border:none;">
            <span class="label">Address</span>
            <span class="value"><?php echo isset($user['ADDRESS']) ? $user['ADDRESS'] : '-'; ?></span>
        </div>

        <a href="patient_home.php" class="btn-back">Back to Home</a>
    </div>

</body>
</html>