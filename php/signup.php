<?php
session_start();
require_once 'config/db_connection.php';

$error_message = '';
$show_success_modal = false;

// Check if signup was successful
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $show_success_modal = true;
    // Clear the success flag from session
    if (isset($_SESSION['signup_success'])) {
        unset($_SESSION['signup_success']);
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $guest_name = trim(isset($_POST['guest_name']) ? $_POST['guest_name'] : '');
    $guest_email = trim(isset($_POST['guest_email']) ? $_POST['guest_email'] : '');
    $guest_password = trim(isset($_POST['guest_password']) ? $_POST['guest_password'] : '');

    // Validation
    if (empty($guest_name) || empty($guest_email) || empty($guest_password)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';

    } elseif (strlen($guest_password) < 6) {
        $error_message = 'Password must be at least 6 characters.';
    } else {
        // Convert to uppercase (except email)
        $guest_name = strtoupper($guest_name);
        $guest_email = strtolower($guest_email); // Ensure email is lowercase

        // Connect to database
        $conn = getDBConnection();

        if ($conn) {
            // Check if email already exists
            $check_sql = "SELECT COUNT(*) as count FROM GUEST WHERE guest_email = :guest_email";
            $check_stmt = oci_parse($conn, $check_sql);
            oci_bind_by_name($check_stmt, ':guest_email', $guest_email);

            if (oci_execute($check_stmt)) {
                $row = oci_fetch_array($check_stmt, OCI_ASSOC);
                if ($row && $row['COUNT'] > 0) {
                    $error_message = 'Email already exists. Please use a different email.';
                } else {
                    // Insert new guest with default guest_type = 'Regular'
                    // guestID is handled by trigger TRG_GUEST_ID
                    $guest_type = 'Regular';
                    $insert_sql = "INSERT INTO GUEST (guest_name, guest_email, guest_type, guest_password) 
                                   VALUES (:guest_name, :guest_email, :guest_type, :guest_password)";
                    $insert_stmt = oci_parse($conn, $insert_sql);

                    oci_bind_by_name($insert_stmt, ':guest_name', $guest_name);
                    oci_bind_by_name($insert_stmt, ':guest_email', $guest_email);
                    oci_bind_by_name($insert_stmt, ':guest_type', $guest_type);
                    oci_bind_by_name($insert_stmt, ':guest_password', $guest_password);

                    if (oci_execute($insert_stmt)) {
                        oci_commit($conn);
                        $_SESSION['signup_success'] = true;
                        // Redirect to prevent form resubmission
                        header('Location: signup.php?success=1');
                        exit();
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
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

            <form method="POST" action="signup.php" class="auth-form">

                <div class="form-group">
                    <label for="guest_email" class="form-label">
                        <i class='bx bx-envelope'></i>
                        Email Address <span class="required">*</span>
                    </label>
                    <input type="email" id="guest_email" name="guest_email" class="form-input"
                        placeholder="Enter your email address"
                        value="<?php echo isset($guest_email) ? htmlspecialchars($guest_email) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="guest_name" class="form-label">
                        <i class='bx bx-user'></i>
                        Full Name <span class="required">*</span>
                    </label>
                    <input type="text" id="guest_name" name="guest_name" class="form-input"
                        placeholder="Enter your full name"
                        value="<?php echo isset($guest_name) ? htmlspecialchars($guest_name) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="guest_password" class="form-label">
                        <i class='bx bx-key-alt'></i>
                        Password <span class="required">*</span>
                    </label>
                    <input type="password" id="guest_password" name="guest_password" class="form-input"
                        placeholder="Enter a strong password" required minlength="6">
                    <small class="form-hint">Use at least 6 characters. This will be your login password.</small>
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

    <!-- Success Modal Popup -->
    <div id="successModal" class="success-modal-overlay <?php echo $show_success_modal ? 'show' : ''; ?>">
        <div class="success-modal">
            <div class="success-modal-content">
                <div class="success-modal-icon">
                    <i class='bx bx-check'></i>
                </div>
                <h2 class="success-modal-title">Registration Successful!</h2>
                <p class="success-modal-message">
                    Your account has been created successfully. You can now login with your email and password.
                </p>
                <button onclick="redirectToLogin()" class="success-modal-button">
                    <i class='bx bx-log-in'></i>
                    Go to Login
                </button>
            </div>
        </div>
    </div>

    <script>
        // Auto-redirect after 4 seconds if modal is shown
        <?php if ($show_success_modal): ?>
            let redirectTimer;

            function redirectToLogin() {
                clearTimeout(redirectTimer);
                window.location.href = 'login.php';
            }

            // Auto-redirect after 4 seconds
            redirectTimer = setTimeout(function () {
                redirectToLogin();
            }, 4000);

            // Close modal on overlay click (optional - can be removed if you want to force button click)
            document.getElementById('successModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    redirectToLogin();
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>