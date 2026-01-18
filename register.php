<?php
session_start();
include 'dbconn.php';

$message = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- 1. COLLECT DATA ---
    $user_type = $_POST['user_type']; 
    $name = $_POST['name'];
    $email = trim($_POST['email']); // Trim to remove spaces
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    
    // Clean IC
    $raw_ic = preg_replace('/[^0-9]/', '', $_POST['ic_number']);
    $ic_number = $_POST['ic_number'];
    if(strlen($raw_ic) == 12) {
        $ic_number = substr($raw_ic, 0, 6) . '-' . substr($raw_ic, 6, 2) . '-' . substr($raw_ic, 8, 4);
    }

    // --- 2. CHECK FOR DUPLICATE EMAIL (The Solution) ---
    $sql_check = "SELECT COUNT(*) AS CNT FROM USERS WHERE EMAIL = :email_check";
    $stmt_check = oci_parse($conn, $sql_check);
    oci_bind_by_name($stmt_check, ":email_check", $email);
    oci_execute($stmt_check);
    $row_check = oci_fetch_assoc($stmt_check);

    if ($row_check['CNT'] > 0) {
        // Email exists! Stop here.
        $message = "Error: This email ($email) is already registered. Please Log In.";
        $msg_type = "error";
    } else {
        // Email is new. Proceed with Registration.

        // --- 3. GENERATE IDs ---
        $s1 = oci_parse($conn, "SELECT NVL(MAX(USERID), 0) + 1 AS NEXT_ID FROM USERS");
        oci_execute($s1);
        $r1 = oci_fetch_assoc($s1);
        $new_userid = $r1['NEXT_ID'];

        $s2 = oci_parse($conn, "SELECT NVL(MAX(CUSTOMERID), 0) + 1 AS NEXT_ID FROM CUSTOMERS");
        oci_execute($s2);
        $r2 = oci_fetch_assoc($s2);
        $new_cust_id = $r2['NEXT_ID'];

        // --- 4. INSERT DATA ---
        
        // A. Insert User
        $sql_user = "INSERT INTO USERS (USERID, NAME, EMAIL, PHONENUMBER, PASSWORD) 
                     VALUES (:user_bv, :name_bv, :email_bv, :phone_bv, :pass_bv)";
        $stmt_user = oci_parse($conn, $sql_user);
        oci_bind_by_name($stmt_user, ":user_bv", $new_userid);
        oci_bind_by_name($stmt_user, ":name_bv", $name);
        oci_bind_by_name($stmt_user, ":email_bv", $email);
        oci_bind_by_name($stmt_user, ":phone_bv", $phone);
        oci_bind_by_name($stmt_user, ":pass_bv", $password);

        // B. Insert Customer
        $sql_cust = "INSERT INTO CUSTOMERS (CUSTOMERID, ICNUMBER, USERID) 
                     VALUES (:cust_bv, :ic_bv, :user_bv)";
        $stmt_cust = oci_parse($conn, $sql_cust);
        oci_bind_by_name($stmt_cust, ":cust_bv", $new_cust_id);
        oci_bind_by_name($stmt_cust, ":ic_bv", $ic_number);
        oci_bind_by_name($stmt_cust, ":user_bv", $new_userid);

        // Execute Parents
        $e1 = oci_execute($stmt_user, OCI_NO_AUTO_COMMIT);
        if (!$e1) { $err = oci_error($stmt_user); die("User Insert Error: " . $err['message']); }

        $e2 = oci_execute($stmt_cust, OCI_NO_AUTO_COMMIT);
        if (!$e2) { $err = oci_error($stmt_cust); die("Customer Insert Error: " . $err['message']); }

        $e3 = false; 

        // C. Insert Child (Student or Public)
        if ($user_type == 'student') {
            $matric_no = $_POST['matric_no'];
            $faculty = $_POST['faculty'];
            $program = $_POST['program'];

            $sql_stu = "INSERT INTO STUDENT (STUDENTID, FACULTY, PROGRAM, CUSTOMERID) 
                        VALUES (:stu_bv, :fac_bv, :prog_bv, :cust_bv)";
            $stmt_stu = oci_parse($conn, $sql_stu);
            oci_bind_by_name($stmt_stu, ":stu_bv", $matric_no);
            oci_bind_by_name($stmt_stu, ":fac_bv", $faculty);
            oci_bind_by_name($stmt_stu, ":prog_bv", $program);
            oci_bind_by_name($stmt_stu, ":cust_bv", $new_cust_id);

            $e3 = oci_execute($stmt_stu, OCI_NO_AUTO_COMMIT);
            if (!$e3) { $err = oci_error($stmt_stu); die("Student Insert Error: " . $err['message']); }

        } else {
            // Public User (Using correct table PUBLICS)
            $s3 = oci_parse($conn, "SELECT NVL(MAX(PUBLICID), 0) + 1 AS NEXT_ID FROM PUBLICS");
            oci_execute($s3);
            $r3 = oci_fetch_assoc($s3);
            $new_public_id = $r3['NEXT_ID'];

            $sql_pub = "INSERT INTO PUBLICS (PUBLICID, CUSTOMERID) VALUES (:pub_bv, :cust_bv)";
            $stmt_pub = oci_parse($conn, $sql_pub);
            oci_bind_by_name($stmt_pub, ":pub_bv", $new_public_id);
            oci_bind_by_name($stmt_pub, ":cust_bv", $new_cust_id);

            $e3 = oci_execute($stmt_pub, OCI_NO_AUTO_COMMIT);
            if (!$e3) { $err = oci_error($stmt_pub); die("Public Insert Error: " . $err['message']); }
        }

        // --- 5. COMMIT OR ROLLBACK ---
        if ($e1 && $e2 && $e3) {
            oci_commit($conn);
            $message = "Registration Successful! Redirecting to login...";
            $msg_type = "success";
            header("refresh:2;url=login.php");
        } else {
            oci_rollback($conn);
            $message = "Registration Failed. Please try again.";
            $msg_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | iHealth</title>
    <style>
        :root { var(--primary: #008080); var(--light: #F4F7F6); }
        body { background-color: var(--light); font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .reg-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 500px; }
        .brand { text-align: center; font-size: 1.8rem; font-weight: bold; color: #2C3E50; margin-bottom: 1.5rem; }
        .brand span { color: #008080; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.3rem; color: #444; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 1rem; }
        .user-type-selector { display: flex; gap: 15px; margin-bottom: 1.5rem; background: #f9f9f9; padding: 10px; border-radius: 8px; }
        .radio-label { display: flex; align-items: center; gap: 5px; cursor: pointer; font-weight: normal; }
        button { width: 100%; padding: 12px; background-color: #008080; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; margin-top: 1rem; }
        button:hover { background-color: #006666; }
        .hidden-section { display: none; background: #e0f2f1; padding: 15px; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #b2dfdb; }
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 1rem; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
    
    <script>
        function toggleStudentFields() {
            var type = document.querySelector('input[name="user_type"]:checked').value;
            var section = document.getElementById('student-section');
            if (type === 'student') {
                section.style.display = 'block';
                document.getElementById('matric').required = true;
                document.getElementById('fac').required = true;
            } else {
                section.style.display = 'none';
                document.getElementById('matric').required = false;
                document.getElementById('fac').required = false;
            }
        }
    </script>
</head>
<body>
    <div class="reg-card">
        <div class="brand">iHealth<span>Register</span></div>

        <?php if($message): ?>
            <div class="alert <?php echo $msg_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label>I am a:</label>
            <div class="user-type-selector">
                <label class="radio-label"><input type="radio" name="user_type" value="student" checked onchange="toggleStudentFields()"> Student</label>
                <label class="radio-label"><input type="radio" name="user_type" value="public" onchange="toggleStudentFields()"> Public User</label>
            </div>

            <div class="form-group"><label>Full Name</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Email Address</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Phone Number</label><input type="text" name="phone" required></div>

            <div class="form-group">
                <label>IC Number (Identity Card)</label>
                <input type="text" name="ic_number" id="ic_display" placeholder="e.g. 980101-10-5555" maxlength="14" required>
            </div>

            <div id="student-section" class="hidden-section" style="display:block;">
                <div class="form-group"><label>Student ID (Matric No)</label><input type="text" name="matric_no" id="matric"></div>
                <div class="form-group"><label>Faculty</label><input type="text" name="faculty" id="fac"></div>
                <div class="form-group"><label>Program Code/Name</label><input type="text" name="program"></div>
            </div>

            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>

            <button type="submit">Create Account</button>
        </form>
        
        <p style="text-align:center; margin-top:1rem; font-size:0.9rem;">
            Already have an account? <a href="login.php" style="color:#008080; font-weight:bold;">Log In</a>
        </p>
    </div>

    <script>
        document.getElementById('ic_display').addEventListener('input', function (e) {
            var target = e.target;
            var input = target.value.replace(/\D/g, '').substring(0, 12); 
            var zip = input.substring(0, 6);
            var middle = input.substring(6, 8);
            var last = input.substring(8, 12);

            if (input.length > 6) { zip += "-" + middle; }
            if (input.length > 8) { zip += "-" + last; }

            target.value = zip;
        });
    </script>
</body>
</html>