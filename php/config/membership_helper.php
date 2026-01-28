<?php
/**
 * Helper function to update membership tier based on booking count
 * 
 * Rules:
 * - 0-4 bookings: Bronze (10%)
 * - 5-9 bookings: Silver (20%)
 * - 10+ bookings: Gold (30%)
 */
function updateMembershipTier($conn, $guestID)
{
    if (!$conn || !$guestID) {
        return false;
    }

    // 1. Check if guest has a membership record
    $check_sql = "SELECT membershipID, disc_rate FROM MEMBERSHIP WHERE guestID = :guestID";
    $check_stmt = oci_parse($conn, $check_sql);
    oci_bind_by_name($check_stmt, ':guestID', $guestID);

    $has_membership = false;
    $current_rate = 0;

    if (oci_execute($check_stmt)) {
        $row = oci_fetch_array($check_stmt, OCI_ASSOC);
        if ($row) {
            $has_membership = true;
            $current_rate = (float) $row['DISC_RATE'];
        }
    }
    oci_free_statement($check_stmt);

    // If no membership record, we don't need to update anything unless we want to auto-create (which we probably shouldn't here, only update existing)
    if (!$has_membership) {
        return false;
    }

    // 2. Count paid bookings
    $count_sql = "SELECT COUNT(*) as count
                  FROM BOOKING b
                  JOIN BILL bl ON b.billNo = bl.billNo
                  WHERE b.guestID = :guestID
                    AND UPPER(bl.bill_status) = 'PAID'";
    $count_stmt = oci_parse($conn, $count_sql);
    oci_bind_by_name($count_stmt, ':guestID', $guestID);

    $booking_count = 0;
    if (oci_execute($count_stmt)) {
        $row = oci_fetch_array($count_stmt, OCI_ASSOC);
        $booking_count = (int) ($row['COUNT'] ?? 0);
    }
    oci_free_statement($count_stmt);

    // 3. Determine correct tier/discount
    $new_rate = 10; // Default Bronze
    if ($booking_count >= 10) {
        $new_rate = 30; // Gold
    } elseif ($booking_count >= 5) {
        $new_rate = 20; // Silver
    }

    // 4. Update if changed
    if ($current_rate != $new_rate) {
        $update_sql = "UPDATE MEMBERSHIP SET disc_rate = :rate WHERE guestID = :guestID";
        $update_stmt = oci_parse($conn, $update_sql);
        oci_bind_by_name($update_stmt, ':rate', $new_rate);
        oci_bind_by_name($update_stmt, ':guestID', $guestID);
        $result = oci_execute($update_stmt, OCI_COMMIT_ON_SUCCESS); // Commit immediately
        oci_free_statement($update_stmt);
        return $result;
    }

    return true; // No change needed
}
?>