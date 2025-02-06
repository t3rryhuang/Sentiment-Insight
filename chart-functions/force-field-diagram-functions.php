<?php
/**
 * chart-functions/force-field-diagram-functions.php
 *
 * Provides a function to get the data for the force field diagram.
 */

/**
 * getForceFieldData
 * Fetches average severity for each topic in the given set & date range,
 * ignoring severity == 5 (which is neutral).
 * Groups them into 'positive' (avgSeverity < 5) and 'negative' (avgSeverity > 5).
 */
function getForceFieldData(mysqli $conn, int $setID, string $startDate, string $endDate): array {
    $sql = "
        SELECT CT.condensedTopic AS topic, 
               AVG(MLC.severity) AS avgSeverity
        FROM MetricLogCondensed MLC
        JOIN CondensedTopic CT ON MLC.condensedTopicID = CT.condensedTopicID
        WHERE MLC.setID = ? 
          AND MLC.date >= ? 
          AND MLC.date <= ?
        GROUP BY CT.condensedTopic
        HAVING AVG(MLC.severity) != 5
        ORDER BY AVG(MLC.severity) DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparing Force Field data query: " . $conn->error);
    }
    $stmt->bind_param("iss", $setID, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $forceFieldData = ['positive' => [], 'negative' => []];
    while ($row = $result->fetch_assoc()) {
        $avgSev = (float) $row['avgSeverity'];
        if ($avgSev < 5) {
            $forceFieldData['positive'][] = [
                'topic'       => $row['topic'],
                'avgSeverity' => $avgSev
            ];
        } elseif ($avgSev > 5) {
            $forceFieldData['negative'][] = [
                'topic'       => $row['topic'],
                'avgSeverity' => $avgSev
            ];
        }
    }
    $stmt->close();

    return $forceFieldData;
}
