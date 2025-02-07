<?php
// Enable error reporting for debugging.
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

// Redirect to sign-in-page.php if the user is not logged in.
if (!isset($_SESSION['username'])) {
    header("Location: sign-in-page.php");
    exit();
}

include 'functions.php';  // Contains connectDB() and display* functions.
$conn = connectDB();

$username = $_SESSION['username'];

// Step 1. Retrieve the accountID for the logged-in user from the Account table.
$accountQuery = "SELECT accountID FROM Account WHERE username = ?";
if ($stmt = $conn->prepare($accountQuery)) {
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($accountID);
        $stmt->fetch();
    } else {
        // If the account is not found, redirect to sign in.
        header("Location: sign-in-page.php");
        exit();
    }
    $stmt->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

// Step 2. Retrieve saved sets for this account along with metrics information.
// The query joins the SavedSet table with the TrackedEntity table and then uses subqueries 
// (as in search-results.php) to compute dataPoints, lastUpdated, and avgSeverity from the MetricLog table.
$savedSetsQuery = "
    SELECT 
        T.setID, 
        T.entityType, 
        T.name,
        (SELECT COUNT(*) FROM MetricLog m WHERE m.setID = T.setID) AS dataPoints,
        (SELECT MAX(date) FROM MetricLog m WHERE m.setID = T.setID) AS lastUpdated,
        (SELECT AVG(severity) FROM MetricLog m 
          WHERE m.setID = T.setID 
            AND date = (SELECT MAX(date) FROM MetricLog WHERE setID = T.setID)
        ) AS avgSeverity
    FROM SavedSet AS S
    JOIN TrackedEntity AS T ON S.setID = T.setID
    WHERE S.accountID = ?
";

if ($stmt = $conn->prepare($savedSetsQuery)) {
    $stmt->bind_param("i", $accountID);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Error preparing statement: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Saved Metric Sets - Sentiment Insight</title>
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
// Display the common header, sidebar, and top navigation (assuming these functions exist).
displayHead("Saved Metric Sets - Sentiment Insight");
displaySidebar();
displayTopNavWithSearch();
?>
<div class="content">
  <div class="results-container">
    <h2>Saved Sets for: <?php echo htmlspecialchars($username); ?></h2>
    
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()):
              // Format the last updated date if available.
              $lastUpdated = $row['lastUpdated'] ? date("F j, Y", strtotime($row['lastUpdated'])) : "N/A";
              // Round the average severity if available.
              $avgSeverity = $row['avgSeverity'] ? round($row['avgSeverity'], 2) : "N/A";
              // Build the icon path based on the entity type (assumes icons are named in lowercase).
              $iconPath = "images/icons/" . strtolower($row['entityType']) . ".svg";
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
      <p>No saved sets found.</p>
    <?php endif; ?>
  </div>
</div>

<?php
$stmt->close();
$conn->close();
?>
</body>
</html>
