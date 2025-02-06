<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
include 'functions.php';
$conn = connectDB();

if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    echo "<p>No search query provided.</p>";
    exit;
}

$searchQuery = trim($_GET['q']);
$searchTerm = "%" . str_replace(' ', '%', $searchQuery) . "%"; // Replace spaces with wildcards

$query = "SELECT 
            t.setID, 
            t.entityType, 
            t.name,
            (SELECT COUNT(*) FROM MetricLog m WHERE m.setID = t.setID) AS dataPoints,
            (SELECT MAX(date) FROM MetricLog m WHERE m.setID = t.setID) AS lastUpdated,
            (SELECT AVG(severity) FROM MetricLog m 
              WHERE m.setID = t.setID AND date = (SELECT MAX(date) FROM MetricLog WHERE setID = t.setID)
            ) AS avgSeverity
          FROM TrackedEntity t
          WHERE REPLACE(t.name, ' ', '') LIKE ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "Error preparing statement: " . htmlspecialchars($conn->error);
    exit;
}
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Search Results - Sentiment Insight</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Adjustments for left alignment within the main content area */
    .results-container {
      padding: 20px;
      padding-left: 0px;
      color: #ccc;
    }

    /* Style each result as a card with a dedicated icon area */
    .result-card {
      background: #1C1F2D;
      border: 1px solid #333;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center; /* Vertically centers children */
      text-decoration: none;
      color: #fff;
      padding: 15px; /* Consistent padding all around */
      position: relative; /* Needed for positioning the chevron icon */
      transition: background 0.3s, box-shadow 0.3s;
    }

    .result-card:hover {
      background: #272a38;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    }

    .icon-container {
      width: 50px;  /* Fixed width */
      height: 50px; /* Fixed height to form a square */
      flex-shrink: 0; /* Prevents the icon from shrinking */
      background: center / cover no-repeat;
      margin-right: 15px; /* Space between icon and text content */
    }

    .content-container {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .entity-title {
      font-size: 1.5em;
      margin: 0 0 5px 0; /* Bottom margin for spacing */
    }

    .meta-data {
      font-size: 0.9em;
      color: #aaa;
      margin: 0;
    }

    .chevron-icon {
      position: absolute;
      right: 15px; /* Spacing from the right edge */
      background: url('images/icons/chevron-right-grey.svg') center/contain no-repeat;
      width: 24px; /* Width of the chevron icon */
      height: 24px; /* Height of the chevron icon */
    }

    .result-card:hover .chevron-icon {
      background-image: url('images/icons/chevron-right.svg');
    }
  </style>
</head>
<body>
<?php
displayHead("Search Results");
displaySidebar();
displayTopNavWithSearch();
?>
<div class="content">
<div class="results-container">
  <h2>Search Results for: <?php echo htmlspecialchars($searchQuery); ?></h2>
  
  <?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): 
            $lastUpdated = $row['lastUpdated'] ? date("F j, Y", strtotime($row['lastUpdated'])) : "N/A";
            $avgSeverity = $row['avgSeverity'] ? round($row['avgSeverity'], 2) : "N/A";
            $iconPath = "images/icons/" . strtolower($row['entityType']) . ".svg"; // Assumes icons are named by entity type.
    ?>
        <a href="metric-set.php?setID=<?php echo urlencode($row['setID']); ?>" class="result-card">
          <div class="icon-container" style="background-image: url('<?php echo $iconPath; ?>');"></div>
          <div class="content-container">
            <div class="entity-title">
              <?php echo htmlspecialchars($row['entityType']) . ": " . htmlspecialchars($row['name']); ?>
            </div>
            <div class="meta-data">
              Data Points: <?php echo htmlspecialchars($row['dataPoints']); ?> &nbsp;&nbsp;|&nbsp;&nbsp; 
              Last Updated: <?php echo htmlspecialchars($lastUpdated); ?> &nbsp;&nbsp;|&nbsp;&nbsp; 
              Average Severity: <?php echo htmlspecialchars($avgSeverity); ?>
            </div>
          </div>
          <div class="chevron-icon"></div>
        </a>
    <?php endwhile; ?>
  <?php else: ?>
      <p>No results found.</p>
  <?php endif; ?>
</div>
</div>

<?php
$stmt->close();
$conn->close();
?>
</body>
</html>
