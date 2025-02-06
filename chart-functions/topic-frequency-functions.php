<?php
/**
 * chart-functions/topic-frequency-functions.php
 *
 * Returns an array of rows: each row = [
 *   'topic'          => string,
 *   'avgSeverity'    => float,
 *   'totalImpressions' => int
 * ],
 * ordered by totalImpressions DESC.
 */

 function getTopicFrequencies(mysqli $conn, int $setID, string $startDate, string $endDate): array {
    // SQL to fetch total impressions for all topics
    $totalSql = "
        SELECT SUM(impressions) AS totalImpressions
        FROM MetricLogCondensed
        WHERE setID = ?
          AND date >= ?
          AND date <= ?
    ";

    // Prepare and execute total impressions query
    $totalStmt = $conn->prepare($totalSql);
    if (!$totalStmt) {
        throw new Exception("Error preparing total impressions query: " . $conn->error);
    }
    $totalStmt->bind_param("iss", $setID, $startDate, $endDate);
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    $totalData = $totalResult->fetch_assoc();
    $totalImpressions = (int)$totalData['totalImpressions'];
    $totalStmt->close();

    // SQL to fetch data for topics
    $sql = "
        SELECT 
            CT.condensedTopic AS topic,
            AVG(MLC.severity) AS avgSeverity,
            SUM(MLC.impressions) AS totalImpressions
        FROM MetricLogCondensed MLC
        JOIN CondensedTopic CT ON MLC.condensedTopicID = CT.condensedTopicID
        WHERE MLC.setID = ?
          AND MLC.date >= ?
          AND MLC.date <= ?
        GROUP BY CT.condensedTopic
        ORDER BY totalImpressions DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparing topic frequency query: " . $conn->error);
    }
    $stmt->bind_param("iss", $setID, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $frequencies = [];
    while ($row = $result->fetch_assoc()) {
        $frequencies[] = [
            'topic'           => $row['topic'],
            'avgSeverity'     => (float)$row['avgSeverity'],
            'totalImpressions'=> (int)$row['totalImpressions'],
            'percentageOfTotal' => ($row['totalImpressions'] / $totalImpressions) * 100
        ];
    }
    $stmt->close();

    return $frequencies;
}
?>
