<?php
// getSuggestions.php
header('Content-Type: application/json');
session_start();
include 'functions.php';

if (!isset($_GET['q'])) {
    echo json_encode([]);
    exit;
}

$q = $_GET['q'];
$conn = connectDB();

$query = "SELECT setID, entityType, name FROM TrackedEntity WHERE name LIKE ? LIMIT 10";
$stmt = $conn->prepare($query);
$searchTerm = '%' . $q . '%';
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while ($row = $result->fetch_assoc()) {
    $suggestions[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($suggestions);
?>
