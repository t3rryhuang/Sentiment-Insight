<?php
// compare-metric-set.php

session_start();

// Redirect to sign-in page if not logged in
if (!isset($_SESSION['username'])) {
    header('Location: sign-in-page.php');
    exit;
}

require 'functions.php'; // Include your functions file that contains connectDB()

// Function to fetch saved metric sets
function fetchSavedSets($conn, $username) {
    $sql = "SELECT SavedSet.setID, TrackedEntity.entityType, TrackedEntity.name
            FROM Account
            JOIN SavedSet ON Account.accountID = SavedSet.accountID
            JOIN TrackedEntity ON SavedSet.setID = TrackedEntity.setID
            WHERE Account.username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $sets = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $sets;
}

// Connect to DB and fetch sets
$conn = connectDB();
$savedSets = fetchSavedSets($conn, $_SESSION['username']);
$conn->close();

?>
<?php
displayHead("Search Results");
displaySidebar();
displayTopNavWithSearch();
?>
<link rel="stylesheet" href="css/compare-set-styles.css">

<div id="compare-header">
    <div class="header-top">
        <h1>Compare Metric Sets</h1>
        <div class="date-picker">
            <label for="startDate">Start Date:</label>
            <input type="date" id="startDate" onchange="loadMetricData()">
            <label for="endDate">End Date:</label>
            <input type="date" id="endDate" onchange="loadMetricData()">
        </div>
    </div>
        <p>Compare your saved metric sets by selecting from the drop down list.</p>
    <div class="metrics-comparison">
        <div class="metric-select-container">
            <select id="metricSet1" onchange="loadMetricData()">
                <option value="">Select Metric Set</option>
                <?php foreach ($savedSets as $set): ?>
                <option value="<?php echo htmlspecialchars($set['setID']); ?>">
                    <?php echo htmlspecialchars($set['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select id="metricSet2" onchange="loadMetricData()">
                <option value="">Select Metric Set</option>
                <?php foreach ($savedSets as $set): ?>
                <option value="<?php echo htmlspecialchars($set['setID']); ?>">
                    <?php echo htmlspecialchars($set['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>


<div class="content">
    

        <div id="metricsOutput">
            <!-- Dynamic content will be loaded here based on selected metric sets and dates -->
        </div>
    </div>
</div>

<script src="js/metric-comparison.js"></script>
</body>
</html>
