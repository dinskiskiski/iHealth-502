<?php
session_start();
include 'dbconn.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Verify User Credentials
    $sql = "SELECT USERID, NAME, PASSWORD FROM USERS WHERE EMAIL = :email_bv";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":email_bv", $email);
    
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        echo "SQL Error: " . $e['message'];
    }
    
    $user = oci_fetch_assoc($stmt);

    if ($user) {
        // Verify Password (plain text check for now)
        if ($user['PASSWORD'] === $password) {
            
            $userid = $user['USERID'];
            $_SESSION['user_id'] = $userid;
            $_SESSION['user_name'] = $user['NAME'];

            // 2. Check if STAFF
            $sql_staff = "SELECT STAFFID FROM STAFF WHERE USERID = :target_id";
            $stmt_staff = oci_parse($conn, $sql_staff);
            oci_bind_by_name($stmt_staff, ":target_id", $userid);
            
            if (!oci_execute($stmt_staff)) {
                $e = oci_error($stmt_staff);
                die("Staff Check Error: " . $e['message']); 
            }
            $is_staff = oci_fetch_assoc($stmt_staff);

            if ($is_staff) {
                $_SESSION['role'] = 'STAFF';
                $_SESSION['staff_id'] = $is_staff['STAFFID'];
                header("Location: staff_home.php"); // Staff go to Dashboard
                exit();
            }

            // 3. Check if CUSTOMER (Patient)
            $sql_cust = "SELECT CUSTOMERID FROM CUSTOMERS WHERE USERID = :target_id_2";
            $stmt_cust = oci_parse($conn, $sql_cust);
            oci_bind_by_name($stmt_cust, ":target_id_2", $userid);
            
            if (!oci_execute($stmt_cust)) {
                $e = oci_error($stmt_cust);
                die("Customer Check Error: " . $e['message']);
            }
            $is_cust = oci_fetch_assoc($stmt_cust);

            if ($is_cust) {
                $_SESSION['role'] = 'PATIENT';
                $_SESSION['customer_id'] = $is_cust['CUSTOMERID'];
                header("Location: patient_home.php"); // Patients go to Portal
                exit();
            }

            $error = "Account exists, but no profile found (Not Staff or Patient).";
        
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "Email not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | iHealth</title>
    <style>
        :root { var(--primary: #008080); var(--light: #F4F7F6); }
        body { background-color: var(--light); font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; text-align: center; }
        .brand { font-size: 1.8rem; font-weight: bold; color: #2C3E50; margin-bottom: 0.5rem; }
        .brand span { color: #008080; } 
        .subtitle { color: #7f8c8d; margin-bottom: 2rem; font-size: 0.95rem; }
        input { width: 100%; padding: 12px; margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 1rem; }
        input:focus { outline: none; border-color: #008080; }
        
        /* Primary Button (Login) */
        .btn-primary { width: 100%; padding: 12px; background-color: #008080; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: 0.3s; margin-bottom: 10px; }
        .btn-primary:hover { background-color: #006666; }

        /* Secondary Button (Register) - New! */
        .btn-secondary { display: block; width: 100%; padding: 12px; background-color: white; color: #008080; border: 2px solid #008080; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; text-decoration: none; box-sizing: border-box; transition: 0.3s; }
        .btn-secondary:hover { background-color: #e0f2f1; }

        .error-msg { background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">iHealth<span>System</span></div>
        <p class="subtitle">Please log in to continue</p>

        <?php if($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="email" name="email" placeholder="Email Address" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            
            <button type="submit" class="btn-primary">Log In</button>
        </form>
        
        <a href="register.php" class="btn-secondary">Create New Account</a>

    </div>
</body>
</html>