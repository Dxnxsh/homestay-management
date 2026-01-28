<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';

// Only managers can perform deletions
if (!isManager()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    $staffId = $data['staffId'] ?? '';

    if (empty($staffId)) {
        echo json_encode(['success' => false, 'message' => 'Missing Staff ID']);
        exit();
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    // Check if staff manages others (Recursion Check)
    $checkSql = "SELECT COUNT(*) as count FROM STAFF WHERE MANAGERID = :staffId";
    $checkStmt = oci_parse($conn, $checkSql);
    oci_bind_by_name($checkStmt, ':staffId', $staffId);
    oci_execute($checkStmt);
    $row = oci_fetch_array($checkStmt, OCI_ASSOC);

    if ($row['COUNT'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete staff: This staff member is a manager for other staff. Reassign them first.']);
        closeDBConnection($conn);
        exit();
    }

    // Delete from Child Tables first (Inheritance)
    $delFullSql = "DELETE FROM FULL_TIME WHERE STAFFID = :staffId";
    $delFullStmt = oci_parse($conn, $delFullSql);
    oci_bind_by_name($delFullStmt, ':staffId', $staffId);
    oci_execute($delFullStmt, OCI_NO_AUTO_COMMIT);

    $delPartSql = "DELETE FROM PART_TIME WHERE STAFFID = :staffId";
    $delPartStmt = oci_parse($conn, $delPartSql);
    oci_bind_by_name($delPartStmt, ':staffId', $staffId);
    oci_execute($delPartStmt, OCI_NO_AUTO_COMMIT);

    // Delete from Main Table
    $delSql = "DELETE FROM STAFF WHERE STAFFID = :staffId";
    $delStmt = oci_parse($conn, $delSql);
    oci_bind_by_name($delStmt, ':staffId', $staffId);

    if (oci_execute($delStmt, OCI_NO_AUTO_COMMIT)) {
        oci_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Staff deleted successfully']);
    } else {
        $e = oci_error($delStmt);
        oci_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e['message']]);
    }

    closeDBConnection($conn);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>