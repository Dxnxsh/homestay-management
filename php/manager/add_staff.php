<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';

// Only managers can add staff
if (!isManager()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Auto-generate ID or let trigger handle it? 
    // Trigger TRG_STAFF_ID handles it if passed NULL.
    // But we might need the ID to insert into child tables. 
    // We should use RETURNING INTO.

    $name = $data['name'] ?? '';
    $phone = $data['phone'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? 'pass123'; // Default password if not provided
    $type = $data['type'] ?? 'Full Time';
    $managerId = $data['managerId'] ?? null;

    if (empty($managerId) || $managerId === '-') {
        $managerId = null;
    }

    if (empty($name) || empty($phone) || empty($email) || empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    // Insert into STAFF and get generated ID
    $sql = "INSERT INTO STAFF (STAFF_NAME, STAFF_PHONENO, STAFF_EMAIL, STAFF_PASSWORD, STAFF_TYPE, MANAGERID) 
            VALUES (:name, :phone, :email, :password, :type, :managerId) 
            RETURNING STAFFID INTO :staffId";

    $stmt = oci_parse($conn, $sql);

    // Bind variables
    oci_bind_by_name($stmt, ':name', $name);
    oci_bind_by_name($stmt, ':phone', $phone);
    oci_bind_by_name($stmt, ':email', $email);
    oci_bind_by_name($stmt, ':password', $password);
    oci_bind_by_name($stmt, ':type', $type);
    oci_bind_by_name($stmt, ':managerId', $managerId);

    // Bind output variable for ID
    $newStaffId = '';
    oci_bind_by_name($stmt, ':staffId', $newStaffId, 10);

    if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
        $e = oci_error($stmt);
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $e['message']]);
        oci_free_statement($stmt);
        closeDBConnection($conn);
        exit();
    }

    // Insert into CHILD tables based on type
    if ($type === 'Full-time') {
        $insSql = "INSERT INTO FULL_TIME (STAFFID, FULL_TIME_SALARY, VACATION_DAYS, BONUS) VALUES (:staffId, 2500, 14, 0)";
        $insStmt = oci_parse($conn, $insSql);
        oci_bind_by_name($insStmt, ':staffId', $newStaffId);
        if (!oci_execute($insStmt, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($insStmt);
            oci_rollback($conn);
            echo json_encode(['success' => false, 'message' => 'Child Insert failed: ' . $e['message']]);
            exit();
        }
        oci_free_statement($insStmt);
    } else if ($type === 'Part-time') {
        $insSql = "INSERT INTO PART_TIME (STAFFID, HOURLY_RATE, SHIFT_TIME) VALUES (:staffId, 10, 'Morning')";
        $insStmt = oci_parse($conn, $insSql);
        oci_bind_by_name($insStmt, ':staffId', $newStaffId);
        if (!oci_execute($insStmt, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($insStmt);
            oci_rollback($conn);
            echo json_encode(['success' => false, 'message' => 'Child Insert failed: ' . $e['message']]);
            exit();
        }
        oci_free_statement($insStmt);
    }

    oci_commit($conn);
    oci_free_statement($stmt);
    closeDBConnection($conn);

    echo json_encode(['success' => true, 'message' => 'Staff added successfully', 'staffId' => $newStaffId]);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>