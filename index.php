<?php 
session_start();
include 'functions.php';
$conn = connectDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sentiment Insight - Search</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Container for the Recently Updated section */
    #recommended-home {
      display: flex;
      flex-direction: column;
      align-items: flex-start; /* Aligns h2 to the left */
      max-width: 900px; /* Centers the container */
      margin-left: auto;
      margin-right: auto;
      padding: 20px;
    }

    /* Heading styling */
    #recommended-home h2 {
      margin-bottom: 10px; /* Adds spacing between the heading and the cards */
      font-size: 1.5em;
      font-weight: bold;
      color: #fff;
    }

    /* Container for metric cards */
    .recent-metrics-container {
      display: flex;
      justify-content: flex-start; /* Aligns cards to the left */
      gap: 20px; /* Space between each card */
      flex-wrap: wrap; /* Allows wrapping on smaller screens */
      width: 100%;
    }

    /* Individual metric card */
    .metric-card {
      background: #1C1F2D;
      border: 1px solid #333;
      border-radius: 8px;
      padding: 15px;
      color: #fff;
      width: 25%; /* Adjusted for side panel */
      min-width: 250px; /* Prevents cards from shrinking too much */
      transition: 0.3s;
      flex-grow: 1; /* Ensures flexibility */
      text-align: left; /* Aligns text to the left */
      position: relative; /* For positioning the chevron */
      cursor: pointer; /* Indicates the card is clickable */
      text-decoration: none; /* Removes underline from all text within the link */
    }

    /* Chevron icon for cards */
    .chevron-icon {
      position: absolute;
      right: 15px; /* Spacing from the right edge */
      top: 50%;
      transform: translateY(-50%); /* Center vertically */
      width: 24px; /* Width of the chevron icon */
      height: 24px; /* Height of the chevron icon */
      background: url('images/icons/chevron-right-grey.svg') center/contain no-repeat;
    }

    /* Hover effect for cards */
    .metric-card:hover {
      background: #272a38;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
      text-decoration: none; /* Ensures no underline appears */
    }

    /* Header inside the metric card */
    .metric-header {
      display: flex;
      align-items: center;
      justify-content: center; /* Centers icon and title */
      margin-bottom: 10px;
    }

    /* Icon inside the metric card */
    .metric-icon {
      width: 40px;
      height: 40px;
      margin-right: 10px;
    }

    /* Metric title inside card */
    .metric-title {
      font-size: 1.2em;
      font-weight: bold;
    }

    /* Entity type text */
    .entity-type {
      color: #fff;
      font-size: 0.9em;
      margin-top: 5px;
      font-weight: bold;
    }

    /* Metric information inside card */
    .metric-info {
      font-size: 0.9em;
      color: #aaa;
    }

    /* Responsive adjustments */
    @media (max-width: 1000px) {
      .metric-card {
        width: 45%; /* Two columns on medium screens */
      }
    }

    @media (max-width: 600px) {
      .metric-card {
        width: 100%; /* One column on small screens */
      }
    }
  </style>
</head>
<body>

<?php
displayHead("Sentiment Insight");
displaySidebar();
displayTopNav();

// Query to get the three most recently updated metric sets
$query = "
  SELECT t.setID, t.entityType, t.name,
         (SELECT COUNT(*) FROM MetricLogCondensed m WHERE m.setID = t.setID) AS dataPoints,
         (SELECT MAX(date) FROM MetricLogCondensed m WHERE m.setID = t.setID) AS lastUpdated,
         (SELECT AVG(severity) FROM MetricLogCondensed m 
          WHERE m.setID = t.setID AND date = (SELECT MAX(date) FROM MetricLogCondensed WHERE setID = t.setID)) AS avgSeverity
  FROM TrackedEntity t
  ORDER BY (SELECT MAX(date) FROM MetricLogCondensed WHERE setID = t.setID) DESC
  LIMIT 3;
";

$result = $conn->query($query);
?>

<div class="content-index">
  <div class="search-container">
    <form id="searchForm" action="search-results.php" method="GET" autocomplete="off">
      <div class="search-box" style="position: relative;">
        <img src="images/icons/search.svg" alt="Search Icon">
        <input type="text" id="searchInput" name="q" placeholder="Search organisation, industry, subreddit" oninput="toggleSubmitButton()">
        <div id="suggestions" class="suggestions-box"></div>
      </div>
      <button type="submit" id="searchButton" disabled>Search</button>
    </form>
  </div>

  <div id="recommended-home">
    <h2>Recently Updated</h2>
    <div class="recent-metrics-container">
      <?php 
      if ($result->num_rows > 0):
        while ($row = $result->fetch_assoc()): 
          $lastUpdated = $row['lastUpdated'] ? date("F j, Y", strtotime($row['lastUpdated'])) : "N/A";
          $avgSeverity = $row['avgSeverity'] ? round($row['avgSeverity'], 2) : "N/A";
          $iconPath = "images/icons/" . strtolower($row['entityType']) . ".svg";
          $entityType = ucfirst(strtolower($row['entityType'])); // Capitalize the first letter
          $logoURL = ($row['entityType'] === 'organisation') 
            ? "https://img.logo.dev/" . urlencode($row['name']) . "?token=pk_JFpZbrpjR1Kvewiwxccf8w" 
            : $iconPath;
      ?>
          <a href="metric-set.php?setID=<?php echo urlencode($row['setID']); ?>" class="metric-card">
            <div class="metric-header">
              <img src="<?php echo htmlspecialchars($logoURL); ?>" alt="Entity Icon" class="metric-icon">
              <div>
                <span class="metric-title"><?php echo htmlspecialchars($row['name']); ?></span>
                <div class="entity-type"><?php echo htmlspecialchars($entityType); ?></div>
              </div>
            </div>
            <div class="metric-info">
              Data Points: <?php echo htmlspecialchars($row['dataPoints']); ?><br>
              Last Updated: <?php echo htmlspecialchars($lastUpdated); ?><br>
              Avg. Severity: <?php echo htmlspecialchars($avgSeverity); ?>
            </div>
            <div class="chevron-icon"></div>
          </a>
      <?php 
        endwhile;
      else:
        echo "<p>No recent updates available.</p>";
      endif;
      ?>
    </div>
  </div>
</div>

<script>
  function toggleSubmitButton() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    searchButton.disabled = searchInput.value.trim() === '';
  }
</script>

</body>
</html>

<?php 
$conn->close();
?>
