<?php 
session_start();
include 'functions.php';
$conn = connectDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Browse Metrics - Sentiment Insight</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .content {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-around;
        padding: 20px;
        gap: 20px;
    }

    .metric-set-card {
        background: #1C1F2D;
        border: 1px solid #333;
        border-radius: 8px;
        color: #fff;
        width: calc(25% - 40px);  /* Adjusting width to account for padding and gap */
        padding: 15px;
        margin-bottom: 10px;
        justify-content: space-between;
        align-items: center;
        text-decoration: none;
        position: relative;
        cursor: pointer;
        transition: background-color 0.3s, box-shadow 0.3s;
    }

    /* Header inside the metric card */
    .metric-header {
      display: flex;
      align-items: center;
      justify-content: center; /* Centers icon and title */
      margin-bottom: 10px;
    }

    .metric-icon {
        width: 40px;
        height: 40px;
        margin-right: 10px;
    }

    .metric-info {
        font-size: 0.9em;
        color: #aaa;
        margin-bottom: 5px;
    }

    .metric-title {
        font-size: 1.2em;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .entity-type {
        font-size: 0.9em;
        color: #ccc;
        font-weight: bold;
        margin-bottom: 5px;
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

    .metric-set-card:hover {
        background-color: #272a38;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    }
  </style>
</head>
<body>

<?php
displayHead("Browse Metrics");
displaySidebar();
displayTopNav();
?>

<div class="content">
  <h2>Browse Metric Sets</h2>
  <div style="display: flex; flex-wrap: wrap; justify-content: space-around;">
    <?php 
    // Query to get all metric sets ordered by last update date
    $query = "
      SELECT t.setID, t.entityType, t.name,
             (SELECT MAX(date) FROM MetricLogCondensed m WHERE m.setID = t.setID) AS lastUpdated,
             (SELECT COUNT(*) FROM MetricLogCondensed m WHERE m.setID = t.setID) AS dataPoints,
             (SELECT AVG(severity) FROM MetricLogCondensed m WHERE m.setID = t.setID) AS avgSeverity
      FROM TrackedEntity t
      ORDER BY lastUpdated DESC;
    ";

    $result = $conn->query($query);
    if ($result->num_rows > 0):
      while ($row = $result->fetch_assoc()): 
        $lastUpdated = $row['lastUpdated'] ? date("F j, Y", strtotime($row['lastUpdated'])) : "N/A";
        $dataPoints = $row['dataPoints'];
        $avgSeverity = number_format($row['avgSeverity'], 2);
        $name = htmlspecialchars($row['name']);
        $entityType = ucfirst(strtolower($row['entityType']));
        $iconPath = "images/icons/" . strtolower($row['entityType']) . ".svg";
    ?>
        

        <a href="metric-set.php?setID=<?php echo urlencode($row['setID']); ?>" class="metric-set-card">
            <div class="metric-header">
            <img src="<?php echo $iconPath; ?>" alt="Entity Icon" class="metric-icon">
              <div>
                <div class="metric-title"><?php echo $name; ?></div>
                <div class="entity-type"><?php echo $entityType; ?></div>
              </div>
            </div>
            <div class="metric-info">
              Data Points: <?php echo $dataPoints; ?><br>
              Last Updated: <?php echo $lastUpdated; ?><br>
              Avg. Severity: <?php echo $avgSeverity; ?>
            </div>
            <div class="chevron-icon"></div>
          </a>
    <?php 
      endwhile;
    else:
      echo "<p>No metric sets found.</p>";
    endif;
    ?>
  </div>
</div>

</body>
</html>

<?php 
$conn->close();
?>
