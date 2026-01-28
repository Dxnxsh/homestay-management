<?php
session_start();
require_once 'config/db_connection.php';

// Redirect if already logged in
if (isset($_SESSION['guestID']) && isset($_SESSION['guest_email'])) {
    header('Location: guest/home.php');
    exit();
} elseif (isset($_SESSION['staffID']) && isset($_SESSION['staff_email'])) {
    // Redirect all staff to manager folder (works for both manager and regular staff)
    header('Location: manager/dashboard.php');
    exit();
}

$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $password = trim(isset($_POST['password']) ? $_POST['password'] : '');

    // Validation
    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Connect to database
        $conn = getDBConnection();

        if ($conn) {
            $user_found = false;

            // First, check if email exists in GUEST table with matching password
            $guest_sql = "SELECT guestID, guest_name, guest_email 
                         FROM GUEST 
                         WHERE guest_email = :email AND guest_password = :password";
            $guest_stmt = oci_parse($conn, $guest_sql);
            oci_bind_by_name($guest_stmt, ':email', $email);
            oci_bind_by_name($guest_stmt, ':password', $password);

            if (oci_execute($guest_stmt)) {
                $guest_row = oci_fetch_array($guest_stmt, OCI_ASSOC);

                if ($guest_row) {
                    // Guest login successful
                    $_SESSION['guestID'] = $guest_row['GUESTID'];
                    $_SESSION['guest_name'] = $guest_row['GUEST_NAME'];
                    $_SESSION['guest_email'] = $guest_row['GUEST_EMAIL'];

                    // Check if profile is complete
                    require_once 'config/session_check.php';
                    if (!isGuestProfileComplete()) {
                        $_SESSION['profile_incomplete'] = true;
                    } else {
                        unset($_SESSION['profile_incomplete']);
                    }

                    oci_free_statement($guest_stmt);
                    closeDBConnection($conn);

                    header('Location: guest/home.php');
                    exit();
                }
            }
            oci_free_statement($guest_stmt);

            // If not a guest, check if email exists in STAFF table
            $staff_sql = "SELECT staffID, staff_name, staff_email, staff_type, staff_password, managerID
                         FROM STAFF 
                         WHERE staff_email = :email";
            $staff_stmt = oci_parse($conn, $staff_sql);
            oci_bind_by_name($staff_stmt, ':email', $email);

            if (oci_execute($staff_stmt)) {
                $staff_row = oci_fetch_array($staff_stmt, OCI_ASSOC + OCI_RETURN_NULLS);

                if ($staff_row) {
                    // Check if password matches staff_password
                    if ($password === $staff_row['STAFF_PASSWORD']) {
                        // Staff login successful
                        $_SESSION['staffID'] = $staff_row['STAFFID'];
                        $_SESSION['staff_name'] = $staff_row['STAFF_NAME'];
                        $_SESSION['staff_email'] = $staff_row['STAFF_EMAIL'];
                        $_SESSION['staff_type'] = $staff_row['STAFF_TYPE'];
                        // Use array key check or null coalescing operator because OCI might not return the key if null
                        $_SESSION['managerID'] = isset($staff_row['MANAGERID']) ? $staff_row['MANAGERID'] : null;

                        oci_free_statement($staff_stmt);
                        closeDBConnection($conn);

                        // Redirect all staff to manager folder
                        header('Location: manager/dashboard.php');
                        exit();
                    } else {
                        $error_message = 'Invalid email or password. Please try again.';
                    }
                } else {
                    $error_message = 'Invalid email or password. Please try again.';
                }
            } else {
                $error = oci_error($staff_stmt);
                $error_message = 'Database error: ' . $error['message'];
            }

            oci_free_statement($staff_stmt);
            closeDBConnection($conn);

            // If we reach here and no error message is set, credentials are invalid
            if (empty($error_message)) {
                $error_message = 'Invalid email or password. Please try again.';
            }
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
    <title>Login - Serena Sanctuary</title>
    <link rel="icon" type="image/png" href="../images/logoNbg.png">
    <link rel="stylesheet" href="../css/phpStyle/authStyle.css">
    <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="../images/logoNbg.png" alt="Serena Sanctuary logo" class="auth-logo">
                <h1 class="auth-title">Welcome Back</h1>
                <p class="auth-subtitle">Login to access your account</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class='bx bx-error-circle'></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="auth-form">
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class='bx bx-envelope'></i>
                        Email Address <span class="required">*</span>
                    </label>
                    <input type="email" id="email" name="email" class="form-input"
                        placeholder="Enter your email address"
                        value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class='bx bx-lock-alt'></i>
                        Password <span class="required">*</span>
                    </label>
                    <input type="password" id="password" name="password" class="form-input"
                        placeholder="Enter your password" required>
                    <small class="form-hint">Use your account password to login.</small>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class='bx bx-log-in'></i>
                    Login
                </button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="signup.php" class="auth-link">Sign up here</a></p>
                <p><a href="../index.html" class="auth-link"><i class='bx bx-arrow-back'></i> Back to Home</a></p>
            </div>
        </div>
    </div>
</body>

</html>