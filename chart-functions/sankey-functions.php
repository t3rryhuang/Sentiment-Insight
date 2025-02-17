<?php
/**
 * chart-functions/sankey-functions.php
 * Provides helper functions for earliest/latest date and for building Sankey data.
 */

/**
 * getEarliestAndLatestDate
 * Returns [earliestDate, latestDate] for a given setID in MetricLogCondensed.
 *
 * @param mysqli $conn
 * @param int    $setID
 * @return array        [ $earliestDateString, $latestDateString ]
 */
function getEarliestAndLatestDate($conn, $setID) {
    $sql = "SELECT MIN(date) AS earliestDate, MAX(date) AS latestDate
            FROM MetricLogCondensed
            WHERE setID = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparing earliest/latest statement: " . $conn->error);
    }
    $stmt->bind_param("i", $setID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $earliest = $row['earliestDate'] ?? '2000-01-01';
    $latest   = $row['latestDate']   ?? date('Y-m-d');
    return [$earliest, $latest];
}

/**
 * getSankeyPlotData
 * Builds the Sankey data structure for a given setID and date range.
 *
 * @param mysqli $conn
 * @param int    $setID
 * @param string $startDate
 * @param string $endDate
 * @return array  Associative array with 'node' => [...], 'link' => [...]
 */
function getSankeyPlotData($conn, $setID, $startDate, $endDate) {

    // 1) Query the MetricLogCondensed table
    $sql = "
        SELECT MLC.severity,
               MLC.impressions,
               A.adjective,
               CT.condensedTopic
        FROM MetricLogCondensed AS MLC
        JOIN Adjective AS A ON MLC.adjectiveID = A.adjectiveID
        JOIN CondensedTopic AS CT ON MLC.condensedTopicID = CT.condensedTopicID
        WHERE MLC.setID = ?
          AND MLC.date >= ?
          AND MLC.date <= ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparing sankey statement: " . $conn->error);
    }
    $stmt->bind_param("iss", $setID, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    // Helper to classify severity
    function classifySeverity($sev) {
        if ($sev < 5) return 'Positive';
        if ($sev == 5) return 'Neutral';
        return 'Negative';
    }

    // Build data structure: sankeyData[sentiment][adjective][topic] = count
    $sankeyData = [];
    $topicTotals = [];
    while ($row = $result->fetch_assoc()) {
        $severity    = (int) $row['severity'];
        $impressions = (int) $row['impressions'];
        $adjective   = $row['adjective'] ?? 'Unknown Adjective';
        $topic       = $row['condensedTopic'] ?? 'Unknown Topic';

        $count = $impressions;
        $sentimentGroup = classifySeverity($severity);

        if (!isset($sankeyData[$sentimentGroup])) {
            $sankeyData[$sentimentGroup] = [];
        }
        if (!isset($sankeyData[$sentimentGroup][$adjective])) {
            $sankeyData[$sentimentGroup][$adjective] = [];
        }
        if (!isset($sankeyData[$sentimentGroup][$adjective][$topic])) {
            $sankeyData[$sentimentGroup][$adjective][$topic] = 0;
        }
        $sankeyData[$sentimentGroup][$adjective][$topic] += $count;

        // track topic totals
        if (!isset($topicTotals[$topic])) {
            $topicTotals[$topic] = 0;
        }
        $topicTotals[$topic] += $count;
    }
    $stmt->close();

    // 2) Keep only top 12 topics
    arsort($topicTotals);
    $topTopics = array_slice(array_keys($topicTotals), 0, 12, true);

    // Filter sankeyData to only those top topics
    $filtered = [];
    foreach ($sankeyData as $sent => $adjArray) {
        foreach ($adjArray as $adj => $topics) {
            foreach ($topics as $tp => $cnt) {
                if (in_array($tp, $topTopics)) {
                    $filtered[$sent][$adj][$tp] = $cnt;
                }
            }
        }
    }

    // 3) Build node/link arrays
    // Node ordering: [ "Reddit Posts", "Positive", "Neutral", "Negative", ...adjectives..., ...topics... ]
    $sentimentNodes = ["Positive", "Neutral", "Negative"];
    $adjectivesSet  = [];
    $topicsSet      = [];

    foreach ($filtered as $sent => $adjArray) {
        foreach ($adjArray as $adj => $topicArray) {
            $adjectivesSet[$adj] = true;
            foreach ($topicArray as $tp => $val) {
                $topicsSet[$tp] = true;
            }
        }
    }
    $adjectivesList = array_keys($adjectivesSet);
    $topicsList     = array_keys($topicsSet);

    $allNodes = array_merge(
        ["Reddit Posts"],    // index 0
        $sentimentNodes,     // 1..3
        $adjectivesList,
        $topicsList
    );

    // Build index map
    $nodeIndex = [];
    foreach ($allNodes as $i => $label) {
        $nodeIndex[$label] = $i;
    }

    // Colors
    function getSentimentColor($s) {
        switch($s) {
            case 'Positive': return '#0B6E4F';
            case 'Neutral':  return '#fa9f42';
            case 'Negative': return '#721817';
            default:         return '#B8BABD';
        }
    }
    $adjectiveColors = [
        'joy'      => '#0B6E4F',
        'neutral'  => '#fa9f42',
        'surprise' => '#fa9f42',
        'sadness'  => '#721817',
        'fear'     => '#721817',
        'disgust'  => '#721817',
        'anger'    => '#721817'
    ];

    // Helper for hex => rgba
    function hexToRgba($hex, $alpha=0.5){
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = str_repeat($hex[0],2).str_repeat($hex[1],2).str_repeat($hex[2],2);
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba($r, $g, $b, $alpha)";
    }

    // Initialize node colors
    $nodeColors = array_fill(0, count($allNodes), '#B8BABD');
    // "Reddit Posts" => index 0 => gray
    $nodeColors[0] = '#B8BABD';
    // sentiment nodes
    $nodeColors[$nodeIndex['Positive']] = getSentimentColor('Positive');
    $nodeColors[$nodeIndex['Neutral']]  = getSentimentColor('Neutral');
    $nodeColors[$nodeIndex['Negative']] = getSentimentColor('Negative');

    // adjective nodes
    foreach ($adjectivesList as $adj) {
        $adjIdx = $nodeIndex[$adj];
        $lower  = strtolower($adj);
        if (isset($adjectiveColors[$lower])) {
            $nodeColors[$adjIdx] = $adjectiveColors[$lower];
        } else {
            $nodeColors[$adjIdx] = '#B8BABD';
        }
    }

    // Build link arrays
    $source = [];
    $target = [];
    $value  = [];
    $linkColors = [];

    // Summation function
    function sumSentimentCounts($dataArr, $sentiment) {
        if (!isset($dataArr[$sentiment])) return 0;
        $sum = 0;
        foreach ($dataArr[$sentiment] as $adj => $topicArr) {
            $sum += array_sum($topicArr);
        }
        return $sum;
    }
    $totalPos = sumSentimentCounts($filtered, 'Positive');
    $totalNeu = sumSentimentCounts($filtered, 'Neutral');
    $totalNeg = sumSentimentCounts($filtered, 'Negative');

    // "Reddit Posts" -> sentiment
    if ($totalPos > 0) {
        $source[] = $nodeIndex['Reddit Posts'];
        $target[] = $nodeIndex['Positive'];
        $value[]  = $totalPos;
        $linkColors[] = hexToRgba('#0B6E4F', 0.5);
    }
    if ($totalNeu > 0) {
        $source[] = $nodeIndex['Reddit Posts'];
        $target[] = $nodeIndex['Neutral'];
        $value[]  = $totalNeu;
        $linkColors[] = hexToRgba('#fa9f42', 0.5);
    }
    if ($totalNeg > 0) {
        $source[] = $nodeIndex['Reddit Posts'];
        $target[] = $nodeIndex['Negative'];
        $value[]  = $totalNeg;
        $linkColors[] = hexToRgba('#721817', 0.5);
    }

    // sentiment -> adjective
    foreach ($filtered as $sent => $adjArray) {
        foreach ($adjArray as $adj => $topics) {
            $sumCnt = array_sum($topics);
            if ($sumCnt > 0) {
                $sIdx = $nodeIndex[$sent];
                $tIdx = $nodeIndex[$adj];
                $source[] = $sIdx;
                $target[] = $tIdx;
                $value[]  = $sumCnt;
                // link color from sentiment node color
                $linkColors[] = hexToRgba($nodeColors[$sIdx], 0.5);
            }
        }
    }

    // adjective -> topic
    $topicFlowsByAdjective = [];
    foreach ($filtered as $sent => $adjArray) {
        foreach ($adjArray as $adj => $topics) {
            $adjIdx = $nodeIndex[$adj];
            foreach ($topics as $tp => $cnt) {
                if ($cnt > 0) {
                    $topIdx = $nodeIndex[$tp];
                    $source[] = $adjIdx;
                    $target[] = $topIdx;
                    $value[]  = $cnt;
                    $linkColors[] = hexToRgba($nodeColors[$adjIdx], 0.5);

                    // track flows => color topic by dominant adjective
                    if (!isset($topicFlowsByAdjective[$tp])) {
                        $topicFlowsByAdjective[$tp] = [];
                    }
                    if (!isset($topicFlowsByAdjective[$tp][$adj])) {
                        $topicFlowsByAdjective[$tp][$adj] = 0;
                    }
                    $topicFlowsByAdjective[$tp][$adj] += $cnt;
                }
            }
        }
    }

    // color topics by largest flow
    foreach ($topicsList as $tp) {
        $tpIdx = $nodeIndex[$tp];
        if (!isset($topicFlowsByAdjective[$tp])) {
            $nodeColors[$tpIdx] = '#B8BABD';
            continue;
        }
        $maxAdj = null;
        $maxVal = 0;
        foreach ($topicFlowsByAdjective[$tp] as $adj => $c) {
            if ($c > $maxVal) {
                $maxVal = $c;
                $maxAdj = $adj;
            }
        }
        if ($maxAdj !== null) {
            $lw = strtolower($maxAdj);
            if (isset($adjectiveColors[$lw])) {
                $nodeColors[$tpIdx] = $adjectiveColors[$lw];
            } else {
                $nodeColors[$tpIdx] = '#B8BABD';
            }
        }
    }

    // Build final array
    return [
        'node' => [
            'label' => $allNodes,
            'color' => $nodeColors
        ],
        'link' => [
            'source' => $source,
            'target' => $target,
            'value'  => $value,
            'color'  => $linkColors
        ]
    ];
}
