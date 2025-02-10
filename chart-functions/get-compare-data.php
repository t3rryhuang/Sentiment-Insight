<?php
// chart-functions/get-compare-data.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['username'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require '../functions.php'; // Adjust path if needed
$conn = connectDB();

$set1      = $_GET['set1'] ?? null;
$set2      = $_GET['set2'] ?? null;
$startDate = $_GET['startDate'] ?? null;
$endDate   = $_GET['endDate'] ?? null;

// 1) Net severity (weighted average)
function getNetSeverity($conn, $setID, $start, $end) {
    if (empty($setID) || empty($start) || empty($end)) {
        return null;
    }

    $sql = "
        SELECT severity, impressions
          FROM MetricLogCondensed
         WHERE setID = ?
           AND `date` >= ?
           AND `date` <= ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["error" => $conn->error]);
        exit;
    }
    $stmt->bind_param("iss", $setID, $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();

    $sumSeverity = 0;
    $sumCount    = 0;
    while ($row = $result->fetch_assoc()) {
        $sumSeverity += $row['severity'] * $row['impressions'];
        $sumCount    += $row['impressions'];
    }
    $stmt->close();

    return ($sumCount > 0) ? ($sumSeverity / $sumCount) : null;
}

// 2) Get 3 lowest severity topics (Most Positive)
function getMostPositive($conn, $setID, $start, $end, $limit = 3) {
    if (empty($setID) || empty($start) || empty($end)) {
        return [];
    }

    $sql = "
        SELECT c.condensedTopic AS topic,
               SUM(m.severity * m.impressions) / SUM(m.impressions) AS avgSeverity
          FROM MetricLogCondensed m
          JOIN CondensedTopic c ON m.condensedTopicID = c.condensedTopicID
         WHERE m.setID = ?
           AND m.`date` >= ?
           AND m.`date` <= ?
         GROUP BY c.condensedTopicID
         ORDER BY avgSeverity ASC
         LIMIT ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["error" => $conn->error]);
        exit;
    }
    $stmt->bind_param("issi", $setID, $start, $end, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'topic' => $row['topic'],
            'avgSeverity' => (float) $row['avgSeverity']
        ];
    }
    $stmt->close();
    return $data;
}

// 3) Get 3 highest severity topics (Most Negative)
function getMostNegative($conn, $setID, $start, $end, $limit = 3) {
    if (empty($setID) || empty($start) || empty($end)) {
        return [];
    }

    $sql = "
        SELECT c.condensedTopic AS topic,
               SUM(m.severity * m.impressions) / SUM(m.impressions) AS avgSeverity
          FROM MetricLogCondensed m
          JOIN CondensedTopic c ON m.condensedTopicID = c.condensedTopicID
         WHERE m.setID = ?
           AND m.`date` >= ?
           AND m.`date` <= ?
         GROUP BY c.condensedTopicID
         ORDER BY avgSeverity DESC
         LIMIT ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["error" => $conn->error]);
        exit;
    }
    $stmt->bind_param("issi", $setID, $start, $end, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'topic' => $row['topic'],
            'avgSeverity' => (float) $row['avgSeverity']
        ];
    }
    $stmt->close();
    return $data;
}

/**
 * 4) Get daily average severity (time series)
 *    Returns array of [ { "date": "YYYY-MM-DD", "avgSeverity": float }, ... ]
 *    ordered by date ascending
 */
function getTimeSeriesAverages($conn, $setID, $start, $end) {
    if (empty($setID) || empty($start) || empty($end)) {
        return [];
    }

    $sql = "
        SELECT `date`,
               SUM(severity * impressions) / SUM(impressions) AS avgSeverity
          FROM MetricLogCondensed
         WHERE setID = ?
           AND `date` >= ?
           AND `date` <= ?
         GROUP BY `date`
         ORDER BY `date` ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["error" => $conn->error]);
        exit;
    }
    $stmt->bind_param("iss", $setID, $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'date' => $row['date'],
            'avgSeverity' => $row['avgSeverity'] !== null ? (float)$row['avgSeverity'] : null,
        ];
    }
    $stmt->close();
    return $data;
}

// Build final data
$response = [
    'netSeverity1'   => getNetSeverity($conn, $set1, $startDate, $endDate),
    'netSeverity2'   => getNetSeverity($conn, $set2, $startDate, $endDate),
    'mostPositive1'  => getMostPositive($conn, $set1, $startDate, $endDate),
    'mostPositive2'  => getMostPositive($conn, $set2, $startDate, $endDate),
    'mostNegative1'  => getMostNegative($conn, $set1, $startDate, $endDate),
    'mostNegative2'  => getMostNegative($conn, $set2, $startDate, $endDate),
    // Time series data
    'timeSeries1'    => getTimeSeriesAverages($conn, $set1, $startDate, $endDate),
    'timeSeries2'    => getTimeSeriesAverages($conn, $set2, $startDate, $endDate),
];

$conn->close();

// Return JSON
echo json_encode($response);
