<?php
// dbconn.php

// 1. Database Credentials
// Update 'your_password' with the password you set for IHEALTHADMIN
$username = "IHEALTHADMIN";
$password = "oracle";

// 2. Connection String
// Format: //hostname:port/service_name
// For Oracle 23ai, the default port is 1521 and service name is usually FREE
$connection_string = "//localhost:1521/FREEPDB1";

// 3. Establish Connection
// oci_connect creates a connection to the Oracle database
$conn = oci_connect($username, $password, $connection_string);

// 4. Check Connection
if (!$conn) {
    $e = oci_error();
    // In production, log this error instead of showing it to the user
    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
    echo "Connection Failed: " . $e['message'];
} else {
    // Uncomment the line below for testing, but comment it out for production
    // echo "Successfully connected to Oracle Database!";
}

?>