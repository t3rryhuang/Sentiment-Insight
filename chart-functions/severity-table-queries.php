<?php
/**
 * Fetch the top 10 topics (grouped by topic) for a given setID & date range, 
 * ordered by max severity descending.
 *
 * @param mysqli $conn
 * @param int    $setID
 * @param string $startDate
 * @param string $endDate
 * @return array           Array of rows, each row has ['topic', 'maxSeverity']
 */
function getTopTenTopics(mysqli $conn, int $setID, string $startDate, string $endDate): array {
    $sql = "
        SELECT CT.condensedTopic AS topic,
               MAX(MLC.severity) AS maxSeverity
        FROM MetricLogCondensed MLC
        JOIN CondensedTopic CT ON MLC.condensedTopicID = CT.condensedTopicID
        WHERE MLC.setID = ?
          AND MLC.date >= ?
          AND MLC.date <= ?
        GROUP BY CT.condensedTopic
        ORDER BY maxSeverity DESC
        LIMIT 10
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error preparing top-10 query: '.$conn->error);
    }
    $stmt->bind_param("iss", $setID, $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $topics = [];
    while ($row = $res->fetch_assoc()) {
        $topics[] = $row;
    }
    $stmt->close();
    return $topics;
}

/**
 * Fetch all grouped topics for a given setID & date range, 
 * with max severity, ordered by severity descending.
 *
 * @param mysqli $conn
 * @param int    $setID
 * @param string $startDate
 * @param string $endDate
 * @return array           Array of rows, each row has ['topic', 'maxSeverity']
 */
function getAllGroupedTopics(mysqli $conn, int $setID, string $startDate, string $endDate): array {
    $sql = "
        SELECT CT.condensedTopic AS topic,
               MAX(MLC.severity) AS maxSeverity
        FROM MetricLogCondensed MLC
        JOIN CondensedTopic CT ON MLC.condensedTopicID = CT.condensedTopicID
        WHERE MLC.setID = ?
          AND MLC.date >= ?
          AND MLC.date <= ?
        GROUP BY CT.condensedTopic
        ORDER BY maxSeverity DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparing full table query: ".$conn->error);
    }
    $stmt->bind_param("iss", $setID, $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $allTopics = [];
    while ($row = $res->fetch_assoc()) {
        $allTopics[] = $row;
    }
    $stmt->close();
    return $allTopics;
}
?>