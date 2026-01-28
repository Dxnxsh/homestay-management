<?php
/**
 * Session Check Helper
 * Use this file to protect pages that require guest authentication
 */

if (!isset($_SESSION)) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool Returns true if user is logged in, false otherwise
 */
function isGuestLoggedIn()
{
    return isset($_SESSION['guestID']) && isset($_SESSION['guest_email']);
}

/**
 * Require guest login - redirects to login if not authenticated
 */
function requireGuestLogin()
{
    if (!isGuestLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

/**
 * Get current guest ID
 * @return int|null Returns guest ID or null if not logged in
 */
function getCurrentGuestID()
{
    return isset($_SESSION['guestID']) ? $_SESSION['guestID'] : null;
}

/**
 * Get current guest name
 * @return string|null Returns guest name or null if not logged in
 */
function getCurrentGuestName()
{
    return isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : null;
}

/**
 * Get current guest email
 * @return string|null Returns guest email or null if not logged in
 */
function getCurrentGuestEmail()
{
    return isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : null;
}

/**
 * Check if guest profile is complete
 * @return bool Returns true if profile is complete, false otherwise
 */
function isGuestProfileComplete()
{
    require_once 'db_connection.php';

    if (!isGuestLoggedIn()) {
        return false;
    }

    $guestID = getCurrentGuestID();
    $conn = getDBConnection();

    if (!$conn) {
        return false;
    }

    $sql = "SELECT guest_phoneNo, guest_gender, guest_address 
            FROM GUEST 
            WHERE guestID = :guestID";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':guestID', $guestID);

    if (oci_execute($stmt)) {
        $row = oci_fetch_array($stmt, OCI_ASSOC);
        oci_free_statement($stmt);
        closeDBConnection($conn);

        if ($row) {
            // Check if required fields are filled
            $phoneNo = trim($row['GUEST_PHONENO'] ?? '');
            $gender = trim($row['GUEST_GENDER'] ?? '');
            $address = trim($row['GUEST_ADDRESS'] ?? '');

            // Profile is complete if all three fields are filled
            return !empty($phoneNo) && !empty($gender) && !empty($address);
        }
    } else {
        oci_free_statement($stmt);
        closeDBConnection($conn);
    }

    return false;
}

/**
 * Check if staff/manager is logged in
 * @return bool Returns true if staff/manager is logged in, false otherwise
 */
function isStaffLoggedIn()
{
    return isset($_SESSION['staffID']) && isset($_SESSION['staff_email']);
}

/**
 * Check if user is a manager
 * @return bool Returns true if user is a manager, false otherwise
 */
function isManager()
{
    return isStaffLoggedIn() && (!isset($_SESSION['managerID']) || $_SESSION['managerID'] === null);
}

/**
 * Require staff login (manager or regular staff) - redirects to login if not authenticated
 */
function requireStaffLogin()
{
    if (!isStaffLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

/**
 * Get current staff ID
 * @return int|null Returns staff ID or null if not logged in
 */
function getCurrentStaffID()
{
    return isset($_SESSION['staffID']) ? $_SESSION['staffID'] : null;
}

/**
 * Get current staff name
 * @return string|null Returns staff name or null if not logged in
 */
function getCurrentStaffName()
{
    return isset($_SESSION['staff_name']) ? $_SESSION['staff_name'] : null;
}

/**
 * Get current staff email
 * @return string|null Returns staff email or null if not logged in
 */
function getCurrentStaffEmail()
{
    return isset($_SESSION['staff_email']) ? $_SESSION['staff_email'] : null;
}