<?php
session_start();
include 'dbconn.php';

// 1. Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Get Appointment ID
if (!isset($_GET['id'])) {
    // Redirect back based on role
    if ($_SESSION['role'] === 'STAFF') {
        header("Location: staff_home.php");
    } else {
        header("Location: patient_home.php");
    }
    exit();
}

$appt_id = $_GET['id'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// 3. Prepare the Update Query
// We only allow cancelling if status is 'Scheduled'
if ($role === 'STAFF') {
    // Staff can cancel ANY Scheduled appointment
    $sql = "UPDATE APPOINTMENT 
            SET APPOINTMENTSTATUS = 'Cancelled' 
            WHERE APPOINTMENTID = :aid AND APPOINTMENTSTATUS = 'Scheduled'";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":aid", $appt_id);

} else {
    // Customers can ONLY cancel their OWN Scheduled appointments
    $sql = "UPDATE APPOINTMENT 
            SET APPOINTMENTSTATUS = 'Cancelled' 
            WHERE APPOINTMENTID = :aid 
            AND USERID = :uid 
            AND APPOINTMENTSTATUS = 'Scheduled'";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":aid", $appt_id);
    oci_bind_by_name($stmt, ":uid", $user_id);
}

// 4. Execute
if (oci_execute($stmt)) {
    // Success - Redirect back
    if ($role === 'STAFF') {
        header("Location: staff_home.php?msg=cancelled");
    } else {
        header("Location: patient_home.php?msg=cancelled");
    }
} else {
    $e = oci_error($stmt);
    echo "Error cancelling appointment: " . $e['message'];
}
?>