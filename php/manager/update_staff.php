<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';

// Only managers can perform updates
if (!isManager()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $data = json_decode(file_get_contents('php://input'), true);

    $staffId = $data['staffId'] ?? '';
    $name = $data['name'] ?? '';
    $phone = $data['phone'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $type = $data['type'] ?? '';
    $managerId = $data['managerId'] ?? null;

    // Convert empty managerId to null
    if (empty($managerId) || $managerId === '-') {
        $managerId = null;
    }

    if (empty($staffId) || empty($name) || empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    // update STAFF table
    $sql = "UPDATE STAFF SET 
            STAFF_NAME = :name, 
            STAFF_PHONENO = :phone, 
            STAFF_EMAIL = :email, 
            STAFF_PASSWORD = :password,
            STAFF_TYPE = :type,
            MANAGERID = :managerId
            WHERE STAFFID = :staffId";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':name', $name);
    oci_bind_by_name($stmt, ':phone', $phone);
    oci_bind_by_name($stmt, ':email', $email);
    oci_bind_by_name($stmt, ':password', $password);
    oci_bind_by_name($stmt, ':type', $type);
    oci_bind_by_name($stmt, ':managerId', $managerId);
    oci_bind_by_name($stmt, ':staffId', $staffId);

    if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
        $e = oci_error($stmt);
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e['message']]);
        oci_rollback($conn);
        closeDBConnection($conn);
        exit();
    }

    // Handle Type Inheritance
    // We need to check if we need to insert/delete from subtype tables
    // For simplicity in this demo, we will try to MERGE or INSERT/DELETE based on type

    if ($type === 'Full-time') {
        // Remove from PART_TIME if exists
        $delSql = "DELETE FROM PART_TIME WHERE STAFFID = :staffId";
        $delStmt = oci_parse($conn, $delSql);
        oci_bind_by_name($delStmt, ':staffId', $staffId);
        oci_execute($delStmt, OCI_NO_AUTO_COMMIT);

        // Check if exists in FULL_TIME
        $checkSql = "SELECT STAFFID FROM FULL_TIME WHERE STAFFID = :staffId";
        $checkStmt = oci_parse($conn, $checkSql);
        oci_bind_by_name($checkStmt, ':staffId', $staffId);
        oci_execute($checkStmt);

        if (!oci_fetch_array($checkStmt, OCI_ASSOC)) {
            // Insert default values if not exists
            $insSql = "INSERT INTO FULL_TIME (STAFFID, FULL_TIME_SALARY, VACATION_DAYS, BONUS) VALUES (:staffId, 3000, 14, 0)";
            $insStmt = oci_parse($conn, $insSql);
            oci_bind_by_name($insStmt, ':staffId', $staffId);
            oci_execute($insStmt, OCI_NO_AUTO_COMMIT);
        }
    } else if ($type === 'Part-time') {
        // Remove from FULL_TIME if exists
        $delSql = "DELETE FROM FULL_TIME WHERE STAFFID = :staffId";
        $delStmt = oci_parse($conn, $delSql);
        oci_bind_by_name($delStmt, ':staffId', $staffId);
        oci_execute($delStmt, OCI_NO_AUTO_COMMIT);

        // Check if exists in PART_TIME
        $checkSql = "SELECT STAFFID FROM PART_TIME WHERE STAFFID = :staffId";
        $checkStmt = oci_parse($conn, $checkSql);
        oci_bind_by_name($checkStmt, ':staffId', $staffId);
        oci_execute($checkStmt);

        if (!oci_fetch_array($checkStmt, OCI_ASSOC)) {
            // Insert default values if not exists
            $insSql = "INSERT INTO PART_TIME (STAFFID, HOURLY_RATE, SHIFT_TIME) VALUES (:staffId, 10, 'Morning')";
            $insStmt = oci_parse($conn, $insSql);
            oci_bind_by_name($insStmt, ':staffId', $staffId);
            oci_execute($insStmt, OCI_NO_AUTO_COMMIT);
        }
    }

    oci_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Staff updated successfully']);

    closeDBConnection($conn);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>