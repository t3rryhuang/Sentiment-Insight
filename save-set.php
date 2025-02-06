<?php
// save-set.php
session_start();
header('Content-Type: application/json');
include 'functions.php'; // Make sure this file contains connectDB()

// Check if user is logged in; if not, return an error response.
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

// Check that setID is provided via POST
if (!isset($_POST['setID'])) {
    echo json_encode(['success' => false, 'message' => 'Missing setID.']);
    exit;
}

$setID = intval($_POST['setID']);

$conn = connectDB();

// Retrieve the user's account_id using the username stored in session
$username = $_SESSION['username'];
$query = "SELECT accountID FROM Account WHERE username = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $account_id = $row['accountID'];
} else {
    echo json_encode(['success' => false, 'message' => 'User account not found.']);
    exit;
}
$stmt->close();

// Toggle save state: check if a record already exists
$checkQuery = "SELECT 1 FROM SavedSet WHERE accountID = ? AND setID = ?";
$checkStmt = $conn->prepare($checkQuery);
if (!$checkStmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$checkStmt->bind_param("ii", $account_id, $setID);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    // Record exists: delete it (unsave)
    $deleteQuery = "DELETE FROM SavedSet WHERE accountID = ? AND setID = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    if (!$deleteStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $deleteStmt->bind_param("ii", $account_id, $setID);
    if ($deleteStmt->execute()) {
        echo json_encode(['success' => true, 'saved' => false]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to unsave set.']);
    }
    $deleteStmt->close();
} else {
    // Record does not exist: insert it (save)
    $insertQuery = "INSERT INTO SavedSet (accountID, setID) VALUES (?, ?)";
    $insertStmt = $conn->prepare($insertQuery);
    if (!$insertStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $insertStmt->bind_param("ii", $account_id, $setID);
    if ($insertStmt->execute()) {
        echo json_encode(['success' => true, 'saved' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save set.']);
    }
    $insertStmt->close();
}
$conn->close();
?>
