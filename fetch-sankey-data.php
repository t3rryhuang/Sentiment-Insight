<?php
// fetch-sankey-data.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'functions.php'; // connectDB()

$conn = connectDB();

// Read GET parameters
$setID = isset($_GET['setID']) ? intval($_GET['setID']) : 0;
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); // fallback to today if not specified

// For example, read from MetricLogCondensed, Adjective, Topic
// We want to build flows like: "Reddit Posts" -> [Positive, Neutral, Negative] -> [Category] -> [Adjective] -> [Topic]
//
// 1) Filter by that date & setID
// 2) Summarize impressions by severity, category, adjective, topic
// 3) Build nodes & links
//
// We'll do a simplistic approach here:

// Node index reference map
//  0 => "Reddit Posts"
//  1 => "Positive"
//  2 => "Neutral"
//  3 => "Negative"
// Then we'll dynamically add categories, adjectives, topics

$nodes = ["Reddit Posts", "Positive", "Neutral", "Negative"];
// We'll store an index for each category, adjective, topic
$categoryMap = [];   // categoryName => nodeIndex
$adjectiveMap = [];  // adjective => nodeIndex
$topicMap = [];      // topic => nodeIndex

$sources = [];
$targets = [];
$values = [];

// We do an SQL join to get category, adjective, topic info
// E.g. "SELECT mlc.*, t.topic, t.category, a.adjective
//       FROM MetricLogCondensed mlc
//       JOIN Topic t ON t.topicID = mlc.condensedTopicID
//       JOIN Adjective a ON a.adjectiveID = mlc.adjectiveID
//       WHERE mlc.setID=? AND mlc.date=?"

$sql = "SELECT mlc.impressions, mlc.severity,
               t.category, t.topic,
               a.adjective
        FROM MetricLogCondensed mlc
        JOIN Topic t ON t.topicID = mlc.condensedTopicID
        JOIN Adjective a ON a.adjectiveID = mlc.adjectiveID
        WHERE mlc.setID=? AND mlc.date=?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => $conn->error]);
    exit;
}
$stmt->bind_param("is", $setID, $selectedDate);
$stmt->execute();
$res = $stmt->get_result();

// Accumulate data in a structure: [sentiment => category => adjective => topic => sumImpressions]
$dataTree = [];

while ($row = $res->fetch_assoc()) {
    $severity = (int)$row['severity'];
    $impressions = (int)$row['impressions'];
    $category = $row['category'];
    $topic = $row['topic'];
    $adjective = $row['adjective'];

    // Determine sentiment label
    // severity < 5 => Positive, =5 => Neutral, >5 => Negative
    if ($severity < 5) {
        $sentiment = "Positive";
    } elseif ($severity == 5) {
        $sentiment = "Neutral";
    } else {
        $sentiment = "Negative";
    }

    // Build nested structure
    if (!isset($dataTree[$sentiment])) {
        $dataTree[$sentiment] = [];
    }
    if (!isset($dataTree[$sentiment][$category])) {
        $dataTree[$sentiment][$category] = [];
    }
    if (!isset($dataTree[$sentiment][$category][$adjective])) {
        $dataTree[$sentiment][$category][$adjective] = [];
    }
    if (!isset($dataTree[$sentiment][$category][$adjective][$topic])) {
        $dataTree[$sentiment][$category][$adjective][$topic] = 0;
    }

    $dataTree[$sentiment][$category][$adjective][$topic] += $impressions;
}
$stmt->close();
$conn->close();

// Now we build the Sankey flows in the order:
// "Reddit Posts"(0) -> sentiment(1-3) -> category(...) -> adjective(...) -> topic(...)

// Step 1: Connect "Reddit Posts"(0) to each sentiment
//   nodeIndex for "Positive" => 1
//   nodeIndex for "Neutral" => 2
//   nodeIndex for "Negative" => 3

// We'll store total impressions for each sentiment so we can create a link from node 0 => that node
$sentimentTotals = ['Positive' => 0, 'Neutral' => 0, 'Negative' => 0];
foreach ($dataTree as $s => $cats) {
    foreach ($cats as $cat => $adjs) {
        foreach ($adjs as $adj => $tops) {
            foreach ($tops as $tp => $imp) {
                $sentimentTotals[$s] += $imp;
            }
        }
    }
}

// For categories, adjectives, and topics, we create new node indices
function getNodeIndex(&$map, &$nodes, $label) {
    if (!isset($map[$label])) {
        $map[$label] = count($nodes);
        $nodes[] = $label; 
    }
    return $map[$label];
}

// 0 => "Reddit Posts", 1 => "Positive", 2 => "Neutral", 3 => "Negative"
$sentIndex = ['Positive' => 1, 'Neutral' => 2, 'Negative' => 3];

// 1) From 0 to sentiment
foreach ($sentimentTotals as $s => $val) {
    // source=0, target=sentIndex[$s], value=$val
    $sources[] = 0; 
    $targets[] = $sentIndex[$s];
    $values[]  = $val;
}

// 2) From sentiment => category => adjective => topic
//    We'll create flows from sentiment => category, then category => adjective, then adjective => topic
foreach ($dataTree as $s => $cats) {
    $sIndex = $sentIndex[$s];
    foreach ($cats as $cat => $adjs) {
        $catIndex = getNodeIndex($categoryMap, $nodes, $cat);
        // sum of impressions for cat
        $catSum = 0;
        foreach ($adjs as $adj => $tops) {
            foreach ($tops as $tp => $imp) {
                $catSum += $imp;
            }
        }
        // link sentiment => category
        $sources[] = $sIndex;
        $targets[] = $catIndex;
        $values[] = $catSum;

        // Now category => adjective
        foreach ($adjs as $adj => $tops) {
            $adjIndex = getNodeIndex($adjectiveMap, $nodes, $adj);
            $adjSum = 0;
            foreach ($tops as $tp => $imp) {
                $adjSum += $imp;
            }
            $sources[] = $catIndex;
            $targets[] = $adjIndex;
            $values[] = $adjSum;

            // adjective => topic
            foreach ($tops as $tp => $imp) {
                $tpIndex = getNodeIndex($topicMap, $nodes, $tp);
                $sources[] = $adjIndex;
                $targets[] = $tpIndex;
                $values[] = $imp;
            }
        }
    }
}

// Build colors
// Node colors: index 0 => #B8BABD, index 1 => #0B6E4F, index 2 => #fa9f42, index 3 => #721817
// Then random or static for categories, adjectives, topics. Simplify to a set of repeated or random colors.

$nodeColors = [
    "#B8BABD", "#0B6E4F", "#fa9f42", "#721817" // the first 4
];
// For all newly created nodes, you can push some color logic, e.g. repeated or random
for ($i = 4; $i < count($nodes); $i++) {
    // example: just pick a grayish color for categories, or random pastel
    $nodeColors[] = "#555555";
}

// Link colors can be partially transparent version of the target node color
$linkColors = [];
for ($i = 0; $i < count($sources); $i++) {
    $targetIdx = $targets[$i];
    // Let’s do RGBA from nodeColors of the target
    $hex = $nodeColors[$targetIdx];
    // Convert #RRGGBB to RGBA(., ., ., 0.6)
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    $linkColors[] = "rgba($r,$g,$b,0.6)";
}

echo json_encode([
    "labels" => $nodes,
    "sources" => $sources,
    "targets" => $targets,
    "values"  => $values,
    "nodeColors" => $nodeColors,
    "linkColors" => $linkColors
]);
