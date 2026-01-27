<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

$guestID = getCurrentGuestID();
$amount = 30.00; // RM30 membership price
$error_message = '';
$allowedReturnPages = ['membership.php', 'booking.php'];
$returnTo = 'membership.php';
if (!empty($_GET['return']) && in_array($_GET['return'], $allowedReturnPages, true)) {
    $returnTo = $_GET['return'];
}
$_SESSION['membership_return_to'] = $returnTo;

// Check if guest already has membership
$conn = getDBConnection();
$has_membership = false;
if ($conn) {
    $check_sql = "SELECT membershipID FROM MEMBERSHIP WHERE guestID = :guestID";
    $check_stmt = oci_parse($conn, $check_sql);
    oci_bind_by_name($check_stmt, ':guestID', $guestID);
    if (oci_execute($check_stmt)) {
        $existing = oci_fetch_array($check_stmt, OCI_ASSOC);
        if ($existing) {
            $has_membership = true;
        }
    }
    oci_free_statement($check_stmt);
    closeDBConnection($conn);
}

// If already has membership, redirect back
if ($has_membership) {
    $_SESSION['membership_flash'] = 'You already have an active membership.';
    $_SESSION['membership_flash_type'] = 'success';
    header('Location: ' . $returnTo);
    exit();
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank = trim($_POST['bank'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($bank)) {
        $error_message = 'Please select a bank.';
    } else {
        // Fake payment successful - redirect to process membership
        $_SESSION['payment_success'] = true;
        $_SESSION['payment_amount'] = $amount;
        $_SESSION['payment_bank'] = $bank;
        header('Location: process_membership_payment.php');
        exit();
    }
}

// Malaysian banks list
$banks = [
    'Affin Bank',
    'Alliance Bank',
    'AmBank',
    'Bank Islam',
    'Bank Muamalat',
    'Bank Rakyat',
    'Bank Simpanan Nasional',
    'CIMB Bank',
    'Hong Leong Bank',
    'HSBC Bank',
    'Maybank',
    'OCBC Bank',
    'Public Bank',
    'RHB Bank',
    'Standard Chartered',
    'UOB Bank'
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay with FPX - Serena Sanctuary</title>
    <link rel="icon" type="image/png" href="../../images/logoNbg.png">
    <link rel="stylesheet" href="../../css/phpStyle/guestStyle/fpxPaymentStyle.css">
    <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="payment-container">
        <div class="payment-card">
            <div class="payment-header">
                <span class="header-text">Pay with</span>
                <div class="fpx-logo">
                    <div class="fpx-diamond">
                        <div class="diamond-inner"></div>
                    </div>
                    <span class="fpx-text">FPX (Current and Saving Account)</span>
                </div>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class='bx bx-error-circle'></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="payment_fpx.php" class="payment-form">
                <div class="form-section">
                    <label class="form-label">Amount</label>
                    <div class="amount-field">
                        <span class="currency">MYR</span>
                        <input type="text" value="<?php echo number_format($amount, 2); ?>" readonly class="amount-input">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-section">
                        <label for="bank" class="form-label">Bank <span class="required">*</span></label>
                        <select name="bank" id="bank" class="form-select" required>
                            <option value="">Select Bank</option>
                            <?php foreach ($banks as $bank): ?>
                                <option value="<?php echo htmlspecialchars($bank); ?>"><?php echo htmlspecialchars($bank); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-section">
                        <label for="email" class="form-label">Email Address (optional)</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="example@example.com"
                            value=""
                        >
                    </div>
                </div>

                <div class="terms-section">
                    <p class="terms-text">
                        By clicking on the "Proceed" button, you hereby agree with 
                        <a href="#" class="terms-link">FPX's Terms and Conditions</a>.
                    </p>
                </div>

                <button type="submit" class="btn-proceed">
                    Proceed
                </button>

                <div class="powered-by">
                    <span>Powered by FPX</span>
                    <div class="fpx-logo-small">
                        <div class="fpx-diamond-small">
                            <div class="diamond-inner-small"></div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="instructions">
                <ul class="instructions-list">
                    <li>You must have Internet Banking Account in order to make transaction using FPX.</li>
                    <li>Please ensure that your browser's pop up blocker has been disabled to avoid any interruption during making transaction.</li>
                    <li>Do not close browser / refresh page until you receive response.</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>



