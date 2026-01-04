<?php
session_start();
require_once 'config/db_connection.php';

$error_message = '';
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guestID = trim(isset($_POST['guestID']) ? $_POST['guestID'] : '');
    $guest_name = trim(isset($_POST['guest_name']) ? $_POST['guest_name'] : '');
    $guest_email = trim(isset($_POST['guest_email']) ? $_POST['guest_email'] : '');
    
    // Validation
    if (empty($guestID) || empty($guest_name) || empty($guest_email)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (!is_numeric($guestID) || $guestID <= 0) {
        $error_message = 'Guest ID must be a positive number.';
    } else {
        // Convert to uppercase (except email)
        $guest_name = strtoupper($guest_name);
        $guest_email = strtolower($guest_email); // Ensure email is lowercase
        
        // Connect to database
        $conn = getDBConnection();
        
        if ($conn) {
            // Check if guestID or email already exists
            $check_sql = "SELECT COUNT(*) as count FROM GUEST WHERE guestID = :guestID OR guest_email = :guest_email";
            $check_stmt = oci_parse($conn, $check_sql);
            oci_bind_by_name($check_stmt, ':guestID', $guestID);
            oci_bind_by_name($check_stmt, ':guest_email', $guest_email);
            
            if (oci_execute($check_stmt)) {
                $row = oci_fetch_array($check_stmt, OCI_ASSOC);
                if ($row && $row['COUNT'] > 0) {
                    $error_message = 'Guest ID or Email already exists. Please use different credentials.';
                } else {
                    // Insert new guest with default guest_type = 'Regular'
                    $guest_type = 'REGULAR';
                    $insert_sql = "INSERT INTO GUEST (guestID, guest_name, guest_email, guest_type) 
                                   VALUES (:guestID, :guest_name, :guest_email, :guest_type)";
                    $insert_stmt = oci_parse($conn, $insert_sql);
                    
                    oci_bind_by_name($insert_stmt, ':guestID', $guestID);
                    oci_bind_by_name($insert_stmt, ':guest_name', $guest_name);
                    oci_bind_by_name($insert_stmt, ':guest_email', $guest_email);
                    oci_bind_by_name($insert_stmt, ':guest_type', $guest_type);
                    
                    if (oci_execute($insert_stmt)) {
                        oci_commit($conn);
                        $success_message = 'Registration successful! You can now login with your email and Guest ID.';
                        // Clear form data
                        $guestID = $guest_name = $guest_email = '';
                    } else {
                        $error = oci_error($insert_stmt);
                        $error_message = 'Registration failed: ' . $error['message'];
                    }
                    
                    oci_free_statement($insert_stmt);
                }
            } else {
                $error = oci_error($check_stmt);
                $error_message = 'Database error: ' . $error['message'];
            }
            
            oci_free_statement($check_stmt);
            closeDBConnection($conn);
        } else {
            $error_message = 'Unable to connect to database. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Serena Sanctuary</title>
    <link rel="icon" type="image/png" href="../images/logoNbg.png">
    <link rel="stylesheet" href="../css/phpStyle/authStyle.css">
    <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="../images/logoNbg.png" alt="Serena Sanctuary logo" class="auth-logo">
                <h1 class="auth-title">Create Account</h1>
                <p class="auth-subtitle">Join Serena Sanctuary and start your homestay journey</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class='bx bx-error-circle'></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class='bx bx-check-circle'></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="signup.php" class="auth-form">
                <div class="form-group">
                    <label for="guestID" class="form-label">
                        <i class='bx bx-id-card'></i>
                        Guest ID <span class="required">*</span>
                    </label>
                    <input 
                        type="number" 
                        id="guestID" 
                        name="guestID" 
                        class="form-input" 
                        placeholder="Enter your Guest ID"
                        value="<?php echo isset($guestID) ? htmlspecialchars($guestID) : ''; ?>"
                        required
                        min="1"
                    >
                    <small class="form-hint">Your Guest ID will be used as your password for login</small>
                </div>

                <div class="form-group">
                    <label for="guest_name" class="form-label">
                        <i class='bx bx-user'></i>
                        Full Name <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="guest_name" 
                        name="guest_name" 
                        class="form-input" 
                        placeholder="Enter your full name"
                        value="<?php echo isset($guest_name) ? htmlspecialchars($guest_name) : ''; ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="guest_email" class="form-label">
                        <i class='bx bx-envelope'></i>
                        Email Address <span class="required">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="guest_email" 
                        name="guest_email" 
                        class="form-input" 
                        placeholder="Enter your email address"
                        value="<?php echo isset($guest_email) ? htmlspecialchars($guest_email) : ''; ?>"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class='bx bx-user-plus'></i>
                    Sign Up
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php" class="auth-link">Login here</a></p>
                <p><a href="../index.html" class="auth-link"><i class='bx bx-arrow-back'></i> Back to Home</a></p>
            </div>
        </div>
    </div>
</body>
</html>

