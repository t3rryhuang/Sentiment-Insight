<?php
/**
 * chart-functions/linechart-functions.php
 * Provides a helper function to fetch the line chart data.
 */

/**
 * getLineChartData
 * Fetches the date + Positive/Neutral/Negative counts for a given setID.
 *
 * @param mysqli $conn    Active DB connection
 * @param int    $setID
 * @return array          An array of associative rows:
 *                        [ ['date'=> 'YYYY-MM-DD', 'Positive'=> x, 'Neutral'=> y, 'Negative'=> z ], ... ]
 */
function getLineChartData($conn, $setID) {
    $sql = "SELECT date,
                   COALESCE(SUM(CASE WHEN severity < 5 THEN impressions END), 0) AS Positive,
                   COALESCE(SUM(CASE WHEN severity = 5 THEN impressions END), 0) AS Neutral,
                   COALESCE(SUM(CASE WHEN severity > 5 THEN impressions END), 0) AS Negative
            FROM MetricLogCondensed
            WHERE setID = ?
            GROUP BY date
            ORDER BY date ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param("i", $setID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();

    return $data;
}
